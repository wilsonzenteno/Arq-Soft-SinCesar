<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;

class AttendeeController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

  private function uid(): ?string {
    $jwt = \App\Core\Http::bearer();
    return $this->sb->userIdFromJwt($jwt);
  }

  /** Helper: decodifica o lanza error HTTP */
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

  /** ========== LISTAS PÚBLICAS ========== */

  // Lista de conferencias (robusto a ambos esquemas)
  public function listConferences(): void {
    // sin select para evitar 406 por columnas inexistentes
    $res = $this->sb->restSelect('conferences', '', false);
    $arr = $this->decodeOrFail($res);

    // normaliza
    $out = array_map(function($c){
      $title = $c['title'] ?? $c['name'] ?? '(sin título)';
      $location = $c['location'] ?? $c['city'] ?? null;

      // fechas posibles
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

    // ordena por (date || starts_at) desc
    usort($out, function($a,$b){
      $ta = strtotime($a['date'] ?? $a['starts_at'] ?? '1970-01-01');
      $tb = strtotime($b['date'] ?? $b['starts_at'] ?? '1970-01-01');
      return $tb <=> $ta;
    });

    Http::json($out);
  }

  // Charlas por conferencia (robusto)
  public function listTalksByConference(): void {
    $cid = $_GET['conference_id'] ?? '';
    if ($cid === '') { Http::json(['error'=>'conference_id inválido'], 400); return; }

    $q   = "conference_id=eq.$cid"; // sin joins para evitar 406 si no hay FK/rooms
    $res = $this->sb->restSelect('talks', $q, false);
    $arr = $this->decodeOrFail($res);

    $out = array_map(function($t){
      return [
        'id'        => $t['id'] ?? null,
        'title'     => $t['title'] ?? '(sin título)',
        'starts_at' => $t['start_time'] ?? ($t['starts_at'] ?? null),
        'ends_at'   => $t['end_time']   ?? ($t['ends_at']   ?? null),
        // si tu esquema viejo tenía "room_id" y haces join en otro lado, aquí deja '—'
        'room_name' => $t['room_name'] ?? null
      ];
    }, is_array($arr) ? $arr : []);

    // orden por inicio asc (si existe)
    usort($out, function($a,$b){
      $ta = strtotime($a['starts_at'] ?? '1970-01-01');
      $tb = strtotime($b['starts_at'] ?? '1970-01-01');
      return $ta <=> $tb;
    });

    Http::json($out);
  }

  // ====== Explorar todo ======
  public function listAllTalks(): void {
    $res = $this->sb->restSelect('talks', '', false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(function($t){
      return [
        'id'        => $t['id'] ?? null,
        'title'     => $t['title'] ?? '(sin título)',
        'starts_at' => $t['start_time'] ?? ($t['starts_at'] ?? null),
        'ends_at'   => $t['end_time']   ?? ($t['ends_at']   ?? null),
        'conference_id' => $t['conference_id'] ?? null
      ];
    }, is_array($arr) ? $arr : []);
    usort($out, function($a,$b){
      $ta = strtotime($a['starts_at'] ?? '1970-01-01');
      $tb = strtotime($b['starts_at'] ?? '1970-01-01');
      return $tb <=> $ta;
    });
    Http::json($out);
  }

  public function listAllCourses(): void {
    $res = $this->sb->restSelect('courses', '', false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(function($w){
      return [
        'id'        => $w['id'] ?? null,
        'title'     => $w['title'] ?? '(sin título)',
        'description'=> $w['description'] ?? null,
        'modality'  => $w['modality'] ?? null,
        'venue'     => $w['venue'] ?? null,
        'stream_url'=> $w['stream_url'] ?? null,
        'starts_at' => $w['starts_at'] ?? null,
        'ends_at'   => $w['ends_at']   ?? null
      ];
    }, is_array($arr) ? $arr : []);
    usort($out, fn($a,$b)=> (strtotime($b['starts_at'] ?? '1970-01-01')) <=> (strtotime($a['starts_at'] ?? '1970-01-01')));
    Http::json($out);
  }

  public function listAllWebinars(): void {
    $res = $this->sb->restSelect('webinars', '', false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(function($w){
      return [
        'id'        => $w['id'] ?? null,
        'title'     => $w['title'] ?? '(sin título)',
        'description'=> $w['description'] ?? null,
        'modality'  => $w['modality'] ?? null,
        'venue'     => $w['venue'] ?? null,
        'stream_url'=> $w['stream_url'] ?? null,
        'starts_at' => $w['starts_at'] ?? null,
        'ends_at'   => $w['ends_at']   ?? null
      ];
    }, is_array($arr) ? $arr : []);
    usort($out, fn($a,$b)=> (strtotime($b['starts_at'] ?? '1970-01-01')) <=> (strtotime($a['starts_at'] ?? '1970-01-01')));
    Http::json($out);
  }

  /** ========== ACCIONES CON USUARIO ========== */

  public function registerToConference(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $cid = $p['conference_id'] ?? '';
    if ($cid==='') { Http::json(['error'=>'conference_id inválido'], 400); return; }

    $res = $this->sb->restUpsert('registrations', [
      'conference_id'=>$cid, 'attendee_id'=>$uid
    ], 'conference_id,attendee_id');
    if (($res['status'] ?? 500) >= 300) { Http::json(['error'=>'No se pudo registrar'], 400); return; }
    Http::json(['ok'=>true]);
  }

  public function voteTalk(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $talkId = $p['talk_id'] ?? '';
    $score  = intval($p['score'] ?? 1);
    if ($talkId==='') { Http::json(['error'=>'talk_id inválido'], 400); return; }
    if ($score<1 || $score>5) $score = 1;

    $res = $this->sb->restUpsert('votes', [
      'talk_id'=>$talkId, 'attendee_id'=>$uid, 'score'=>$score
    ], 'talk_id,attendee_id');
    if (($res['status'] ?? 500) >= 300) { Http::json(['error'=>'No se pudo votar'], 400); return; }
    Http::json(['ok'=>true]);
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

    // filtramos por conference_id en PHP para evitar joins 406
    $res = $this->sb->restSelect('votes', "attendee_id=eq.$uid", false);
    $votes = $this->decodeOrFail($res);
    $out = [];
    if (is_array($votes)) {
      foreach ($votes as $v) {
        $tid = $v['talk_id'] ?? null;
        if (!$tid) continue;
        // obtenemos talk minimal para ver su conference_id
        $r2 = $this->sb->restSelect('talks', "id=eq.$tid", false);
        $t  = $this->decodeOrFail($r2);
        $t0 = $t[0] ?? null;
        if ($t0 && ($t0['conference_id'] ?? '') === $cid) $out[] = ['talk_id'=>$tid];
      }
    }
    Http::json($out);
  }

  /** Likes conferencia */
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

  /** Evaluación conferencia (4 preguntas) */
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
}
