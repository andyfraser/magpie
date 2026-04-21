<?php

namespace Tests\Unit;

use Tests\TestCase;

class HelperTest extends TestCase {
    public function testGetBaseUrlHttp(): void {
        $_SERVER['HTTPS'] = 'off';
        $_SERVER['HTTP_HOST'] = 'magpie.local';
        $this->assertEquals('http://magpie.local', get_base_url());
    }

    public function testGetBaseUrlHttps(): void {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['HTTP_HOST'] = 'magpie.local';
        $this->assertEquals('https://magpie.local', get_base_url());
    }

    public function testGetBaseUrlDefaultHost(): void {
        unset($_SERVER['HTTP_HOST']);
        $_SERVER['HTTPS'] = 'off';
        $this->assertEquals('http://localhost', get_base_url());
    }
}
