<?php
declare(strict_types=1);

namespace Magpie\Controllers;

class UserController extends BaseController {
    public function avatar(): void {
        $uid = require_auth();
        $ct = strtolower($_SERVER['CONTENT_TYPE'] ?? '');

        if (str_starts_with($ct, 'application/json')) {
            $body   = $this->getPayload();
            $preset = $body['preset'] ?? '';
            $valid  = array_map(fn($n) => "magpie_0{$n}.svg", range(1, 9));
            $valid[] = 'magpie_10.svg';
            if (!in_array($preset, $valid, true)) json_error('Invalid preset name');

            $old = db_query_single($this->db, 'SELECT avatar FROM users WHERE id=:id', [':id' => $uid], true);
            if ($old && $old['avatar'] && !str_starts_with($old['avatar'], 'presets/')) {
                $p = UPLOADS_DIR . $old['avatar'];
                if (file_exists($p)) unlink($p);
            }

            db_exec($this->db, 'UPDATE users SET avatar=:v WHERE id=:id', [':v' => 'presets/' . $preset, ':id' => $uid]);
            json_ok(['user' => format_user(db_query_single($this->db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true))]);
        }

        if (empty($_FILES['avatar'])) json_error('No file uploaded');
        $file = $_FILES['avatar'];
        if ($file['error'] !== UPLOAD_ERR_OK) json_error('Upload error (code ' . $file['error'] . ')');
        if ($file['size'] > 2 * 1024 * 1024) json_error('File too large (max 2 MB)');

        $info = @getimagesize($file['tmp_name']);
        if (!$info) json_error('Invalid image file');

        $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
        if (!in_array($info[2], $allowed)) json_error('Only JPEG, PNG, GIF and WebP are allowed');

        $ext      = image_type_to_extension($info[2], false);
        $filename = $uid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;

        if (!is_dir(UPLOADS_DIR)) mkdir(UPLOADS_DIR, 0755, true);

        $old = db_query_single($this->db, 'SELECT avatar FROM users WHERE id=:id', [':id' => $uid], true);
        if ($old && $old['avatar'] && !str_starts_with($old['avatar'], 'presets/')) {
            $p = UPLOADS_DIR . $old['avatar'];
            if (file_exists($p)) unlink($p);
        }

        if (!move_uploaded_file($file['tmp_name'], UPLOADS_DIR . $filename))
            json_error('Failed to save file');

        db_exec($this->db, 'UPDATE users SET avatar=:v WHERE id=:id', [':v' => $filename, ':id' => $uid]);
        json_ok(['user' => format_user(db_query_single($this->db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true))]);
    }

    public function updateMe(): void {
        $uid   = require_auth();
        $input = $this->getPayload();

        $dn    = trim($input['display_name'] ?? '');
        $bio   = trim($input['bio'] ?? '');
        $email = trim($input['email'] ?? '');

        if (mb_strlen($dn)  > 50)  json_error('Display name too long (max 50 characters)');
        if (mb_strlen($bio) > 160) json_error('Bio too long (max 160 characters)');
        if (!$email)                json_error('Email address is required');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Invalid email address');

        $current = db_query_single($this->db, 'SELECT email FROM users WHERE id=:id', [':id' => $uid], true);
        $email_changed = strtolower($email) !== strtolower($current['email']);
        if ($email_changed) {
            if (db_query_single($this->db, 'SELECT id FROM users WHERE email=:e AND id != :id', [':e' => $email, ':id' => $uid]))
                json_error('Email address is already in use');
            $v_token = bin2hex(random_bytes(32));
            db_exec($this->db, 'UPDATE users SET display_name=:dn, bio=:bio, email=:e, email_verified=0, verification_token=:v WHERE id=:id', [
                ':dn' => $dn ?: null,
                ':bio' => $bio ?: null,
                ':e' => $email,
                ':v' => $v_token,
                ':id' => $uid
            ]);
            $uname = db_query_single($this->db, 'SELECT username FROM users WHERE id=:id', [':id' => $uid]);
            $url   = get_base_url() . "/#verify=" . $v_token;
            $text  = "Hello $uname,\n\nPlease verify your new Magpie email address by visiting the link below:\n\n$url\n\nIf you did not request this change, please contact support.";
            $html  = mail_html_wrap(
                "Confirm your new email address",
                "<p style='margin:0 0 12px;font-size:15px;color:#374151;line-height:1.6;'>Hi <strong>$uname</strong>,</p><p style='margin:0;font-size:15px;color:#374151;line-height:1.6;'>Your Magpie email address was changed. Click the button below to verify your new address.</p>",
                "Verify Email Address",
                $url
            );
            send_mail($email, "Verify your new Magpie email address", $text, $html);
        } else {
            db_exec($this->db, 'UPDATE users SET display_name=:dn, bio=:bio WHERE id=:id', [
                ':dn' => $dn ?: null,
                ':bio' => $bio ?: null,
                ':id' => $uid
            ]);
        }

        json_ok(['user' => format_user(db_query_single($this->db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true))]);
    }

    public function destroyMe(): void {
        $uid = require_auth();
        delete_user_data($this->db, $uid);
        if (!empty($_COOKIE['magpie_rmb'])) {
            setcookie('magpie_rmb', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
        }
        session_destroy();
        json_ok(['deleted' => true]);
    }

    public function index(): void {
        $uid = require_auth();
        $q   = trim($_GET['q'] ?? '');
        $following_only = (bool)($_GET['following'] ?? false);

        $where = "WHERE u.id != :uid AND u.disabled = 0";
        $params = [':uid' => $uid];

        if ($q !== '') {
            $where .= " AND (u.username LIKE :q OR u.display_name LIKE :q)";
            $params[':q'] = "%$q%";
        }
        if ($following_only) {
            $where .= " AND u.id IN (SELECT followee_id FROM follows WHERE follower_id=:uid)";
        }

        $res = db_query($this->db, "
            SELECT u.*,
                   CASE WHEN f.follower_id IS NOT NULL THEN 1 ELSE 0 END AS following
            FROM   users u
            LEFT JOIN follows f ON f.follower_id=:uid AND f.followee_id=u.id
            $where
            ORDER BY u.username ASC
            LIMIT 50
        ", $params);

        $users = [];
        while ($row = $res->fetch()) {
            $u = format_user($row);
            $u['following'] = (bool)$row['following'];
            $users[] = $u;
        }
        json_ok(['users' => $users]);
    }

    public function follow(string $username): void {
        $uid = require_auth();
        $target = db_query_single($this->db, 'SELECT id FROM users WHERE username=:u', [':u' => $username], true);
        if (!$target) json_error('User not found', 404);
        $tid = (int)$target['id'];
        if ($tid === $uid) json_error('Cannot follow yourself');

        $already = (bool)db_query_single($this->db, 'SELECT 1 FROM follows WHERE follower_id=:f AND followee_id=:e', [
            ':f' => $uid,
            ':e' => $tid
        ]);

        if ($already) {
            db_exec($this->db, 'DELETE FROM follows WHERE follower_id=:f AND followee_id=:e', [
                ':f' => $uid,
                ':e' => $tid
            ]);
            $following = false;
        } else {
            db_exec($this->db, 'INSERT INTO follows (follower_id, followee_id) VALUES (:f,:e)', [
                ':f' => $uid,
                ':e' => $tid
            ]);
            $following = true;

            db_exec($this->db, 'INSERT INTO notifications (user_id,actor_id,type,created_at) VALUES (:u,:a,:t,:ts)', [
                ':u'  => $tid,
                ':a'  => $uid,
                ':t'  => 'follow',
                ':ts' => time()
            ]);
        }

        $follower_count = (int)db_query_single($this->db, 'SELECT COUNT(*) FROM follows WHERE followee_id=:tid', [':tid' => $tid]);
        json_ok(['following' => $following, 'follower_count' => $follower_count]);
    }
}
