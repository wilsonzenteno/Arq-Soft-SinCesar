<?php
namespace App\Core;

class View {
  public static function render(string $view, array $data = []): void {
    extract($data, EXTR_SKIP);
    $viewFile = __DIR__ . '/../Views/' . $view . '.php';
    $layout   = __DIR__ . '/../Views/layout.php';
    if (!file_exists($viewFile)) { http_response_code(500); echo "Vista no encontrada: $view"; return; }
    ob_start(); include $viewFile; $content = ob_get_clean();
    include $layout;
  }
}
