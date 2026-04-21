<?php
declare(strict_types=1);

namespace Magpie\Controllers;

class AuthController extends BaseController {
    public function me(): void {
        $uid = current_user_id();
        if (!$uid) {
            json_ok(['user' => null, 'csrf_token' => get_csrf_token()]);
        }
        $u = db_query_single($this->db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true);
        json_ok(['user' => $u ? format_user($u) : null, 'csrf_token' => get_csrf_token()]);
    }

    public function signup(): void {
        check_rate_limit($this->db, 'signup_' . $_SERVER['REMOTE_ADDR'], 5, 3600);
        $input = $this->getPayload();
        $username = trim($input['username'] ?? '');
        $email    = trim($input['email'] ?? '');
        $password = $input['password'] ?? '';

        if (!preg_match('/^\w{1,30}$/', $username))
            json_error('Username must be 1–30 characters: letters, numbers, underscores only');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))
            json_error('Invalid email address');
        if (strlen($password) < 6)
            json_error('Password must be at least 6 characters');

        if (db_query_single($this->db, 'SELECT id FROM users WHERE username=:u', [':u' => $username]))
            json_error('Username already taken');

        if (db_query_single($this->db, 'SELECT id FROM users WHERE email=:e', [':e' => $email]))
            json_error('Email already registered');

        $hash     = password_hash($password, PASSWORD_BCRYPT);
        $is_admin = ((int)db_query_single($this->db, 'SELECT COUNT(*) FROM users') === 0) ? 1 : 0;
        $v_token  = bin2hex(random_bytes(32));

        db_exec($this->db, 'INSERT INTO users (username, email, password, is_admin, verification_token, created_at) VALUES (:u,:e,:p,:a,:v,:t)', [
            ':u' => $username,
            ':e' => $email,
            ':p' => $hash,
            ':a' => $is_admin,
            ':v' => $v_token,
            ':t' => time()
        ]);

        $uid = (int)$this->db->lastInsertId();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $uid;

        $url = get_base_url() . "/#verify=" . $v_token;
        $text = "Hello $username,\n\nThank you for joining Magpie! Please verify your email address by visiting the link below:\n\n$url\n\nIf you did not create an account, you can safely ignore this message.";
        $html = mail_html_wrap(
            "Confirm your email address",
            "<p style='margin:0 0 12px;font-size:15px;color:#374151;line-height:1.6;'>Hi <strong>$username</strong>,</p><p style='margin:0;font-size:15px;color:#374151;line-height:1.6;'>Thank you for joining Magpie! Click the button below to verify your email address and activate your account.</p>",
            "Verify Email Address",
            $url
        );
        send_mail($email, "Verify your Magpie account", $text, $html);

        json_ok([
            'user'       => format_user(db_query_single($this->db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true)),
            'csrf_token' => get_csrf_token()
        ]);
    }

    public function login(): void {
        check_rate_limit($this->db, 'login_' . $_SERVER['REMOTE_ADDR'], 10, 600);
        $input = $this->getPayload();
        $username = trim($input['username'] ?? '');
        $password = $input['password'] ?? '';

        $u = db_query_single($this->db, 'SELECT * FROM users WHERE username=:u OR email=:u', [':u' => $username], true);
        if (!$u || !password_verify($password, $u['password']))
            json_error('Invalid username or password', 401);
        if ($u['disabled'])
            json_error('Your account has been disabled', 403);

        session_regenerate_id(true);
        $_SESSION['user_id'] = (int)$u['id'];

        if (!empty($input['remember_me'])) {
            $days    = max(1, (int)(get_setting($this->db, 'remember_me_days') ?: '30'));
            $token   = bin2hex(random_bytes(32));
            $expires = time() + $days * 86400;
            db_exec($this->db, 'INSERT INTO remember_tokens (token, user_id, expires) VALUES (:t, :u, :e)', [
                ':t' => $token, ':u' => (int)$u['id'], ':e' => $expires,
            ]);
            setcookie('magpie_rmb', $token, [
                'expires'  => $expires,
                'path'     => '/',
                'httponly' => true,
                'samesite' => 'Lax',
            ]);
        }

        json_ok([
            'user'       => format_user($u),
            'csrf_token' => get_csrf_token()
        ]);
    }

    public function logout(): void {
        if (!empty($_COOKIE['magpie_rmb'])) {
            db_exec($this->db, 'DELETE FROM remember_tokens WHERE token=:t', [':t' => $_COOKIE['magpie_rmb']]);
            setcookie('magpie_rmb', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
        session_start();
        session_regenerate_id(true);
        json_ok(['ok' => true, 'csrf_token' => get_csrf_token()]);
    }

    public function verifyEmail(): void {
        $input = $this->getPayload();
        $token = trim($input['token'] ?? '');
        if (!$token) json_error('Token required');

        $u = db_query_single($this->db, 'SELECT id FROM users WHERE verification_token=:t', [':t' => $token], true);
        if (!$u) json_error('Invalid or expired token');

        db_exec($this->db, 'UPDATE users SET email_verified=1, verification_token=NULL WHERE id=:id', [':id' => $u['id']]);
        json_ok(['ok' => true]);
    }

    public function forgotPassword(): void {
        check_rate_limit($this->db, 'forgot_' . $_SERVER['REMOTE_ADDR'], 5, 3600);
        $input = $this->getPayload();
        $email = trim($input['email'] ?? '');
        if (!$email) json_error('Email required');

        $u = db_query_single($this->db, 'SELECT id, username FROM users WHERE email=:e', [':e' => $email], true);
        if ($u) {
            $token = bin2hex(random_bytes(32));
            $expires = time() + 3600;
            db_exec($this->db, 'UPDATE users SET reset_token=:t, reset_token_expires=:e WHERE id=:id', [
                ':t' => $token,
                ':e' => $expires,
                ':id' => $u['id']
            ]);
            
            $url = get_base_url() . "/#reset=" . $token;
            $uname = $u['username'];
            $text = "Hello $uname,\n\nWe received a request to reset the password for your Magpie account. Click the link below to choose a new password:\n\n$url\n\nThis link expires in 1 hour. If you did not request a password reset, you can safely ignore this message.";
            $html = mail_html_wrap(
                "Reset your password",
                "<p style='margin:0 0 12px;font-size:15px;color:#374151;line-height:1.6;'>Hi <strong>$uname</strong>,</p><p style='margin:0;font-size:15px;color:#374151;line-height:1.6;'>We received a request to reset the password for your Magpie account. Click the button below to choose a new password. This link will expire in <strong>1 hour</strong>.</p>",
                "Reset Password",
                $url
            );
            send_mail($email, "Reset your Magpie password", $text, $html);
        }
        json_ok(['ok' => true]);
    }

    public function resetPassword(): void {
        $input = $this->getPayload();
        $token = trim($input['token'] ?? '');
        $password = $input['password'] ?? '';

        if (!$token) json_error('Token required');
        if (strlen($password) < 6) json_error('Password must be at least 6 characters');

        $u = db_query_single($this->db, 'SELECT id FROM users WHERE reset_token=:t AND reset_token_expires > :now', [
            ':t' => $token,
            ':now' => time()
        ], true);
        if (!$u) json_error('Invalid or expired token');

        $hash = password_hash($password, PASSWORD_BCRYPT);
        db_exec($this->db, 'UPDATE users SET password=:p, reset_token=NULL, reset_token_expires=NULL WHERE id=:id', [
            ':p' => $hash,
            ':id' => $u['id']
        ]);
        json_ok(['ok' => true]);
    }

    public function resendVerification(): void {
        $uid = require_auth();
        $u = db_query_single($this->db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true);
        if ($u['email_verified']) json_error('Email already verified');
        
        $v_token = $u['verification_token'];
        if (!$v_token) {
            $v_token = bin2hex(random_bytes(32));
            db_exec($this->db, 'UPDATE users SET verification_token=:v WHERE id=:id', [':v' => $v_token, ':id' => $uid]);
        }

        $url = get_base_url() . "/#verify=" . $v_token;
        $uname = $u['username'];
        $text = "Hello $uname,\n\nPlease verify your Magpie email address by visiting the link below:\n\n$url\n\nIf you did not request this, you can safely ignore this message.";
        $html = mail_html_wrap(
            "Confirm your email address",
            "<p style='margin:0 0 12px;font-size:15px;color:#374151;line-height:1.6;'>Hi <strong>$uname</strong>,</p><p style='margin:0;font-size:15px;color:#374151;line-height:1.6;'>Click the button below to verify your email address and confirm your Magpie account.</p>",
            "Verify Email Address",
            $url
        );
        send_mail($u['email'], "Verify your Magpie account", $text, $html);
        
        json_ok(['ok' => true]);
    }
}
