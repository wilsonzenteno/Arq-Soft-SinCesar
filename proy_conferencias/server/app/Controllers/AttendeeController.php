<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Conference;
use App\Models\Talk;
use App\Models\Registration;
use App\Models\Vote;
use App\Models\Supabase;

/** (CONTROLADOR) — Casos de uso de asistentes, llama a MODELOS */
class AttendeeController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

  public function listConferences(): void {
    Http::json(Conference::listAll($this->sb));
  }

  public function listTalksByConference(): void {
    $confId = intval($_GET['conference_id'] ?? 0);
    Http::json(Talk::listByConferenceWithRoom($this->sb, $confId));
  }

  public function registerToConference(): void {
    $jwt = Http::bearer();
    $uid = $this->sb->userIdFromJwt($jwt);
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $confId = intval($p['conference_id'] ?? 0);
    $ok = Registration::upsert($this->sb, $uid, $confId);
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }

  public function voteTalk(): void {
    $jwt = Http::bearer();
    $uid = $this->sb->userIdFromJwt($jwt);
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $talkId = intval($p['talk_id'] ?? 0);
    $ok = Vote::upsert($this->sb, $talkId, $uid);
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'Fail'], 400);
  }
}
