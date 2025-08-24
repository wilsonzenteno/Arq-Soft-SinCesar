<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;
use App\Models\Conference;
use App\Models\Room;
use App\Models\Talk;
use App\Models\Announcement;

/** (CONTROLADOR) — Acciones de staff/admin -> MODELOS */
class StaffController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

  private function requireStaff(): bool {
    $uid = $this->sb->userIdFromJwt(Http::bearer());
    $role = $uid ? $this->sb->getUserRole($uid) : null;
    return in_array($role, ['admin','staff'], true);
  }

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
}
