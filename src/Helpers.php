<?php
declare(strict_types=1);

function get_base_url(): string {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https" : "http";
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return "$protocol://$host";
}

function check_rate_limit(PDO $db, string $key, int $limit, int $window_seconds): void {
    $now = time();
    db_exec($db, 'DELETE FROM rate_limits WHERE expires < ?', [$now]);
    
    $row = db_query_single($db, 'SELECT hits, expires FROM rate_limits WHERE `key`=?', [$key], true);
    if ($row) {
        if ($row['hits'] >= $limit) {
            json_error('Too many requests. Please try again later.', 429);
        }
        db_exec($db, 'UPDATE rate_limits SET hits = hits + 1 WHERE `key`=?', [$key]);
    } else {
        db_exec($db, 'INSERT INTO rate_limits (`key`, hits, expires) VALUES (?, 1, ?)', [
            $key,
            $now + $window_seconds
        ]);
    }
}

function get_csrf_token(): string {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
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

function get_setting(PDO $db, string $key, string $default = ''): string {
    return (string)(db_query_single($db, 'SELECT `value` FROM settings WHERE `key`=?', [$key]) ?? $default);
}

function json_error(string $msg, int $code = 400): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $msg]);
    exit;
}

function json_ok(mixed $data): never {
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function get_json_payload(): array {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}

function current_user_id(): ?int {
    return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;
}

function require_auth(): int {
    $uid = current_user_id();
    if (!$uid) json_error('Not authenticated', 401);
    return $uid;
}

function require_admin(PDO $db): int {
    $uid  = require_auth();
    $user = db_query_single($db, 'SELECT is_admin FROM users WHERE id=?', [$uid], true);
    if (!$user || !$user['is_admin']) json_error('Forbidden', 403);
    return $uid;
}

function db_exec(PDO $db, string $sql, array $params = []): void {
    $stmt = $db->prepare($sql);
    foreach ($params as $i => $val) {
        if (is_string($i) && strpos($sql, $i) === false) continue;
        $type = is_int($val) ? PDO::PARAM_INT : (is_bool($val) ? PDO::PARAM_BOOL : (is_null($val) ? PDO::PARAM_NULL : PDO::PARAM_STR));
        $stmt->bindValue(is_int($i) ? $i + 1 : $i, $val, $type);
    }
    $stmt->execute();
}

function db_query(PDO $db, string $sql, array $params = []): PDOStatement {
    $stmt = $db->prepare($sql);
    foreach ($params as $i => $val) {
        if (is_string($i) && strpos($sql, $i) === false) continue;
        $type = is_int($val) ? PDO::PARAM_INT : (is_bool($val) ? PDO::PARAM_BOOL : (is_null($val) ? PDO::PARAM_NULL : PDO::PARAM_STR));
        $stmt->bindValue(is_int($i) ? $i + 1 : $i, $val, $type);
    }
    $stmt->execute();
    return $stmt;
}

function db_query_single(PDO $db, string $sql, array $params = [], bool $entire_row = false): mixed {
    $stmt = $db->prepare($sql);
    foreach ($params as $i => $val) {
        if (is_string($i) && strpos($sql, $i) === false) continue;
        $type = is_int($val) ? PDO::PARAM_INT : (is_bool($val) ? PDO::PARAM_BOOL : (is_null($val) ? PDO::PARAM_NULL : PDO::PARAM_STR));
        $stmt->bindValue(is_int($i) ? $i + 1 : $i, $val, $type);
    }
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row === false) return null;
    if ($entire_row) return $row;
    return reset($row);
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

function post_select_cols(string $alias, ?int $uid): string {
    $liked_col  = $uid ? ", CASE WHEN lp.user_id IS NOT NULL THEN 1 ELSE 0 END AS liked_flag"
                       : ', 0 AS liked_flag';
    $follow_col = $uid ? ", CASE WHEN f.follower_id IS NOT NULL THEN 1 ELSE 0 END AS following"
                       : ', 0 AS following';
    $reposted_col = $uid ? ", CASE WHEN (SELECT 1 FROM posts r WHERE r.quote_id=$alias.id AND r.user_id=:uid AND r.body='' AND r.image IS NULL LIMIT 1) IS NOT NULL THEN 1 ELSE 0 END AS reposted_flag"
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
    $liked_join  = $uid ? "LEFT JOIN liked_posts lp ON lp.post_id=$a.id AND lp.user_id=:uid" : '';
    $follow_join = $uid ? "LEFT JOIN follows f ON f.follower_id=:uid AND f.followee_id=$a.user_id" : '';
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

function fetch_post(PDO $db, int $post_id, ?int $uid): ?array {
    $cols  = post_select_cols('p', $uid);
    $joins = post_join_sql('p', $uid);
    $params = [':id' => $post_id];
    if ($uid) $params[':uid'] = $uid;
    $row   = db_query_single($db, "SELECT $cols FROM posts p $joins WHERE p.id=:id", $params, true);
    return $row ? format_post_row($row, $uid) : null;
}

function fetch_descendants(PDO $db, int $post_id, int $depth, ?int $uid, int &$count, int $max = 200): array {
    if ($count >= $max) return [];
    $cols   = post_select_cols('p', $uid);
    $joins  = post_join_sql('p', $uid);
    $params = [':id' => $post_id];
    if ($uid) $params[':uid'] = $uid;
    $res    = db_query($db, "SELECT $cols FROM posts p $joins WHERE p.parent_id=:id ORDER BY p.created_at ASC", $params);
    $result = [];
    while ($row = $res->fetch()) {
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

function delete_post_cascade(PDO $db, int $post_id): void {
    $res = db_query($db, 'SELECT id FROM posts WHERE parent_id=:id', [':id' => $post_id]);
    while ($child = $res->fetch()) {
        delete_post_cascade($db, (int)$child['id']);
    }
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

function delete_user_data(PDO $db, int $uid): void {
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
    while ($row = $res->fetch()) {
        delete_post_cascade($db, (int)$row['id']);
    }
    db_exec($db, 'UPDATE posts SET parent_id=NULL WHERE user_id=:id AND parent_id IS NOT NULL', [':id' => $uid]);
    db_exec($db, 'DELETE FROM liked_posts WHERE post_id IN (SELECT id FROM posts WHERE user_id=:id)', [':id' => $uid]);
    db_exec($db, 'DELETE FROM notifications WHERE post_id IN (SELECT id FROM posts WHERE user_id=:id)', [':id' => $uid]);
    $img_res = db_query($db, 'SELECT image FROM posts WHERE user_id=:id AND image IS NOT NULL', [':id' => $uid]);
    while ($img_row = $img_res->fetch()) {
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
