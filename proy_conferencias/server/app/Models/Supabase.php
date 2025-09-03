<?php
namespace App\Models;

class Supabase {
  private string $url; private string $anon; private string $service;

  public function __construct() {
    $cfg = require __DIR__ . '/../../config.php';
    $this->url     = rtrim($cfg['SUPABASE_URL'] ?? '', '/');
    $this->anon    = $cfg['SUPABASE_ANON_KEY'] ?? '';
    $this->service = $cfg['SUPABASE_SERVICE_ROLE'] ?? '';
  }

  public function userIdFromJwt(?string $hdr): ?string {
    if (!$hdr) return null;
    if (preg_match('/^[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+\.[A-Za-z0-9-_]+$/', $hdr)) { $jwt = $hdr; }
    elseif (preg_match('/Bearer\\s+(.*)$/i', $hdr, $m)) { $jwt = $m[1]; }
    else { $jwt = $hdr; }
    $parts = explode('.', $jwt); if (count($parts) < 2) return null;
    $payload = strtr($parts[1], '-_', '+/');
    $payload = base64_decode($payload . str_repeat('=', (4 - strlen($payload) % 4) % 4));
    $obj = json_decode($payload ?: "{}", true) ?? [];
    return $obj['sub'] ?? null;
  }

  private function http(string $method, string $url, array $headers = [], $body = null): array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    if (!empty($headers)) curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    if ($body !== null) curl_setopt($ch, CURLOPT_POSTFIELDS, is_string($body) ? $body : json_encode($body));
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $respBody = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    return ['status'=>$status, 'body'=>$respBody, 'error'=>$err];
  }

  /* ========= REST =========== */
  public function restSelect(string $table, string $query, bool $useAnon=true): array {
    $endpoint = $this->url . "/rest/v1/" . rawurlencode($table) . ($query ? "?$query" : "");
    $key = $useAnon ? $this->anon : $this->service;
    return $this->http('GET', $endpoint, [
      "apikey: $key","Authorization: Bearer $key",
      "Accept-Profile: public","Content-Profile: public"
    ]);
  }
  public function restInsert(string $table, array $payload, bool $useAnon=false): array {
    $endpoint = $this->url . "/rest/v1/" . rawurlencode($table);
    $key = $useAnon ? $this->anon : $this->service;
    return $this->http('POST', $endpoint, [
      "apikey: $key","Authorization: Bearer $key",
      "Content-Type: application/json","Prefer: return=representation"
    ], $payload);
  }
  public function restUpsert(string $table, array $payload, string $onConflict, bool $useAnon=false): array {
    $endpoint = $this->url . "/rest/v1/" . rawurlencode($table) . "?on_conflict=" . rawurlencode($onConflict);
    $key = $useAnon ? $this->anon : $this->service;
    return $this->http('POST', $endpoint, [
      "apikey: $key",
      "Authorization: Bearer $key",
      "Content-Type: application/json",
      "Prefer: return=representation,resolution=merge-duplicates",
      "Accept-Profile: public",
      "Content-Profile: public"
    ], $payload);
  }


  public function restUpdate(string $table, array $payload, string $filter, bool $useAnon=false): array {
    $endpoint = $this->url . "/rest/v1/" . rawurlencode($table) . ($filter ? "?$filter" : "");
    $key = $useAnon ? $this->anon : $this->service;
    return $this->http('PATCH', $endpoint, [
      "apikey: $key","Authorization: Bearer $key",
      "Content-Type: application/json","Prefer: return=representation"
    ], $payload);
  }
  public function restDelete(string $table, string $filter, bool $useAnon=false): array {
    $endpoint = $this->url . "/rest/v1/" . rawurlencode($table) . ($filter ? "?$filter" : "");
    $key = $useAnon ? $this->anon : $this->service;
    return $this->http('DELETE', $endpoint, [
      "apikey: $key","Authorization: Bearer $key",
      "Accept-Profile: public","Content-Profile: public"
    ]);
  }

  /* ========= STORAGE =========== */
  public function storageUpload(string $bucket, string $path, string $content, string $contentType='application/octet-stream', bool $debug=false) {
    // Codifica cada segmento para soportar espacios y caracteres especiales
    $normPath = implode('/', array_map('rawurlencode', explode('/', ltrim($path,'/'))));
    $endpoint = $this->url . "/storage/v1/object/" . rawurlencode($bucket) . "/" . $normPath;
    $len = strlen($content);

    $res = $this->http('POST', $endpoint, [
      "Authorization: Bearer {$this->service}",
      "apikey: {$this->service}",
      "Content-Type: $contentType",
      "Content-Length: $len",
      "x-upsert: true"
    ], $content);

    if ($debug) {
      return [
        'ok'      => (($res['status'] ?? 500) < 300),
        'status'  => $res['status'] ?? 0,
        'body'    => $res['body'] ?? '',
        'endpoint'=> $endpoint
      ];
    }
    return (($res['status'] ?? 500) < 300);
  }

  public function storageStream(string $bucket, string $path): bool {
    $normPath = implode('/', array_map('rawurlencode', explode('/', ltrim($path,'/'))));
    $endpoint = $this->url . "/storage/v1/object/" . rawurlencode($bucket) . "/" . $normPath;

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

  public function storageExists(string $bucket, string $path): array {
    $normPath = implode('/', array_map('rawurlencode', explode('/', ltrim($path,'/'))));
    $endpoint = $this->url . "/storage/v1/object/" . rawurlencode($bucket) . "/" . $normPath;

    // Intento HEAD
    $resHead = $this->http('HEAD', $endpoint, [
      "Authorization: Bearer {$this->service}",
      "apikey: {$this->service}"
    ]);
    if (($resHead['status'] ?? 500) < 300) return ['ok'=>true, 'status'=>$resHead['status']];

    // Fallback: GET con Range mínimo
    $resGet = $this->http('GET', $endpoint, [
      "Authorization: Bearer {$this->service}",
      "apikey: {$this->service}",
      "Range: bytes=0-0"
    ]);
    $ok = (($resGet['status'] ?? 500) < 300);
    return ['ok'=>$ok, 'status'=>$resGet['status'] ?? 0];
  }

    public function storageHead(string $bucket, string $path): bool {
    $normPath = implode('/', array_map('rawurlencode', explode('/', ltrim($path,'/'))));
    $endpoint = $this->url . "/storage/v1/object/" . rawurlencode($bucket) . "/" . $normPath;
    $res = $this->http('HEAD', $endpoint, [
      "Authorization: Bearer {$this->service}",
      "apikey: {$this->service}"
    ]);
    return (($res['status'] ?? 500) < 300);
  }

  /* ========= PERFIL / AUTH ADMIN =========== */
  public function getUserRole(string $userId): ?string {
    $res = $this->restSelect('profiles', "id=eq.$userId&select=role", false);
    $arr = json_decode($res['body'] ?? "[]", true);
    return $arr[0]['role'] ?? null;
  }

  public function authAdminSearchUsers(string $email): array {
    if ($email === '') return [];
    $url = $this->url . "/auth/v1/admin/users?email=" . rawurlencode($email);
    $res = $this->http('GET', $url, [
      "Authorization: Bearer {$this->service}",
      "apikey: {$this->service}",
      "Content-Type: application/json"
    ]);
    $arr = json_decode($res['body'] ?? "[]", true) ?? [];
    $users = $arr['users'] ?? (is_array($arr) ? $arr : []);
    $out = [];
    foreach ($users as $u) {
      $out[] = [
        'id'    => $u['id'] ?? null,
        'email' => $u['email'] ?? ($u['user_metadata']['email'] ?? null),
        'name'  => $u['user_metadata']['full_name'] ?? ($u['user_metadata']['name'] ?? null)
      ];
    }
    return $out;
  }
}
