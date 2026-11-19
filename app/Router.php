<?php
declare(strict_types=1);

final class Router
{
    /** @var array<int, array{0:string,1:string,2:array{0:class-string,1:string}}> */
    private array $routes = [];

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    private function add(string $method, string $pattern, array $handler): void
    {
        $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[1-9][0-9]{0,9})', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $method = strtoupper($method);
        if ($method === 'HEAD') {
            $method = 'GET';
        }

        $pathMatched = false;
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if (!preg_match($regex, $path, $m)) {
                continue;
            }
            $pathMatched = true;
            if ($routeMethod !== $method) {
                continue;
            }

            if ($method === 'POST') {
                verify_csrf();
            }

            $params = [];
            foreach ($m as $key => $value) {
                if (is_string($key)) {
                    $params[] = (int) $value;
                }
            }

            [$class, $action] = $handler;
            (new $class())->$action(...$params);
            return;
        }

        render_error($pathMatched ? 405 : 404);
    }
}
