<?php
declare(strict_types=1);

namespace App\Core;

final class Router
{
    /** @var array<int, array{string, string, callable|array}> */
    private array $routes = [];

    public function get(string $path, callable|array $handler): void  { $this->add('GET', $path, $handler); }
    public function post(string $path, callable|array $handler): void { $this->add('POST', $path, $handler); }

    private function add(string $method, string $path, callable|array $handler): void
    {
        $this->routes[] = [$method, $path, $handler];
    }

    public function dispatch(string $method, string $uri): void
    {
        // Remove subpasta, se existir
        $base = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '')), '/');
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base)) ?: '/';
        }
        $uri = '/' . trim($uri, '/');

        foreach ($this->routes as [$m, $path, $handler]) {
            if ($m !== $method) {
                continue;
            }
            $regex = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $path) . '$#';
            if (preg_match($regex, $uri, $matches)) {
                $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
                if (is_array($handler)) {
                    [$class, $action] = $handler;
                    (new $class())->$action(...$params);
                } else {
                    $handler(...$params);
                }
                return;
            }
        }

        http_response_code(404);
        view('public/404', ['title' => 'Página não encontrada']);
    }
}
