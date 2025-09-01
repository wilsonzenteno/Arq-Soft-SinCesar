<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;
use App\Models\Conference;
use App\Models\Room;
use App\Models\Talk;
use App\Models\Announcement;

class StaffController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

  private function requireStaff(): bool {
    $uid = $this->sb->userIdFromJwt(Http::bearer());
    $role = $uid ? $this->sb->getUserRole($uid) : null;
    return in_array($role, ['admin','staff'], true);
  }

  /** CREATE */
  public function createConference(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Conference::create($this->sb, $p['name']??'', $p['city']??'', $p['starts_at']??'', $p['ends_at']??'');
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }
  public function createRoom(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Room::create($this->sb, intval($p['conference_id']??0), $p['name']??'');
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }
  public function createTalk(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Talk::create($this->sb,
      intval($p['conference_id']??0),
      isset($p['room_id']) ? (is_null($p['room_id']) ? null : intval($p['room_id'])) : null,
      (string)($p['title']??''),
      (string)($p['starts_at']??''),
      (string)($p['ends_at']??''),
      isset($p['speaker_id']) && $p['speaker_id']!=='' ? (string)$p['speaker_id'] : null
    );
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }
  public function createAnnouncement(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Announcement::create($this->sb, (string)($p['title']??''), (string)($p['body']??''), isset($p['conference_id']) ? intval($p['conference_id']) : null);
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /** READ helpers */
  public function roomsByConference(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $confId = intval($_GET['conference_id'] ?? 0);
    if ($confId<=0) { Http::json(['error'=>'conference_id inválido'], 400); return; }
    Http::json(Room::listByConference($this->sb, $confId));
  }
  public function listTalks(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $confId = intval($_GET['conference_id'] ?? 0);
    if ($confId<=0) { Http::json(['error'=>'conference_id inválido'], 400); return; }
    Http::json(Talk::listByConferenceAdmin($this->sb, $confId));
  }
  public function listAnnouncements(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $confId = isset($_GET['conference_id']) && $_GET['conference_id']!=='' ? intval($_GET['conference_id']) : null;
    Http::json(Announcement::listAdmin($this->sb, $confId));
  }
  public function searchUsersByEmail(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $email = trim((string)($_GET['email'] ?? ''));
    Http::json($this->sb->authAdminSearchUsers($email));
  }

  /** UPDATE */
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
    $ok = Room::update($this->sb, intval($p['id']??0), ['name'=>$p['name']??null]);
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
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
  public function updateAnnouncement(): void {
    if (!$this->requireStaff()) { Http::json(['error'=>'No autorizado'], 403); return; }
    $p = Http::jsonInput();
    $ok = Announcement::update($this->sb, intval($p['id']??0), [
      'title'=>$p['title']??null, 'body'=>$p['body']??null,
      'conference_id'=> isset($p['conference_id']) && $p['conference_id']!=='' ? intval($p['conference_id']) : null
    ]);
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  /** DELETE */
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
    $p = Http::jsonInput();
    $ok = Announcement::delete($this->sb, intval($p['id']??0));
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }
}
