<?php
namespace App\Core;

/** (CORE) — Enrutador mínimo */
class Router {
  private array $routes = ['GET'=>[], 'POST'=>[]];

  public function get(string $path, $handler): void  { $this->routes['GET'][$path]  = $handler; }
  public function post(string $path, $handler): void { $this->routes['POST'][$path] = $handler; }

  private function currentRoute(): string { return $_GET['route'] ?? '/'; }

  public function dispatch(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $route = $this->currentRoute();
    $handler = $this->routes[$method][$route] ?? null;
    if (!$handler) { http_response_code(404); echo "Not found"; return; }
    if (is_callable($handler)) { $handler(); return; }
    if (is_array($handler)) { [$class,$fn] = $handler; (new $class())->$fn(); return; }
    http_response_code(500); echo "Bad handler";
  }
}
