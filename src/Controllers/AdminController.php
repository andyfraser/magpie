<?php
declare(strict_types=1);

namespace Magpie\Controllers;

class AdminController extends BaseController {
    public function users(): void {
        require_admin($this->db);
        $res = db_query($this->db, '
            SELECT u.*, COUNT(p.id) AS post_count
            FROM   users u LEFT JOIN posts p ON p.user_id=u.id
            GROUP  BY u.id ORDER BY u.created_at ASC
        ');
        $users = [];
        while ($row = $res->fetch()) {
            $u = format_user($row);
            $u['post_count'] = (int)$row['post_count'];
            $users[] = $u;
        }
        json_ok(['users' => $users]);
    }

    public function updateUser(string $id): void {
        $admin_uid = require_admin($this->db);
        $tid = (int)$id;

        if (!db_query_single($this->db, 'SELECT id FROM users WHERE id=:id', [':id' => $tid], true))
            json_error('User not found', 404);

        $input  = $this->getPayload();
        $fields = [];
        $params = [':tid' => $tid];

        if (array_key_exists('display_name', $input)) {
            $v = trim($input['display_name'] ?? '');
            if (mb_strlen($v) > 50) json_error('Display name too long');
            $fields[] = 'display_name=:dn';
            $params[':dn'] = $v ?: null;
        }
        if (array_key_exists('bio', $input)) {
            $v = trim($input['bio'] ?? '');
            if (mb_strlen($v) > 160) json_error('Bio too long');
            $fields[] = 'bio=:bio';
            $params[':bio'] = $v;
        }
        if (array_key_exists('disabled', $input)) {
            if ($tid === $admin_uid && $input['disabled']) json_error('Cannot disable your own account');
            $fields[] = 'disabled=:disabled';
            $params[':disabled'] = $input['disabled'] ? 1 : 0;
        }
        if (array_key_exists('is_admin', $input)) {
            if ($tid === $admin_uid && !$input['is_admin']) json_error('Cannot remove your own admin status');
            $fields[] = 'is_admin=:is_admin';
            $params[':is_admin'] = $input['is_admin'] ? 1 : 0;
        }

        if ($fields) {
            db_exec($this->db, 'UPDATE users SET ' . implode(',', $fields) . ' WHERE id=:tid', $params);
        }

        $row = db_query_single($this->db, "
            SELECT u.*, COUNT(p.id) AS post_count
            FROM users u LEFT JOIN posts p ON p.user_id=u.id
            WHERE u.id=:id GROUP BY u.id
        ", [':id' => $tid], true);
        $u = format_user($row);
        $u['post_count'] = (int)$row['post_count'];
        json_ok(['user' => $u]);
    }

    public function deleteUser(string $id): void {
        $admin_uid = require_admin($this->db);
        $tid = (int)$id;

        if ($tid === $admin_uid) json_error('Cannot delete your own account from the admin panel');
        if (!db_query_single($this->db, 'SELECT id FROM users WHERE id=:id', [':id' => $tid], true))
            json_error('User not found', 404);

        delete_user_data($this->db, $tid);
        json_ok(['deleted' => true]);
    }

    public function settings(): void {
        require_admin($this->db);
        json_ok(['settings' => [
            'remember_me_days' => (int)(get_setting($this->db, 'remember_me_days') ?: 30),
        ]]);
    }

    public function updateSettings(): void {
        require_admin($this->db);
        $input = $this->getPayload();
        if (array_key_exists('remember_me_days', $input)) {
            $days = (int)$input['remember_me_days'];
            if ($days < 1 || $days > 365) json_error('Remember me days must be between 1 and 365');
            
            global $CONFIG;
            if ($CONFIG['db']['driver'] === 'mysql') {
                db_exec($this->db, 'INSERT INTO settings (`key`, `value`) VALUES (:k, :v) ON DUPLICATE KEY UPDATE `value`=VALUES(`value`)', [
                    ':k' => 'remember_me_days', ':v' => (string)$days,
                ]);
            } else {
                db_exec($this->db, 'INSERT INTO settings (key, value) VALUES (:k, :v) ON CONFLICT(key) DO UPDATE SET value=excluded.value', [
                    ':k' => 'remember_me_days', ':v' => (string)$days,
                ]);
            }
        }
        json_ok(['settings' => [
            'remember_me_days' => (int)(get_setting($this->db, 'remember_me_days') ?: 30),
        ]]);
    }
}
