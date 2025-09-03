<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;
use App\Models\Resource;

class SpeakerController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

  /* ================= Helpers auth/identidad ================= */

  private function bearer(): ?string { return \App\Core\Http::bearer(); }

  private function jwtPayload(?string $hdr): array {
    if (!$hdr) return [];
    $jwt = $hdr;
    if (preg_match('/Bearer\\s+(.+)/i', $hdr, $m)) $jwt = $m[1];
    $parts = explode('.', $jwt);
    if (count($parts) < 2) return [];
    $payload = strtr($parts[1], '-_', '+/');
    $payload = base64_decode($payload . str_repeat('=', (4 - strlen($payload) % 4) % 4));
    return json_decode($payload ?: "{}", true) ?? [];
  }

  private function uid(): ?string {
    return $this->sb->userIdFromJwt($this->bearer());
  }
  private function userEmail(): ?string {
    $p = $this->jwtPayload($this->bearer());
    return $p['email'] ?? null;
  }

  /* ================= Helpers REST ================= */

  private function trySelect(string $table, array $selectQueries, bool $useAnon=false): array {
    foreach ($selectQueries as $q) {
      $res = $this->sb->restSelect($table, $q, $useAnon);
      if (($res['status'] ?? 500) < 300) return $res;
    }
    return ['status'=>500,'body'=>'[]'];
  }
  private function decodeArr(array $res): array {
    return json_decode($res['body'] ?? "[]", true) ?? [];
  }

  /* ================= Ownership checks ================= */

  private function isTalkOwnedBy(int $talkId, string $uid, ?string $email): bool {
    $r = $this->sb->restSelect('talks', "id=eq.$talkId&select=speaker_id,speaker_email", false);
    $arr = $this->decodeArr($r);
    $t = $arr[0] ?? null; if (!$t) return false;
    if (!empty($t['speaker_id']) && $t['speaker_id'] === $uid) return true;
    if ($email && !empty($t['speaker_email']) && strcasecmp($t['speaker_email'], $email) === 0) return true;
    return false;
  }

  private function isOwnedBy(string $table, int $id, string $uid): bool {
    $r = $this->sb->restSelect($table, "id=eq.$id&select=owner_id", false);
    $arr = $this->decodeArr($r);
    $row = $arr[0] ?? null;
    return $row && isset($row['owner_id']) && $row['owner_id'] === $uid;
  }

  /* ================= LISTADOS (solo “mis *”) ================= */

  public function myTalks(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }

    $res = $this->trySelect('talks', [
      "speaker_id=eq.$uid&select=id,conference_id,title,starts_at,ends_at,room_id,speaker_id,speaker_email&order=starts_at.desc",
    ], false);
    $arr = $this->decodeArr($res);

    if (!$arr) {
      $email = $this->userEmail();
      if ($email) {
        $res2 = $this->sb->restSelect(
          'talks',
          "speaker_email=eq." . rawurlencode($email) . "&select=id,conference_id,title,starts_at,ends_at,room_id,speaker_id,speaker_email&order=starts_at.desc",
          false
        );
        $arr = $this->decodeArr($res2);
      }
    }

    foreach ($arr as &$t) { $t['start_time']=$t['starts_at']??null; $t['end_time']=$t['ends_at']??null; }
    Http::json($arr);
  }

  public function myConferences(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }

    $res = $this->trySelect('conferences', [
      "owner_id=eq.$uid&select=id,title,description,location,date,owner_id,modality,venue,stream_url&order=date.desc",
      "owner_id=eq.$uid&select=id,name,city,starts_at,ends_at,owner_id,modality,venue,stream_url&order=starts_at.desc"
    ], false);
    Http::json($this->decodeArr($res));
  }

  public function myWebinars(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $res = $this->sb->restSelect(
      'webinars',
      "owner_id=eq.$uid&select=id,title,description,modality,venue,stream_url,starts_at,ends_at,owner_id&order=starts_at.desc",
      false
    );
    Http::json($this->decodeArr($res));
  }

  public function myCourses(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $res = $this->sb->restSelect(
      'courses',
      "owner_id=eq.$uid&select=id,title,description,modality,venue,stream_url,starts_at,ends_at,owner_id&order=starts_at.desc",
      false
    );
    Http::json($this->decodeArr($res));
  }

  /* ================= CONFERENCIAS (propias) ================= */

  public function createConferenceBySpeaker(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();

    // Normaliza / valida
    $name = trim((string)($p['name'] ?? $p['title'] ?? ''));
    $city = trim((string)($p['city'] ?? $p['location'] ?? ''));
    $st   = $p['starts_at'] ?? ($p['date'] ?? null);
    $en   = $p['ends_at']   ?? null;

    if ($name === '' || $city === '') { Http::json(['error'=>'Datos inválidos (nombre y ciudad obligatorios)'], 400); return; }
    if (!$st) { Http::json(['error'=>'Falta fecha de inicio (starts_at)'], 400); return; }
    if (!$en) {
      $ts = strtotime($st);
      if ($ts === false) { Http::json(['error'=>'Fecha inicio inválida'], 400); return; }
      $en = gmdate('c', $ts + 3600); // +1h por defecto
    }

    // Inserta usando columnas reales del esquema
    $payload = [
      'name'       => $name,
      'city'       => $city,
      'starts_at'  => $st,
      'ends_at'    => $en,
      'owner_id'   => $uid,
      'modality'   => $p['modality'] ?? null,
      'venue'      => $p['venue'] ?? null,
      'stream_url' => $p['stream_url'] ?? null,
      // por compatibilidad, también guardamos si existen columnas "title/description/location/date" (no fallan si la tabla no las tiene)
      'title'       => $p['title'] ?? null,
      'description' => $p['description'] ?? null,
      'location'    => $p['location'] ?? null,
      'date'        => $p['date'] ?? null,
    ];

    $res = $this->sb->restInsert('conferences', $payload, false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function updateConferenceBySpeaker(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (string)($p['id'] ?? '');
    if ($id==='') { Http::json(['error'=>'id inválido'], 400); return; }

    $check = $this->sb->restSelect('conferences', "id=eq.$id&select=owner_id", false);
    $row   = ($this->decodeArr($check)[0] ?? null);
    if ($row && isset($row['owner_id']) && $row['owner_id'] !== $uid) {
      Http::json(['error'=>'No autorizado'], 403); return;
    }

    $st = $p['starts_at'] ?? ($p['date'] ?? null);
    $en = $p['ends_at']   ?? null;
    if ($st && !$en) { $ts=strtotime($st); if ($ts!==false) $en = gmdate('c',$ts+3600); }

    $pl = array_filter([
      'name'        => $p['name'] ?? ($p['title'] ?? null),
      'city'        => $p['city'] ?? ($p['location'] ?? null),
      'starts_at'   => $st,
      'ends_at'     => $en,
      'modality'    => $p['modality']  ?? null,
      'venue'       => $p['venue']     ?? null,
      'stream_url'  => $p['stream_url'] ?? null,
      'title'       => $p['title'] ?? null,
      'description' => $p['description'] ?? null,
      'location'    => $p['location'] ?? null,
      'date'        => $p['date'] ?? null
    ], fn($v)=>$v!==null);

    $res = $this->sb->restUpdate('conferences', $pl, "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function deleteConferenceBySpeaker(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (string)($p['id'] ?? '');
    if ($id==='') { Http::json(['error'=>'id inválido'], 400); return; }
    if (!$this->isOwnedBy('conferences', intval($id,10), $uid)) {
      Http::json(['error'=>'No autorizado'], 403); return;
    }
    $res = $this->sb->restDelete('conferences', "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /* ================= CHARLAS (propias) ================= */

  public function createOwnTalk(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();

    $confId = $p['conference_id'] ?? '';
    $title  = trim((string)($p['title'] ?? ''));
    $st     = $p['starts_at'] ?? ($p['start_time'] ?? null);
    $en     = $p['ends_at']   ?? ($p['end_time']   ?? null);

    if ($confId==='' || $title==='') { Http::json(['error'=>'Datos inválidos'], 400); return; }
    if (!$st) { Http::json(['error'=>'Falta fecha de inicio (starts_at)'], 400); return; }
    if (!$en) {
      $ts = strtotime($st);
      if ($ts === false) { Http::json(['error'=>'Formato de fecha inválido'], 400); return; }
      $en = gmdate('c', $ts + 3600);
    }

    $payload = [
      'conference_id' => is_numeric($confId) ? intval($confId,10) : $confId,
      'title'         => $title,
      'starts_at'     => $st,
      'ends_at'       => $en,
      'speaker_id'    => $uid,
      'speaker_email' => $this->userEmail() ?? null
    ];

    $res = $this->sb->restInsert('talks', $payload, false);
    if (($res['status'] ?? 500) < 300) {
      $rows = json_decode($res['body'] ?? '[]', true) ?? [];
      $id   = $rows[0]['id'] ?? null;
      Http::json(['ok'=>true, 'id'=>$id]); return;
    }
    $err = json_decode($res['body'] ?? "{}", true);
    Http::json(['error'=> ($err['message'] ?? 'Fail'), 'debug'=>$err], 400);
  }

  public function updateOwnTalk(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (int)($p['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id inválido'], 400); return; }

    if (!$this->isTalkOwnedBy($id, $uid, $this->userEmail())) {
      Http::json(['error'=>'No autorizado'], 403); return;
    }

    $st = $p['starts_at'] ?? ($p['start_time'] ?? null);
    $en = $p['ends_at']   ?? ($p['end_time']   ?? null);
    if ($st && !$en) { $ts=strtotime($st); if ($ts!==false) $en = gmdate('c',$ts+3600); }

    $pl = array_filter([
      'conference_id' => $p['conference_id'] ?? null,
      'title'         => $p['title'] ?? null,
      'starts_at'     => $st,
      'ends_at'       => $en
    ], fn($v)=>$v!==null);

    $res = $this->sb->restUpdate('talks', $pl, "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function deleteOwnTalk(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (int)($p['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id inválido'], 400); return; }

    if (!$this->isTalkOwnedBy($id, $uid, $this->userEmail())) {
      Http::json(['error'=>'No autorizado'], 403); return;
    }
    $res = $this->sb->restDelete('talks', "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /* ================= SLIDES (a BD: tabla resources) ================= */

  private function guessMime(string $filePath, ?string $origName): string {
    if (function_exists('finfo_open')) {
      $f = finfo_open(FILEINFO_MIME_TYPE);
      if ($f) { $m = finfo_file($f, $filePath); finfo_close($f); if ($m) return $m; }
    }
    $ext = strtolower(pathinfo($origName ?? '', PATHINFO_EXTENSION));
    if ($ext==='pdf') return 'application/pdf';
    if ($ext==='ppt' || $ext==='pptx') return 'application/vnd.ms-powerpoint';
    return 'application/octet-stream';
  }

  public function uploadSlides(): void {
    $uid = $this->sb->userIdFromJwt($_SERVER['HTTP_AUTHORIZATION'] ?? null);
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }

    $talkId = isset($_POST['talk_id']) ? (int)$_POST['talk_id'] : 0;
    if ($talkId <= 0 || empty($_FILES['file'])) {
      Http::json(['error'=>'Datos inválidos (talk_id o file)'], 400); return;
    }

    // Charla existe
    $chk = $this->sb->restSelect('talks', "id=eq.$talkId&select=id", false);
    $exists = (json_decode($chk['body'] ?? '[]', true) ?? []);
    if (!$exists) { Http::json(['error'=>'La charla no existe'], 400); return; }

    $tmp  = $_FILES['file']['tmp_name'] ?? null;
    $name = $_FILES['file']['name'] ?? null;
    $size = (int)($_FILES['file']['size'] ?? 0);

    if (!$tmp || !is_uploaded_file($tmp)) { Http::json(['error'=>'Subida inválida'], 400); return; }
    if ($size <= 0) { Http::json(['error'=>'Archivo vacío'], 400); return; }

    $content = file_get_contents($tmp);
    if ($content === false || $content === '') { Http::json(['error'=>'No se pudo leer el archivo'], 400); return; }

    $mime = $this->guessMime($tmp, $name);
    $b64  = base64_encode($content);
    $fileName = basename($name ?: ('slides_talk_'.$talkId.'.pdf'));

    // Guardar/actualizar en tabla resources (BD)
    $resp = Resource::upsert($this->sb, $talkId, $fileName, $mime, $b64, $uid);

    if (($resp['status'] ?? 500) < 300) {
      $rows = json_decode($resp['body'] ?? '[]', true) ?? [];
      Http::json(['ok'=>true, 'talk_id'=>$talkId, 'filename'=>$fileName, 'mime'=>$mime, 'rows'=>$rows]); return;
    }
    Http::json(['error'=>'No se pudo guardar en resources','status'=>$resp['status']??null,'body'=>$resp['body']??null], 400);
  }

  /* ================= WEBINARS (propios) ================= */

  public function myWebinarsCreate(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $payload = [
      'owner_id'    => $uid,
      'title'       => (string)($p['title'] ?? ''),
      'description' => $p['description'] ?? null,
      'modality'    => $p['modality'] ?? 'virtual',
      'venue'       => $p['venue'] ?? null,
      'stream_url'  => $p['stream_url'] ?? null,
      'starts_at'   => $p['starts_at'] ?? null,
      'ends_at'     => $p['ends_at'] ?? null,
      'owner_email' => $this->userEmail()
    ];
    $res = $this->sb->restInsert('webinars', $payload, false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function myWebinarsUpdate(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (int)($p['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id inválido'], 400); return; }
    if (!$this->isOwnedBy('webinars', $id, $uid)) { Http::json(['error'=>'No autorizado'], 403); return; }

    $pl = array_filter([
      'title'       => $p['title'] ?? null,
      'description' => $p['description'] ?? null,
      'modality'    => $p['modality'] ?? null,
      'venue'       => $p['venue'] ?? null,
      'stream_url'  => $p['stream_url'] ?? null,
      'starts_at'   => $p['starts_at'] ?? null,
      'ends_at'     => $p['ends_at'] ?? null,
    ], fn($v)=>$v!==null);

    $res = $this->sb->restUpdate('webinars', $pl, "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function myWebinarsDelete(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (int)($p['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id inválido'], 400); return; }
    if (!$this->isOwnedBy('webinars', $id, $uid)) { Http::json(['error'=>'No autorizado'], 403); return; }

    $res = $this->sb->restDelete('webinars', "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /* ================= COURSES (propios) ================= */

  public function myCoursesCreate(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $payload = [
      'owner_id'    => $uid,
      'title'       => (string)($p['title'] ?? ''),
      'description' => $p['description'] ?? null,
      'modality'    => $p['modality'] ?? 'presencial',
      'venue'       => $p['venue'] ?? null,
      'stream_url'  => $p['stream_url'] ?? null,
      'starts_at'   => $p['starts_at'] ?? null,
      'ends_at'     => $p['ends_at'] ?? null,
      'owner_email' => $this->userEmail()
    ];
    $res = $this->sb->restInsert('courses', $payload, false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function myCoursesUpdate(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (int)($p['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id inválido'], 400); return; }
    if (!$this->isOwnedBy('courses', $id, $uid)) { Http::json(['error'=>'No autorizado'], 403); return; }

    $pl = array_filter([
      'title'       => $p['title'] ?? null,
      'description' => $p['description'] ?? null,
      'modality'    => $p['modality'] ?? null,
      'venue'       => $p['venue'] ?? null,
      'stream_url'  => $p['stream_url'] ?? null,
      'starts_at'   => $p['starts_at'] ?? null,
      'ends_at'     => $p['ends_at'] ?? null,
    ], fn($v)=>$v!==null);

    $res = $this->sb->restUpdate('courses', $pl, "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function myCoursesDelete(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (int)($p['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id inválido'], 400); return; }
    if (!$this->isOwnedBy('courses', $id, $uid)) { Http::json(['error'=>'No autorizado'], 403); return; }

    $res = $this->sb->restDelete('courses', "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }
}
