<?php
namespace App\Core;

class Http {
  public static function json($data, int $status = 200): void {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
  }

  public static function jsonInput(): array {
    $raw = file_get_contents('php://input');
    if (!$raw) return [];
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
  }

  /** Devuelve el JWT desde Authorization: Bearer ... o, si falta, desde la cookie 'jwt' */
  public static function bearer(): ?string {
    $auth = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;
    if ($auth) {
      if (preg_match('/Bearer\s+(.+)/i', $auth, $m)) return trim($m[1]);
      // por si alguien envía el token "pelado"
      if (preg_match('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+$/', $auth)) return $auth;
    }
    // Fallback: cookie HttpOnly set por /auth.session
    if (!empty($_COOKIE['jwt'])) return $_COOKIE['jwt'];
    return null;
  }
}
