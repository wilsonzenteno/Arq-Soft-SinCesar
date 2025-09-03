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

  private function isRegistered(string $table, string $idField, $id, string $uid): bool {
    $q = $idField . "=eq.$id&attendee_id=eq.$uid";
    $r = $this->sb->restSelect($table, $q, false);
    $a = $this->decodeOrFail($r);
    return is_array($a) && count($a) > 0;
  }

  /** ================= LISTAS PÚBLICAS ================= */

  public function listConferences(): void {
    $res = $this->sb->restSelect('conferences', '', false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(function($c){
      $title = $c['title'] ?? $c['name'] ?? '(sin título)';
      $location = $c['location'] ?? $c['city'] ?? null;
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

  /** ================= ACCIONES EXISTENTES ================= */

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

  public function voteTalk(): void { // (existente)
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

  public function myVotes(): void { // votos de charlas (existente)
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
        $r2 = $this->sb->restSelect('talks', "id=eq.$tid", false);
        $t  = $this->decodeOrFail($r2);
        $t0 = $t[0] ?? null;
        if ($t0 && ($t0['conference_id'] ?? '') === $cid) $out[] = ['talk_id'=>$tid];
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

  /** ================= NUEVO: REGISTRO charlas/cursos/webinars ================= */

  public function registerToTalk(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $tid = $p['talk_id'] ?? '';
    if ($tid==='') { Http::json(['error'=>'talk_id inválido'], 400); return; }
    $res = $this->sb->restUpsert('talk_registrations', [
      'talk_id'=>$tid, 'attendee_id'=>$uid
    ], 'talk_id,attendee_id', false);
    (($res['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo registrar'], 400);
  }

  public function myTalkRegistrations(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $res = $this->sb->restSelect('talk_registrations', "attendee_id=eq.$uid", false);
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
    $res = $this->sb->restSelect('course_registrations', "attendee_id=eq.$uid", false);
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
    $res = $this->sb->restSelect('webinar_registrations', "attendee_id=eq.$uid", false);
    $arr = $this->decodeOrFail($res);
    $out = array_map(fn($r)=>['webinar_id'=>$r['webinar_id'] ?? null], is_array($arr)?$arr:[]);
    Http::json($out);
  }

  /** ================= NUEVO: VOTOS cursos/webinars (solo inscritos) ================= */

  public function courseVote(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $id = $p['course_id'] ?? '';
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
    $r = $this->sb->restSelect('course_votes', "attendee_id=eq.$uid", false);
    $a = $this->decodeOrFail($r);
    $out = array_map(fn($x)=>['course_id'=>$x['course_id'] ?? null, 'liked'=> (bool)($x['liked'] ?? false)], is_array($a)?$a:[]);
    Http::json($out);
  }

  public function webinarVote(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $id = $p['webinar_id'] ?? '';
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
    $r = $this->sb->restSelect('webinar_votes', "attendee_id=eq.$uid", false);
    $a = $this->decodeOrFail($r);
    $out = array_map(fn($x)=>['webinar_id'=>$x['webinar_id'] ?? null, 'liked'=> (bool)($x['liked'] ?? false)], is_array($a)?$a:[]);
    Http::json($out);
  }

  /** ================= NUEVO: EVALUACIONES cursos/webinars (solo inscritos) ================= */

  public function getCourseEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $id = $_GET['course_id'] ?? '';
    if ($id==='') { Http::json(['error'=>'course_id inválido'], 400); return; }
    if (!$this->isRegistered('course_registrations','course_id',$id,$uid)) {
      Http::json(null); return; // No inscrito => no hay evaluación propia
    }
    $r = $this->sb->restSelect('course_evaluations', "course_id=eq.$id&attendee_id=eq.$uid", false);
    $a = $this->decodeOrFail($r);
    Http::json($a[0] ?? null);
  }

  public function saveCourseEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $id = $p['course_id'] ?? '';
    if ($id==='') { Http::json(['error'=>'course_id inválido'], 400); return; }
    if (!$this->isRegistered('course_registrations','course_id',$id,$uid)) {
      Http::json(['error'=>'Debes estar inscrito para evaluar'], 403); return;
    }
    $q1 = max(1, min(5, intval($p['q1'] ?? $p['q1_useful'] ?? 3)));
    $q2 = max(1, min(5, intval($p['q2'] ?? $p['q2_expectations'] ?? 3)));
    $q3 = max(1, min(5, intval($p['q3'] ?? $p['q3_content'] ?? 3)));
    $q4 = max(1, min(5, intval($p['q4'] ?? $p['q4_logistics'] ?? 3)));
    $comments = (string)($p['comments'] ?? '');
    $payload = [
      'course_id'=>$id, 'attendee_id'=>$uid,
      'q1_useful'=>$q1,'q2_expectations'=>$q2,'q3_content'=>$q3,'q4_logistics'=>$q4,
      'comments'=>$comments,'updated_at'=>gmdate('c')
    ];
    $r = $this->sb->restUpsert('course_evaluations', $payload, 'course_id,attendee_id', false);
    (($r['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo guardar'], 400);
  }

  public function getWebinarEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $id = $_GET['webinar_id'] ?? '';
    if ($id==='') { Http::json(['error'=>'webinar_id inválido'], 400); return; }
    if (!$this->isRegistered('webinar_registrations','webinar_id',$id,$uid)) {
      Http::json(null); return;
    }
    $r = $this->sb->restSelect('webinar_evaluations', "webinar_id=eq.$id&attendee_id=eq.$uid", false);
    $a = $this->decodeOrFail($r);
    Http::json($a[0] ?? null);
  }

  public function saveWebinarEvaluation(): void {
    $uid = $this->uid();
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $p = Http::jsonInput();
    $id = $p['webinar_id'] ?? '';
    if ($id==='') { Http::json(['error'=>'webinar_id inválido'], 400); return; }
    if (!$this->isRegistered('webinar_registrations','webinar_id',$id,$uid)) {
      Http::json(['error'=>'Debes estar inscrito para evaluar'], 403); return;
    }
    $q1 = max(1, min(5, intval($p['q1'] ?? $p['q1_useful'] ?? 3)));
    $q2 = max(1, min(5, intval($p['q2'] ?? $p['q2_expectations'] ?? 3)));
    $q3 = max(1, min(5, intval($p['q3'] ?? $p['q3_content'] ?? 3)));
    $q4 = max(1, min(5, intval($p['q4'] ?? $p['q4_logistics'] ?? 3)));
    $comments = (string)($p['comments'] ?? '');
    $payload = [
      'webinar_id'=>$id, 'attendee_id'=>$uid,
      'q1_useful'=>$q1,'q2_expectations'=>$q2,'q3_content'=>$q3,'q4_logistics'=>$q4,
      'comments'=>$comments,'updated_at'=>gmdate('c')
    ];
    $r = $this->sb->restUpsert('webinar_evaluations', $payload, 'webinar_id,attendee_id', false);
    (($r['status'] ?? 500) < 300) ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo guardar'], 400);
  }
}
