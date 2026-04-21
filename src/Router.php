<?php
declare(strict_types=1);

namespace Magpie;

class Router {
    private array $routes = [];

    public function add(string $method, string $path, callable|array $handler): void {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => $this->preparePath($path),
            'handler' => $handler
        ];
    }

    public function get(string $path, callable|array $handler): void {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable|array $handler): void {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable|array $handler): void {
        $this->add('PUT', $path, $handler);
    }

    public function patch(string $path, callable|array $handler): void {
        $this->add('PATCH', $path, $handler);
    }

    public function delete(string $path, callable|array $handler): void {
        $this->add('DELETE', $path, $handler);
    }

    public function dispatch(string $method, string $path): void {
        $method = strtoupper($method);
        $path = '/' . trim($path, '/');

        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (preg_match($route['path'], $path, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                $handler = $route['handler'];

                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    $controller = new $class();
                    $controller->$action(...$params);
                } else {
                    $handler(...$params);
                }
                return;
            }
        }

        json_error('Not found', 404);
    }

    private function preparePath(string $path): string {
        $path = '/' . trim($path, '/');
        $regex = preg_replace('/\{([a-zA-Z0-9_]+)\}/', '(?P<$1>[^/]+)', $path);
        return '#^' . $regex . '$#';
    }
}
