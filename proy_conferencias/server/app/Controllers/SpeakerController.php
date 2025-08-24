<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;
use App\Models\Talk;
use App\Models\Resource;

/** (CONTROLADOR) — Acciones de orador -> MODELOS */
class SpeakerController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

  public function myTalks(): void {
    $uid = $this->sb->userIdFromJwt(Http::bearer());
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    Http::json(Talk::listBySpeaker($this->sb, $uid));
  }

  public function claimTalk(): void {
    $uid = $this->sb->userIdFromJwt(Http::bearer());
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $p = Http::jsonInput();
    $talkId = intval($p['talk_id'] ?? 0);
    $ok = Talk::claimIfNull($this->sb, $talkId, $uid);
    $ok ? Http::json(['ok'=>true]) : Http::json(['error'=>'No se pudo'], 400);
  }

  public function uploadSlides(): void {
    $uid = $this->sb->userIdFromJwt($_SERVER['HTTP_AUTHORIZATION'] ?? null);
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $talkId = isset($_POST['talk_id']) ? intval($_POST['talk_id']) : 0;
    if ($talkId<=0 || empty($_FILES['file'])) { Http::json(['error'=>'Datos inválidos'], 400); return; }

    $fileTmp = $_FILES['file']['tmp_name'];
    $fileName = basename($_FILES['file']['name']);
    $path = "talks/$talkId/slides/$fileName";
    $ok1 = $this->sb->storageUpload('slides', $path, file_get_contents($fileTmp), mime_content_type($fileTmp) ?: 'application/pdf');
    $ok2 = Resource::upsert($this->sb, $talkId, $path, $uid);
    ($ok1 && $ok2) ? Http::json(['ok'=>true]) : Http::json(['error'=>'Upload fail'], 400);
  }
}
