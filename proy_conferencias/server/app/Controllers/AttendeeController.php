<?php
// server/app/Controllers/AttendeeController.php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;

class AttendeeController {
  private Supabase $sb;

  public function __construct(){
    $this->sb = new Supabase();
  }

  /* ================= Helpers ================= */

  private function uid(): ?string {
    $jwt = Http::bearer();
    return $this->sb->userIdFromJwt($jwt);
  }

  private function decode(array $r): array {
    return json_decode($r['body'] ?? '[]', true) ?? [];
  }

  private function decodeOrFail(array $res): array {
    $status = intval($res['status'] ?? 500);
    $body   = $res['body'] ?? '';
    $json   = json_decode($body !== '' ? $body : '[]', true);
    if ($status >= 300) {
      $msg = is_array($json) && isset($json['message']) ? $json['message'] : 'Supabase error';
      Http::json(['error'=>$msg, 'debug'=>$json], 500);
      exit;
    }
    return (is_array($json) ? $json : []);
  }

  private function isRegistered(string $table, string $idField, $id, string $uid): bool {
    $q = $idField . "=eq.$id&attendee_id=eq.$uid&select=$idField";
    $r = $this->sb->restSelect($table, $q, false);
    $a = $this->decodeOrFail($r);
    return is_array($a) && count($a) > 0;
  }

  /* ===================== TALK DETAILS (para asistentes) ===================== */

  // GET /attendee.talk.details&id=123
  public function talkDetails(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) { Http::json(['error'=>'id inválido'], 400); return; }

    // Solo columnas que existen
    $qTalk = "id=eq.$id&select=" . rawurlencode(
      "id,title,starts_at,ends_at,conference_id,room_id,modality,venue,stream_url"
    );
    $rTalk = $this->sb->restSelect('talks', $qTalk, true);
    $talk  = ($this->decodeOrFail($rTalk)[0] ?? null);
    if (!$talk) { Http::json(['error'=>'Charla no encontrada'], 404); return; }

    // Sala (opcional)
    $room = null;
    $roomName = null;
    $roomCapacity = null;
    if (!empty($talk['room_id'])) {
      $rid = (int)$talk['room_id'];
      $qRoom = "id=eq.$rid&select=" . rawurlencode("id,name,number,capacity");
      $rRoom = $this->sb->restSelect('rooms', $qRoom, true);
      $room  = ($this->decodeOrFail($rRoom)[0] ?? null);
      if ($room) {
        $roomName = trim(($room['name'] ?? '') . ' ' . ($room['number'] ?? ''));
        $roomCapacity = isset($room['capacity']) ? (is_null($room['capacity']) ? null : (int)$room['capacity']) : null;
      }
    }

    // Conferencia (para ciudad)
    $confCity = null;
    if (!empty($talk['conference_id'])) {
      $cid  = (int)$talk['conference_id'];
      $qConf= "id=eq.$cid&select=" . rawurlencode("id,city,name");
      $rConf= $this->sb->restSelect('conferences', $qConf, true);
      $conf = ($this->decodeOrFail($rConf)[0] ?? null);
      $confCity = $conf['city'] ?? null;
    }

    // Ocupación: contamos registros de la charla
    $taken = 0;
    $rRegs = $this->sb->restSelect('talk_registrations', "talk_id=eq.$id&select=talk_id", false);
    $arrRegs = $this->decodeOrFail($rRegs);
    if (is_array($arrRegs)) $taken = count($arrRegs);

    $capacity = $roomCapacity; // null = sin límite
    $seatsLeft = is_null($capacity) ? null : max(0, $capacity - $taken);
    $isFull = is_null($capacity) ? false : ($taken >= $capacity);

    $out = [
      'id'             => $talk['id'],
      'title'          => $talk['title'] ?? '(sin título)',
      'starts_at'      => $talk['starts_at'] ?? null,
      'ends_at'        => $talk['ends_at'] ?? null,
      'conference_id'  => $talk['conference_id'] ?? null,
      'room_id'        => $talk['room_id'] ?? null,
      'room_name'      => $roomName,
      'room_capacity'  => $capacity,
      'seats_taken'    => $taken,
      'seats_left'     => $seatsLeft,
      'is_full'        => $isFull,
      'modality'       => $talk['modality'] ?? null,       // 'presencial' | 'virtual' | 'hibrida'
      'venue'          => $talk['venue'] ?? null,          // lugar físico si aplica
      'stream_url'     => $talk['stream_url'] ?? null,     // URL si aplica
      'conference_city'=> $confCity
    ];

    Http::json($out);
  }

  /* ================= Notificaciones (anuncios dirigidos) ================= */

  public function notifications(): void {
    header('Content-Type: application/json; charset=utf-8');

    $jwt = \App\Core\Http::bearer();
    $uid = $this->sb->userIdFromJwt($jwt);
    if (!$uid) { Http::json(['items'=>[]]); return; }

    $limit = isset($_GET['limit']) ? max(1, min(200, (int)$_GET['limit'])) : 50;
    $since = isset($_GET['since']) ? (string)$_GET['since'] : null;

    $args = ['p_attendee_id' => $uid, 'p_limit' => $limit];
    if ($since) $args['p_since'] = $since;

    $rpc = $this->sb->restRpc('get_user_announcements', $args, false);
    $rpcStatus = (int)($rpc['status'] ?? 500);

    if ($rpcStatus < 300) {
      $items = json_decode($rpc['body'] ?? '[]', true) ?? [];
      usort($items, fn($a,$b)=> strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01'));
      $items = array_slice($items, 0, $limit);
      Http::json(['items'=>$items]);
      return;
    }

    try {
      $talks   = $this->decodeOrFail($this->sb->restSelect('talk_registrations',    "attendee_id=eq.$uid&select=talk_id",    false));
      $courses = $this->decodeOrFail($this->sb->restSelect('course_registrations',  "attendee_id=eq.$uid&select=course_id",  false));
      $webs    = $this->decodeOrFail($this->sb->restSelect('webinar_registrations', "attendee_id=eq.$uid&select=webinar_id", false));

      $talkIds   = array_values(array_unique(array_map(fn($r)=>$r['talk_id']    ?? null, $talks)));
      $courseIds = array_values(array_unique(array_map(fn($r)=>$r['course_id']  ?? null, $courses)));
      $webIds    = array_values(array_unique(array_map(fn($r)=>$r['webinar_id'] ?? null, $webs)));

      $parts = [];
      $encodeList = function(array $ids){
        $ids = array_values(array_filter($ids, fn($v)=> $v !== null && $v !== ''));
        if (!$ids) return '';
        return 'ref_id.in.(' . implode(',', array_map('rawurlencode', $ids)) . ')';
      };

      if ($talkIds)   $parts[] = "and(kind.eq.talk,"   . $encodeList($talkIds)   . ")";
      if ($courseIds) $parts[] = "and(kind.eq.course," . $encodeList($courseIds) . ")";
      if ($webIds)    $parts[] = "and(kind.eq.webinar,". $encodeList($webIds)    . ")";

      $query = '';
      if ($parts) {
        $query = "select=id,title,body,kind,ref_id,created_at&or=(" . implode(',', $parts) . ")";
        if ($since) $query .= "&created_at=gt." . rawurlencode($since);
        $query .= "&order=created_at.desc&limit=" . $limit;
      } else {
        Http::json(['items'=>[]]); return;
      }

      $res  = $this->sb->restSelect('announcements', $query, false);
      $list = $this->decodeOrFail($res);

      $items = array_map(function($a){
        return [
          'id'         => $a['id'] ?? null,
          'title'      => $a['title'] ?? 'Notificación',
          'body'       => $a['body'] ?? '',
          'kind'       => $a['kind'] ?? null,
          'ref_id'     => $a['ref_id'] ?? null,
          'created_at' => $a['created_at'] ?? null
        ];
      }, is_array($list) ? $list : []);

      usort($items, fn($a,$b)=> strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01'));
      $items = array_slice($items, 0, $limit);

      Http::json(['items'=>$items]);
    } catch (\Throwable $e) {
      Http::json(['items'=>[], 'error'=>'fallback_failed', 'message'=>$e->getMessage()], 500);
    }
  }

  /* ================= Listas públicas ================= */

  public function listConferences(): void {
    $res = $this->sb->restSelect('conferences', '', false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(function($c){
      $title = $c['title'] ?? $c['name'] ?? '(sin título)';
      $location = $c['city'] ?? null;
      $date     = $c['date']      ?? null;
      $starts   = $c['starts_at'] ?? ($c['start_time'] ?? null);
      $ends     = $c['ends_at']   ?? ($c['end_time']   ?? null);
      return [
        'id'         => $c['id'] ?? null,
        'title'      => $title,
        'location'   => $location,
        'date'       => $date,
        'starts_at'  => $starts,
        'ends_at'    => $ends,
        'modality'   => $c['modality']   ?? null,
        'venue'      => $c['venue']      ?? null,
        'stream_url' => $c['stream_url'] ?? null
      ];
    }, is_array($arr) ? $arr : []);
    usort($out, function($a,$b){
      $ta = strtotime($a['date'] ?? $a['starts_at'] ?? '1970-01-01');
      $tb = strtotime($b['date'] ?? $b['starts_at'] ?? '1970-01-01');
      return $tb <=> $ta;
    });
    Http::json($out);
  }

  public function listTalksByConference(): void {
    $cid = $_GET['conference_id'] ?? '';
    if ($cid === '') { Http::json(['error'=>'conference_id inválido'], 400); return; }
    $q   = "conference_id=eq.$cid";
    $res = $this->sb->restSelect('talks', $q, false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(function($t){
      return [
        'id'        => $t['id'] ?? null,
        'title'     => $t['title'] ?? '(sin título)',
        'starts_at' => $t['start_time'] ?? ($t['starts_at'] ?? null),
        'ends_at'   => $t['end_time']   ?? ($t['ends_at']   ?? null),
        'room_name' => $t['room_name'] ?? null
      ];
    }, is_array($arr) ? $arr : []);
    usort($out, function($a,$b){
      $ta = strtotime($a['starts_at'] ?? '1970-01-01');
      $tb = strtotime($b['starts_at'] ?? '1970-01-01');
      return $ta <=> $tb;
    });
    Http::json($out);
  }

  // GET /attendee.talks.all
  public function listAllTalks(): void {
    $res = $this->sb->restSelect('talks', '', false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(function($t){
      return [
        'id'            => $t['id'] ?? null,
        'title'         => $t['title'] ?? '(sin título)',
        'starts_at'     => $t['start_time'] ?? ($t['starts_at'] ?? null),
        'ends_at'       => $t['end_time']   ?? ($t['ends_at']   ?? null),
        'conference_id' => $t['conference_id'] ?? null,
        'room_name'     => $t['room_name'] ?? null,
      ];
    }, is_array($arr) ? $arr : []);
    usort($out, fn($a,$b)=> (strtotime($b['starts_at'] ?? '1970-01-01')) <=> (strtotime($a['starts_at'] ?? '1970-01-01')));
    Http::json($out);
  }

  // GET /attendee.courses.all
  public function listAllCourses(): void {
    $res = $this->sb->restSelect('courses', '', false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(function($w){
      return [
        'id'         => $w['id'] ?? null,
        'title'      => $w['title'] ?? '(sin título)',
        'description'=> $w['description'] ?? null,
        'modality'   => $w['modality'] ?? null,
        'venue'      => $w['venue'] ?? null,
        'stream_url' => $w['stream_url'] ?? null,
        'starts_at'  => $w['starts_at'] ?? null,
        'ends_at'    => $w['ends_at']   ?? null
      ];
    }, is_array($arr) ? $arr : []);
    usort($out, fn($a,$b)=> (strtotime($b['starts_at'] ?? '1970-01-01')) <=> (strtotime($a['starts_at'] ?? '1970-01-01')));
    Http::json($out);
  }

  // GET /attendee.webinars.all
  public function listAllWebinars(): void {
    $res = $this->sb->restSelect('webinars', '', false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(function($w){
      return [
        'id'         => $w['id'] ?? null,
        'title'      => $w['title'] ?? '(sin título)',
        'description'=> $w['description'] ?? null,
        'modality'   => $w['modality'] ?? null,
        'venue'      => $w['venue'] ?? null,
        'stream_url' => $w['stream_url'] ?? null,
        'starts_at'  => $w['starts_at'] ?? null,
        'ends_at'    => $w['ends_at']   ?? null
      ];
    }, is_array($arr) ? $arr : []);
    usort($out, fn($a,$b)=> (strtotime($b['starts_at'] ?? '1970-01-01')) <=> (strtotime($a['starts_at'] ?? '1970-01-01')));
    Http::json($out);
  }

  /* ================= Conferencias (legado/soporte) ================= */

  public function registerToConference(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $cid = $p['conference_id'] ?? '';
    if ($cid==='') { Http::json(['error'=>'conference_id inválido'], 400); return; }

    $res = $this->sb->restUpsert('registrations', [
      'conference_id'=>$cid, 'attendee_id'=>$uid
    ], 'conference_id,attendee_id', false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo registrar'], 400);
  }

  public function myRegistrations(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $res = $this->sb->restSelect('registrations', "attendee_id=eq.$uid", false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(fn($r)=>['conference_id'=>$r['conference_id'] ?? null], is_array($arr)?$arr:[]);
    Http::json($out);
  }

  public function myVotes(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $cid = $_GET['conference_id'] ?? '';
    if ($cid==='') { Http::json(['error'=>'conference_id inválido'], 400); return; }
    $res = $this->sb->restSelect('votes', "attendee_id=eq.$uid", false);
    $votes = $this->decodeOrFail($res);
    $out = [];
    if (is_array($votes)) {
      foreach ($votes as $v) {
        $tid = $v['talk_id'] ?? null; if (!$tid) continue;
        $r2 = $this->sb->restSelect('talks', "id=eq.$tid&select=id,conference_id", false);
        $t  = $this->decodeOrFail($r2);
        $t0 = $t[0] ?? null;
        if ($t0 && (string)($t0['conference_id'] ?? '') === (string)$cid) $out[] = ['talk_id'=>$tid];
      }
    }
    Http::json($out);
  }

  public function myConferenceLikes(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $res = $this->sb->restSelect('conference_votes', "attendee_id=eq.$uid", false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(fn($r)=>['conference_id'=>$r['conference_id'] ?? null], is_array($arr)?$arr:[]);
    Http::json($out);
  }

  public function likeConference(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $cid = $p['conference_id'] ?? '';
    $like= isset($p['like']) ? (bool)$p['like'] : true;
    if ($cid==='') { Http::json(['error'=>'conference_id inválido'], 400); return; }

    if ($like) {
      $res = $this->sb->restUpsert('conference_votes', [
        'conference_id'=>$cid, 'attendee_id'=>$uid
      ], 'conference_id,attendee_id', false);
      (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
    } else {
      $res = $this->sb->restDelete('conference_votes', "conference_id=eq.$cid&attendee_id=eq.$uid", false);
      (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
    }
  }

  public function getConferenceEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $cid = $_GET['conference_id'] ?? '';
    if ($cid==='') { Http::json(['error'=>'conference_id inválido'], 400); return; }
    $r = $this->sb->restSelect('conference_evaluations', "conference_id=eq.$cid&attendee_id=eq.$uid", false);
    $a = $this->decodeOrFail($r);
    Http::json($a[0] ?? null);
  }

  public function saveConferenceEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $cid = $p['conference_id'] ?? '';
    if ($cid==='') { Http::json(['error'=>'conference_id inválido'], 400); return; }
    $q1 = max(1, min(5, intval($p['q1'] ?? $p['q1_useful'] ?? 3)));
    $q2 = max(1, min(5, intval($p['q2'] ?? $p['q2_expectations'] ?? 3)));
    $q3 = max(1, min(5, intval($p['q3'] ?? $p['q3_content'] ?? 3)));
    $q4 = max(1, min(5, intval($p['q4'] ?? $p['q4_logistics'] ?? 3)));
    $comments = (string)($p['comments'] ?? '');
    $payload = [
      'conference_id'=>$cid, 'attendee_id'=>$uid,
      'q1_useful'=>$q1, 'q2_expectations'=>$q2, 'q3_content'=>$q3, 'q4_logistics'=>$q4,
      'comments'=>$comments, 'updated_at'=>gmdate('c')
    ];
    $r = $this->sb->restUpsert('conference_evaluations', $payload, 'conference_id,attendee_id', false);
    (($r['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo guardar'], 400);
  }

  /* ================= Inscripción a charlas/cursos/webinars ================= */

  public function registerToTalk(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $tid = $p['talk_id'] ?? '';
    if ($tid==='') { Http::json(['error'=>'talk_id inválido'], 400); return; }

    // Obtenemos sala y modalidad de la charla
    $qTalk = "id=eq.$tid&select=" . rawurlencode("id,room_id,modality");
    $rTalk = $this->sb->restSelect('talks', $qTalk, false);
    $tArr  = $this->decodeOrFail($rTalk);
    $t0    = $tArr[0] ?? null;
    if (!$t0) { Http::json(['error'=>'Charla no encontrada'], 404); return; }

    $modality = strtolower((string)($t0['modality'] ?? 'presencial'));
    $roomId   = $t0['room_id'] ?? null;

    // Si hay sala y capacidad (y no es virtual), validamos aforo
    if ($roomId && $modality !== 'virtual') {
      $qRoom = "id=eq.$roomId&select=" . rawurlencode("id,capacity");
      $rRoom = $this->sb->restSelect('rooms', $qRoom, false);
      $room  = $this->decodeOrFail($rRoom)[0] ?? null;

      $capacity = isset($room['capacity']) ? (is_null($room['capacity']) ? null : (int)$room['capacity']) : null;

      if (!is_null($capacity)) {
        $rRegs = $this->sb->restSelect('talk_registrations', "talk_id=eq.$tid&select=talk_id", false);
        $arrRegs = $this->decodeOrFail($rRegs);
        $taken = is_array($arrRegs) ? count($arrRegs) : 0;

        if ($taken >= $capacity) {
          Http::json(['error'=>'Sala llena','code'=>'ROOM_FULL'], 409);
          return;
        }
      }
    }

    // Inserción idempotente (ON CONFLICT DO NOTHING)
    $res = $this->sb->restUpsert('talk_registrations', [
      'talk_id'=>$tid, 'attendee_id'=>$uid
    ], 'talk_id,attendee_id', false);

    (($res['status'] ?? 500) < 300)
      ? Http::json(['ok'=>true])
      : Http::json(['error'=>'No se pudo registrar'], 400);
  }

  public function myTalkRegistrations(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $res = $this->sb->restSelect('talk_registrations', "attendee_id=eq.$uid&select=talk_id", false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(fn($r)=>['talk_id'=>$r['talk_id'] ?? null], is_array($arr)?$arr:[]);
    Http::json($out);
  }

  public function registerToCourse(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $cid = $p['course_id'] ?? '';
    if ($cid==='') { Http::json(['error'=>'course_id inválido'], 400); return; }
    $res = $this->sb->restUpsert('course_registrations', [
      'course_id'=>$cid, 'attendee_id'=>$uid
    ], 'course_id,attendee_id', false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo registrar'], 400);
  }

  public function myCourseRegistrations(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $res = $this->sb->restSelect('course_registrations', "attendee_id=eq.$uid&select=course_id", false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(fn($r)=>['course_id'=>$r['course_id'] ?? null], is_array($arr)?$arr:[]);
    Http::json($out);
  }

  public function registerToWebinar(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $wid = $p['webinar_id'] ?? '';
    if ($wid==='') { Http::json(['error'=>'webinar_id inválido'], 400); return; }
    $res = $this->sb->restUpsert('webinar_registrations', [
      'webinar_id'=>$wid, 'attendee_id'=>$uid
    ], 'webinar_id,attendee_id', false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo registrar'], 400);
  }

  public function myWebinarRegistrations(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $res = $this->sb->restSelect('webinar_registrations', "attendee_id=eq.$uid&select=webinar_id", false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(fn($r)=>['webinar_id'=>$r['webinar_id'] ?? null], is_array($arr)?$arr:[]);
    Http::json($out);
  }

  /* ================= Votos (likes/dislikes) ================= */

  public function talkVote(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $tid  = $p['talk_id'] ?? null;
    $like = isset($p['like']) ? (bool)$p['like'] : null;

    if (!$tid || !is_numeric($tid)) { Http::json(['error'=>'talk_id inválido'], 400); return; }
    if ($like === null) { Http::json(['error'=>'like requerido'], 400); return; }
    if (!$this->isRegistered('talk_registrations','talk_id',$tid,$uid)) {
      Http::json(['error'=>'Debes estar inscrito en la charla para votar'], 403); return;
    }

    $res = $this->sb->restUpsert('talk_votes', [
      'talk_id'=>intval($tid,10), 'attendee_id'=>$uid, 'liked'=>$like
    ], 'talk_id,attendee_id', false);

    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo votar','debug'=>$this->decode($res)], 400);
  }

  public function myTalkVotes(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $r = $this->sb->restSelect('talk_votes', "attendee_id=eq.$uid&select=talk_id,liked", false);
    $a = $this->decodeOrFail($r);
    $out = array_map(fn($x)=>['talk_id'=>$x['talk_id'] ?? null, 'liked'=> isset($x['liked']) ? (bool)$x['liked'] : null], $a);
    Http::json($out);
  }

  public function courseVote(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $id   = $p['course_id'] ?? '';
    $like = isset($p['like']) ? (bool)$p['like'] : null;
    if ($id==='') { Http::json(['error'=>'course_id inválido'], 400); return; }
    if ($like===null) { Http::json(['error'=>'like requerido'], 400); return; }
    if (!$this->isRegistered('course_registrations','course_id',$id,$uid)) {
      Http::json(['error'=>'Debes estar inscrito para votar'], 403); return;
    }
    $res = $this->sb->restUpsert('course_votes', [
      'course_id'=>$id, 'attendee_id'=>$uid, 'liked'=>$like
    ], 'course_id,attendee_id', false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo votar'], 400);
  }

  public function myCourseVotes(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $r = $this->sb->restSelect('course_votes', "attendee_id=eq.$uid&select=course_id,liked", false);
    $a = $this->decodeOrFail($r);
    $out = array_map(fn($x)=>['course_id'=>$x['course_id'] ?? null, 'liked'=> (bool)($x['liked'] ?? false)], is_array($a)?$a:[]);
    Http::json($out);
  }

  public function webinarVote(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $id   = $p['webinar_id'] ?? '';
    $like = isset($p['like']) ? (bool)$p['like'] : null;
    if ($id==='') { Http::json(['error'=>'webinar_id inválido'], 400); return; }
    if ($like===null) { Http::json(['error'=>'like requerido'], 400); return; }
    if (!$this->isRegistered('webinar_registrations','webinar_id',$id,$uid)) {
      Http::json(['error'=>'Debes estar inscrito para votar'], 403); return;
    }
    $res = $this->sb->restUpsert('webinar_votes', [
      'webinar_id'=>$id, 'attendee_id'=>$uid, 'liked'=>$like
    ], 'webinar_id,attendee_id', false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo votar'], 400);
  }

  public function myWebinarVotes(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $r = $this->sb->restSelect('webinar_votes', "attendee_id=eq.$uid&select=webinar_id,liked", false);
    $a = $this->decodeOrFail($r);
    $out = array_map(fn($x)=>['webinar_id'=>$x['webinar_id'] ?? null, 'liked'=> (bool)($x['liked'] ?? false)], is_array($a)?$a:[]);
    Http::json($out);
  }

  /* ================= Evaluaciones (charla/curso/webinar) ================= */

  public function getTalkEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $tid = $_GET['talk_id'] ?? '';
    if ($tid==='') { Http::json(['error'=>'talk_id inválido'], 400); return; }

    if (!$this->isRegistered('talk_registrations','talk_id',$tid,$uid)) {
      Http::json(null); return;
    }
    $r = $this->sb->restSelect('talk_evaluations', "talk_id=eq.$tid&attendee_id=eq.$uid", false);
    $a = $this->decodeOrFail($r);
    Http::json($a[0] ?? null);
  }

  public function saveTalkEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $tid = $p['talk_id'] ?? '';
    if ($tid==='') { Http::json(['error'=>'talk_id inválido'], 400); return; }

    if (!$this->isRegistered('talk_registrations','talk_id',$tid,$uid)) {
      Http::json(['error'=>'Debes estar inscrito para evaluar'], 403); return;
    }

    $q1 = max(1, min(5, intval($p['q1'] ?? $p['q1_useful'] ?? 3)));
    $q2 = max(1, min(5, intval($p['q2'] ?? $p['q2_expectations'] ?? 3)));
    $q3 = max(1, min(5, intval($p['q3'] ?? $p['q3_content'] ?? 3)));
    $q4 = max(1, min(5, intval($p['q4'] ?? $p['q4_logistics'] ?? 3)));
    $comments = (string)($p['comments'] ?? '');

    $payload = [
      'talk_id'=>intval($tid,10), 'attendee_id'=>$uid,
      'q1_useful'=>$q1,'q2_expectations'=>$q2,'q3_content'=>$q3,'q4_logistics'=>$q4,
      'comments'=>$comments,'updated_at'=>gmdate('c')
    ];
    $r = $this->sb->restUpsert('talk_evaluations', $payload, 'talk_id,attendee_id', false);
    (($r['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo guardar'], 400);
  }

  public function getCourseEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $cid = $_GET['course_id'] ?? '';
    if ($cid==='') { Http::json(['error'=>'course_id inválido'], 400); return; }

    if (!$this->isRegistered('course_registrations','course_id',$cid,$uid)) {
      Http::json(null); return;
    }
    $r = $this->sb->restSelect('course_evaluations', "course_id=eq.$cid&attendee_id=eq.$uid", false);
    $a = $this->decodeOrFail($r);
    Http::json($a[0] ?? null);
  }

  public function saveCourseEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $cid = $p['course_id'] ?? '';
    if ($cid==='') { Http::json(['error'=>'course_id inválido'], 400); return; }

    if (!$this->isRegistered('course_registrations','course_id',$cid,$uid)) {
      Http::json(['error'=>'Debes estar inscrito para evaluar'], 403); return;
    }

    $q1 = max(1, min(5, intval($p['q1'] ?? $p['q1_useful'] ?? 3)));
    $q2 = max(1, min(5, intval($p['q2'] ?? $p['q2_expectations'] ?? 3)));
    $q3 = max(1, min(5, intval($p['q3'] ?? $p['q3_content'] ?? 3)));
    $q4 = max(1, min(5, intval($p['q4'] ?? $p['q4_logistics'] ?? 3)));
    $comments = (string)($p['comments'] ?? '');

    $payload = [
      'course_id'=>intval($cid,10), 'attendee_id'=>$uid,
      'q1_useful'=>$q1,'q2_expectations'=>$q2,'q3_content'=>$q3,'q4_logistics'=>$q4,
      'comments'=>$comments,'updated_at'=>gmdate('c')
    ];
    $r = $this->sb->restUpsert('course_evaluations', $payload, 'course_id,attendee_id', false);
    (($r['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo guardar'], 400);
  }

  public function getWebinarEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $wid = $_GET['webinar_id'] ?? '';
    if ($wid==='') { Http::json(['error'=>'webinar_id inválido'], 400); return; }

    if (!$this->isRegistered('webinar_registrations','webinar_id',$wid,$uid)) {
      Http::json(null); return;
    }
    $r = $this->sb->restSelect('webinar_evaluations', "webinar_id=eq.$wid&attendee_id=eq.$uid", false);
    $a = $this->decodeOrFail($r);
    Http::json($a[0] ?? null);
  }

  public function saveWebinarEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $wid = $p['webinar_id'] ?? '';
    if ($wid==='') { Http::json(['error'=>'webinar_id inválido'], 400); return; }

    if (!$this->isRegistered('webinar_registrations','webinar_id',$wid,$uid)) {
      Http::json(['error'=>'Debes estar inscrito para evaluar'], 403); return;
    }

    $q1 = max(1, min(5, intval($p['q1'] ?? $p['q1_useful'] ?? 3)));
    $q2 = max(1, min(5, intval($p['q2'] ?? $p['q2_expectations'] ?? 3)));
    $q3 = max(1, min(5, intval($p['q3'] ?? $p['q3_content'] ?? 3)));
    $q4 = max(1, min(5, intval($p['q4'] ?? $p['q4_logistics'] ?? 3)));
    $comments = (string)($p['comments'] ?? '');

    $payload = [
      'webinar_id'=>intval($wid,10), 'attendee_id'=>$uid,
      'q1_useful'=>$q1,'q2_expectations'=>$q2,'q3_content'=>$q3,'q4_logistics'=>$q4,
      'comments'=>$comments,'updated_at'=>gmdate('c')
    ];
    $r = $this->sb->restUpsert('webinar_evaluations', $payload, 'webinar_id,attendee_id', false);
    (($r['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo guardar'], 400);
  }
}
