<?php
declare(strict_types=1);

use Magpie\Router;
use Magpie\Database;
use Magpie\Controllers\AuthController;
use Magpie\Controllers\PostController;
use Magpie\Controllers\UserController;
use Magpie\Controllers\NotificationController;
use Magpie\Controllers\AdminController;

if (!defined('MAGPIE_TESTING')) {
    session_start();
    header('Content-Type: application/json');
}

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit(0);

// ── Configuration ───────────────────────────────────────────

$CONFIG = file_exists(__DIR__ . '/config.php') ? require __DIR__ . '/config.php' : [
    'db' => [
        'driver'      => 'sqlite',
        'sqlite_path' => __DIR__ . '/magpie.db',
    ]
];

if (!defined('UPLOADS_DIR'))       define('UPLOADS_DIR',       __DIR__ . '/uploads/avatars/');
if (!defined('UPLOADS_URL'))       define('UPLOADS_URL',       '/uploads/avatars/');
if (!defined('POSTS_UPLOADS_DIR')) define('POSTS_UPLOADS_DIR', __DIR__ . '/uploads/posts/');
if (!defined('POSTS_UPLOADS_URL')) define('POSTS_UPLOADS_URL', '/uploads/posts/');
if (!defined('MAX_POST_LENGTH'))   define('MAX_POST_LENGTH',   500);
if (!defined('SCHEMA_VERSION'))    define('SCHEMA_VERSION',    9);

require_once __DIR__ . '/src/autoload.php';
require_once __DIR__ . '/src/Helpers.php';

// ── Database ──────────────────────────────────────────────

$db = Database::getConnection($CONFIG);

// ── Middleware (Auto-login & CSRF) ────────────────────────

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

validate_csrf();

// ── Routing ───────────────────────────────────────────────

$router = new Router();

// Auth
$router->get('/auth/me', [AuthController::class, 'me']);
$router->post('/auth/signup', [AuthController::class, 'signup']);
$router->post('/auth/login', [AuthController::class, 'login']);
$router->post('/auth/logout', [AuthController::class, 'logout']);
$router->post('/auth/verify-email', [AuthController::class, 'verifyEmail']);
$router->post('/auth/forgot-password', [AuthController::class, 'forgotPassword']);
$router->post('/auth/reset-password', [AuthController::class, 'resetPassword']);
$router->post('/auth/resend-verification', [AuthController::class, 'resendVerification']);

// Users
$router->get('/users', [UserController::class, 'index']);
$router->post('/users/me/avatar', [UserController::class, 'avatar']);
$router->put('/users/me', [UserController::class, 'updateMe']);
$router->delete('/users/me', [UserController::class, 'destroyMe']);
$router->post('/users/{username}/follow', [UserController::class, 'follow']);

// Posts
$router->get('/posts', [PostController::class, 'index']);
$router->post('/posts', [PostController::class, 'store']);
$router->get('/posts/{id}/thread', [PostController::class, 'thread']);
$router->put('/posts/{id}', [PostController::class, 'update']);
$router->delete('/posts/{id}', [PostController::class, 'destroy']);
$router->post('/posts/{id}/like', [PostController::class, 'like']);
$router->post('/posts/{id}/repost', [PostController::class, 'repost']);

// Notifications
$router->get('/notifications', [NotificationController::class, 'index']);
$router->post('/notifications/read', [NotificationController::class, 'markAsRead']);
$router->get('/stream', [NotificationController::class, 'stream']);

// Admin
$router->get('/admin/users', [AdminController::class, 'users']);
$router->patch('/admin/users/{id}', [AdminController::class, 'updateUser']);
$router->delete('/admin/users/{id}', [AdminController::class, 'deleteUser']);
$router->get('/admin/settings', [AdminController::class, 'settings']);
$router->patch('/admin/settings', [AdminController::class, 'updateSettings']);

if (!defined('MAGPIE_TESTING')) {
    $path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
    $path = preg_replace('#^api\.php/?#', '', $path);
    $router->dispatch($_SERVER['REQUEST_METHOD'], $path);
}
