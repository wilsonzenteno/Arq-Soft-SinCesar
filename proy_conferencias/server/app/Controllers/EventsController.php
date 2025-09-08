<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;
use App\Models\Event;

class EventsController {
  private Supabase $sb;
  private ?string $uid;

  public function __construct() {
    $this->sb = new Supabase();
    $this->uid = $this->sb->userIdFromJwt(Http::bearer());
  }

  /* ===== Attendee ===== */
  public function listAll(): void {
    $type = $_GET['type'] ?? null;
    $data = Event::listAll($this->sb, $type);
    Http::json($data);
  }

  public function register(): void {
    if (!$this->uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $j = Http::jsonInput();
    $eid = intval($j['event_id'] ?? 0);
    if ($eid<=0) { Http::json(['error'=>'event_id requerido'], 400); return; }
    $ok = Event::register($this->sb, $eid, $this->uid);
    Http::json(['ok'=>$ok], $ok?200:400);
  }

  public function myRegs(): void {
    if (!$this->uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    Http::json(['data'=>Event::myRegistrations($this->sb, $this->uid)]);
  }

  public function vote(): void {
    if (!$this->uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $j = Http::jsonInput();
    $eid = intval($j['event_id'] ?? 0);
    if ($eid<=0) { Http::json(['error'=>'event_id requerido'], 400); return; }
    $liked = array_key_exists('liked',$j) ? (!!$j['liked']) : null;
    $score = isset($j['score']) ? intval($j['score']) : null;
    $ok = Event::vote($this->sb, $eid, $this->uid, $liked, $score);
    Http::json(['ok'=>$ok], $ok?200:400);
  }

  public function myVotes(): void {
    if (!$this->uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    Http::json(['data'=>Event::myVotes($this->sb, $this->uid)]);
  }

  public function getEval(): void {
    if (!$this->uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $eid = intval($_GET['event_id'] ?? 0);
    if ($eid<=0) { Http::json(null); return; }
    Http::json(Event::getEvaluation($this->sb, $eid, $this->uid));
  }

  public function saveEval(): void {
    if (!$this->uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $j = Http::jsonInput();
    $eid = intval($j['event_id'] ?? 0);
    if ($eid<=0) { Http::json(['error'=>'event_id requerido'], 400); return; }
    $ok = Event::saveEvaluation($this->sb, $eid, $this->uid, $j);
    Http::json(['ok'=>$ok], $ok?200:400);
  }

  /* ===== Speaker ===== */
  public function mine(): void {
    if (!$this->uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $type = $_GET['type'] ?? null;
    Http::json(['data'=>Event::listMine($this->sb, $this->uid, $type)]);
  }

  public function create(): void {
    if (!$this->uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $role = $this->sb->getUserRole($this->uid);
    if (!in_array($role, ['speaker','staff','admin'], true)) { Http::json(['error'=>'No autorizado'], 403); return; }
    $j = Http::jsonInput();
    $r = Event::create($this->sb, $this->uid, $j);
    if (($r['status'] ?? 500) >= 300) { Http::json(['error'=>'No se pudo crear'], 400); return; }
    Http::json(['id'=>$r['data']['id'] ?? null, 'data'=>$r['data'] ?? null], 200);
  }

  public function update(): void {
    if (!$this->uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $j = Http::jsonInput(); $id = intval($j['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id requerido'], 400); return; }
    $ok = Event::update($this->sb, $id, $this->uid, $j);
    Http::json(['ok'=>$ok], $ok?200:400);
  }

  public function delete(): void {
    if (!$this->uid) { Http::json(['error'=>'No autenticado'], 401); return; }
    $j = Http::jsonInput(); $id = intval($j['id'] ?? 0);
    if ($id<=0) { Http::json(['error'=>'id requerido'], 400); return; }
    $ok = Event::delete($this->sb, $id, $this->uid);
    Http::json(['ok'=>$ok], $ok?200:400);
  }
}
