<?php
session_start();
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

const DB_PATH              = __DIR__ . '/magpie.db';
const UPLOADS_DIR          = __DIR__ . '/uploads/avatars/';
const UPLOADS_URL          = '/uploads/avatars/';
const POSTS_UPLOADS_DIR    = __DIR__ . '/uploads/posts/';
const POSTS_UPLOADS_URL    = '/uploads/posts/';
const MAX_POST_LENGTH      = 500;
const SCHEMA_VERSION       = 9;

// ── Database ──────────────────────────────────────────────

function get_db(): SQLite3 {
    $db = new SQLite3(DB_PATH);
    $db->enableExceptions(true);
    $db->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');

    $db->exec('CREATE TABLE IF NOT EXISTS schema_version (version INTEGER NOT NULL)');
    $current = $db->querySingle('SELECT version FROM schema_version LIMIT 1');
    if ((int)$current !== SCHEMA_VERSION) {
        $db->exec('PRAGMA foreign_keys=OFF;');
        $db->exec('
            DROP TABLE IF EXISTS rate_limits;
            DROP TABLE IF EXISTS notifications;
            DROP TABLE IF EXISTS liked_posts;
            DROP TABLE IF EXISTS posts;
            DROP TABLE IF EXISTS follows;
            DROP TABLE IF EXISTS users;
            DROP TABLE IF EXISTS remember_tokens;
            DROP TABLE IF EXISTS settings;
            DELETE FROM schema_version;
        ');
        $db->exec('PRAGMA foreign_keys=ON;');
        $db->exec('INSERT INTO schema_version VALUES (' . SCHEMA_VERSION . ')');
    }

    $db->exec('
        CREATE TABLE IF NOT EXISTS users (
            id           INTEGER PRIMARY KEY AUTOINCREMENT,
            username     TEXT    NOT NULL UNIQUE,
            email        TEXT    NOT NULL UNIQUE,
            email_verified INTEGER NOT NULL DEFAULT 0,
            verification_token TEXT,
            reset_token  TEXT,
            reset_token_expires INTEGER,
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
            image      TEXT,
            likes      INTEGER NOT NULL DEFAULT 0,
            parent_id  INTEGER REFERENCES posts(id),
            quote_id   INTEGER REFERENCES posts(id),
            edited_at  INTEGER,
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
        CREATE TABLE IF NOT EXISTS notifications (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id    INTEGER NOT NULL REFERENCES users(id),
            actor_id   INTEGER NOT NULL REFERENCES users(id),
            type       TEXT    NOT NULL,
            post_id    INTEGER REFERENCES posts(id),
            read       INTEGER NOT NULL DEFAULT 0,
            created_at INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS remember_tokens (
            token   TEXT    NOT NULL PRIMARY KEY,
            user_id INTEGER NOT NULL REFERENCES users(id),
            expires INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS settings (
            key   TEXT NOT NULL PRIMARY KEY,
            value TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS rate_limits (
            key     TEXT NOT NULL PRIMARY KEY,
            hits    INTEGER NOT NULL,
            expires INTEGER NOT NULL
        );
    ');
    return $db;
}

// ── Helpers ───────────────────────────────────────────────

function get_base_url(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    // In a real production app, this should be a hardcoded constant.
    return "$protocol://$host";
}

function check_rate_limit(SQLite3 $db, string $key, int $limit, int $window_seconds): void {
    $now = time();
    db_exec($db, 'DELETE FROM rate_limits WHERE expires < :now', [':now' => $now]);
    
    $row = db_query_single($db, 'SELECT hits, expires FROM rate_limits WHERE key=:k', [':k' => $key], true);
    if ($row) {
        if ($row['hits'] >= $limit) {
            json_error('Too many requests. Please try again later.', 429);
        }
        db_exec($db, 'UPDATE rate_limits SET hits = hits + 1 WHERE key=:k', [':k' => $key]);
    } else {
        db_exec($db, 'INSERT INTO rate_limits (key, hits, expires) VALUES (:k, 1, :e)', [
            ':k' => $key,
            ':e' => $now + $window_seconds
        ]);
    }
}

function get_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validate_csrf(): void {
    $method = $_SERVER['REQUEST_METHOD'];
    if (in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if (!$token || $token !== ($_SESSION['csrf_token'] ?? '')) {
            json_error('Invalid CSRF token', 403);
        }
    }
}
function get_setting(SQLite3 $db, string $key, string $default = ''): string {
    return (string)(db_query_single($db, 'SELECT value FROM settings WHERE key=:k', [':k' => $key]) ?? $default);
}

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
    $user = db_query_single($db, 'SELECT is_admin FROM users WHERE id=:id', [':id' => $uid], true);
    if (!$user || !$user['is_admin']) json_error('Forbidden', 403);
    return $uid;
}

// ── Database Helpers ──────────────────────────────────────

function db_exec(SQLite3 $db, string $sql, array $params = []): void {
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $type = is_int($val) ? SQLITE3_INTEGER : (is_float($val) ? SQLITE3_FLOAT : (is_null($val) ? SQLITE3_NULL : SQLITE3_TEXT));
        $stmt->bindValue($key, $val, $type);
    }
    $stmt->execute();
}

function db_query(SQLite3 $db, string $sql, array $params = []): SQLite3Result {
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $type = is_int($val) ? SQLITE3_INTEGER : (is_float($val) ? SQLITE3_FLOAT : (is_null($val) ? SQLITE3_NULL : SQLITE3_TEXT));
        $stmt->bindValue($key, $val, $type);
    }
    return $stmt->execute();
}

function db_query_single(SQLite3 $db, string $sql, array $params = [], bool $entire_row = false): mixed {
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $val) {
        $type = is_int($val) ? SQLITE3_INTEGER : (is_float($val) ? SQLITE3_FLOAT : (is_null($val) ? SQLITE3_NULL : SQLITE3_TEXT));
        $stmt->bindValue($key, $val, $type);
    }
    $result = $stmt->execute();
    $row = $result->fetchArray($entire_row ? SQLITE3_ASSOC : SQLITE3_NUM);
    if ($row === false) return null;
    return $entire_row ? $row : $row[0];
}

function format_user(array $u): array {
    return [
        'id'           => (int)$u['id'],
        'username'     => $u['username'],
        'email'        => $u['email'] ?? null,
        'email_verified' => (bool)($u['email_verified'] ?? false),
        'display_name' => $u['display_name'] ?: null,
        'bio'          => $u['bio'] ?: null,
        'avatar'       => $u['avatar'] ? UPLOADS_URL . $u['avatar'] : null,
        'is_admin'     => (bool)$u['is_admin'],
        'disabled'     => (bool)$u['disabled'],
        'created_at'   => (int)$u['created_at'],
    ];
}

function send_mail(string $to, string $subject, string $text, string $html): bool {
    $boundary = '----=_Part_' . md5(uniqid());
    $headers = implode("\r\n", [
        'From: Magpie <noreply@magpie.local>',
        'MIME-Version: 1.0',
        'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        'X-Mailer: PHP/' . phpversion(),
    ]);
    $body  = "--$boundary\r\n";
    $body .= "Content-Type: text/plain; charset=utf-8\r\n\r\n$text\r\n\r\n";
    $body .= "--$boundary\r\n";
    $body .= "Content-Type: text/html; charset=utf-8\r\n\r\n$html\r\n\r\n";
    $body .= "--$boundary--";
    // mail() will use sendmail_path from php.ini, which should be set to Mailpit
    return mail($to, $subject, $body, $headers);
}

function mail_html_wrap(string $heading, string $body_html, string $cta_text, string $cta_url): string {
    $baseUrl = get_base_url();
    return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Magpie</title>
</head>
<body style="margin:0;padding:0;background:#f4f4f5;font-family:Arial,Helvetica,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f4f5;padding:40px 0;">
  <tr><td align="center">
    <table width="560" cellpadding="0" cellspacing="0" style="background:#ffffff;border-radius:8px;overflow:hidden;box-shadow:0 1px 4px rgba(0,0,0,.08);">
      <!-- Header -->
      <tr>
        <td style="background:#ffffff;padding:28px 40px;text-align:center;border-bottom:1px solid #e5e7eb;">
          <img src="$baseUrl/logo.png" alt="Magpie" width="40" height="40" style="vertical-align:middle;margin-right:10px;"><span style="font-size:22px;font-weight:700;color:#0f1419;letter-spacing:0.5px;vertical-align:middle;">Magpie</span>
        </td>
      </tr>
      <!-- Body -->
      <tr>
        <td style="padding:40px 40px 32px;">
          <h1 style="margin:0 0 16px;font-size:22px;color:#111827;line-height:1.3;">$heading</h1>
          $body_html
          <table cellpadding="0" cellspacing="0" style="margin:32px 0;">
            <tr>
              <td style="background:#1a1a2e;border-radius:6px;">
                <a href="$cta_url"
                   style="display:inline-block;padding:14px 28px;font-size:15px;font-weight:600;
                          color:#ffffff;text-decoration:none;letter-spacing:0.2px;">
                  $cta_text
                </a>
              </td>
            </tr>
          </table>
          <p style="margin:0;font-size:13px;color:#6b7280;">
            Or copy and paste this link into your browser:<br>
            <a href="$cta_url" style="color:#4f46e5;word-break:break-all;">$cta_url</a>
          </p>
        </td>
      </tr>
      <!-- Footer -->
      <tr>
        <td style="background:#f9fafb;border-top:1px solid #e5e7eb;padding:20px 40px;text-align:center;">
          <p style="margin:0;font-size:12px;color:#9ca3af;">
            You received this email because an action was taken on your Magpie account.<br>
            If you did not request this, you can safely ignore this message.
          </p>
        </td>
      </tr>
    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

// Columns and joins for a full post query.
// $alias is the posts table alias.
function post_select_cols(string $alias, ?int $uid): string {
    $liked_col  = $uid ? ", CASE WHEN lp.user_id IS NOT NULL THEN 1 ELSE 0 END AS liked_flag"
                       : ', 0 AS liked_flag';
    $follow_col = $uid ? ", CASE WHEN f.follower_id IS NOT NULL THEN 1 ELSE 0 END AS following"
                       : ', 0 AS following';
    $reposted_col = $uid ? ", CASE WHEN (SELECT 1 FROM posts r WHERE r.quote_id=$alias.id AND r.user_id=$uid AND r.body='' AND r.image IS NULL LIMIT 1) IS NOT NULL THEN 1 ELSE 0 END AS reposted_flag"
                         : ', 0 AS reposted_flag';
    $a = $alias;
    return "
        $a.*,
        COALESCE(u.display_name, u.username) AS display_name,
        u.avatar AS user_avatar,
        (SELECT COUNT(*) FROM posts r WHERE r.parent_id = $a.id) AS reply_count,
        (SELECT COUNT(*) FROM posts r WHERE r.quote_id = $a.id AND r.body='' AND r.image IS NULL) AS repost_count,
        pu.username AS parent_username,
        COALESCE(pu.display_name, pu.username) AS parent_display_name,
        qp.id AS quote_post_id,
        qp.body AS quote_body,
        qp.username AS quote_username,
        qp.created_at AS quote_created_at,
        COALESCE(qu.display_name, qu.username) AS quote_display_name,
        qu.avatar AS quote_avatar_file
        $liked_col $follow_col $reposted_col
    ";
}

function post_join_sql(string $alias, ?int $uid): string {
    $a           = $alias;
    $liked_join  = $uid ? "LEFT JOIN liked_posts lp ON lp.post_id=$a.id AND lp.user_id=$uid" : '';
    $follow_join = $uid ? "LEFT JOIN follows f ON f.follower_id=$uid AND f.followee_id=$a.user_id" : '';
    return "
        JOIN  users u  ON u.id  = $a.user_id
        LEFT JOIN posts  pp ON pp.id = $a.parent_id
        LEFT JOIN users  pu ON pu.id = pp.user_id
        LEFT JOIN posts  qp ON qp.id = $a.quote_id
        LEFT JOIN users  qu ON qu.id = qp.user_id
        $liked_join $follow_join
    ";
}

function format_post_row(array $row, ?int $uid): array {
    $row['id']           = (int)$row['id'];
    $row['user_id']      = (int)$row['user_id'];
    $row['likes']        = (int)$row['likes'];
    $row['created_at']   = (int)$row['created_at'];
    $row['parent_id']    = isset($row['parent_id']) && $row['parent_id'] !== null ? (int)$row['parent_id'] : null;
    $row['quote_id']     = isset($row['quote_id'])  && $row['quote_id']  !== null ? (int)$row['quote_id']  : null;
    $row['reply_count']  = (int)($row['reply_count']  ?? 0);
    $row['repost_count'] = (int)($row['repost_count'] ?? 0);
    $row['liked']        = (bool)($row['liked_flag']  ?? false);
    $row['reposted']     = (bool)($row['reposted_flag'] ?? false);
    $row['own']          = $uid ? ((int)$row['user_id'] === $uid) : false;
    $row['following']    = (bool)($row['following'] ?? false);
    $row['avatar_url']   = !empty($row['user_avatar']) ? UPLOADS_URL . $row['user_avatar'] : null;
    $image_urls = [];
    if (!empty($row['image'])) {
        $decoded = json_decode($row['image'], true);
        if (is_array($decoded)) {
            foreach ($decoded as $fn) $image_urls[] = POSTS_UPLOADS_URL . $fn;
        } else {
            $image_urls[] = POSTS_UPLOADS_URL . $row['image'];
        }
    }
    $row['image_urls'] = $image_urls;
    $row['edited_at']   = isset($row['edited_at']) && $row['edited_at'] !== null ? (int)$row['edited_at'] : null;

    $row['parent_username']     = $row['parent_username']     ?? null;
    $row['parent_display_name'] = $row['parent_display_name'] ?? null;

    if (!empty($row['quote_post_id'])) {
        $row['quote'] = [
            'id'           => (int)$row['quote_post_id'],
            'body'         => $row['quote_body'],
            'username'     => $row['quote_username'],
            'display_name' => $row['quote_display_name'] ?: $row['quote_username'],
            'created_at'   => (int)$row['quote_created_at'],
            'avatar_url'   => !empty($row['quote_avatar_file']) ? UPLOADS_URL . $row['quote_avatar_file'] : null,
        ];
    } else {
        $row['quote'] = null;
    }

    unset($row['user_avatar'], $row['liked_flag'], $row['reposted_flag'], $row['image'],
          $row['quote_post_id'], $row['quote_body'], $row['quote_username'],
          $row['quote_created_at'], $row['quote_display_name'], $row['quote_avatar_file']);

    return $row;
}

function fetch_post(SQLite3 $db, int $post_id, ?int $uid): ?array {
    $cols  = post_select_cols('p', $uid);
    $joins = post_join_sql('p', $uid);
    $row   = db_query_single($db, "SELECT $cols FROM posts p $joins WHERE p.id=:id", [':id' => $post_id], true);
    return $row ? format_post_row($row, $uid) : null;
}

function delete_post_cascade(SQLite3 $db, int $post_id): void {
    // Delete child replies first
    $res = db_query($db, 'SELECT id FROM posts WHERE parent_id=:id', [':id' => $post_id]);
    while ($child = $res->fetchArray(SQLITE3_ASSOC)) {
        delete_post_cascade($db, (int)$child['id']);
    }
    // Delete post images if present
    $post_data = db_query_single($db, 'SELECT image FROM posts WHERE id=:id', [':id' => $post_id], true);
    if ($post_data && !empty($post_data['image'])) {
        $decoded = json_decode($post_data['image'], true);
        $filenames = is_array($decoded) ? $decoded : [$post_data['image']];
        foreach ($filenames as $fn) {
            $p = POSTS_UPLOADS_DIR . $fn;
            if (file_exists($p)) unlink($p);
        }
    }
    db_exec($db, 'DELETE FROM liked_posts   WHERE post_id=:id', [':id' => $post_id]);
    db_exec($db, 'DELETE FROM notifications WHERE post_id=:id', [':id' => $post_id]);
    db_exec($db, 'DELETE FROM posts         WHERE id=:id',      [':id' => $post_id]);
}

function delete_user_data(SQLite3 $db, int $uid): void {
    $user = db_query_single($db, 'SELECT avatar FROM users WHERE id=:id', [':id' => $uid], true);
    if ($user && $user['avatar']) {
        $path = UPLOADS_DIR . $user['avatar'];
        if (file_exists($path)) unlink($path);
    }
    db_exec($db, 'DELETE FROM notifications WHERE user_id=:id OR actor_id=:id', [':id' => $uid]);
    db_exec($db, 'DELETE FROM follows WHERE follower_id=:id OR followee_id=:id', [':id' => $uid]);
    db_exec($db, 'DELETE FROM liked_posts WHERE user_id=:id', [':id' => $uid]);
    db_exec($db, 'DELETE FROM remember_tokens WHERE user_id=:id', [':id' => $uid]);

    $res = db_query($db, 'SELECT id FROM posts WHERE user_id=:id AND parent_id IS NULL', [':id' => $uid]);
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        delete_post_cascade($db, (int)$row['id']);
    }
    // Orphaned replies from this user (parent was someone else's post)
    db_exec($db, 'UPDATE posts SET parent_id=NULL WHERE user_id=:id AND parent_id IS NOT NULL', [':id' => $uid]);
    db_exec($db, 'DELETE FROM liked_posts WHERE post_id IN (SELECT id FROM posts WHERE user_id=:id)', [':id' => $uid]);
    db_exec($db, 'DELETE FROM notifications WHERE post_id IN (SELECT id FROM posts WHERE user_id=:id)', [':id' => $uid]);
    // Clean up images for any remaining posts
    $img_res = db_query($db, 'SELECT image FROM posts WHERE user_id=:id AND image IS NOT NULL', [':id' => $uid]);
    while ($img_row = $img_res->fetchArray(SQLITE3_ASSOC)) {
        $decoded = json_decode($img_row['image'], true);
        $filenames = is_array($decoded) ? $decoded : [$img_row['image']];
        foreach ($filenames as $fn) {
            $p = POSTS_UPLOADS_DIR . $fn;
            if (file_exists($p)) unlink($p);
        }
    }
    db_exec($db, 'DELETE FROM posts WHERE user_id=:id', [':id' => $uid]);
    db_exec($db, 'DELETE FROM users WHERE id=:id',      [':id' => $uid]);
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
validate_csrf();

// Auto-login via remember-me cookie
if (!isset($_SESSION['user_id']) && !empty($_COOKIE['magpie_rmb'])) {
    $rmb_token = $_COOKIE['magpie_rmb'];
    $rmb_row = db_query_single($db, 'SELECT user_id FROM remember_tokens WHERE token=:t AND expires > :now', [
        ':t' => $rmb_token, ':now' => time()
    ], true);
    if ($rmb_row) {
        $rmb_user = db_query_single($db, 'SELECT id, disabled FROM users WHERE id=:id', [':id' => $rmb_row['user_id']], true);
        if ($rmb_user && !$rmb_user['disabled']) {
            $_SESSION['user_id'] = (int)$rmb_user['id'];
        }
    }
}

// ══════════════════════════════════════════════════════════
// AUTH
// ══════════════════════════════════════════════════════════

if ($method === 'GET' && $resource === 'auth' && $sub1 === 'me') {
    $uid = current_user_id();
    if (!$uid) json_ok(['user' => null, 'csrf_token' => get_csrf_token()]);
    $u = db_query_single($db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true);
    json_ok(['user' => $u ? format_user($u) : null, 'csrf_token' => get_csrf_token()]);
}

if ($method === 'POST' && $resource === 'auth' && $sub1 === 'signup') {
    check_rate_limit($db, 'signup_' . $_SERVER['REMOTE_ADDR'], 5, 3600); // 5 per hour
    $input    = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $email    = trim($input['email'] ?? '');
    $password = $input['password'] ?? '';

    if (!preg_match('/^\w{1,30}$/', $username))
        json_error('Username must be 1–30 characters: letters, numbers, underscores only');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
        json_error('Invalid email address');
    if (strlen($password) < 6)
        json_error('Password must be at least 6 characters');

    if (db_query_single($db, 'SELECT id FROM users WHERE username=:u', [':u' => $username]))
        json_error('Username already taken');

    if (db_query_single($db, 'SELECT id FROM users WHERE email=:e', [':e' => $email]))
        json_error('Email already registered');

    $hash     = password_hash($password, PASSWORD_BCRYPT);
    $is_admin = (db_query_single($db, 'SELECT COUNT(*) FROM users') === 0) ? 1 : 0;
    $v_token  = bin2hex(random_bytes(32));

    db_exec($db, 'INSERT INTO users (username, email, password, is_admin, verification_token, created_at) VALUES (:u,:e,:p,:a,:v,:t)', [
        ':u' => $username,
        ':e' => $email,
        ':p' => $hash,
        ':a' => $is_admin,
        ':v' => $v_token,
        ':t' => time()
    ]);

    $uid = $db->lastInsertRowID();
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
        'user'       => format_user(db_query_single($db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true)),
        'csrf_token' => get_csrf_token()
    ]);
}

if ($method === 'POST' && $resource === 'auth' && $sub1 === 'login') {
    check_rate_limit($db, 'login_' . $_SERVER['REMOTE_ADDR'], 10, 600); // 10 per 10 mins
    $input    = json_decode(file_get_contents('php://input'), true);
    $username = trim($input['username'] ?? '');
    $password = $input['password'] ?? '';

    $u = db_query_single($db, 'SELECT * FROM users WHERE username=:u OR email=:u', [':u' => $username], true);
    if (!$u || !password_verify($password, $u['password']))
        json_error('Invalid username or password', 401);
    if ($u['disabled'])
        json_error('Your account has been disabled', 403);

    session_regenerate_id(true);
    $_SESSION['user_id'] = $u['id'];

    if (!empty($input['remember_me'])) {
        $days    = max(1, (int)(get_setting($db, 'remember_me_days') ?: 30));
        $token   = bin2hex(random_bytes(32));
        $expires = time() + $days * 86400;
        db_exec($db, 'INSERT INTO remember_tokens (token, user_id, expires) VALUES (:t, :u, :e)', [
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

if ($method === 'POST' && $resource === 'auth' && $sub1 === 'logout') {
    if (!empty($_COOKIE['magpie_rmb'])) {
        db_exec($db, 'DELETE FROM remember_tokens WHERE token=:t', [':t' => $_COOKIE['magpie_rmb']]);
        setcookie('magpie_rmb', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
    session_destroy();
    session_start();
    session_regenerate_id(true);
    json_ok(['ok' => true, 'csrf_token' => get_csrf_token()]);
}

if ($method === 'POST' && $resource === 'auth' && $sub1 === 'verify-email') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? '');
    if (!$token) json_error('Token required');

    $u = db_query_single($db, 'SELECT id FROM users WHERE verification_token=:t', [':t' => $token], true);
    if (!$u) json_error('Invalid or expired token');

    db_exec($db, 'UPDATE users SET email_verified=1, verification_token=NULL WHERE id=:id', [':id' => $u['id']]);
    json_ok(['ok' => true]);
}

if ($method === 'POST' && $resource === 'auth' && $sub1 === 'forgot-password') {
    check_rate_limit($db, 'forgot_' . $_SERVER['REMOTE_ADDR'], 5, 3600); // 5 per hour
    $input = json_decode(file_get_contents('php://input'), true);
    $email = trim($input['email'] ?? '');
    if (!$email) json_error('Email required');

    $u = db_query_single($db, 'SELECT id, username FROM users WHERE email=:e', [':e' => $email], true);
    if ($u) {
        $token = bin2hex(random_bytes(32));
        $expires = time() + 3600; // 1 hour
        db_exec($db, 'UPDATE users SET reset_token=:t, reset_token_expires=:e WHERE id=:id', [
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
    // Always return OK for security
    json_ok(['ok' => true]);
}

if ($method === 'POST' && $resource === 'auth' && $sub1 === 'reset-password') {
    $input = json_decode(file_get_contents('php://input'), true);
    $token = trim($input['token'] ?? '');
    $password = $input['password'] ?? '';

    if (!$token) json_error('Token required');
    if (strlen($password) < 6) json_error('Password must be at least 6 characters');

    $u = db_query_single($db, 'SELECT id FROM users WHERE reset_token=:t AND reset_token_expires > :now', [
        ':t' => $token,
        ':now' => time()
    ], true);
    if (!$u) json_error('Invalid or expired token');

    $hash = password_hash($password, PASSWORD_BCRYPT);
    db_exec($db, 'UPDATE users SET password=:p, reset_token=NULL, reset_token_expires=NULL WHERE id=:id', [
        ':p' => $hash,
        ':id' => $u['id']
    ]);
    json_ok(['ok' => true]);
}

if ($method === 'POST' && $resource === 'auth' && $sub1 === 'resend-verification') {
    $uid = require_auth();
    $u = db_query_single($db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true);
    if ($u['email_verified']) json_error('Email already verified');
    
    $v_token = $u['verification_token'];
    if (!$v_token) {
        $v_token = bin2hex(random_bytes(32));
        db_exec($db, 'UPDATE users SET verification_token=:v WHERE id=:id', [':v' => $v_token, ':id' => $uid]);
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

// ══════════════════════════════════════════════════════════
// USER PROFILE
// ══════════════════════════════════════════════════════════

// POST /users/me/avatar — must come before PUT /users/me
if ($method === 'POST' && $resource === 'users' && $sub1 === 'me' && $sub2 === 'avatar') {
    $uid = require_auth();

    // Preset selection (JSON body with preset name)
    $ct = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_starts_with($ct, 'application/json')) {
        $body   = json_decode(file_get_contents('php://input'), true);
        $preset = $body['preset'] ?? '';
        $valid  = array_map(fn($n) => "magpie_0{$n}.svg", range(1,9));
        $valid[] = 'magpie_10.svg';
        if (!in_array($preset, $valid, true)) json_error('Invalid preset name');

        $old = db_query_single($db, 'SELECT avatar FROM users WHERE id=:id', [':id' => $uid], true);
        if ($old && $old['avatar'] && !str_starts_with($old['avatar'], 'presets/')) {
            $p = UPLOADS_DIR . $old['avatar'];
            if (file_exists($p)) unlink($p);
        }

        db_exec($db, 'UPDATE users SET avatar=:v WHERE id=:id', [':v' => 'presets/' . $preset, ':id' => $uid]);
        json_ok(['user' => format_user(db_query_single($db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true))]);
    }

    // File upload
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

    $old = db_query_single($db, 'SELECT avatar FROM users WHERE id=:id', [':id' => $uid], true);
    if ($old && $old['avatar'] && !str_starts_with($old['avatar'], 'presets/')) {
        $p = UPLOADS_DIR . $old['avatar'];
        if (file_exists($p)) unlink($p);
    }

    if (!move_uploaded_file($file['tmp_name'], UPLOADS_DIR . $filename))
        json_error('Failed to save file');

    db_exec($db, 'UPDATE users SET avatar=:v WHERE id=:id', [':v' => $filename, ':id' => $uid]);
    json_ok(['user' => format_user(db_query_single($db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true))]);
}

// PUT /users/me — update display_name, email, and bio
if ($method === 'PUT' && $resource === 'users' && $sub1 === 'me') {
    $uid   = require_auth();
    $input = json_decode(file_get_contents('php://input'), true);

    $dn    = trim($input['display_name'] ?? '');
    $bio   = trim($input['bio'] ?? '');
    $email = trim($input['email'] ?? '');

    if (mb_strlen($dn)  > 50)  json_error('Display name too long (max 50 characters)');
    if (mb_strlen($bio) > 160) json_error('Bio too long (max 160 characters)');
    if (!$email)                json_error('Email address is required');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) json_error('Invalid email address');

    $current = db_query_single($db, 'SELECT email FROM users WHERE id=:id', [':id' => $uid], true);
    $email_changed = strtolower($email) !== strtolower($current['email']);
    if ($email_changed) {
        if (db_query_single($db, 'SELECT id FROM users WHERE email=:e AND id != :id', [':e' => $email, ':id' => $uid]))
            json_error('Email address is already in use');
        $v_token = bin2hex(random_bytes(32));
        db_exec($db, 'UPDATE users SET display_name=:dn, bio=:bio, email=:e, email_verified=0, verification_token=:v WHERE id=:id', [
            ':dn' => $dn ?: null,
            ':bio' => $bio ?: null,
            ':e' => $email,
            ':v' => $v_token,
            ':id' => $uid
        ]);
        $uname = db_query_single($db, 'SELECT username FROM users WHERE id=:id', [':id' => $uid]);
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
        db_exec($db, 'UPDATE users SET display_name=:dn, bio=:bio WHERE id=:id', [
            ':dn' => $dn ?: null,
            ':bio' => $bio ?: null,
            ':id' => $uid
        ]);
    }

    json_ok(['user' => format_user(db_query_single($db, 'SELECT * FROM users WHERE id=:id', [':id' => $uid], true))]);
}

// DELETE /users/me — delete own account
if ($method === 'DELETE' && $resource === 'users' && $sub1 === 'me') {
    $uid = require_auth();
    delete_user_data($db, $uid);
    if (!empty($_COOKIE['magpie_rmb'])) {
        setcookie('magpie_rmb', '', ['expires' => time() - 3600, 'path' => '/', 'httponly' => true, 'samesite' => 'Lax']);
    }
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

    $where = "WHERE u.id != :uid AND u.disabled = 0";
    $params = [':uid' => $uid];

    if ($q !== '') {
        $where .= " AND (u.username LIKE :q OR u.display_name LIKE :q)";
        $params[':q'] = "%$q%";
    }
    if ($following_only) {
        $where .= " AND u.id IN (SELECT followee_id FROM follows WHERE follower_id=:uid)";
    }

    $res = db_query($db, "
        SELECT u.*,
               CASE WHEN f.follower_id IS NOT NULL THEN 1 ELSE 0 END AS following
        FROM   users u
        LEFT JOIN follows f ON f.follower_id=:uid AND f.followee_id=u.id
        $where
        ORDER BY u.username ASC
        LIMIT 50
    ", $params);

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

function fetch_descendants(SQLite3 $db, int $post_id, int $depth, ?int $uid, int &$count, int $max = 200): array {
    if ($count >= $max) return [];
    $cols   = post_select_cols('p', $uid);
    $joins  = post_join_sql('p', $uid);
    $res    = db_query($db, "SELECT $cols FROM posts p $joins WHERE p.parent_id=:id ORDER BY p.created_at ASC", [':id' => $post_id]);
    $result = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        if ($count >= $max) break;
        $formatted          = format_post_row($row, $uid);
        $formatted['depth'] = $depth;
        $result[]           = $formatted;
        $count++;
        $children = fetch_descendants($db, $formatted['id'], $depth + 1, $uid, $count, $max);
        $result   = array_merge($result, $children);
    }
    return $result;
}

// GET /posts/:id/thread
if ($method === 'GET' && $resource === 'posts' && $id && $sub2 === 'thread') {
    $uid  = current_user_id();
    $post = fetch_post($db, $id, $uid);
    if (!$post) json_error('Post not found', 404);

    // Walk up the ancestor chain
    $ancestors = [];
    $pid       = $post['parent_id'];
    $anc_depth = 0;
    while ($pid && $anc_depth < 20) {
        $ancestor = fetch_post($db, $pid, $uid);
        if (!$ancestor) break;
        array_unshift($ancestors, $ancestor);
        $pid = $ancestor['parent_id'];
        $anc_depth++;
    }

    // Recursively fetch all descendants (depth-first)
    $count   = 0;
    $replies = fetch_descendants($db, $id, 1, $uid, $count);

    json_ok(['ancestors' => $ancestors, 'post' => $post, 'replies' => $replies]);
}

if ($method === 'GET' && $resource === 'posts') {
    $page   = max(1, (int)($_GET['page'] ?? 1));
    $limit  = 20;
    $offset = ($page - 1) * $limit;
    $uid    = current_user_id();

    $feed       = $_GET['feed'] ?? '';
    $liked_join = '';
    $params     = [':lim' => $limit, ':off' => $offset];
    $conditions = [];

    if ($uid) {
        $params[':uid'] = $uid;
        if ($feed === 'following') {
            $conditions[] = "(p.user_id IN (SELECT followee_id FROM follows WHERE follower_id=:uid) OR p.user_id=:uid)";
        } elseif ($feed === 'liked') {
            $liked_join = "JOIN liked_posts lp2 ON lp2.post_id = p.id AND lp2.user_id = :uid";
        }
    }

    $where_clause = $conditions ? 'WHERE ' . implode(' AND ', $conditions) : '';
    $cols         = post_select_cols('p', $uid);
    $joins        = post_join_sql('p', $uid);

    $res = db_query($db, "
        SELECT $cols FROM posts p
        $liked_join $joins
        $where_clause
        ORDER BY p.created_at DESC
        LIMIT :lim OFFSET :off
    ", $params);

    $posts = [];
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
        $posts[] = format_post_row($row, $uid);
    }

    $total = (int)db_query_single($db, "SELECT COUNT(*) FROM posts p $liked_join $where_clause", $params);
    json_ok(['posts' => $posts, 'total' => $total, 'page' => $page, 'pages' => (int)ceil($total / $limit)]);
}

if ($method === 'POST' && $resource === 'posts' && !$id) {
    $uid = require_auth();
    $u   = db_query_single($db, 'SELECT username, disabled, email_verified FROM users WHERE id=:id', [':id' => $uid], true);
    if ($u['disabled']) json_error('Your account has been disabled', 403);
    if (!$u['email_verified']) json_error('Please verify your email address to post', 403);

    $body      = trim($_POST['body'] ?? '');
    $parent_id = isset($_POST['parent_id']) && ctype_digit((string)$_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
    $quote_id  = isset($_POST['quote_id'])  && ctype_digit((string)$_POST['quote_id'])  ? (int)$_POST['quote_id']  : null;

    // Handle optional image uploads (up to 4)
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
        $parent = db_query_single($db, 'SELECT id, user_id FROM posts WHERE id=:id', [':id' => $parent_id], true);
        if (!$parent) json_error('Parent post not found', 404);
    }
    if ($quote_id) {
        $quoted = db_query_single($db, 'SELECT id, user_id FROM posts WHERE id=:id', [':id' => $quote_id], true);
        if (!$quoted) json_error('Quoted post not found', 404);
    }

    db_exec($db, 'INSERT INTO posts (user_id, username, body, image, parent_id, quote_id, created_at) VALUES (:u,:n,:b,:img,:p,:q,:t)', [
        ':u'   => $uid,
        ':n'   => $u['username'],
        ':b'   => $body,
        ':img' => $image_json,
        ':p'   => $parent_id,
        ':q'   => $quote_id,
        ':t'   => time()
    ]);

    $nid = $db->lastInsertRowID();

    // Create reply notification
    if ($parent_id && isset($parent)) {
        $target_uid = (int)$parent['user_id'];
        if ($target_uid !== $uid) {
            db_exec($db, 'INSERT INTO notifications (user_id,actor_id,type,post_id,created_at) VALUES (:u,:a,:t,:p,:ts)', [
                ':u'  => $target_uid,
                ':a'  => $uid,
                ':t'  => 'reply',
                ':p'  => $nid,
                ':ts' => time()
            ]);
        }
    }

    // Create quote notification
    if ($quote_id && isset($quoted)) {
        $target_uid = (int)$quoted['user_id'];
        if ($target_uid !== $uid) {
            $type = ($body === '' && empty($uploaded_filenames)) ? 'repost' : 'quote';
            db_exec($db, 'INSERT INTO notifications (user_id,actor_id,type,post_id,created_at) VALUES (:u,:a,:t,:p,:ts)', [
                ':u'  => $target_uid,
                ':a'  => $uid,
                ':t'  => $type,
                ':p'  => $nid,
                ':ts' => time()
            ]);
        }
    }

    $post = fetch_post($db, $nid, $uid);
    json_ok($post);
}

if ($method === 'POST' && $resource === 'posts' && $id && $sub2 === 'repost') {
    $uid = require_auth();
    $u   = db_query_single($db, 'SELECT username, disabled, email_verified FROM users WHERE id=:id', [':id' => $uid], true);
    if ($u['disabled']) json_error('Your account has been disabled', 403);
    if (!$u['email_verified']) json_error('Please verify your email address to post', 403);

    $original = db_query_single($db, 'SELECT id, user_id FROM posts WHERE id=:id', [':id' => $id], true);
    if (!$original) json_error('Post not found', 404);

    // Look for existing pure repost
    $existing = db_query_single($db, "SELECT id FROM posts WHERE user_id=:u AND quote_id=:q AND body='' AND image IS NULL", [
        ':u' => $uid, ':q' => $id
    ]);

    if ($existing) {
        // Un-repost
        db_exec($db, 'DELETE FROM notifications WHERE actor_id=:u AND post_id=:p AND type=\'repost\'', [':u' => $uid, ':p' => $existing]);
        db_exec($db, 'DELETE FROM posts WHERE id=:id', [':id' => $existing]);
    } else {
        // Repost
        db_exec($db, 'INSERT INTO posts (user_id, username, body, image, quote_id, created_at) VALUES (:u,:n,\'\',NULL,:q,:t)', [
            ':u' => $uid, ':n' => $u['username'], ':q' => $id, ':t' => time()
        ]);
        $nid = $db->lastInsertRowID();
        
        $target_uid = (int)$original['user_id'];
        if ($target_uid !== $uid) {
            db_exec($db, 'INSERT INTO notifications (user_id,actor_id,type,post_id,created_at) VALUES (:u,:a,\'repost\',:p,:ts)', [
                ':u'  => $target_uid,
                ':a'  => $uid,
                ':p'  => $nid,
                ':ts' => time()
            ]);
        }
    }

    json_ok(fetch_post($db, $id, $uid));
}

if ($method === 'POST' && $resource === 'posts' && $id && $sub2 === 'like') {
    $uid  = require_auth();
    $post = db_query_single($db, 'SELECT id FROM posts WHERE id=:id', [':id' => $id], true);
    if (!$post) json_error('Post not found', 404);

    $already = (bool)db_query_single($db, 'SELECT 1 FROM liked_posts WHERE post_id=:p AND user_id=:u', [':p' => $id, ':u' => $uid]);

    if ($already) {
        db_exec($db, 'DELETE FROM liked_posts WHERE post_id=:p AND user_id=:u', [':p' => $id, ':u' => $uid]);
        db_exec($db, 'UPDATE posts SET likes=MAX(0,likes-1) WHERE id=:id', [':id' => $id]);
    } else {
        db_exec($db, 'INSERT INTO liked_posts (post_id,user_id) VALUES (:p,:u)', [':p' => $id, ':u' => $uid]);
        db_exec($db, 'UPDATE posts SET likes=likes+1 WHERE id=:id', [':id' => $id]);
    }

    json_ok(fetch_post($db, $id, $uid));
}

if ($method === 'PUT' && $resource === 'posts' && $id) {
    $uid  = require_auth();
    $post = db_query_single($db, 'SELECT user_id, image FROM posts WHERE id=:id', [':id' => $id], true);
    if (!$post)                         json_error('Post not found', 404);
    if ((int)$post['user_id'] !== $uid) json_error('Forbidden', 403);

    $ct = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if (str_starts_with($ct, 'application/json')) {
        // Legacy text-only edit
        $input      = json_decode(file_get_contents('php://input'), true);
        $body       = trim($input['body'] ?? '');
        $image_json = $post['image'];
        if ($body === '') json_error('Post body is required');
    } else {
        // Multipart edit: body + optional image changes
        $body = trim($_POST['body'] ?? '');

        $existing = [];
        if (!empty($post['image'])) {
            $decoded  = json_decode($post['image'], true);
            $existing = is_array($decoded) ? $decoded : [$post['image']];
        }

        // Filenames the client wants to keep — validated against what the post actually owns
        $keep = isset($_POST['keep_images']) ? (array)$_POST['keep_images'] : $existing;
        $kept = array_values(array_filter($keep, fn($fn) => in_array($fn, $existing, true)));

        // Delete images not being kept
        foreach ($existing as $fn) {
            if (!in_array($fn, $kept, true)) {
                $p = POSTS_UPLOADS_DIR . $fn;
                if (file_exists($p)) unlink($p);
            }
        }

        // Upload new images
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

    db_exec($db, 'UPDATE posts SET body=:b, image=:img, edited_at=:e WHERE id=:id', [
        ':b'   => $body,
        ':img' => $image_json,
        ':e'   => time(),
        ':id'  => $id,
    ]);
    json_ok(fetch_post($db, $id, $uid));
}

if ($method === 'DELETE' && $resource === 'posts' && $id && $sub1 !== 'users') {
    $uid  = require_auth();
    $post = db_query_single($db, 'SELECT user_id FROM posts WHERE id=:id', [':id' => $id], true);
    if (!$post)                         json_error('Post not found', 404);
    if ((int)$post['user_id'] !== $uid) json_error('Forbidden', 403);

    delete_post_cascade($db, $id);
    json_ok(['deleted' => true]);
}

// ══════════════════════════════════════════════════════════
// NOTIFICATIONS
// ══════════════════════════════════════════════════════════

if ($method === 'GET' && $resource === 'notifications') {
    $uid = require_auth();

    $res = db_query($db, "
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
    while ($row = $res->fetchArray(SQLITE3_ASSOC)) {
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

    $unread = (int)db_query_single($db, 'SELECT COUNT(*) FROM notifications WHERE user_id=:uid AND read=0', [':uid' => $uid]);
    json_ok(['notifications' => $notifs, 'unread' => $unread]);
}

if ($method === 'POST' && $resource === 'notifications' && $sub1 === 'read') {
    $uid = require_auth();
    db_exec($db, 'UPDATE notifications SET read=1 WHERE user_id=:uid', [':uid' => $uid]);
    json_ok(['ok' => true]);
}

// ══════════════════════════════════════════════════════════
// ADMIN
// ══════════════════════════════════════════════════════════

if ($method === 'GET' && $resource === 'admin' && $sub1 === 'users') {
    require_admin($db);
    $res = db_query($db, '
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

    if (!db_query_single($db, 'SELECT id FROM users WHERE id=:id', [':id' => $tid], true))
        json_error('User not found', 404);

    $input  = json_decode(file_get_contents('php://input'), true);
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
        db_exec($db, 'UPDATE users SET ' . implode(',', $fields) . ' WHERE id=:tid', $params);
    }

    $row = db_query_single($db, "
        SELECT u.*, COUNT(p.id) AS post_count
        FROM users u LEFT JOIN posts p ON p.user_id=u.id
        WHERE u.id=:id GROUP BY u.id
    ", [':id' => $tid], true);
    $u = format_user($row);
    $u['post_count'] = (int)$row['post_count'];
    json_ok(['user' => $u]);
}

if ($method === 'DELETE' && $resource === 'admin' && $sub1 === 'users' && $target_id) {
    $admin_uid = require_admin($db);
    $tid       = $target_id;

    if ($tid === $admin_uid) json_error('Cannot delete your own account from the admin panel');
    if (!db_query_single($db, 'SELECT id FROM users WHERE id=:id', [':id' => $tid], true))
        json_error('User not found', 404);

    delete_user_data($db, $tid);
    json_ok(['deleted' => true]);
}

if ($method === 'GET' && $resource === 'admin' && $sub1 === 'settings') {
    require_admin($db);
    json_ok(['settings' => [
        'remember_me_days' => (int)(get_setting($db, 'remember_me_days') ?: 30),
    ]]);
}

if ($method === 'PATCH' && $resource === 'admin' && $sub1 === 'settings') {
    require_admin($db);
    $input = json_decode(file_get_contents('php://input'), true);
    if (array_key_exists('remember_me_days', $input)) {
        $days = (int)$input['remember_me_days'];
        if ($days < 1 || $days > 365) json_error('Remember me days must be between 1 and 365');
        db_exec($db, 'INSERT INTO settings (key, value) VALUES (:k, :v) ON CONFLICT(key) DO UPDATE SET value=excluded.value', [
            ':k' => 'remember_me_days', ':v' => (string)$days,
        ]);
    }
    json_ok(['settings' => [
        'remember_me_days' => (int)(get_setting($db, 'remember_me_days') ?: 30),
    ]]);
}

// ══════════════════════════════════════════════════════════
// FOLLOWS
// ══════════════════════════════════════════════════════════

// POST /users/:username/follow — toggle follow
if ($method === 'POST' && $resource === 'users' && $sub1 && $sub1 !== 'me' && !is_numeric($sub1) && $sub2 === 'follow') {
    $uid = require_auth();
    $target = db_query_single($db, 'SELECT id FROM users WHERE username=:u', [':u' => $sub1], true);
    if (!$target) json_error('User not found', 404);
    $tid = (int)$target['id'];
    if ($tid === $uid) json_error('Cannot follow yourself');

    $already = (bool)db_query_single($db, 'SELECT 1 FROM follows WHERE follower_id=:f AND followee_id=:e', [
        ':f' => $uid,
        ':e' => $tid
    ]);

    if ($already) {
        db_exec($db, 'DELETE FROM follows WHERE follower_id=:f AND followee_id=:e', [
            ':f' => $uid,
            ':e' => $tid
        ]);
        $following = false;
    } else {
        db_exec($db, 'INSERT INTO follows (follower_id, followee_id) VALUES (:f,:e)', [
            ':f' => $uid,
            ':e' => $tid
        ]);
        $following = true;

        // Create follow notification
        db_exec($db, 'INSERT INTO notifications (user_id,actor_id,type,created_at) VALUES (:u,:a,:t,:ts)', [
            ':u'  => $tid,
            ':a'  => $uid,
            ':t'  => 'follow',
            ':ts' => time()
        ]);
    }

    $follower_count = (int)db_query_single($db, 'SELECT COUNT(*) FROM follows WHERE followee_id=:tid', [':tid' => $tid]);
    json_ok(['following' => $following, 'follower_count' => $follower_count]);
}

json_error('Not found', 404);
