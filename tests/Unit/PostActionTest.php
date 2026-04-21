<?php

namespace Tests\Unit;

use Tests\TestCase;
use PDO;

class PostActionTest extends TestCase {
    private ?PDO $db = null;

    public function setUp(): void {
        $this->db = new PDO('sqlite::memory:');
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Initialize schema
        $this->db->exec('CREATE TABLE users (id INTEGER PRIMARY KEY AUTOINCREMENT, username TEXT)');
        $this->db->exec('CREATE TABLE posts (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, username TEXT, body TEXT, image TEXT, likes INTEGER DEFAULT 0, parent_id INTEGER, quote_id INTEGER, edited_at INTEGER, created_at INTEGER)');
        $this->db->exec('CREATE TABLE liked_posts (post_id INTEGER, user_id INTEGER, PRIMARY KEY (post_id, user_id))');
        $this->db->exec('CREATE TABLE notifications (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER, actor_id INTEGER, type TEXT, post_id INTEGER, read INTEGER DEFAULT 0, created_at INTEGER)');
        
        db_exec($this->db, 'INSERT INTO users (id, username) VALUES (1, "alice")');
    }

    public function testDeletePostCascade(): void {
        // Create a post with an image
        $imageFilename = 'test_image.jpg';
        $imagePath = POSTS_UPLOADS_DIR . $imageFilename;
        file_put_contents($imagePath, 'dummy data');
        
        db_exec($this->db, 'INSERT INTO posts (id, user_id, username, body, image, created_at) VALUES (100, 1, "alice", "Hello", ?, ?)', [
            json_encode([$imageFilename]),
            time()
        ]);
        
        // Add a like
        db_exec($this->db, 'INSERT INTO liked_posts (post_id, user_id) VALUES (100, 1)');
        
        // Verify they exist
        $this->assertEquals(1, (int)db_query_single($this->db, 'SELECT COUNT(*) FROM posts WHERE id=100'));
        $this->assertTrue(file_exists($imagePath));
        
        // Delete
        delete_post_cascade($this->db, 100);
        
        // Verify they are gone
        $this->assertEquals(0, (int)db_query_single($this->db, 'SELECT COUNT(*) FROM posts WHERE id=100'));
        $this->assertEquals(0, (int)db_query_single($this->db, 'SELECT COUNT(*) FROM liked_posts WHERE post_id=100'));
        $this->assertFalse(file_exists($imagePath));
    }
}
