<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;
use App\Models\Conference;
use App\Models\Room;
use App\Models\Talk;

class StaffController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

  private function requireStaff(): bool {
    $uid = $this->sb->userIdFromJwt(Http::bearer());
    $role = $uid ? $this->sb->getUserRole($uid) : null;
    return in_array($role, ['admin','staff'], true);
  }

  /* ======================== CREATE ======================== */

  public function createConference(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Conference::create($this->sb, $p['name']??'', $p['city']??'', $p['starts_at']??'', $p['ends_at']??'');
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function createRoom(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();

    $name = trim((string)($p['name'] ?? ''));
    if ($name === '') { Http::json(['error'=>'Nombre es obligatorio'], 400); return; }

    $number   = array_key_exists('number', $p) ? (trim((string)$p['number']) ?: null) : null;
    $capacity = array_key_exists('capacity', $p) ? (int)$p['capacity'] : null;
    if ($capacity !== null && $capacity < 0) { Http::json(['error'=>'Capacidad inválida'], 400); return; }

    // conference_id ahora es OPCIONAL
    $confId = array_key_exists('conference_id', $p) ? (int)$p['conference_id'] : null;
    if ($confId !== null && $confId <= 0) $confId = null;

    $payload = [
      'conference_id' => $confId,
      'name'          => $name,
      'number'        => $number,
      'capacity'      => $capacity
    ];
    $res = $this->sb->restInsert('rooms', $payload, false);
    if (($res['status'] ?? 500) >= 300) {
      Http::json(['error'=>'No se pudo crear la sala', 'detail'=>$res['body'] ?? null], 400);
      return;
    }
    Http::json(['ok'=>true]);
  }

  public function createTalk(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Talk::create(
      $this->sb,
      intval($p['conference_id']??0),
      isset($p['room_id']) ? (is_null($p['room_id']) ? null : intval($p['room_id'])) : null,
      (string)($p['title']??''),
      (string)($p['starts_at']??''),
      (string)($p['ends_at']??''),
      isset($p['speaker_id']) && $p['speaker_id']!=='' ? (string)$p['speaker_id'] : null
    );
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /** ===== Anuncios por tipo/destino (talk|course|webinar + ref_id) ===== */
  public function createAnnouncement(): void {
    $jwt = Http::bearer();
    $uid = $this->sb->userIdFromJwt($jwt);
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }

    $role = $this->sb->getUserRole($uid);
    if (!in_array($role, ['admin','staff'], true)) { Http::json(['error'=>'No autorizado'], 403); return; }

    $in    = Http::jsonInput();
    $title = trim((string)($in['title'] ?? ''));
    $body  = trim((string)($in['body']  ?? ''));
    $kind  = trim((string)($in['kind']  ?? '')); // talk|course|webinar
    $refId = (int)($in['ref_id'] ?? 0);

    if ($title === '' || $body === '') {
      Http::json(['error'=>'Título y mensaje son requeridos'], 400); return;
    }
    if (!in_array($kind, ['talk','course','webinar'], true)) {
      Http::json(['error'=>'kind inválido (talk|course|webinar)'], 400); return;
    }
    if ($refId <= 0) {
      Http::json(['error'=>'ref_id inválido'], 400); return;
    }

    if (!$this->announcementTargetExists($this->sb, $kind, $refId)) {
      Http::json(['error'=>"No existe el destino ($kind #$refId)"], 400); return;
    }

    $res = $this->sb->restInsert('announcements', [
      'title'      => $title,
      'body'       => $body,
      'kind'       => $kind,
      'ref_id'     => $refId,
      'created_at' => gmdate('c'),
      'created_by' => $uid
    ], false);

    if (($res['status'] ?? 500) >= 300) {
      Http::json(['error'=>'No se pudo crear el anuncio','dbg'=>$res['body'] ?? null], 400); return;
    }

    $rows = json_decode($res['body'] ?? '[]', true) ?: [];
    $row  = $rows[0] ?? null;
    Http::json(['ok'=>true, 'announcement'=>$row]);
  }

  /* =================== READ (helpers + listados) =================== */

  public function roomsByConference(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $confId = intval($_GET['conference_id'] ?? 0);
    if ($confId<=0) { Http::json(['error'=>'conference_id inválido'], 400); return; }
    Http::json(Room::listByConference($this->sb, $confId));
  }

  /** NUEVO: listar TODAS las salas */
  public function listAllRooms(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    // Traemos campos útiles para UI
    $res = $this->sb->restSelect('rooms', 'select=id,conference_id,name,number,capacity,created_at&order=created_at.desc', false);
    $arr = json_decode($res['body'] ?? '[]', true) ?? [];
    Http::json($arr);
  }

  public function listTalks(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $confId = intval($_GET['conference_id'] ?? 0);
    if ($confId<=0) { Http::json(['error'=>'conference_id inválido'], 400); return; }
    Http::json(Talk::listByConferenceAdmin($this->sb, $confId));
  }

  /** Listar anuncios (filtros: kind, ref_id) + paginación + enrich opcional */
  public function listAnnouncements(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }

    $kind   = $_GET['kind']   ?? null;                       // talk|course|webinar
    $refId  = isset($_GET['ref_id']) ? (int)$_GET['ref_id'] : null;
    $enrich = isset($_GET['enrich']) ? (int)$_GET['enrich'] : 0;

    $limit  = isset($_GET['limit'])  ? max(1, min(200, (int)$_GET['limit'])) : 100;
    $offset = isset($_GET['offset']) ? max(0, (int)$_GET['offset']) : 0;

    $conds = [];
    if ($kind && in_array($kind, ['talk','course','webinar'], true)) { $conds[] = 'kind=eq.' . rawurlencode($kind); }
    if ($refId) { $conds[] = 'ref_id=eq.' . $refId; }

    $qs = 'select=id,title,body,kind,ref_id,created_at,created_by&order=created_at.desc'
        . "&limit=$limit&offset=$offset";
    if ($conds) $qs = implode('&', $conds) . '&' . $qs;

    $res = $this->sb->restSelect('announcements', $qs, false);
    $arr = json_decode($res['body'] ?? '[]', true) ?? [];

    if ($enrich && $arr) {
      $byKind = ['talk'=>[], 'course'=>[], 'webinar'=>[]];
      foreach ($arr as $a) {
        $k = (string)($a['kind'] ?? '');
        $r = (int)($a['ref_id'] ?? 0);
        if ($k && $r) $byKind[$k][$r] = true;
      }
      $titles = [
        'talk'    => $this->fetchTitlesMap($this->sb, 'talks',    'id,title', array_keys($byKind['talk'])),
        'course'  => $this->fetchTitlesMap($this->sb, 'courses',  'id,title', array_keys($byKind['course'])),
        'webinar' => $this->fetchTitlesMap($this->sb, 'webinars', 'id,title', array_keys($byKind['webinar'])),
      ];
      foreach ($arr as &$a) {
        $k = (string)($a['kind'] ?? '');
        $r = (int)($a['ref_id'] ?? 0);
        $a['ref_title'] = $titles[$k][$r] ?? null;
      }
      unset($a);
    }

    Http::json($arr);
  }

  /* ======================== Usuarios ======================== */

  public function searchUsersByEmail(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $email = trim((string)($_GET['email'] ?? ''));
    $users = $this->sb->authAdminSearchUsers($email);
    foreach ($users as &$u) {
      $u['role'] = $u['id'] ? ($this->sb->getUserRole($u['id']) ?? null) : null;
    }
    unset($u);
    Http::json($users);
  }

  /* ======================== UPDATE ======================== */

  public function updateConference(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Conference::update($this->sb, intval($p['id']??0), [
      'name'=>$p['name']??null, 'city'=>$p['city']??null,
      'starts_at'=>$p['starts_at']??null, 'ends_at'=>$p['ends_at']??null
    ]);
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function updateRoom(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();

    $id = (int)($p['id'] ?? 0);
    if ($id <= 0) { Http::json(['error'=>'ID inválido'], 400); return; }

    $pl = [];
    if (array_key_exists('name', $p))    $pl['name']    = trim((string)$p['name']);
    if (array_key_exists('number', $p))  $pl['number']  = (trim((string)$p['number']) ?: null);
    if (array_key_exists('capacity',$p)) {
      $cap = $p['capacity'] === null || $p['capacity'] === '' ? null : (int)$p['capacity'];
      if ($cap !== null && $cap < 0) { Http::json(['error'=>'Capacidad inválida'], 400); return; }
      $pl['capacity'] = $cap;
    }
    if (array_key_exists('conference_id', $p)) {
      $confId = (int)$p['conference_id']; if ($confId <= 0) $confId = null;
      $pl['conference_id'] = $confId;
    }

    if (!$pl) { Http::json(['ok'=>true]); return; }

    $res = $this->sb->restUpdate('rooms', $pl, "id=eq.$id", false);
    if (($res['status'] ?? 500) >= 300) {
      Http::json(['error'=>'No se pudo actualizar la sala', 'detail'=>$res['body'] ?? null], 400);
      return;
    }
    Http::json(['ok'=>true]);
  }

  public function updateTalk(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Talk::update($this->sb, intval($p['id']??0), [
      'conference_id'=> isset($p['conference_id']) ? intval($p['conference_id']) : null,
      'room_id'=> isset($p['room_id']) ? ( $p['room_id']===null || $p['room_id']==='' ? null : intval($p['room_id']) ) : null,
      'title'=> $p['title']??null,
      'starts_at'=> $p['starts_at']??null,
      'ends_at'=> $p['ends_at']??null,
      'speaker_id'=> array_key_exists('speaker_id',$p) ? ($p['speaker_id']?:null) : null
    ]);
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /** Update de anuncio con kind/ref_id */
  public function updateAnnouncement(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p  = Http::jsonInput();
    $id = (int)($p['id'] ?? 0);
    if ($id <= 0) { Http::json(['error'=>'id inválido'], 400); return; }

    $kind  = array_key_exists('kind',$p)   ? (string)$p['kind']   : null;
    $refId = array_key_exists('ref_id',$p) ? (int)$p['ref_id']    : null;

    if ($kind !== null && !in_array($kind, ['talk','course','webinar'], true)) {
      Http::json(['error'=>'kind inválido (talk|course|webinar)'], 400); return;
    }
    if ($refId !== null && $refId <= 0) {
      Http::json(['error'=>'ref_id inválido'], 400); return;
    }
    if ($kind !== null && $refId !== null) {
      if (!$this->announcementTargetExists($this->sb, $kind, $refId)) {
        Http::json(['error'=>"No existe el destino ($kind #$refId)"], 400); return;
      }
    }

    $pl = array_filter([
      'title'  => array_key_exists('title',$p) ? trim((string)$p['title']) : null,
      'body'   => array_key_exists('body', $p) ? trim((string)$p['body'])  : null,
      'kind'   => $kind,
      'ref_id' => $refId,
    ], fn($v)=>$v!==null);

    if (!$pl) { Http::json(['error'=>'Nada que actualizar'], 400); return; }

    $res = $this->sb->restUpdate('announcements', $pl, "id=eq.$id", false);
    if (($res['status'] ?? 500) >= 300) {
      Http::json(['error'=>'No se pudo actualizar','dbg'=>$res['body'] ?? null], 400); return;
    }

    $sel = $this->sb->restSelect('announcements', "id=eq.$id&select=id,title,body,kind,ref_id,created_at,created_by", false);
    $row = (json_decode($sel['body'] ?? '[]', true) ?: [])[0] ?? null;
    Http::json(['ok'=>true, 'announcement'=>$row]);
  }

  /** Sólo admin puede actualizar roles */
  public function updateUserRole(): void {
    $sb = new Supabase();
    $jwt = Http::bearer();
    $me  = $sb->userIdFromJwt($jwt);
    if (!$me) { Http::json(['error'=>'No autenticado'], 401); return; }

    $myRole = $sb->getUserRole($me);
    if ($myRole !== 'admin') { Http::json(['error'=>'Solo un admin puede cambiar roles'], 403); return; }

    $in   = Http::jsonInput();
    $id   = $in['id'] ?? $in['user_id'] ?? null;
    $role = isset($in['role']) ? trim(strtolower((string)$in['role'])) : null;

    $allowed = ['admin','staff','speaker','attendee'];
    if (!$id || !$role || !in_array($role, $allowed, true)) {
      Http::json(['error'=>'Parámetros inválidos. Esperado: { id|user_id, role ∈ '.implode(', ',$allowed).' }'], 400);
      return;
    }

    $res = $sb->restUpdate('profiles', ['role'=>$role], "id=eq.$id", false);
    if (($res['status'] ?? 500) >= 300) {
      Http::json(['error'=>'No se pudo actualizar el rol', 'detail'=>$res['body'] ?? ''], 500);
      return;
    }

    Http::json(['ok'=>true, 'id'=>$id, 'role'=>$role], 200);
  }

  /* ======================== DELETE ======================== */

  public function deleteConference(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Conference::delete($this->sb, intval($p['id']??0));
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function deleteRoom(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Room::delete($this->sb, intval($p['id']??0));
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function deleteTalk(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Talk::delete($this->sb, intval($p['id']??0));
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function deleteAnnouncement(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p  = Http::jsonInput();
    $id = (int)($p['id'] ?? 0);
    if ($id <= 0) { Http::json(['error'=>'id inválido'], 400); return; }

    // Comprobar exista
    $exists = $this->sb->restSelect('announcements', "id=eq.$id&select=id", false);
    $arrEx  = json_decode($exists['body'] ?? '[]', true) ?: [];
    if (!$arrEx) { Http::json(['error'=>'No existe el anuncio'], 404); return; }

    $res = $this->sb->restDelete('announcements', "id=eq.$id", false);
    if (($res['status'] ?? 500) >= 300) {
      Http::json(['error'=>'No se pudo eliminar','dbg'=>$res['body'] ?? null], 400); return;
    }
    Http::json(['ok'=>true]);
  }

  /* ======================== Helpers internos ======================== */

  /** Verifica que exista el recurso destino según kind/ref_id */
  private function announcementTargetExists(Supabase $sb, string $kind, int $refId): bool {
    $table = $kind === 'talk' ? 'talks' : ($kind === 'course' ? 'courses' : 'webinars');
    $res = $sb->restSelect($table, "id=eq.$refId&select=id&limit=1", false);
    $arr = json_decode($res['body'] ?? '[]', true) ?? [];
    return !empty($arr);
  }

  /** Devuelve mapa id=>title para enriquecer listados */
  private function fetchTitlesMap(Supabase $sb, string $table, string $select, array $ids): array {
    $map = [];
    if (!$ids) return $map;
    $chunks = array_chunk($ids, 200);
    foreach ($chunks as $chunk) {
      $in = 'in.(' . implode(',', array_map('intval', $chunk)) . ')';
      $qs = "id=$in&select=" . rawurlencode($select);
      $res = $sb->restSelect($table, $qs, false);
      $arr = json_decode($res['body'] ?? '[]', true) ?? [];
      foreach ($arr as $row) {
        $map[(int)$row['id']] = (string)($row['title'] ?? '');
      }
    }
    return $map;
  }
}
