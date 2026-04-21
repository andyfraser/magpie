<?php

namespace Tests\Unit;

use Tests\TestCase;

class UserTest extends TestCase {
    public function testFormatUser(): void {
        $u = [
            'id' => 1,
            'username' => 'alice',
            'email' => 'alice@example.com',
            'email_verified' => 1,
            'display_name' => 'Alice in Wonderland',
            'bio' => 'Curiouser and curiouser!',
            'avatar' => 'alice.jpg',
            'is_admin' => 0,
            'disabled' => 0,
            'created_at' => 1600000000
        ];

        $formatted = format_user($u);

        $this->assertEquals(1, $formatted['id']);
        $this->assertEquals('alice', $formatted['username']);
        $this->assertEquals('Alice in Wonderland', $formatted['display_name']);
        $this->assertTrue($formatted['email_verified']);
        $this->assertEquals('/uploads/avatars/alice.jpg', $formatted['avatar']);
        $this->assertFalse($formatted['is_admin']);
    }

    public function testFormatUserWithNulls(): void {
        $u = [
            'id' => 2,
            'username' => 'bob',
            'email' => 'bob@example.com',
            'email_verified' => 0,
            'display_name' => '',
            'bio' => null,
            'avatar' => null,
            'is_admin' => 1,
            'disabled' => 0,
            'created_at' => 1600000001
        ];

        $formatted = format_user($u);

        $this->assertEquals(2, $formatted['id']);
        $this->assertEquals('bob', $formatted['username']);
        $this->assertEquals(null, $formatted['display_name']);
        $this->assertFalse($formatted['email_verified']);
        $this->assertEquals(null, $formatted['avatar']);
        $this->assertTrue($formatted['is_admin']);
    }
}
