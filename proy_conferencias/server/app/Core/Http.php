<?php
namespace App\Core;

/** (CORE) — Utilidades HTTP */
class Http {
  public static function jsonInput(): array {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw ?: '[]', true);
    return is_array($data) ? $data : [];
  }
  public static function bearer(): ?string {
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
    if (preg_match('/Bearer\\s+(.*)$/i', $hdr, $m)) return $m[1];
    return null;
  }
  public static function json($payload, int $status=200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  }
}
