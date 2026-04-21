<?php

namespace Tests\Unit;

use Tests\TestCase;

class PostTest extends TestCase {
    public function testFormatPostRow(): void {
        $row = [
            'id' => '10',
            'user_id' => '1',
            'username' => 'alice',
            'body' => 'Hello world',
            'image' => '["img1.jpg", "img2.jpg"]',
            'likes' => '5',
            'parent_id' => null,
            'quote_id' => null,
            'edited_at' => null,
            'created_at' => '1600000000',
            'display_name' => 'Alice',
            'user_avatar' => 'alice.jpg',
            'reply_count' => '2',
            'repost_count' => '1',
            'parent_username' => null,
            'parent_display_name' => null,
            'quote_post_id' => null,
            'liked_flag' => '1',
            'following' => '0',
            'reposted_flag' => '0'
        ];

        $formatted = format_post_row($row, 1);

        $this->assertEquals(10, $formatted['id']);
        $this->assertEquals(1, $formatted['user_id']);
        $this->assertEquals('Hello world', $formatted['body']);
        $this->assertCount(2, $formatted['image_urls']);
        $this->assertEquals('/uploads/posts/img1.jpg', $formatted['image_urls'][0]);
        $this->assertTrue($formatted['liked']);
        $this->assertFalse($formatted['reposted']);
        $this->assertTrue($formatted['own']);
    }

    public function testFormatPostRowWithQuote(): void {
        $row = [
            'id' => '11',
            'user_id' => '2',
            'username' => 'bob',
            'body' => 'Nice post!',
            'image' => null,
            'likes' => '0',
            'parent_id' => null,
            'quote_id' => '10',
            'edited_at' => null,
            'created_at' => '1600000010',
            'display_name' => 'Bob',
            'user_avatar' => null,
            'reply_count' => '0',
            'repost_count' => '0',
            'parent_username' => null,
            'parent_display_name' => null,
            'quote_post_id' => '10',
            'quote_body' => 'Hello world',
            'quote_username' => 'alice',
            'quote_created_at' => '1600000000',
            'quote_display_name' => 'Alice',
            'quote_avatar_file' => 'alice.jpg',
            'liked_flag' => '0',
            'following' => '1',
            'reposted_flag' => '0'
        ];

        $formatted = format_post_row($row, 1);

        $this->assertEquals(11, $formatted['id']);
        $this->assertNotNull($formatted['quote']);
        $this->assertEquals(10, $formatted['quote']['id']);
        $this->assertEquals('alice', $formatted['quote']['username']);
        $this->assertTrue($formatted['following']);
        $this->assertFalse($formatted['own']);
    }
}
