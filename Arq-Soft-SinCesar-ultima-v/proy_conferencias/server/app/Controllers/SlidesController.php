<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;
use App\Models\Resource;

class SlidesController
{
  private Supabase $sb;
  public function __construct() { $this->sb = new Supabase(); }

  /** GET /slides.exists&talk_id=123
   *  Devuelve { exists: true/false, filename?, mime_type? }
   */
  public function exists(): void
  {
    $talkId = isset($_GET['talk_id']) ? (int)$_GET['talk_id'] : 0;
    if ($talkId <= 0) { Http::json(['exists' => false, 'error' => 'talk_id inválido'], 400); return; }

    $row = Resource::getByTalk($this->sb, $talkId);
    if (!$row) { Http::json(['exists' => false]); return; }

    Http::json([
      'exists'   => true,
      'filename' => $row['filename'] ?? null,
      'mime_type'=> $row['mime_type'] ?? null,
    ]);
  }

  /** GET /slides.download&talk_id=123
   *  Descarga el archivo guardado en content_base64 (o 404 si no existe).
   */
  public function download(): void {
    $sb = new Supabase();

    // auth
    $jwt = $_SERVER['HTTP_AUTHORIZATION'] ?? ($_COOKIE['jwt'] ?? null);
    $uid = $sb->userIdFromJwt($jwt);
    if (!$uid) { Http::json(['error'=>'No autenticado'], 401); return; }

    // talk_id
    $talkId = isset($_GET['talk_id']) ? (int)$_GET['talk_id'] : 0;
    if ($talkId <= 0) { Http::json(['error'=>'talk_id inválido'], 400); return; }

    // 🔒 debe estar inscrito a la charla
    $reg = $sb->restSelect('talk_registrations', "talk_id=eq.$talkId&attendee_id=eq.$uid&select=talk_id", false);
    $regArr = json_decode($reg['body'] ?? '[]', true) ?? [];
    if (!$regArr) { Http::json(['error'=>'Debes estar inscrito para descargar'], 403); return; }

    // buscar recurso
    $r = $sb->restSelect('resources', "talk_id=eq.$talkId&select=filename,mime_type,content_base64", false);
    $rows = json_decode($r['body'] ?? '[]', true) ?? [];
    $res = $rows[0] ?? null;
    if (!$res || empty($res['content_base64'])) { Http::json(['error'=>'Sin slides'], 404); return; }

    $filename = $res['filename'] ?: ('slides_talk_'.$talkId.'.pdf');
    $mime = $res['mime_type'] ?: 'application/octet-stream';
    $bin = base64_decode($res['content_base64']);

    header('Content-Type: '.$mime);
    header('Content-Disposition: attachment; filename="'.addslashes($filename).'"');
    header('Content-Length: '.strlen($bin));
    echo $bin;
    exit;
  }

  /** POST /speaker.slides.upload  (form-data: talk_id, file)
   *  Sube PDF/PPT y lo guarda en la tabla resources como base64.
   */
  public function upload(): void
  {
    $uid = $this->sb->userIdFromJwt($_SERVER['HTTP_AUTHORIZATION'] ?? null);
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }

    $talkId = isset($_POST['talk_id']) ? (int)$_POST['talk_id'] : 0;
    if ($talkId <= 0 || empty($_FILES['file'])) {
      Http::json(['error'=>'Datos inválidos (talk_id o file)'], 400); return;
    }

    // Valida que la charla exista
    $chk = $this->sb->restSelect('talks', "id=eq.$talkId&select=id", false);
    $exists = (json_decode($chk['body'] ?? '[]', true) ?? []);
    if (!$exists) { Http::json(['error'=>'La charla no existe'], 400); return; }

    // Archivo
    $tmp  = $_FILES['file']['tmp_name'] ?? null;
    $name = $_FILES['file']['name'] ?? null;
    $size = (int)($_FILES['file']['size'] ?? 0);

    if (!$tmp || !is_uploaded_file($tmp)) { Http::json(['error'=>'Subida inválida'], 400); return; }
    if ($size <= 0) { Http::json(['error'=>'Archivo vacío'], 400); return; }

    $content = file_get_contents($tmp);
    if ($content === false || $content === '') { Http::json(['error'=>'No se pudo leer el archivo'], 400); return; }

    // MIME
    $mime = 'application/octet-stream';
    if (function_exists('finfo_open')) {
      $f = finfo_open(FILEINFO_MIME_TYPE);
      if ($f) { $m = finfo_file($f, $tmp); finfo_close($f); if ($m) $mime = $m; }
    }
    $ext = strtolower(pathinfo($name ?? '', PATHINFO_EXTENSION));
    if ($ext === 'pdf') $mime = 'application/pdf';
    if ($ext === 'ppt' || $ext === 'pptx') $mime = 'application/vnd.ms-powerpoint';

    $b64 = base64_encode($content);
    $fileName = basename($name ?: ('slides_talk_'.$talkId.'.pdf'));

    // Guarda/actualiza en resources (con path NOT NULL)
    $resp = Resource::upsert($this->sb, $talkId, $fileName, $mime, $b64, $uid);
    if (($resp['status'] ?? 500) < 300) {
      $rows = json_decode($resp['body'] ?? '[]', true) ?? [];
      Http::json(['ok'=>true, 'talk_id'=>$talkId, 'filename'=>$fileName, 'mime'=>$mime, 'rows'=>$rows]);
      return;
    }

    Http::json(['error'=>'No se pudo guardar en resources','status'=>$resp['status']??null,'body'=>$resp['body']??null], 400);
  }
}
