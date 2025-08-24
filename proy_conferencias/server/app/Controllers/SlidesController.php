<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;
use App\Models\Resource;
use App\Models\Talk;

/** (CONTROLADOR) — Descarga protegida de PDF del MODELO de Storage */
class SlidesController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

  // GET /index.php?route=slides.download&talk_id=123
  public function download(): void {
    $talkId = isset($_GET['talk_id']) ? intval($_GET['talk_id']) : 0;
    if ($talkId<=0) { http_response_code(400); echo "Parámetro inválido"; return; }

    $res = Resource::getByTalk($this->sb, $talkId);
    if (!$res) { http_response_code(404); echo "Sin diapositivas"; return; }

    $uid = $this->sb->userIdFromJwt(Http::bearer());
    if (!$uid) { http_response_code(401); echo "No auth"; return; }
    if (!Talk::userRegisteredForTalkConference($this->sb, $talkId, $uid)) {
      http_response_code(403); echo "No autorizado"; return;
    }

    $ok = $this->sb->storageStream('slides', $res['path']);
    if (!$ok) { http_response_code(502); echo "Error storage"; }
  }
}
