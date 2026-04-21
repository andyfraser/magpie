<?php

namespace Tests\Unit;

use Tests\TestCase;
use Magpie\Router;

class RouterTest extends TestCase {
    public function testRouteDispatch(): void {
        $router = new Router();
        $called = false;
        
        $router->get('/test', function() use (&$called) {
            $called = true;
        });
        
        $router->dispatch('GET', '/test');
        $this->assertTrue($called);
    }

    public function testRouteParameters(): void {
        $router = new Router();
        $capturedId = null;
        
        $router->get('/posts/{id}', function($id) use (&$capturedId) {
            $capturedId = $id;
        });
        
        $router->dispatch('GET', '/posts/123');
        $this->assertEquals('123', $capturedId);
    }

    public function testRouteRegexSafety(): void {
        $router = new Router();
        $called = false;
        
        $router->get('/posts/{id}/edit', function($id) use (&$called) {
            $called = true;
        });
        
        $router->dispatch('GET', '/posts/123/edit');
        $this->assertTrue($called);
    }
}
