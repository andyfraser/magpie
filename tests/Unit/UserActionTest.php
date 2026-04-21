<?php

namespace Tests\Unit;

use Tests\TestCase;
use PDO;

class UserActionTest extends TestCase {
    private ?PDO $db = null;

    public function setUp(): void {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Initialize schema
        $this->db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT, email TEXT, avatar TEXT)');
        $this->db->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, username TEXT, body TEXT, image TEXT, likes INTEGER DEFAULT 0, parent_id INTEGER, quote_id INTEGER, edited_at INTEGER, created_at INTEGER)');
        $this->db->exec('CREATE TABLE liked_posts (post_id INTEGER, user_id INTEGER, PRIMARY KEY (post_id, user_id))');
        $this->db->exec('CREATE TABLE follows (follower_id INTEGER, followee_id INTEGER, PRIMARY KEY (follower_id, followee_id))');
        $this->db->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, actor_id INTEGER, type TEXT, post_id INTEGER, read INTEGER DEFAULT 0, created_at INTEGER)');
        $this->db->exec('CREATE TABLE remember_tokens (token TEXT PRIMARY KEY, user_id INTEGER, expires INTEGER)');
    }

    public function testDeleteUserData(): void {
        db_exec($this->db, 'INSERT INTO users (id, username, email) VALUES (1, "alice", "alice@example.com")');
        db_exec($this->db, 'INSERT INTO posts (id, user_id, username, body, created_at) VALUES (10, 1, "alice", "Post by alice", ?)', [time()]);
        db_exec($this->db, 'INSERT INTO notifications (user_id, actor_id, type, created_at) VALUES (1, 2, "follow", ?)', [time()]);
        
        $this->assertEquals(1, (int)db_query_single($this->db, 'SELECT COUNT(*) FROM users WHERE id=1'));
        $this->assertEquals(1, (int)db_query_single($this->db, 'SELECT COUNT(*) FROM posts WHERE user_id=1'));
        
        delete_user_data($this->db, 1);
        
        $this->assertEquals(0, (int)db_query_single($this->db, 'SELECT COUNT(*) FROM users WHERE id=1'));
        $this->assertEquals(0, (int)db_query_single($this->db, 'SELECT COUNT(*) FROM posts WHERE user_id=1'));
        $this->assertEquals(0, (int)db_query_single($this->db, 'SELECT COUNT(*) FROM notifications WHERE user_id=1'));
    }
}
