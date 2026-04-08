<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

const DB_PATH         = __DIR__ . '/magpie.db';
const UPLOADS_DIR     = __DIR__ . '/uploads/avatars/';
const UPLOADS_URL     = '/uploads/avatars/';
const MAX_POST_LENGTH = 500;
const SCHEMA_VERSION  = 4;

// ── Database ──────────────────────────────────────────────

function get_db(): SQLite3 {
    $db = new SQLite3(DB_PATH);
    $db->enableExceptions(true);
    $db->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');

    $db->exec('CREATE TABLE IF NOT EXISTS schema_version (version INTEGER NOT NULL)');
    $current = $db->querySingle('SELECT version FROM schema_version LIMIT 1');
    if ((int)$current !== SCHEMA_VERSION) {
        $db->exec('
            DROP TABLE IF EXISTS liked_posts;
            DROP TABLE IF EXISTS posts;
            DROP TABLE IF EXISTS users;
            DELETE FROM schema_version;
        ');
        $db->exec('INSERT INTO schema_version VALUES (' . SCHEMA_VERSION . ')');
    }

    $db->exec('
        CREATE TABLE IF NOT EXISTS users (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            username     TEXT    NOT NULL UNIQUE,
            display_name TEXT,
            bio          TEXT,
            avatar       TEXT,
            password     TEXT    NOT NULL,
            is_admin     INTEGER NOT NULL DEFAULT 0,
            disabled     INTEGER NOT NULL DEFAULT 0,
            created_at   INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS posts (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL REFERENCES users(id),
            username   TEXT    NOT NULL,
            body       TEXT    NOT NULL,
            likes      INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS liked_posts (
            post_id INTEGER NOT NULL REFERENCES posts(id),
            user_id INTEGER NOT NULL REFERENCES users(id),
            PRIMARY KEY (post_id, user_id)
        );
        CREATE TABLE IF NOT EXISTS follows (
            follower_id INTEGER NOT NULL REFERENCES users(id),
            followee_id INTEGER NOT NULL REFERENCES users(id),
            PRIMARY KEY (follower_id, followee_id)
        );
    ');
    return $db;
}

// ── Helpers ───────────────────────────────────────────────

function json_error(string $msg, int $code = 400): never {
    http_response_code($code);
    echo json_encode(['error' => $msg]);
    exit;
}

function json_ok(mixed $data): never {
    echo json_encode($data);
    exit;
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function require_auth(): int {
    $uid = current_user_id();
    if (!$uid) json_error('Not authenticated', 401);
    return $uid;
}

function require_admin(SQLite3 $db): int {
    $uid  = require_auth();
    $user = $db->querySingle("SELECT is_admin FROM users WHERE id=$uid", true);
    if (!$user || !$user['is_admin']) json_error('Forbidden', 403);
    return $uid;
}

function format_user(array $u): array {
    return [
        'id'           => (int)$u['id'],
        'username'     => $u['username'],
        'display_name' => $u['display_name'] ?: null,
        'bio'          => $u['bio'] ?: null,
        'avatar'       => $u['avatar'] ? UPLOADS_URL . $u['avatar'] : null,
        'is_admin'     => (bool)$u['is_admin'],
        'disabled'     => (bool)$u['disabled'],
        'created_at'   => (int)$u['created_at'],
    ];
}

function delete_user_data(SQLite3 $db, int $uid): void {
    $user = $db->querySingle("SELECT avatar FROM users WHERE id=$uid", true);
    if ($user && $user['avatar']) {
        $path = UPLOADS_DIR . $user['avatar'];
        if (file_exists($path)) unlink($path);
    }
    $db->exec("DELETE FROM follows WHERE follower_id=$uid OR followee_id=$uid");
    $db->exec("DELETE FROM liked_posts WHERE user_id=$uid");
    $res = $db->query("SELECT id FROM posts WHERE user_id=$uid");
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $db->exec("DELETE FROM liked_posts WHERE post_id={$row['id']}");
    }
    $db->exec("DELETE FROM posts WHERE user_id=$uid");
    $db->exec("DELETE FROM users WHERE id=$uid");
}

// ── Routing ───────────────────────────────────────────────

$method    = $_SERVER['REQUEST_METHOD'];
$path      = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts     = explode('/', preg_replace('#^api\.php/?#', '', $path));
$resource  = $parts[0] ?? '';
$sub1      = $parts[1] ?? '';
$sub2      = $parts[2] ?? '';
$id        = is_numeric($sub1) ? (int)$sub1 : null;
$target_id = is_numeric($sub2) ? (int)$sub2 : null;

$db = get_db();

// ══════════════════════════════════════════════════════════
// AUTH
// ══════════════════════════════════════════════════════════

if ($method === 'GET' && $resource === 'auth' && $sub1 === 'me') {
    $uid = current_user_id();
    if (!$uid) json_ok(['user' => null]);
    $u = $db->querySingle("SELECT * FROM users WHERE id=$uid", true);
    json_ok(['user' => $u ? format_user($u) : null]);
}

if ($method === 'POST' && $resource === 'auth' && $sub1 === 'signup') {
    $input    = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    if (!preg_match('/^\w{1,30}$/', $username))
        json_error('Username must be 1–30 characters: letters, numbers, underscores only');
    if (strlen($password) < 6)
        json_error('Password must be at least 6 characters');

    $esc = SQLite3::escapeString($username);
    if ($db->querySingle("SELECT id FROM users WHERE username='$esc'"))
        json_error('Username already taken');

    $hash     = password_hash($password, PASSWORD_BCRYPT);
    $is_admin = ($db->querySingle('SELECT COUNT(*) FROM users') === 0) ? 1 : 0;

    $stmt = $db->prepare('INSERT INTO users (username, password, is_admin, created_at) VALUES (:u,:p,:a,:t)');
    $stmt->bindValue(':u', $username, SQLITE3_TEXT);
    $stmt->bindValue(':p', $hash,     SQLITE3_TEXT);
    $stmt->bindValue(':a', $is_admin, SQLITE3_INTEGER);
    $stmt->bindValue(':t', time(),    SQLITE3_INTEGER);
    $stmt->execute();

    $uid = $db->lastInsertRowID();
    $_SESSION['user_id'] = $uid;
    json_ok(['user' => format_user($db->querySingle("SELECT * FROM users WHERE id=$uid", true))]);
}

if ($method === 'POST' && $resource === 'auth' && $sub1 === 'login') {
    $input    = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    $esc  = SQLite3::escapeString($username);
    $u    = $db->querySingle("SELECT * FROM users WHERE username='$esc'", true);
    if (!$u || !password_verify($password, $u['password']))
        json_error('Invalid username or password', 401);
    if ($u['disabled'])
        json_error('Your account has been disabled', 403);

    $_SESSION['user_id'] = $u['id'];
    json_ok(['user' => format_user($u)]);
}

if ($method === 'POST' && $resource === 'auth' && $sub1 === 'logout') {
    session_destroy();
    json_ok(['ok' => true]);
}

// ══════════════════════════════════════════════════════════
// USER PROFILE
// ══════════════════════════════════════════════════════════

// POST /users/me/avatar — must come before PUT /users/me
if ($method === 'POST' && $resource === 'users' && $sub1 === 'me' && $sub2 === 'avatar') {
    $uid = require_auth();

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

    $old = $db->querySingle("SELECT avatar FROM users WHERE id=$uid", true);
    if ($old && $old['avatar']) {
        $p = UPLOADS_DIR . $old['avatar'];
        if (file_exists($p)) unlink($p);
    }

    if (!move_uploaded_file($file['tmp_name'], UPLOADS_DIR . $filename))
        json_error('Failed to save file');

    $esc = SQLite3::escapeString($filename);
    $db->exec("UPDATE users SET avatar='$esc' WHERE id=$uid");
    json_ok(['user' => format_user($db->querySingle("SELECT * FROM users WHERE id=$uid", true))]);
}

// PUT /users/me — update display_name and bio
if ($method === 'PUT' && $resource === 'users' && $sub1 === 'me') {
    $uid   = require_auth();
    $input = json_decode(file_get_contents('php://input'), true);

    $dn  = trim($input['display_name'] ?? '');
    $bio = trim($input['bio'] ?? '');

    if (mb_strlen($dn)  > 50)  json_error('Display name too long (max 50 characters)');
    if (mb_strlen($bio) > 160) json_error('Bio too long (max 160 characters)');

    $dn_sql  = $dn  ? "'" . SQLite3::escapeString($dn)  . "'" : 'NULL';
    $bio_sql = $bio ? "'" . SQLite3::escapeString($bio) . "'" : 'NULL';
    $db->exec("UPDATE users SET display_name=$dn_sql, bio=$bio_sql WHERE id=$uid");

    json_ok(['user' => format_user($db->querySingle("SELECT * FROM users WHERE id=$uid", true))]);
}

// DELETE /users/me — delete own account
if ($method === 'DELETE' && $resource === 'users' && $sub1 === 'me') {
    $uid = require_auth();
    delete_user_data($db, $uid);
    session_destroy();
    json_ok(['deleted' => true]);
}

// ══════════════════════════════════════════════════════════
// USERS
// ══════════════════════════════════════════════════════════

if ($method === 'GET' && $resource === 'users' && !$sub1) {
    $uid = require_auth();
    $q   = trim($_GET['q'] ?? '');
    $following_only = (bool)($_GET['following'] ?? false);

    $where = "WHERE u.id != $uid AND u.disabled = 0";
    if ($q !== '') {
        $esc = SQLite3::escapeString($q);
        $where .= " AND (u.username LIKE '%$esc%' OR u.display_name LIKE '%$esc%')";
    }
    if ($following_only) {
        $where .= " AND u.id IN (SELECT followee_id FROM follows WHERE follower_id=$uid)";
    }

    $res = $db->query("
        SELECT u.*, 
               CASE WHEN f.follower_id IS NOT NULL THEN 1 ELSE 0 END AS following
        FROM   users u
        LEFT JOIN follows f ON f.follower_id=$uid AND f.followee_id=u.id
        $where
        ORDER BY u.username ASC
        LIMIT 50
    ");

    $users = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $u = format_user($row);
        $u['following'] = (bool)$row['following'];
        $users[] = $u;
    }
    json_ok(['users' => $users]);
}

// ══════════════════════════════════════════════════════════
// POSTS
// ══════════════════════════════════════════════════════════

if ($method === 'GET' && $resource === 'posts') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;
    $uid    = current_user_id();

    $feed        = $_GET['feed'] ?? '';
    $feed_where  = '';
    $liked_join  = '';
    if ($uid && $feed === 'following') {
        $feed_where = "WHERE (p.user_id IN (SELECT followee_id FROM follows WHERE follower_id=$uid) OR p.user_id=$uid)";
    } elseif ($uid && $feed === 'liked') {
        $liked_join = "JOIN liked_posts lp ON lp.post_id = p.id AND lp.user_id = $uid";
    }

    $follow_join = $uid ? "LEFT JOIN follows f ON f.follower_id=$uid AND f.followee_id=p.user_id" : '';
    $follow_col  = $uid ? ", CASE WHEN f.follower_id IS NOT NULL THEN 1 ELSE 0 END AS following" : '';

    $stmt = $db->prepare("
        SELECT p.*,
               COALESCE(u.display_name, u.username) AS display_name,
               u.avatar AS user_avatar
               $follow_col
        FROM   posts p
        JOIN   users u ON u.id = p.user_id
        $liked_join
        $follow_join
        $feed_where
        ORDER  BY p.created_at DESC
        LIMIT  :lim OFFSET :off
    ");
    $stmt->bindValue(':lim', $limit,  SQLITE3_INTEGER);
    $stmt->bindValue(':off', $offset, SQLITE3_INTEGER);
    $result = $stmt->execute();

    $posts = [];
    while ($row = $result->fetchArray(SQLITE3_ASSOC)) {
        $liked = false; $own = false; $following = false;
        if ($uid) {
            $ls = $db->prepare('SELECT 1 FROM liked_posts WHERE post_id=:p AND user_id=:u');
            $ls->bindValue(':p', $row['id'], SQLITE3_INTEGER);
            $ls->bindValue(':u', $uid,       SQLITE3_INTEGER);
            $liked     = (bool)$ls->execute()->fetchArray();
            $own       = ((int)$row['user_id'] === $uid);
            $following = (bool)($row['following'] ?? false);
        }
        $row['liked']      = $liked;
        $row['own']        = $own;
        $row['following']  = $following;
        $row['avatar_url'] = $row['user_avatar'] ? UPLOADS_URL . $row['user_avatar'] : null;
        unset($row['user_avatar']);
        $posts[] = $row;
    }

    $total = (int)$db->querySingle("SELECT COUNT(*) FROM posts p $liked_join $feed_where");
    json_ok(['posts' => $posts, 'total' => $total, 'page' => $page, 'pages' => (int)ceil($total / $limit)]);
}

if ($method === 'POST' && $resource === 'posts' && !$id) {
    $uid = require_auth();
    $u   = $db->querySingle("SELECT username, disabled FROM users WHERE id=$uid", true);
    if ($u['disabled']) json_error('Your account has been disabled', 403);

    $input = json_decode(file_get_contents('php://input'), true);
    $body  = trim($input['body'] ?? '');

    if ($body === '')                       json_error('Post body is required');
    if (mb_strlen($body) > MAX_POST_LENGTH) json_error('Post exceeds ' . MAX_POST_LENGTH . ' character limit');

    $stmt = $db->prepare('INSERT INTO posts (user_id, username, body, created_at) VALUES (:u,:n,:b,:t)');
    $stmt->bindValue(':u', $uid,              SQLITE3_INTEGER);
    $stmt->bindValue(':n', $u['username'],    SQLITE3_TEXT);
    $stmt->bindValue(':b', $body,             SQLITE3_TEXT);
    $stmt->bindValue(':t', time(),            SQLITE3_INTEGER);
    $stmt->execute();

    $nid  = $db->lastInsertRowID();
    $post = $db->querySingle("
        SELECT p.*, COALESCE(u.display_name, u.username) AS display_name, u.avatar AS user_avatar
        FROM posts p JOIN users u ON u.id=p.user_id WHERE p.id=$nid
    ", true);
    $post['liked']     = false;
    $post['own']       = true;
    $post['avatar_url'] = $post['user_avatar'] ? UPLOADS_URL . $post['user_avatar'] : null;
    unset($post['user_avatar']);
    json_ok($post);
}

if ($method === 'POST' && $resource === 'posts' && $id && $sub2 === 'like') {
    $uid  = require_auth();
    $post = $db->querySingle("SELECT id FROM posts WHERE id=$id", true);
    if (!$post) json_error('Post not found', 404);

    $ls = $db->prepare('SELECT 1 FROM liked_posts WHERE post_id=:p AND user_id=:u');
    $ls->bindValue(':p', $id,  SQLITE3_INTEGER);
    $ls->bindValue(':u', $uid, SQLITE3_INTEGER);
    $already = (bool)$ls->execute()->fetchArray();

    if ($already) {
        $s = $db->prepare('DELETE FROM liked_posts WHERE post_id=:p AND user_id=:u');
        $s->bindValue(':p', $id,  SQLITE3_INTEGER);
        $s->bindValue(':u', $uid, SQLITE3_INTEGER);
        $s->execute();
        $db->exec("UPDATE posts SET likes=MAX(0,likes-1) WHERE id=$id");
        $liked = false;
    } else {
        $s = $db->prepare('INSERT INTO liked_posts (post_id,user_id) VALUES (:p,:u)');
        $s->bindValue(':p', $id,  SQLITE3_INTEGER);
        $s->bindValue(':u', $uid, SQLITE3_INTEGER);
        $s->execute();
        $db->exec("UPDATE posts SET likes=likes+1 WHERE id=$id");
        $liked = true;
    }

    $post = $db->querySingle("
        SELECT p.*, COALESCE(u.display_name, u.username) AS display_name, u.avatar AS user_avatar
        FROM posts p JOIN users u ON u.id=p.user_id WHERE p.id=$id
    ", true);
    $post['liked']     = $liked;
    $post['own']       = ((int)$post['user_id'] === $uid);
    $post['avatar_url'] = $post['user_avatar'] ? UPLOADS_URL . $post['user_avatar'] : null;
    unset($post['user_avatar']);
    json_ok($post);
}

if ($method === 'DELETE' && $resource === 'posts' && $id && $sub1 !== 'users') {
    $uid  = require_auth();
    $post = $db->querySingle("SELECT user_id FROM posts WHERE id=$id", true);
    if (!$post)                         json_error('Post not found', 404);
    if ((int)$post['user_id'] !== $uid) json_error('Forbidden', 403);

    $db->exec("DELETE FROM liked_posts WHERE post_id=$id");
    $db->exec("DELETE FROM posts WHERE id=$id");
    json_ok(['deleted' => true]);
}

// ══════════════════════════════════════════════════════════
// ADMIN
// ══════════════════════════════════════════════════════════

if ($method === 'GET' && $resource === 'admin' && $sub1 === 'users') {
    require_admin($db);
    $res   = $db->query('
        SELECT u.*, COUNT(p.id) AS post_count
        FROM   users u LEFT JOIN posts p ON p.user_id=u.id
        GROUP  BY u.id ORDER BY u.created_at ASC
    ');
    $users = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $u = format_user($row);
        $u['post_count'] = (int)$row['post_count'];
        $users[] = $u;
    }
    json_ok(['users' => $users]);
}

if ($method === 'PATCH' && $resource === 'admin' && $sub1 === 'users' && $target_id) {
    $admin_uid = require_admin($db);
    $tid       = $target_id;

    if (!$db->querySingle("SELECT id FROM users WHERE id=$tid", true))
        json_error('User not found', 404);

    $input  = json_decode(file_get_contents('php://input'), true);
    $fields = [];

    if (array_key_exists('display_name', $input)) {
        $v = trim($input['display_name'] ?? '');
        if (mb_strlen($v) > 50) json_error('Display name too long');
        $fields[] = 'display_name=' . ($v ? "'" . SQLite3::escapeString($v) . "'" : 'NULL');
    }
    if (array_key_exists('bio', $input)) {
        $v = trim($input['bio'] ?? '');
        if (mb_strlen($v) > 160) json_error('Bio too long');
        $fields[] = "bio='" . SQLite3::escapeString($v) . "'";
    }
    if (array_key_exists('disabled', $input)) {
        if ($tid === $admin_uid && $input['disabled']) json_error('Cannot disable your own account');
        $fields[] = 'disabled=' . ($input['disabled'] ? 1 : 0);
    }
    if (array_key_exists('is_admin', $input)) {
        if ($tid === $admin_uid && !$input['is_admin']) json_error('Cannot remove your own admin status');
        $fields[] = 'is_admin=' . ($input['is_admin'] ? 1 : 0);
    }

    if ($fields) $db->exec("UPDATE users SET " . implode(',', $fields) . " WHERE id=$tid");

    $row = $db->querySingle("
        SELECT u.*, COUNT(p.id) AS post_count
        FROM users u LEFT JOIN posts p ON p.user_id=u.id
        WHERE u.id=$tid GROUP BY u.id
    ", true);
    $u = format_user($row);
    $u['post_count'] = (int)$row['post_count'];
    json_ok(['user' => $u]);
}

if ($method === 'DELETE' && $resource === 'admin' && $sub1 === 'users' && $target_id) {
    $admin_uid = require_admin($db);
    $tid       = $target_id;

    if ($tid === $admin_uid) json_error('Cannot delete your own account from the admin panel');
    if (!$db->querySingle("SELECT id FROM users WHERE id=$tid", true))
        json_error('User not found', 404);

    delete_user_data($db, $tid);
    json_ok(['deleted' => true]);
}

// ══════════════════════════════════════════════════════════
// FOLLOWS
// ══════════════════════════════════════════════════════════

// POST /users/:username/follow — toggle follow
if ($method === 'POST' && $resource === 'users' && $sub1 && $sub1 !== 'me' && !is_numeric($sub1) && $sub2 === 'follow') {
    $uid = require_auth();
    $esc = SQLite3::escapeString($sub1);
    $target = $db->querySingle("SELECT id FROM users WHERE username='$esc'", true);
    if (!$target) json_error('User not found', 404);
    $tid = (int)$target['id'];
    if ($tid === $uid) json_error('Cannot follow yourself');

    $s = $db->prepare('SELECT 1 FROM follows WHERE follower_id=:f AND followee_id=:e');
    $s->bindValue(':f', $uid, SQLITE3_INTEGER);
    $s->bindValue(':e', $tid, SQLITE3_INTEGER);
    $already = (bool)$s->execute()->fetchArray();

    if ($already) {
        $s = $db->prepare('DELETE FROM follows WHERE follower_id=:f AND followee_id=:e');
        $s->bindValue(':f', $uid, SQLITE3_INTEGER);
        $s->bindValue(':e', $tid, SQLITE3_INTEGER);
        $s->execute();
        $following = false;
    } else {
        $s = $db->prepare('INSERT INTO follows (follower_id, followee_id) VALUES (:f,:e)');
        $s->bindValue(':f', $uid, SQLITE3_INTEGER);
        $s->bindValue(':e', $tid, SQLITE3_INTEGER);
        $s->execute();
        $following = true;
    }

    $follower_count = (int)$db->querySingle("SELECT COUNT(*) FROM follows WHERE followee_id=$tid");
    json_ok(['following' => $following, 'follower_count' => $follower_count]);
}

json_error('Not found', 404);
