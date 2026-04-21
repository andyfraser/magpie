<?php
declare(strict_types=1);

namespace Magpie\Controllers;

class PostController extends BaseController {
    public function thread(string $id): void {
        $id = (int)$id;
        $uid  = current_user_id();
        $post = fetch_post($this->db, $id, $uid);
        if (!$post) json_error('Post not found', 404);

        $ancestors = [];
        $pid       = $post['parent_id'];
        $anc_depth = 0;
        while ($pid && $anc_depth < 20) {
            $ancestor = fetch_post($this->db, $pid, $uid);
            if (!$ancestor) break;
            array_unshift($ancestors, $ancestor);
            $pid = $ancestor['parent_id'];
            $anc_depth++;
        }

        $count   = 0;
        $replies = fetch_descendants($this->db, $id, 1, $uid, $count);

        json_ok(['ancestors' => $ancestors, 'post' => $post, 'replies' => $replies]);
    }

    public function index(): void {
        $page   = max(1, (int)($_GET['page'] ?? 1));
        $limit  = 20;
        $offset = ($page - 1) * $limit;
        $uid    = current_user_id();

        $feed       = $_GET['feed'] ?? '';
        $q          = trim($_GET['q'] ?? '');
        $username   = trim($_GET['username'] ?? '');
        $liked_join = '';
        $params     = [];
        $conditions = [];

        if ($q !== '') {
            $conditions[] = "p.body LIKE :q";
            $params[':q'] = "%$q%";
        }
        if ($username !== '') {
            $conditions[] = "p.username = :uname";
            $params[':uname'] = $username;
        }

        if ($uid) {
            if ($feed === 'following') {
                $conditions[] = "(p.user_id IN (SELECT followee_id FROM follows WHERE follower_id=:uid) OR p.user_id=:uid)";
                $params[':uid'] = $uid;
            } elseif ($feed === 'liked') {
                $liked_join = "JOIN liked_posts lp2 ON lp2.post_id = p.id AND lp2.user_id = :uid";
                $params[':uid'] = $uid;
            }
        }

        $where_clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
        $total = (int)db_query_single($this->db, "SELECT COUNT(*) FROM posts p $liked_join $where_clause", $params);

        $cols         = post_select_cols('p', $uid);
        $joins        = post_join_sql('p', $uid);

        $main_params = $params;
        if ($uid) $main_params[':uid'] = $uid;
        $main_params[':lim'] = $limit;
        $main_params[':off'] = $offset;

        $res = db_query($this->db, "
            SELECT $cols FROM posts p
            $liked_join $joins
            $where_clause
            ORDER BY p.created_at DESC
            LIMIT :lim OFFSET :off
        ", $main_params);

        $posts = [];
        while ($row = $res->fetch()) {
            $posts[] = format_post_row($row, $uid);
        }

        json_ok(['posts' => $posts, 'total' => $total, 'page' => $page, 'pages' => (int)ceil($total / $limit)]);
    }

    public function store(): void {
        $uid = require_auth();
        $u   = db_query_single($this->db, 'SELECT username, disabled, email_verified FROM users WHERE id=:id', [':id' => $uid], true);
        if ($u['disabled']) json_error('Your account has been disabled', 403);
        if (!$u['email_verified']) json_error('Please verify your email address to post', 403);

        $body      = trim($_POST['body'] ?? '');
        $parent_id = isset($_POST['parent_id']) && ctype_digit((string)$_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $quote_id  = isset($_POST['quote_id'])  && ctype_digit((string)$_POST['quote_id'])  ? (int)$_POST['quote_id']  : null;

        $uploaded_filenames = [];
        if (!empty($_FILES['images'])) {
            $f = $_FILES['images'];
            $count = is_array($f['name']) ? count($f['name']) : 1;
            if ($count > 4) json_error('Maximum 4 images allowed per post');
            if (!is_dir(POSTS_UPLOADS_DIR)) mkdir(POSTS_UPLOADS_DIR, 0755, true);
            for ($i = 0; $i < $count; $i++) {
                $err  = is_array($f['error'])    ? $f['error'][$i]    : $f['error'];
                $size = is_array($f['size'])     ? $f['size'][$i]     : $f['size'];
                $tmp  = is_array($f['tmp_name']) ? $f['tmp_name'][$i] : $f['tmp_name'];
                if ($err === UPLOAD_ERR_NO_FILE) continue;
                if ($err !== UPLOAD_ERR_OK) json_error('Upload error (code ' . $err . ')');
                if ($size > 5 * 1024 * 1024) json_error('Image too large (max 5 MB)');
                $info = @getimagesize($tmp);
                if (!$info) json_error('Invalid image file');
                $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
                if (!in_array($info[2], $allowed)) json_error('Only JPEG, PNG, GIF and WebP are allowed');
                $ext      = image_type_to_extension($info[2], false);
                $filename = $uid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                if (!move_uploaded_file($tmp, POSTS_UPLOADS_DIR . $filename)) {
                    foreach ($uploaded_filenames as $fn) { $p = POSTS_UPLOADS_DIR . $fn; if (file_exists($p)) unlink($p); }
                    json_error('Failed to save image');
                }
                $uploaded_filenames[] = $filename;
            }
        }
        $image_json = !empty($uploaded_filenames) ? json_encode($uploaded_filenames) : null;

        if ($body === '' && empty($uploaded_filenames) && !$quote_id) json_error('Post body is required');
        if (mb_strlen($body) > MAX_POST_LENGTH) json_error('Post exceeds ' . MAX_POST_LENGTH . ' character limit');

        if ($parent_id) {
            $parent = db_query_single($this->db, 'SELECT id, user_id FROM posts WHERE id=:id', [':id' => $parent_id], true);
            if (!$parent) json_error('Parent post not found', 404);
        }
        if ($quote_id) {
            $quoted = db_query_single($this->db, 'SELECT id, user_id FROM posts WHERE id=:id', [':id' => $quote_id], true);
            if (!$quoted) json_error('Quoted post not found', 404);
        }

        db_exec($this->db, 'INSERT INTO posts (user_id, username, body, image, parent_id, quote_id, created_at) VALUES (:u,:n,:b,:img,:p,:q,:t)', [
            ':u'   => $uid,
            ':n'   => $u['username'],
            ':b'   => $body,
            ':img' => $image_json,
            ':p'   => $parent_id,
            ':q'   => $quote_id,
            ':t'   => time()
        ]);

        $nid = (int)$this->db->lastInsertId();

        if ($parent_id && isset($parent)) {
            $target_uid = (int)$parent['user_id'];
            if ($target_uid !== $uid) {
                db_exec($this->db, 'INSERT INTO notifications (user_id,actor_id,type,post_id,created_at) VALUES (:u,:a,:t,:p,:ts)', [
                    ':u'  => $target_uid,
                    ':a'  => $uid,
                    ':t'  => 'reply',
                    ':p'  => $nid,
                    ':ts' => time()
                ]);
            }
        }

        if ($quote_id && isset($quoted)) {
            $target_uid = (int)$quoted['user_id'];
            if ($target_uid !== $uid) {
                $type = ($body === '' && empty($uploaded_filenames)) ? 'repost' : 'quote';
                db_exec($this->db, 'INSERT INTO notifications (user_id,actor_id,type,post_id,created_at) VALUES (:u,:a,:t,:p,:ts)', [
                    ':u'  => $target_uid,
                    ':a'  => $uid,
                    ':t'  => $type,
                    ':p'  => $nid,
                    ':ts' => time()
                ]);
            }
        }

        $post = fetch_post($this->db, $nid, $uid);
        json_ok($post);
    }

    public function repost(string $id): void {
        $id = (int)$id;
        $uid = require_auth();
        $u   = db_query_single($this->db, 'SELECT username, disabled, email_verified FROM users WHERE id=:id', [':id' => $uid], true);
        if ($u['disabled']) json_error('Your account has been disabled', 403);
        if (!$u['email_verified']) json_error('Please verify your email address to post', 403);

        $original = db_query_single($this->db, 'SELECT id, user_id FROM posts WHERE id=:id', [':id' => $id], true);
        if (!$original) json_error('Post not found', 404);

        $existing = db_query_single($this->db, "SELECT id FROM posts WHERE user_id=:u AND quote_id=:q AND body='' AND image IS NULL", [
            ':u' => $uid, ':q' => $id
        ]);

        if ($existing) {
            db_exec($this->db, 'DELETE FROM notifications WHERE actor_id=:u AND post_id=:p AND type=\'repost\'', [':u' => $uid, ':p' => (int)$existing]);
            db_exec($this->db, 'DELETE FROM posts WHERE id=:id', [':id' => (int)$existing]);
        } else {
            db_exec($this->db, 'INSERT INTO posts (user_id, username, body, image, quote_id, created_at) VALUES (:u,:n,\'\',NULL,:q,:t)', [
                ':u' => $uid, ':n' => $u['username'], ':q' => $id, ':t' => time()
            ]);
            $nid = (int)$this->db->lastInsertId();
            
            $target_uid = (int)$original['user_id'];
            if ($target_uid !== $uid) {
                db_exec($this->db, 'INSERT INTO notifications (user_id,actor_id,type,post_id,created_at) VALUES (:u,:a,\'repost\',:p,:ts)', [
                    ':u'  => $target_uid,
                    ':a'  => $uid,
                    ':p'  => $nid,
                    ':ts' => time()
                ]);
            }
        }

        json_ok(fetch_post($this->db, $id, $uid));
    }

    public function like(string $id): void {
        $id = (int)$id;
        $uid  = require_auth();
        $post = db_query_single($this->db, 'SELECT id FROM posts WHERE id=:id', [':id' => $id], true);
        if (!$post) json_error('Post not found', 404);

        $already = (bool)db_query_single($this->db, 'SELECT 1 FROM liked_posts WHERE post_id=:p AND user_id=:u', [':p' => $id, ':u' => $uid]);

        if ($already) {
            db_exec($this->db, 'DELETE FROM liked_posts WHERE post_id=:p AND user_id=:u', [':p' => $id, ':u' => $uid]);
            db_exec($this->db, 'UPDATE posts SET likes=MAX(0,likes-1) WHERE id=:id', [':id' => $id]);
        } else {
            db_exec($this->db, 'INSERT INTO liked_posts (post_id,user_id) VALUES (:p,:u)', [':p' => $id, ':u' => $uid]);
            db_exec($this->db, 'UPDATE posts SET likes=likes+1 WHERE id=:id', [':id' => $id]);
        }

        json_ok(fetch_post($this->db, $id, $uid));
    }

    public function update(string $id): void {
        $id = (int)$id;
        $uid  = require_auth();
        $post = db_query_single($this->db, 'SELECT user_id, image FROM posts WHERE id=:id', [':id' => $id], true);
        if (!$post)                         json_error('Post not found', 404);
        if ((int)$post['user_id'] !== $uid) json_error('Forbidden', 403);

        $ct = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
        if (str_starts_with($ct, 'application/json')) {
            $input      = $this->getPayload();
            $body       = trim($input['body'] ?? '');
            $image_json = $post['image'];
            if ($body === '') json_error('Post body is required');
        } else {
            $body = trim($_POST['body'] ?? '');

            $existing = [];
            if (!empty($post['image'])) {
                $decoded  = json_decode($post['image'], true);
                $existing = is_array($decoded) ? $decoded : [$post['image']];
            }

            $keep = isset($_POST['keep_images']) ? (array)$_POST['keep_images'] : $existing;
            $kept = array_values(array_filter($keep, fn($fn) => in_array($fn, $existing, true)));

            foreach ($existing as $fn) {
                if (!in_array($fn, $kept, true)) {
                    $p = POSTS_UPLOADS_DIR . $fn;
                    if (file_exists($p)) unlink($p);
                }
            }

            $new_files = [];
            if (!empty($_FILES['images'])) {
                $f     = $_FILES['images'];
                $count = is_array($f['name']) ? count($f['name']) : 1;
                if (count($kept) + $count > 4) json_error('Maximum 4 images allowed per post');
                if (!is_dir(POSTS_UPLOADS_DIR)) mkdir(POSTS_UPLOADS_DIR, 0755, true);
                for ($i = 0; $i < $count; $i++) {
                    $err  = is_array($f['error'])    ? $f['error'][$i]    : $f['error'];
                    $size = is_array($f['size'])     ? $f['size'][$i]     : $f['size'];
                    $tmp  = is_array($f['tmp_name']) ? $f['tmp_name'][$i] : $f['tmp_name'];
                    if ($err === UPLOAD_ERR_NO_FILE) continue;
                    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) json_error('Image too large (max 5 MB)');
                    if ($err !== UPLOAD_ERR_OK) json_error('Upload error (code ' . $err . ')');
                    if ($size > 5 * 1024 * 1024) json_error('Image too large (max 5 MB)');
                    $info = @getimagesize($tmp);
                    if (!$info) json_error('Invalid image file');
                    $allowed = [IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_GIF, IMAGETYPE_WEBP];
                    if (!in_array($info[2], $allowed)) json_error('Only JPEG, PNG, GIF and WebP are allowed');
                    $ext      = image_type_to_extension($info[2], false);
                    $filename = $uid . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
                    if (!move_uploaded_file($tmp, POSTS_UPLOADS_DIR . $filename)) {
                        foreach ($new_files as $nf) { $p = POSTS_UPLOADS_DIR . $nf; if (file_exists($p)) unlink($p); }
                        json_error('Failed to save image');
                    }
                    $new_files[] = $filename;
                }
            }

            $all_images = array_merge($kept, $new_files);
            $image_json = !empty($all_images) ? json_encode($all_images) : null;
            if ($body === '' && $image_json === null) json_error('Post body is required');
        }

        if (mb_strlen($body) > MAX_POST_LENGTH) json_error('Post exceeds ' . MAX_POST_LENGTH . ' character limit');

        db_exec($this->db, 'UPDATE posts SET body=:b, image=:img, edited_at=:e WHERE id=:id', [
            ':b'   => $body,
            ':img' => $image_json,
            ':e'   => time(),
            ':id'  => $id,
        ]);
        json_ok(fetch_post($this->db, $id, $uid));
    }

    public function destroy(string $id): void {
        $id = (int)$id;
        $uid  = require_auth();
        $post = db_query_single($this->db, 'SELECT user_id FROM posts WHERE id=:id', [':id' => $id], true);
        if (!$post)                         json_error('Post not found', 404);
        if ((int)$post['user_id'] !== $uid) json_error('Forbidden', 403);

        delete_post_cascade($this->db, $id);
        json_ok(['deleted' => true]);
    }
}
