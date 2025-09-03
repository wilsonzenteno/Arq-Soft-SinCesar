<?php
namespace App\Core;

class Router {
  private array $routes = ['GET'=>[], 'POST'=>[]];

  public function get(string $path, $handler): void  { $this->routes['GET'][$this->norm($path)] = $handler; }
  public function post(string $path, $handler): void { $this->routes['POST'][$this->norm($path)] = $handler; }

  private function norm(?string $p): string {
    $p = $p ?? '/';
    if ($p === '') $p = '/';
    if ($p[0] !== '/') $p = '/'.$p;
    // sin slash final (excepto la raíz)
    if ($p !== '/' && str_ends_with($p, '/')) $p = rtrim($p,'/');
    return $p;
  }

  private function resolve(string $method, string $path) {
    $n = $this->norm($path);
    // match exacto
    if (isset($this->routes[$method][$n])) return $this->routes[$method][$n];
    // intenta variantes comunes (con/sin slash inicial por si llega “route=foo.bar”)
    $alts = [];
    if ($n !== '/' && $n[0] === '/') $alts[] = substr($n,1);
    if (!str_starts_with($n, '/')) $alts[] = '/'.$n;
    foreach ($alts as $alt) {
      $altN = $this->norm($alt);
      if (isset($this->routes[$method][$altN])) return $this->routes[$method][$altN];
    }
    return null;
  }

  public function dispatch(): void {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    // 1) Prioriza ?route=...
    $path = $_GET['route'] ?? null;

    // 2) Si no viene, usa la URL real
    if ($path === null || $path === '') {
      $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
      // si viene /index.php?route=..., normaliza a “/”
      if (preg_match('#/index\.php$#i', $path)) {
        $path = $_GET['route'] ?? '/';
      }
    }

    $handler = $this->resolve($method, $path);
    if (!$handler) {
      http_response_code(404);
      echo "Not Found: [$method] ".$path;
      return;
    }

    if (is_array($handler) && is_string($handler[0])) {
      $class = $handler[0]; $methodName = $handler[1];
      $obj = new $class();
      $obj->$methodName();
      return;
    }
    if (is_callable($handler)) { $handler(); return; }

    http_response_code(500);
    echo "Handler inválido";
  }
}
