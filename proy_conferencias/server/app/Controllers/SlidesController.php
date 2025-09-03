<?php
namespace App\Controllers;

use App\Models\Supabase;
use App\Models\Resource;
use App\Models\Talk;

class SlidesController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

  private function jwtFromRequest(): ?string {
    $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if (preg_match('/Bearer\\s+(.*)$/i', $hdr, $m)) return $m[1];
    return $_COOKIE['jwt'] ?? null;
  }

  public function download(): void {
    $talkId = isset($_GET['talk_id']) ? intval($_GET['talk_id']) : 0;
    if ($talkId<=0) { http_response_code(400); echo "Parámetro inválido"; return; }

    $row = Resource::getByTalk($this->sb, $talkId);
    if (!$row) { http_response_code(404); echo "Sin diapositivas"; return; }
    $b64  = $row['content_b64'] ?? '';
    $mime = $row['mime'] ?? 'application/pdf';
    $name = $row['filename'] ?? 'slides.pdf';

    $uid = $this->sb->userIdFromJwt($this->jwtFromRequest());
    if (!$uid) { http_response_code(401); echo "No auth"; return; }

    // Permisos: admin/staff/speaker/inscrito o uploader
    $isAllowed = Talk::userRegisteredForTalkConference($this->sb, $talkId, $uid)
                || (!empty($row['uploaded_by']) && $row['uploaded_by'] === $uid);

    if (!$isAllowed) { http_response_code(403); echo "No autorizado"; return; }

    $bytes = base64_decode($b64, true);
    if ($bytes === false) { http_response_code(500); echo "Contenido corrupto"; return; }

    header("Content-Type: $mime");
    header('Content-Disposition: inline; filename="'.basename($name).'"');
    echo $bytes;
  }

  // opcional: para que el front pregunte si hay slides
  public function exists(): void {
    $talkId = isset($_GET['talk_id']) ? intval($_GET['talk_id']) : 0;
    if ($talkId<=0) { http_response_code(400); echo "Parámetro inválido"; return; }
    $row = Resource::getByTalk($this->sb, $talkId);
    header('Content-Type: application/json');
    echo json_encode(['exists'=> (bool)$row]);
  }
}
