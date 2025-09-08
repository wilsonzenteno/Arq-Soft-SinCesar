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

  /* ================== Helpers de rol/seguridad extra ================== */

  private function roleOf(?string $uid): ?string {
    return $uid ? $this->sb->getUserRole($uid) : null;
  }

  private function requireSpeakerOrStaff(): ?string {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return null; }
    $role = $this->roleOf($uid);
    if (!in_array($role, ['speaker','admin','staff'], true)) {
      Http::json(['error'=>'No autorizado'], 403); return null;
    }
    return $uid;
  }

  private function decodeOrFail(array $res): array {
    $status = intval($res['status'] ?? 500);
    $body   = $res['body'] ?? '';
    $json   = json_decode($body ?: '[]', true);
    if ($status >= 300) {
      $msg = is_array($json) && isset($json['message']) ? $json['message'] : 'Supabase error';
      Http::json(['error'=>$msg, 'debug'=>$json], 500);
      exit;
    }
    return (is_array($json) ? $json : []);
  }

  private function norm($v) { if ($v === null) return null; if (is_string($v)) return trim($v); return $v; }

  private function diffChanges(array $old, array $new, array $fields, string $kind): array {
    $labels = [
      'title'=>'Título','description'=>'Descripción','starts_at'=>'Inicio','ends_at'=>'Fin',
      'room_id'=>'Sala','modality'=>'Modalidad','venue'=>'Lugar','stream_url'=>'Stream',
      'cert_enabled'=>'Certificado','cert_form_url'=>'Formulario'
    ];
    $formatDate = function($v){ if (!$v) return '—'; try { return (new \DateTime($v))->format('d/m/Y H:i'); } catch (\Throwable $e) { return (string)$v; } };
    $changes = [];
    foreach ($fields as $f) {
      $ov = $this->norm($old[$f] ?? null);
      $nv = $this->norm($new[$f] ?? null);
      if ($ov == $nv) continue;
      if ($f === 'starts_at' || $f === 'ends_at') {
        $changes[] = "{$labels[$f]}: {$formatDate($ov)} → {$formatDate($nv)}";
      } else {
        $o = ($ov === null || $ov === '') ? '—' : (string)$ov;
        $n = ($nv === null || $nv === '') ? '—' : (string)$nv;
        $changes[] = "{$labels[$f]}: “{$o}” → “{$n}”";
      }
    }
    return $changes;
  }

  private function createChangeAnnouncement(string $kind, int $refId, string $title, array $changes, string $createdBy): bool {
    if (!$changes) return false;
    $body = "Se realizaron cambios en el {$kind}:\n- " . implode("\n- ", $changes);
    $payload = ['title'=>$title,'body'=>$body,'kind'=>$kind,'ref_id'=>$refId,'created_at'=>gmdate('c'),'created_by'=>$createdBy];
    $res = $this->sb->restInsert('announcements', $payload, false);
    return (($res['status'] ?? 500) < 300);
  }

  private function getAndCheckOwnership(string $kind, int $id, string $uid): ?array {
    if ($kind === 'talk') {
      $r = $this->sb->restSelect('talks', "id=eq.$id", false);
      $a = $this->decodeOrFail($r); $row = $a[0] ?? null;
      if (!$row) { Http::json(['error'=>'Charla no encontrada'], 404); return null; }
      $owner = $row['speaker_id'] ?? null; $role = $this->roleOf($uid);
      if ($role === 'admin' || $role === 'staff') return $row;
      if (!$owner || $owner !== $uid) { Http::json(['error'=>'No eres dueño de esta charla'], 403); return null; }
      return $row;
    }
    if ($kind === 'course') {
      $r = $this->sb->restSelect('courses', "id=eq.$id", false);
      $a = $this->decodeOrFail($r); $row = $a[0] ?? null;
      if (!$row) { Http::json(['error'=>'Curso no encontrado'], 404); return null; }
      $owner = $row['owner_id'] ?? null; $role = $this->roleOf($uid);
      if ($role === 'admin' || $role === 'staff') return $row;
      if (!$owner || $owner !== $uid) { Http::json(['error'=>'No eres dueño de este curso'], 403); return null; }
      return $row;
    }
    if ($kind === 'webinar') {
      $r = $this->sb->restSelect('webinars', "id=eq.$id", false);
      $a = $this->decodeOrFail($r); $row = $a[0] ?? null;
      if (!$row) { Http::json(['error'=>'Webinar no encontrado'], 404); return null; }
      $owner = $row['owner_id'] ?? null; $role = $this->roleOf($uid);
      if ($role === 'admin' || $role === 'staff') return $row;
      if (!$owner || $owner !== $uid) { Http::json(['error'=>'No eres dueño de este webinar'], 403); return null; }
      return $row;
    }
    Http::json(['error'=>'Tipo inválido'], 400);
    return null;
  }

  /* ================= LISTADOS (solo “mis *”) ================= */

  public function myTalks(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }

    $res = $this->trySelect('talks', [
      "speaker_id=eq.$uid&select=id,conference_id,title,starts_at,ends_at,room_id,modality,venue,stream_url,speaker_id,speaker_email&order=starts_at.desc",
    ], false);
    $arr = $this->decodeArr($res);

    if (!$arr) {
      $email = $this->userEmail();
      if ($email) {
        $res2 = $this->sb->restSelect(
          'talks',
          "speaker_email=eq." . rawurlencode($email) . "&select=id,conference_id,title,starts_at,ends_at,room_id,modality,venue,stream_url,speaker_id,speaker_email&order=starts_at.desc",
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
      "owner_id=eq.$uid&select=id,title,description,modality,venue,stream_url,room_id,starts_at,ends_at,owner_id&order=starts_at.desc",
      false
    );
    Http::json($this->decodeArr($res));
  }

  public function myCourses(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    // IMPORTANTE: devolvemos los campos de certificado
    $res = $this->sb->restSelect(
      'courses',
      "owner_id=eq.$uid&select=id,title,description,modality,venue,stream_url,room_id,starts_at,ends_at,owner_id,cert_enabled,cert_form_url&order=starts_at.desc",
      false
    );
    Http::json($this->decodeArr($res));
  }

  /* ================= CONFERENCIAS (propias) ================= */

  public function createConferenceBySpeaker(): void {
    $sb  = new Supabase(); $jwt = \App\Core\Http::bearer(); $uid = $sb->userIdFromJwt($jwt);
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $role = $sb->getUserRole($uid);
    if (!in_array($role, ['admin','staff','speaker'], true)) {
      Http::json(['error'=>'No autorizado: se requiere rol speaker/staff/admin'], 403); return;
    }

    $in = Http::jsonInput();
    $name = trim((string)($in['name'] ?? $in['title'] ?? ''));
    $city = trim((string)($in['city'] ?? $in['location'] ?? ''));
    $starts = $in['starts_at'] ?? $in['start'] ?? $in['date'] ?? null;
    $ends   = $in['ends_at']   ?? $in['end']   ?? null;

    $iso = function($v) { if(!$v) return null; try {
      if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $v)) { $v .= ':00'; }
      $d = new \DateTime($v); return $d->format('c'); } catch (\Throwable $e) { return null; } };

    $startsIso = $iso($starts); $endsIso = $iso($ends);
    if (!$endsIso && $startsIso) { try { $d = new \DateTime($startsIso); $d->modify('+1 hour'); $endsIso = $d->format('c'); } catch (\Throwable $e) {} }
    if ($name === '' || $city === '' || !$startsIso) { Http::json(['error'=>'Parámetros inválidos. Requiere name/city/starts_at'], 400); return; }

    $ok = \App\Models\Conference::create($sb, $name, $city, $startsIso, $endsIso ?? $startsIso);
    if (!$ok) { Http::json(['error'=>'No se pudo crear la conferencia'], 500); return; }
    Http::json(['ok'=>true, 'name'=>$name, 'city'=>$city, 'starts_at'=>$startsIso, 'ends_at'=>$endsIso ?? $startsIso], 200);
  }

  public function updateConferenceBySpeaker(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput(); $id = (string)($p['id'] ?? '');
    if ($id==='') { Http::json(['error'=>'id inválido'], 400); return; }

    $check = $this->sb->restSelect('conferences', "id=eq.$id&select=owner_id", false);
    $row   = ($this->decodeArr($check)[0] ?? null);
    if ($row && isset($row['owner_id']) && $row['owner_id'] !== $uid) { Http::json(['error'=>'No autorizado'], 403); return; }

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
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput(); $id = (int)($p['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id inválido'], 400); return; }
    if (!$this->isOwnedBy('conferences', $id, $uid)) { Http::json(['error'=>'No autorizado'], 403); return; }
    $res = $this->sb->restDelete('conferences', "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /* ================= CHARLAS (propias) ================= */

  public static function nint($v): ?int { if ($v === '' || $v === null || !isset($v)) return null; $n = (int)$v; return $n > 0 ? $n : null; }

  public function createOwnTalk(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();

    $confId = $p['conference_id'] ?? null;
    $roomId = self::nint($p['room_id'] ?? null);
    $mod    = isset($p['modality']) ? (string)$p['modality'] : 'presencial';
    $venue  = $p['venue'] ?? null;
    $surl   = $p['stream_url'] ?? null;
    $title  = trim((string)($p['title'] ?? ''));
    $st     = $p['starts_at'] ?? ($p['start_time'] ?? null);
    $en     = $p['ends_at']   ?? ($p['end_time']   ?? null);

    if ($title==='') { Http::json(['error'=>'Falta título'], 400); return; }
    if (!$st) { Http::json(['error'=>'Falta fecha de inicio (starts_at)'], 400); return; }
    if (!$en) {
      $ts = strtotime($st);
      if ($ts === false) { Http::json(['error'=>'Formato de fecha inválido'], 400); return; }
      $en = gmdate('c', $ts + 3600);
    }

    // Si modalidad es virtual, forzamos room_id a null
    if ($mod === 'virtual') { $roomId = null; }

    $payload = [
      'title'         => $title,
      'starts_at'     => $st,
      'ends_at'       => $en,
      'speaker_id'    => $uid,
      'speaker_email' => $this->userEmail() ?? null,
      'room_id'       => $roomId,
      'modality'      => $mod,
      'venue'         => $venue,
      'stream_url'    => $surl,
    ];
    if ($confId !== null && $confId !== '') {
      $payload['conference_id'] = is_numeric($confId) ? intval($confId,10) : $confId;
    }

    $res = $this->sb->restInsert('talks', $payload, false);
    if (($res['status'] ?? 500) < 300) {
      $rows = json_decode($res['body'] ?? '[]', true) ?? []; $id = $rows[0]['id'] ?? null;
      Http::json(['ok'=>true, 'id'=>$id]); return;
    }
    $err = json_decode($res['body'] ?? "{}", true);
    Http::json(['error'=> ($err['message'] ?? 'Fail'), 'debug'=>$err], 400);
  }

  public function updateMyTalk(): void {
    $uid = $this->requireSpeakerOrStaff(); if (!$uid) return;
    $in = Http::jsonInput();

    $id = intval($in['id'] ?? 0);
    if ($id <= 0) { Http::json(['error'=>'id inválido'], 400); return; }

    $old = $this->getAndCheckOwnership('talk', $id, $uid); if (!$old) return;

    $upd = [
      'title'        => array_key_exists('title',       $in) ? (string)$in['title']       : null,
      'description'  => array_key_exists('description', $in) ? (string)$in['description'] : null,
      'starts_at'    => array_key_exists('starts_at',   $in) ? ($in['starts_at'] ?: null) : null,
      'ends_at'      => array_key_exists('ends_at',     $in) ? ($in['ends_at']   ?: null) : null,
      'room_id'      => array_key_exists('room_id',     $in) ? ($in['room_id'] === '' ? null : (is_null($in['room_id']) ? null : intval($in['room_id']))) : null,
      'conference_id'=> array_key_exists('conference_id',$in) ? ($in['conference_id'] === '' ? null : (is_null($in['conference_id']) ? null : intval($in['conference_id']))) : null,
      'modality'     => array_key_exists('modality',    $in) ? ($in['modality'] ?: null)  : null,
      'venue'        => array_key_exists('venue',       $in) ? ($in['venue']    ?: null)  : null,
      'stream_url'   => array_key_exists('stream_url',  $in) ? ($in['stream_url']?: null) : null,
    ];

    // Si modalidad cambia a virtual, forzamos room_id a null en el update
    if (isset($upd['modality']) && $upd['modality'] === 'virtual') {
      $upd['room_id'] = null;
    }

    $payload = array_filter($upd, fn($v)=> $v !== null);
    if (!$payload) { Http::json(['ok'=>true, 'changed'=>[]]); return; }

    $res = $this->sb->restUpdate('talks', $payload, "id=eq.$id", false);
    if (($res['status'] ?? 500) >= 300) {
      Http::json(['error'=>'No se pudo actualizar la charla','detail'=>$res['body'] ?? ''], 400); return;
    }

    $r2 = $this->sb->restSelect('talks', "id=eq.$id", false);
    $a2 = $this->decodeOrFail($r2); $new = $a2[0] ?? [];

    $fields = ['title','description','starts_at','ends_at','room_id','modality','venue','stream_url'];
    $changes = $this->diffChanges($old, $new, $fields, 'talk');

    if ($changes) {
      $titleAnn = 'Actualización de charla: ' . ($new['title'] ?? $old['title'] ?? "(#{$id})");
      $this->createChangeAnnouncement('talk', $id, $titleAnn, $changes, $uid);
    }

    Http::json(['ok'=>true, 'changed'=>$changes]);
  }

  public function updateOwnTalk(): void { $this->updateMyTalk(); }

  public function deleteOwnTalk(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput(); $id = (int)($p['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id inválido'], 400); return; }

    if (!$this->isTalkOwnedBy($id, $uid, $this->userEmail())) { Http::json(['error'=>'No autorizado'], 403); return; }
    $res = $this->sb->restDelete('talks', "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /* ================= SLIDES ================= */

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

    $chk = $this->sb->restSelect('talks', "id=eq.$talkId&select=id", false);
    $exists = (json_decode($chk['body'] ?? '[]', true) ?? []);
    if (!$exists) { Http::json(['error'=>'La charla no existe'], 400); return; }

    $tmp  = $_FILES['file']['tmp_name'] ?? null;
    $name = $_FILES['file']['name'] ?? null;
    $size = (int)$_FILES['file']['size'] ?? 0;
    if (!$tmp || !is_uploaded_file($tmp)) { Http::json(['error'=>'Subida inválida'], 400); return; }
    if ($size <= 0) { Http::json(['error'=>'Archivo vacío'], 400); return; }

    $content = file_get_contents($tmp);
    if ($content === false || $content === '') { Http::json(['error'=>'No se pudo leer el archivo'], 400); return; }

    $mime = $this->guessMime($tmp, $name);
    $b64  = base64_encode($content);
    $fileName = basename($name ?: ('slides_talk_'.$talkId.'.pdf'));

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
      'room_id'     => self::nint($p['room_id'] ?? null),
      'starts_at'   => $p['starts_at'] ?? null,
      'ends_at'     => $p['ends_at'] ?? null,
      'owner_email' => $this->userEmail()
    ];
    if ($payload['title'] === '') { Http::json(['error'=>'title requerido'], 400); return; }
    $res = $this->sb->restInsert('webinars', $payload, false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function updateMyWebinar(): void {
    $uid = $this->requireSpeakerOrStaff(); if (!$uid) return;
    $in = Http::jsonInput();

    $id = intval($in['id'] ?? 0);
    if ($id <= 0) { Http::json(['error'=>'id inválido'], 400); return; }

    $old = $this->getAndCheckOwnership('webinar', $id, $uid); if (!$old) return;

    $upd = [
      'title'       => array_key_exists('title',       $in) ? (string)$in['title']       : null,
      'description' => array_key_exists('description', $in) ? (string)$in['description'] : null,
      'starts_at'   => array_key_exists('starts_at',   $in) ? ($in['starts_at'] ?: null) : null,
      'ends_at'     => array_key_exists('ends_at',     $in) ? ($in['ends_at']   ?: null) : null,
      'modality'    => array_key_exists('modality',    $in) ? ($in['modality']  ?: null) : null,
      'venue'       => array_key_exists('venue',       $in) ? ($in['venue']     ?: null) : null,
      'stream_url'  => array_key_exists('stream_url',  $in) ? ($in['stream_url']?: null) : null,
      'room_id'     => array_key_exists('room_id',     $in) ? ($in['room_id'] === '' ? null : (is_null($in['room_id']) ? null : intval($in['room_id']))) : null,
    ];
    $payload = array_filter($upd, fn($v)=> $v !== null);
    if (!$payload) { Http::json(['ok'=>true, 'changed'=>[]]); return; }

    $res = $this->sb->restUpdate('webinars', $payload, "id=eq.$id", false);
    if (($res['status'] ?? 500) >= 300) {
      Http::json(['error'=>'No se pudo actualizar el webinar','detail'=>$res['body'] ?? ''], 400); return;
    }

    $r2 = $this->sb->restSelect('webinars', "id=eq.$id", false);
    $a2 = $this->decodeOrFail($r2);
    $new = $a2[0] ?? [];

    $fields = ['title','description','starts_at','ends_at','modality','venue','stream_url','room_id'];
    $changes = $this->diffChanges($old, $new, $fields, 'webinar');

    if ($changes) {
      $titleAnn = 'Actualización de webinar: ' . ($new['title'] ?? $old['title'] ?? "(#{$id})");
      $this->createChangeAnnouncement('webinar', $id, $titleAnn, $changes, $uid);
    }

    Http::json(['ok'=>true, 'changed'=>$changes]);
  }

  public function myWebinarsUpdate(): void { $this->updateMyWebinar(); }

  public function myWebinarsDelete(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput(); $id = (int)($p['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id inválido'], 400); return; }
    if (!$this->isOwnedBy('webinars', $id, $uid)) { Http::json(['error'=>'No autorizado'], 403); return; }
    $res = $this->sb->restDelete('webinars', "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /* ================= COURSES (propios) ================= */

  public function myCoursesCreate(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();

    // Normalización de campos de certificado (nuevos)
    $certEnabled = isset($p['cert_enabled'])
      ? (bool)$p['cert_enabled']
      : ( (isset($p['has_certificate']) && $p['has_certificate']) ? true : false );

    $certUrl = $p['cert_form_url'] ?? ($p['certificate_url'] ?? null);

    if ($certEnabled && (!$certUrl || trim((string)$certUrl)==='')) {
      Http::json(['error'=>'cert_form_url requerido cuando cert_enabled=true'], 400); return;
    }

    $payload = [
      'owner_id'     => $uid,
      'title'        => (string)($p['title'] ?? ''),
      'description'  => $p['description'] ?? null,
      'modality'     => $p['modality'] ?? 'presencial',
      'venue'        => $p['venue'] ?? null,
      'stream_url'   => $p['stream_url'] ?? null,
      'room_id'      => self::nint($p['room_id'] ?? null),
      'starts_at'    => $p['starts_at'] ?? null,
      'ends_at'      => $p['ends_at'] ?? null,
      'owner_email'  => $this->userEmail(),
      // NUEVOS CAMPOS
      'cert_enabled' => $certEnabled,
      'cert_form_url'=> $certUrl
    ];

    if ($payload['title'] === '') { Http::json(['error'=>'title requerido'], 400); return; }

    $res = $this->sb->restInsert('courses', $payload, false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail','debug'=>json_decode($res['body']??'{}',true)], 400);
  }

  public function updateMyCourse(): void {
    $uid = $this->requireSpeakerOrStaff(); if (!$uid) return;
    $in = Http::jsonInput();

    $id = intval($in['id'] ?? 0);
    if ($id <= 0) { Http::json(['error'=>'id inválido'], 400); return; }

    $old = $this->getAndCheckOwnership('course', $id, $uid); if (!$old) return;

    // Nuevos campos: cert_enabled & cert_form_url también aceptados
    $upd = [
      'title'        => array_key_exists('title',       $in) ? (string)$in['title']       : null,
      'description'  => array_key_exists('description', $in) ? (string)$in['description'] : null,
      'starts_at'    => array_key_exists('starts_at',   $in) ? ($in['starts_at'] ?: null) : null,
      'ends_at'      => array_key_exists('ends_at',     $in) ? ($in['ends_at']   ?: null) : null,
      'modality'     => array_key_exists('modality',    $in) ? ($in['modality']  ?: null) : null,
      'venue'        => array_key_exists('venue',       $in) ? ($in['venue']     ?: null) : null,
      'stream_url'   => array_key_exists('stream_url',  $in) ? ($in['stream_url']?: null) : null,
      'room_id'      => array_key_exists('room_id',     $in) ? ($in['room_id'] === '' ? null : (is_null($in['room_id']) ? null : intval($in['room_id']))) : null,
      'cert_enabled' => array_key_exists('cert_enabled',$in) ? (bool)$in['cert_enabled']  : null,
      'cert_form_url'=> array_key_exists('cert_form_url',$in)? ($in['cert_form_url'] ?: null) : null,
    ];

    // Validación suave: si activan certificado pero no mandan URL, no forzamos error, solo guardamos null
    if (isset($upd['cert_enabled']) && $upd['cert_enabled'] === true && isset($upd['cert_form_url']) && (!($upd['cert_form_url']))) {
      // puedes descomentar si quieres que sea obligatorio en update
      // return Http::json(['error'=>'cert_form_url requerido cuando cert_enabled=true'], 400);
    }

    $payload = array_filter($upd, fn($v)=> $v !== null);
    if (!$payload) { Http::json(['ok'=>true, 'changed'=>[]]); return; }

    $res = $this->sb->restUpdate('courses', $payload, "id=eq.$id", false);
    if (($res['status'] ?? 500) >= 300) {
      Http::json(['error'=>'No se pudo actualizar el curso','detail'=>$res['body'] ?? ''], 400); return;
    }

    $r2 = $this->sb->restSelect('courses', "id=eq.$id", false);
    $a2 = $this->decodeOrFail($r2);
    $new = $a2[0] ?? [];

    $fields = ['title','description','starts_at','ends_at','modality','venue','stream_url','room_id','cert_enabled','cert_form_url'];
    $changes = $this->diffChanges($old, $new, $fields, 'course');

    if ($changes) {
      $titleAnn = 'Actualización de curso: ' . ($new['title'] ?? $old['title'] ?? "(#{$id})");
      $this->createChangeAnnouncement('course', $id, $titleAnn, $changes, $uid);
    }

    Http::json(['ok'=>true, 'changed'=>$changes]);
  }

  public function myCoursesUpdate(): void { $this->updateMyCourse(); }

  public function myCoursesDelete(): void {
    $uid = $this->uid(); if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput(); $id = (int)($p['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id inválido'], 400); return; }
    if (!$this->isOwnedBy('courses', $id, $uid)) { Http::json(['error'=>'No autorizado'], 403); return; }
    $res = $this->sb->restDelete('courses', "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /* ===================== ESTADÍSTICAS / LISTAS (sin cambios sustanciales) ===================== */

  public function talkStats(): void {
    header('Content-Type: application/json; charset=utf-8');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid id']); return; }
    try {
      $registeredRows = $this->selectRows('talk_registrations', "talk_id=eq.$id&select=attendee_id");
      $registered = is_array($registeredRows) ? count($registeredRows) : 0;
      $likesNew    = $this->countRows('talk_votes', "talk_id=eq.$id&liked=eq.true");
      $dislikesNew = $this->countRows('talk_votes', "talk_id=eq.$id&liked=eq.false");
      $legacyLikes = $this->countRows('votes', "talk_id=eq.$id");
      $likes    = $likesNew + $legacyLikes;
      $dislikes = $dislikesNew;
      $evalsArr = $this->selectRows('talk_evaluations', "talk_id=eq.$id&select=q1_useful,q2_expectations,q3_content,q4_logistics");
      $n = count($evalsArr);
      $sum = ['q1'=>0,'q2'=>0,'q3'=>0,'q4'=>0];
      foreach ($evalsArr as $e) { $sum['q1'] += (int)($e['q1_useful'] ?? 0); $sum['q2'] += (int)($e['q2_expectations'] ?? 0); $sum['q3'] += (int)($e['q3_content'] ?? 0); $sum['q4'] += (int)($e['q4_logistics'] ?? 0); }
      $avg = ($n > 0) ? ['q1'=>round($sum['q1']/$n,2), 'q2'=>round($sum['q2']/$n,2), 'q3'=>round($sum['q3']/$n,2), 'q4'=>round($sum['q4']/$n,2)] : ['q1'=>null,'q2'=>null,'q3'=>null,'q4'=>null];
      echo json_encode(['id'=>$id,'registered'=>$registered,'likes'=>$likes,'dislikes'=>$dislikes,'evals_count'=>$n,'avg'=>$avg]);
    } catch (\Throwable $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
  }

  public function courseStats(): void {
    header('Content-Type: application/json; charset=utf-8');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid id']); return; }
    try {
      $registeredRows = $this->selectRows('course_registrations', "course_id=eq.$id&select=attendee_id");
      $registered = is_array($registeredRows) ? count($registeredRows) : 0;
      $likes    = $this->countRows('course_votes',  "course_id=eq.$id&liked=eq.true");
      $dislikes = $this->countRows('course_votes',  "course_id=eq.$id&liked=eq.false");
      $evalsArr = $this->selectRows('course_evaluations', "course_id=eq.$id&select=q1_useful,q2_expectations,q3_content,q4_logistics");
      $n = count($evalsArr);
      $sum = ['q1'=>0,'q2'=>0,'q3'=>0,'q4'=>0];
      foreach ($evalsArr as $e) { $sum['q1'] += (int)($e['q1_useful'] ?? 0); $sum['q2'] += (int)($e['q2_expectations'] ?? 0); $sum['q3'] += (int)($e['q3_content'] ?? 0); $sum['q4'] += (int)($e['q4_logistics'] ?? 0); }
      $avg = ($n > 0) ? ['q1'=>round($sum['q1']/$n,2), 'q2'=>round($sum['q2']/$n,2), 'q3'=>round($sum['q3']/$n,2), 'q4'=>round($sum['q4']/$n,2)] : ['q1'=>null,'q2'=>null,'q3'=>null,'q4'=>null];
      echo json_encode(['id'=>$id,'registered'=>$registered,'likes'=>$likes,'dislikes'=>$dislikes,'evals_count'=>$n,'avg'=>$avg]);
    } catch (\Throwable $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
  }

  public function webinarStats(): void {
    header('Content-Type: application/json; charset=utf-8');
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { http_response_code(400); echo json_encode(['error'=>'Invalid id']); return; }
    try {
      $registeredRows = $this->selectRows('webinar_registrations', "webinar_id=eq.$id&select=attendee_id");
      $registered = is_array($registeredRows) ? count($registeredRows) : 0;
      $likes    = $this->countRows('webinar_votes', "webinar_id=eq.$id&liked=eq.true");
      $dislikes = $this->countRows('webinar_votes', "webinar_id=eq.$id&liked=eq.false");
      $evalsArr = $this->selectRows('webinar_evaluations', "webinar_id=eq.$id&select=q1_useful,q2_expectations,q3_content,q4_logistics");
      $n = count($evalsArr);
      $sum = ['q1'=>0,'q2'=>0,'q3'=>0,'q4'=>0];
      foreach ($evalsArr as $e) { $sum['q1'] += (int)($e['q1_useful'] ?? 0); $sum['q2'] += (int)($e['q2_expectations'] ?? 0); $sum['q3'] += (int)($e['q3_content'] ?? 0); $sum['q4'] += (int)($e['q4_logistics'] ?? 0); }
      $avg = ($n > 0) ? ['q1'=>round($sum['q1']/$n,2), 'q2'=>round($sum['q2']/$n,2), 'q3'=>round($sum['q3']/$n,2), 'q4'=>round($sum['q4']/$n,2)] : ['q1'=>null,'q2'=>null,'q3'=>null,'q4'=>null];
      echo json_encode(['id'=>$id,'registered'=>$registered,'likes'=>$likes,'dislikes'=>$dislikes,'evals_count'=>$n,'avg'=>$avg]);
    } catch (\Throwable $e) { http_response_code(500); echo json_encode(['error'=>$e->getMessage()]); }
  }

  public function talkRegistrations(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id<=0) { Http::json(['error'=>'id inválido'],400); return; }
    $regsRes = $this->sb->restSelect('talk_registrations', "talk_id=eq.$id&select=attendee_id,registered_at", true);
    $regs = json_decode($regsRes['body'] ?? '[]', true) ?? [];
    if (!$regs) { Http::json([]); return; }
    $ids = array_values(array_unique(array_map(fn($r)=>$r['attendee_id'] ?? null, $regs))); $ids = array_filter($ids);
    $profiles = [];
    if ($ids) {
      $in = 'in.(' . implode(',', array_map('rawurlencode',$ids)) . ')';
      $pRes = $this->sb->restSelect('profiles', "id=$in&select=id,full_name", true);
      $profilesArr = json_decode($pRes['body'] ?? '[]', true) ?? [];
      foreach ($profilesArr as $p) { $profiles[$p['id']] = $p['full_name'] ?? null; }
    }
    $out = array_map(fn($r)=>['attendee_id'=>$r['attendee_id'],'name'=>$profiles[$r['attendee_id']] ?? null,'registered_at'=>$r['registered_at']], $regs);
    Http::json($out);
  }

  public function courseRegistrations(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id<=0) { Http::json(['error'=>'id inválido'],400); return; }
    $regsRes = $this->sb->restSelect('course_registrations', "course_id=eq.$id&select=attendee_id,registered_at", true);
    $regs = json_decode($regsRes['body'] ?? '[]', true) ?? [];
    if (!$regs) { Http::json([]); return; }
    $ids = array_values(array_unique(array_map(fn($r)=>$r['attendee_id'] ?? null, $regs))); $ids = array_filter($ids);
    $profiles = [];
    if ($ids) {
      $in = 'in.(' . implode(',', array_map('rawurlencode',$ids)) . ')';
      $pRes = $this->sb->restSelect('profiles', "id=$in&select=id,full_name", true);
      $profilesArr = json_decode($pRes['body'] ?? '[]', true) ?? [];
      foreach ($profilesArr as $p) { $profiles[$p['id']] = $p['full_name'] ?? null; }
    }
    $out = array_map(fn($r)=>['attendee_id'=>$r['attendee_id'],'name'=>$profiles[$r['attendee_id']] ?? null,'registered_at'=>$r['registered_at']], $regs);
    Http::json($out);
  }

  public function webinarRegistrations(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id<=0) { Http::json(['error'=>'id inválido'],400); return; }
    $regsRes = $this->sb->restSelect('webinar_registrations', "webinar_id=eq.$id&select=attendee_id,registered_at", true);
    $regs = json_decode($regsRes['body'] ?? '[]', true) ?? [];
    if (!$regs) { Http::json([]); return; }
    $ids = array_values(array_unique(array_map(fn($r)=>$r['attendee_id'] ?? null, $regs))); $ids = array_filter($ids);
    $profiles = [];
    if ($ids) {
      $in = 'in.(' . implode(',', array_map('rawurlencode',$ids)) . ')';
      $pRes = $this->sb->restSelect('profiles', "id=$in&select=id,full_name", true);
      $profilesArr = json_decode($pRes['body'] ?? '[]', true) ?? [];
      foreach ($profilesArr as $p) { $profiles[$p['id']] = $p['full_name'] ?? null; }
    }
    $out = array_map(fn($r)=>['attendee_id'=>$r['attendee_id'],'name'=>$profiles[$r['attendee_id']] ?? null,'registered_at'=>$r['registered_at']], $regs);
    Http::json($out);
  }

  /* ===== Helpers privados ===== */
  private function selectRows(string $table, string $query): array {
    $r1 = $this->sb->restSelect($table, $query, false);
    if (($r1['status'] ?? 500) < 300) return $this->decodeArr($r1);
    $r2 = $this->sb->restSelect($table, $query, true);
    if (($r2['status'] ?? 500) < 300) return $this->decodeArr($r2);
    return [];
  }

  private function countRows(string $table, string $query): int {
    $qs = $query; if (stripos($qs, 'select=') === false) { $qs .= '&select=id'; }
    $rows = $this->selectRows($table, $qs); return is_array($rows) ? count($rows) : 0;
  }

  private function json($data): void {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
  }
}
