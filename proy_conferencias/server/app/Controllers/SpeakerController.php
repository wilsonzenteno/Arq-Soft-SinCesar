<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;

class SpeakerController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

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

  /* ---------- helpers compat ---------- */
  private function tryInsert(string $table, array $payloads, bool $useAnon=false): array {
    foreach ($payloads as $pl) {
      $res = $this->sb->restInsert($table, $pl, $useAnon);
      if (($res['status'] ?? 500) < 300) return $res;
    }
    return ['status'=>400,'body'=>'[]'];
  }
  private function trySelect(string $table, array $selectQueries, bool $useAnon=false): array {
    foreach ($selectQueries as $q) {
      $res = $this->sb->restSelect($table, $q, $useAnon);
      if (($res['status'] ?? 500) < 300) return $res;
    }
    return ['status'=>500,'body'=>'[]'];
  }
  private function tryUpdate(string $table, string $filter, array $payloads, bool $useAnon=false): array {
    foreach ($payloads as $pl) {
      $pl = array_filter($pl, fn($v)=>$v!==null);
      if (!$pl) continue;
      $res = $this->sb->restUpdate($table, $pl, $filter, $useAnon);
      if (($res['status'] ?? 500) < 300) return $res;
    }
    return ['status'=>500,'body'=>'[]'];
  }

  /* ---------- LISTADOS ---------- */

  public function myTalks(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }

    // 1) talks con speaker_id = auth.uid
    $res = $this->trySelect('talks', [
      "speaker_id=eq.$uid&select=id,conference_id,speaker_id,title,description,start_time,end_time,starts_at,ends_at,created_at,updated_at&order=start_time.desc",
      "speaker_id=eq.$uid&select=id,conference_id,speaker_id,title,description,starts_at,ends_at,created_at,updated_at&order=starts_at.desc"
    ], false);
    $arr = json_decode($res['body'] ?? "[]", true) ?? [];

    // 2) Si no hay y existe tabla 'speakers', buscar por email
    if (!$arr) {
      $email = $this->userEmail();
      if ($email) {
        $sp = $this->sb->restSelect('speakers', "email=eq." . rawurlencode($email) . "&select=id", false);
        $sarr = json_decode($sp['body'] ?? "[]", true) ?? [];
        $sid = $sarr[0]['id'] ?? null;
        if ($sid) {
          $res2 = $this->trySelect('talks', [
            "speaker_id=eq.$sid&select=id,conference_id,speaker_id,title,description,start_time,end_time,starts_at,ends_at,created_at,updated_at&order=start_time.desc",
            "speaker_id=eq.$sid&select=id,conference_id,speaker_id,title,description,starts_at,ends_at,created_at,updated_at&order=starts_at.desc"
          ], false);
          $arr = json_decode($res2['body'] ?? "[]", true) ?? [];
        }
      }
    }

    foreach ($arr as &$t) {
      $t['start_time'] = $t['start_time'] ?? ($t['starts_at'] ?? null);
      $t['end_time']   = $t['end_time']   ?? ($t['ends_at']   ?? null);
    }
    Http::json($arr);
  }

  public function myConferences(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    // Preferir conferencias con owner_id (nuevo esquema), fallback a antiguas name/city
    $res = $this->trySelect('conferences', [
      "owner_id=eq.$uid&select=id,title,description,location,date,owner_id,modality,venue,stream_url,created_at,updated_at&order=date.desc",
      "select=id,title,description,location,date,modality,venue,stream_url,created_at,updated_at&order=date.desc",
      "select=id,name,city,starts_at,ends_at&order=starts_at.asc"
    ], false);
    $arr = json_decode($res['body'] ?? "[]", true) ?? [];
    Http::json($arr);
  }

  public function myWebinars(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $res = $this->sb->restSelect('webinars', "owner_id=eq.$uid&select=id,title,description,modality,venue,stream_url,starts_at,ends_at,created_at&order=starts_at.desc", false);
    $arr = json_decode($res['body'] ?? "[]", true) ?? [];
    Http::json($arr);
  }

  public function myCourses(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $res = $this->sb->restSelect('courses', "owner_id=eq.$uid&select=id,title,description,modality,venue,stream_url,starts_at,ends_at,created_at&order=starts_at.desc", false);
    $arr = json_decode($res['body'] ?? "[]", true) ?? [];
    Http::json($arr);
  }

  /* ---------- CONFERENCIAS ---------- */

  public function createConferenceBySpeaker(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $res = $this->tryInsert('conferences', [
      [
        'title'       => (string)($p['title'] ?? ($p['name'] ?? '')),
        'description' => $p['description'] ?? null,
        'location'    => $p['location'] ?? ($p['city'] ?? null),
        'date'        => $p['date'] ?? ($p['starts_at'] ?? null),
        'owner_id'    => $uid,
        'modality'    => $p['modality'] ?? null,
        'venue'       => $p['venue'] ?? null,
        'stream_url'  => $p['stream_url'] ?? null
      ],
      [
        'name'      => (string)($p['name'] ?? ($p['title'] ?? '')),
        'city'      => $p['city'] ?? ($p['location'] ?? null),
        'starts_at' => $p['starts_at'] ?? ($p['date'] ?? null),
        'ends_at'   => $p['ends_at'] ?? null
      ]
    ], false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function updateConferenceBySpeaker(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (string)($p['id'] ?? '');
    if ($id==='') { Http::json(['error'=>'id inválido'], 400); return; }

    $check = $this->sb->restSelect('conferences', "id=eq.$id&select=owner_id", false);
    $row   = (json_decode($check['body'] ?? "[]", true) ?? [])[0] ?? null;
    if ($row && isset($row['owner_id']) && $row['owner_id'] !== $uid) {
      Http::json(['error'=>'No autorizado'], 403); return;
    }

    $res = $this->tryUpdate('conferences', "id=eq.$id", [
      [
        'title'       => $p['title'] ?? null,
        'description' => $p['description'] ?? null,
        'location'    => $p['location'] ?? null,
        'date'        => $p['date'] ?? null,
        'modality'    => $p['modality'] ?? null,
        'venue'       => $p['venue'] ?? null,
        'stream_url'  => $p['stream_url'] ?? null
      ],
      [
        'name'      => $p['name']      ?? $p['title'] ?? null,
        'city'      => $p['city']      ?? $p['location'] ?? null,
        'starts_at' => $p['starts_at'] ?? $p['date'] ?? null,
        'ends_at'   => $p['ends_at']   ?? null
      ]
    ], false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function deleteConferenceBySpeaker(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (string)($p['id'] ?? '');
    if ($id==='') { Http::json(['error'=>'id inválido'], 400); return; }
    $res = $this->sb->restDelete('conferences', "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /* ---------- CHARLAS ---------- */

  public function createOwnTalk(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $confId = (string)($p['conference_id'] ?? '');
    $title  = (string)($p['title'] ?? '');
    if ($confId==='' || $title==='') { Http::json(['error'=>'Datos inválidos'], 400); return; }

    // Si existe speakers.email == mi email, úsalo como speaker_id (soporta nuevo esquema)
    $speakerId = null;
    $email = $this->userEmail();
    if ($email) {
      $sp = $this->sb->restSelect('speakers', "email=eq." . rawurlencode($email) . "&select=id", false);
      $sarr = json_decode($sp['body'] ?? "[]", true) ?? [];
      $speakerId = $sarr[0]['id'] ?? null;
    }

    $payloads = [
      [ // esquema nuevo (timestamps start_time/end_time)
        'conference_id' => $confId,
        'speaker_id'    => $uid,
        'title'         => $title,
        'description'   => $p['description'] ?? null,
        'start_time'    => $p['start_time'] ?? ($p['starts_at'] ?? null),
        'end_time'      => $p['end_time']   ?? ($p['ends_at']   ?? null)
      ],
      [ // esquema antiguo (starts_at/ends_at)
        'conference_id' => is_numeric($confId) ? intval($confId,10) : $confId,
        'speaker_id'    => $uid,
        'title'         => $title,
        'description'   => $p['description'] ?? null,
        'starts_at'     => $p['start_time'] ?? $p['starts_at'] ?? null,
        'ends_at'       => $p['end_time']   ?? $p['ends_at']   ?? null
      ]
    ];

    if ($speakerId) {
      $payloads[] = [
        'conference_id' => $confId, 'speaker_id' => $speakerId, 'title'=>$title,
        'description'=>$p['description'] ?? null,
        'start_time' => $p['start_time'] ?? ($p['starts_at'] ?? null),
        'end_time'   => $p['end_time']   ?? ($p['ends_at']   ?? null)
      ];
      $payloads[] = [
        'conference_id' => is_numeric($confId) ? intval($confId,10) : $confId,
        'speaker_id' => $speakerId, 'title'=>$title,
        'description'=>$p['description'] ?? null,
        'starts_at' => $p['start_time'] ?? $p['starts_at'] ?? null,
        'ends_at'   => $p['end_time']   ?? $p['ends_at']   ?? null
      ];
    }

    $res = $this->tryInsert('talks', $payloads, false);
    if (($res['status'] ?? 500) < 300) {
      $rows = json_decode($res['body'] ?? '[]', true) ?? [];
      $id   = $rows[0]['id'] ?? null;
      Http::json(['ok'=>true, 'id'=>$id]); return;
    }
    Http::json(['error'=>'Fail'], 400);
  }

  public function updateOwnTalk(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (string)($p['id'] ?? '');
    if ($id==='') { Http::json(['error'=>'id inválido'], 400); return; }

    $res = $this->tryUpdate('talks', "id=eq.$id", [
      [
        'conference_id' => $p['conference_id'] ?? null,
        'title'         => $p['title'] ?? null,
        'description'   => $p['description'] ?? null,
        'start_time'    => $p['start_time'] ?? null,
        'end_time'      => $p['end_time'] ?? null
      ],
      [
        'conference_id' => $p['conference_id'] ?? null,
        'title'         => $p['title'] ?? null,
        'starts_at'     => $p['start_time'] ?? $p['starts_at'] ?? null,
        'ends_at'       => $p['end_time']   ?? $p['ends_at']   ?? null
      ]
    ], false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function deleteOwnTalk(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $id = (string)($p['id'] ?? '');
    if ($id==='') { Http::json(['error'=>'id inválido'], 400); return; }
    $res = $this->sb->restDelete('talks', "id=eq.$id", false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /* ---------- SLIDES (ya existente) ---------- */

  public function uploadSlides(): void {
    $uid = $this->sb->userIdFromJwt($_SERVER['HTTP_AUTHORIZATION'] ?? null);
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $talkId = isset($_POST['talk_id']) ? (string)$_POST['talk_id'] : '';
    if ($talkId==='' || empty($_FILES['file'])) { Http::json(['error'=>'Datos inválidos'], 400); return; }

    $fileTmp = $_FILES['file']['tmp_name'];
    $fileName = basename($_FILES['file']['name']);
    $path = "talks/$talkId/slides/$fileName";
    $ok1 = $this->sb->storageUpload('slides', $path, file_get_contents($fileTmp), mime_content_type($fileTmp) ?: 'application/pdf');

    // registra/actualiza recurso (tabla resources – compat)
    $ok2 = true;
    if (class_exists('\\App\\Models\\Resource')) {
      $ok2 = \App\Models\Resource::upsert($this->sb, is_numeric($talkId)?intval($talkId,10):$talkId, $path, $uid);
    }

    ($ok1 && $ok2) ? Http::json(['ok'=>true, 'path'=>$path]) : Http::json(['error'=>'Upload fail'], 400);
  }
}
