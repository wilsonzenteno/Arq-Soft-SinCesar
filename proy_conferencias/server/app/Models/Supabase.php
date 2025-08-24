<?php
namespace App\Models;

/** (MODELO) — Cliente de datos/servicios (DB, Storage, Profiles) en Supabase */
class Supabase {
  private string $url; private string $anon; private string $service;

  public function __construct() {
    $cfg = require __DIR__ . '/../../config.php';
    $this->url     = rtrim($cfg['SUPABASE_URL'] ?? '', '/');
    $this->anon    = $cfg['SUPABASE_ANON_KEY'] ?? '';
    $this->service = $cfg['SUPABASE_SERVICE_ROLE'] ?? '';
  }

  // === helpers ===
  public function userIdFromJwt(?string $hdr): ?string {
    if (!$hdr) return null;
    if (preg_match('/Bearer\\s+(.*)$/i', $hdr, $m)) { $jwt = $m[1]; }
    else { $jwt = $hdr; }
    $parts = explode('.', $jwt); if (count($parts) < 2) return null;
    $payload = str_replace(['-','_'], ['+','/'], $parts[1]);
    $payload = base64_decode($payload . str_repeat('=', 3 - (3 + strlen($payload)) % 4));
    $obj = json_decode($payload ?: "{}", true) ?? [];
    return $obj['sub'] ?? null;
  }

  private function http(string $method, string $url, array $headers = [], $body = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    $respBody = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['status'=>$status, 'body'=>$respBody, 'error'=>$err];
  }

  // === REST (PostgREST) ===
  public function restSelect(string $table, string $query, bool $useAnon=true): array {
    $endpoint = $this->url . "/rest/v1/" . rawurlencode($table) . ($query ? "?$query" : "");
    return $this->http('GET', $endpoint, [
      "apikey: " . ($useAnon ? $this->anon : $this->service),
      "Authorization: Bearer " . ($useAnon ? $this->anon : $this->service),
      "Accept-Profile: public",
      "Content-Profile: public"
    ]);
  }
  public function restInsert(string $table, array $payload): array {
    $endpoint = $this->url . "/rest/v1/" . rawurlencode($table);
    return $this->http('POST', $endpoint, [
      "apikey: {$this->service}",
      "Authorization: Bearer {$this->service}",
      "Content-Type: application/json",
      "Prefer: return=representation"
    ], $payload);
  }
  public function restUpsert(string $table, array $payload, string $onConflict): array {
    $endpoint = $this->url . "/rest/v1/" . rawurlencode($table) . "?on_conflict=" . rawurlencode($onConflict);
    return $this->http('POST', $endpoint, [
      "apikey: {$this->service}",
      "Authorization: Bearer {$this->service}",
      "Content-Type: application/json",
      "Prefer: return=representation,resolution=merge-duplicates"
    ], $payload);
  }
  public function restUpdate(string $table, array $payload, string $filter): array {
    $endpoint = $this->url . "/rest/v1/" . rawurlencode($table) . ($filter ? "?$filter" : "");
    return $this->http('PATCH', $endpoint, [
      "apikey: {$this->service}",
      "Authorization: Bearer {$this->service}",
      "Content-Type: application/json",
      "Prefer: return=representation"
    ], $payload);
  }

  // === Storage ===
  public function storageUpload(string $bucket, string $path, string $content, string $contentType='application/octet-stream'): bool {
    $endpoint = $this->url . "/storage/v1/object/" . rawurlencode($bucket) . "/" . $path;
    $res = $this->http('POST', $endpoint, [
      "Authorization: Bearer {$this->service}",
      "apikey: {$this->service}",
      "Content-Type: $contentType"
    ], $content);
    return ($res['status'] ?? 500) < 300;
  }

  public function storageStream(string $bucket, string $path): bool {
    $endpoint = $this->url . "/storage/v1/object/" . rawurlencode($bucket) . "/" . $path;
    $res = $this->http('GET', $endpoint, [
      "Authorization: Bearer {$this->service}",
      "apikey: {$this->service}"
    ]);
    if (($res['status'] ?? 500) >= 300) return false;
    header('Content-Type: application/pdf');
    header('Content-Disposition: inline; filename="'.basename($path).'"');
    echo $res['body'];
    return true;
  }

  // === Profiles ===
  public function getUserRole(string $userId): ?string {
    $res = $this->restSelect('profiles', "id=eq.$userId&select=role");
    $arr = json_decode($res['body'] ?? "[]", true);
    return $arr[0]['role'] ?? null;
  }
}
