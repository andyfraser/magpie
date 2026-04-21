<?php
declare(strict_types=1);

namespace Magpie;

use PDO;

class Database {
    public static function getConnection(array $config): PDO {
        $c = $config['db'];
        
        if ($c['driver'] === 'mysql') {
            $initCommand = defined('Pdo\Mysql::ATTR_INIT_COMMAND') 
                ? \Pdo\Mysql::ATTR_INIT_COMMAND 
                : (defined('PDO::MYSQL_ATTR_INIT_COMMAND') ? PDO::MYSQL_ATTR_INIT_COMMAND : 1002);

            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
                $initCommand => "SET NAMES {$c['charset']}"
            ];

            $dsnNoDb = "mysql:host={$c['host']};charset={$c['charset']}";
            $tmpDb = new PDO($dsnNoDb, $c['user'], $c['pass'], $options);
            $tmpDb->exec("CREATE DATABASE IF NOT EXISTS `{$c['dbname']}` CHARACTER SET {$c['charset']} COLLATE {$c['charset']}_unicode_ci");
            $tmpDb = null;

            $dsn = "mysql:host={$c['host']};dbname={$c['dbname']};charset={$c['charset']}";
            $db = new PDO($dsn, $c['user'], $c['pass'], $options);
        } else {
            $db = new PDO("sqlite:" . $c['sqlite_path'], null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => true,
            ]);
            $db->exec('PRAGMA journal_mode=WAL; PRAGMA foreign_keys=ON;');
        }

        self::initializeSchema($db, $c['driver']);

        return $db;
    }

    private static function initializeSchema(PDO $db, string $driver): void {
        db_exec($db, 'CREATE TABLE IF NOT EXISTS schema_version (version INTEGER NOT NULL)');
        $current = db_query_single($db, 'SELECT version FROM schema_version LIMIT 1');
        
        if ((int)$current !== SCHEMA_VERSION) {
            if ($driver === 'sqlite') $db->exec('PRAGMA foreign_keys=OFF;');
            
            $tables = ['rate_limits', 'notifications', 'liked_posts', 'posts', 'follows', 'users', 'remember_tokens', 'settings'];
            foreach ($tables as $table) {
                $db->exec("DROP TABLE IF EXISTS $table");
            }
            $db->exec('DELETE FROM schema_version');
            
            if ($driver === 'sqlite') $db->exec('PRAGMA foreign_keys=ON;');
            db_exec($db, 'INSERT INTO schema_version (version) VALUES (?)', [SCHEMA_VERSION]);
        }

        if ($driver === 'mysql') {
            $db->exec('
                CREATE TABLE IF NOT EXISTS users (
                    id           INT AUTO_INCREMENT PRIMARY KEY,
                    username     VARCHAR(255) NOT NULL UNIQUE,
                    email        VARCHAR(255) NOT NULL UNIQUE,
                    email_verified TINYINT(1) NOT NULL DEFAULT 0,
                    verification_token VARCHAR(255),
                    reset_token  VARCHAR(255),
                    reset_token_expires INT,
                    display_name VARCHAR(255),
                    bio          TEXT,
                    avatar       VARCHAR(255),
                    password     VARCHAR(255) NOT NULL,
                    is_admin     TINYINT(1) NOT NULL DEFAULT 0,
                    disabled     TINYINT(1) NOT NULL DEFAULT 0,
                    created_at   INT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE TABLE IF NOT EXISTS posts (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    user_id    INT NOT NULL,
                    username   VARCHAR(255) NOT NULL,
                    body       TEXT NOT NULL,
                    image      TEXT,
                    likes      INT NOT NULL DEFAULT 0,
                    parent_id  INT,
                    quote_id   INT,
                    edited_at  INT,
                    created_at INT NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    FOREIGN KEY (parent_id) REFERENCES posts(id),
                    FOREIGN KEY (quote_id) REFERENCES posts(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE TABLE IF NOT EXISTS liked_posts (
                    post_id INT NOT NULL,
                    user_id INT NOT NULL,
                    PRIMARY KEY (post_id, user_id),
                    FOREIGN KEY (post_id) REFERENCES posts(id),
                    FOREIGN KEY (user_id) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE TABLE IF NOT EXISTS follows (
                    follower_id INT NOT NULL,
                    followee_id INT NOT NULL,
                    PRIMARY KEY (follower_id, followee_id),
                    FOREIGN KEY (follower_id) REFERENCES users(id),
                    FOREIGN KEY (followee_id) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE TABLE IF NOT EXISTS notifications (
                    id         INT AUTO_INCREMENT PRIMARY KEY,
                    user_id    INT NOT NULL,
                    actor_id   INT NOT NULL,
                    type       VARCHAR(50) NOT NULL,
                    post_id    INT,
                    `read`     TINYINT(1) NOT NULL DEFAULT 0,
                    created_at INT NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id),
                    FOREIGN KEY (actor_id) REFERENCES users(id),
                    FOREIGN KEY (post_id) REFERENCES posts(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE TABLE IF NOT EXISTS remember_tokens (
                    token   VARCHAR(255) NOT NULL PRIMARY KEY,
                    user_id INT NOT NULL,
                    expires INT NOT NULL,
                    FOREIGN KEY (user_id) REFERENCES users(id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE TABLE IF NOT EXISTS settings (
                    `key`   VARCHAR(255) NOT NULL PRIMARY KEY,
                    `value` TEXT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
                CREATE TABLE IF NOT EXISTS rate_limits (
                    `key`     VARCHAR(255) NOT NULL PRIMARY KEY,
                    hits    INT NOT NULL,
                    expires INT NOT NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
            ');
        } else {
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
                    key     TEXT    NOT NULL PRIMARY KEY,
                    value   TEXT    NOT NULL
                );
                CREATE TABLE IF NOT EXISTS rate_limits (
                    key     TEXT    NOT NULL PRIMARY KEY,
                    hits    INTEGER NOT NULL,
                    expires INTEGER NOT NULL
                );
            ');
        }
    }
}
