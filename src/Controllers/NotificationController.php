<?php
declare(strict_types=1);

namespace Magpie\Controllers;

class NotificationController extends BaseController {
    public function index(): void {
        $uid = require_auth();

        $res = db_query($this->db, "
            SELECT n.*,
                   a.username     AS actor_username,
                   a.display_name AS actor_display_name,
                   a.avatar       AS actor_avatar,
                   p.body         AS post_body,
                   pp.id          AS parent_post_id
            FROM   notifications n
            JOIN   users a ON a.id = n.actor_id
            LEFT JOIN posts p  ON p.id  = n.post_id
            LEFT JOIN posts pp ON pp.id = p.parent_id
            WHERE  n.user_id = :uid
            ORDER  BY n.created_at DESC
            LIMIT  50
        ", [':uid' => $uid]);

        $notifs = [];
        while ($row = $res->fetch()) {
            $notifs[] = [
                'id'           => (int)$row['id'],
                'type'         => $row['type'],
                'read'         => (bool)$row['read'],
                'created_at'   => (int)$row['created_at'],
                'post_id'      => $row['post_id'] ? (int)$row['post_id'] : null,
                'parent_post_id' => $row['parent_post_id'] ? (int)$row['parent_post_id'] : null,
                'post_body'    => $row['post_body'] ? mb_substr($row['post_body'], 0, 100) : null,
                'actor' => [
                    'username'     => $row['actor_username'],
                    'display_name' => $row['actor_display_name'] ?: $row['actor_username'],
                    'avatar'       => $row['actor_avatar'] ? UPLOADS_URL . $row['actor_avatar'] : null,
                ],
            ];
        }

        $unread = (int)db_query_single($this->db, 'SELECT COUNT(*) FROM notifications WHERE user_id=:uid AND `read`=0', [':uid' => $uid]);
        json_ok(['notifications' => $notifs, 'unread' => $unread]);
    }

    public function markAsRead(): void {
        $uid = require_auth();
        db_exec($this->db, 'UPDATE notifications SET `read`=1 WHERE user_id=:uid', [':uid' => $uid]);
        json_ok(['ok' => true]);
    }

    public function stream(): void {
        $uid = current_user_id();
        if (!$uid) {
            header('HTTP/1.1 401 Unauthorized');
            exit;
        }

        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        header('X-Accel-Buffering: no');

        set_time_limit(0);
        session_write_close();

        $last_notif_id = (int)db_query_single($this->db, 'SELECT MAX(id) FROM notifications WHERE user_id=:uid', [':uid' => $uid]) ?: 0;
        $last_post_id  = (int)db_query_single($this->db, 'SELECT MAX(id) FROM posts') ?: 0;

        while (true) {
            if (connection_aborted()) break;

            $latest_notif = (int)db_query_single($this->db, 'SELECT MAX(id) FROM notifications WHERE user_id=:uid', [':uid' => $uid]) ?: 0;
            if ($latest_notif > $last_notif_id) {
                $unread = (int)db_query_single($this->db, 'SELECT COUNT(*) FROM notifications WHERE user_id=:uid AND `read`=0', [':uid' => $uid]);
                echo "data: " . json_encode(['type' => 'notification', 'unread' => $unread]) . "\n\n";
                $last_notif_id = $latest_notif;
            }

            $latest_post = (int)db_query_single($this->db, 'SELECT MAX(id) FROM posts') ?: 0;
            if ($latest_post > $last_post_id) {
                $post = db_query_single($this->db, 'SELECT user_id FROM posts WHERE id=:id', [':id' => $latest_post], true);
                if ($post && (int)$post['user_id'] !== $uid) {
                    echo "data: " . json_encode(['type' => 'new_post']) . "\n\n";
                }
                $last_post_id = $latest_post;
            }

            if (ob_get_level() > 0) ob_flush();
            flush();
            sleep(5);
        }
        exit;
    }
}
