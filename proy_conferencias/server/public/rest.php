<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors','1');

require __DIR__ . '/../public/index.bootstrap.php';

use App\Models\Supabase;

header('Content-Type: application/json; charset=utf-8');

$type = $_GET['type'] ?? '';
$id   = $_GET['id'] ?? '';

$sb = new Supabase();

function out($arr){ echo json_encode($arr, JSON_UNESCAPED_UNICODE); exit; }
function fail($code,$msg){ http_response_code($code); out(['error'=>$msg]); }

if (!$type || !$id) fail(400,'bad request');

$ok = function(array $res){
  $st = intval($res['status'] ?? 500);
  $j  = json_decode($res['body'] ?? '[]', true);
  if ($st >= 300) fail(500, is_array($j)&&isset($j['message'])?$j['message']:'Supabase error');
  return is_array($j)?$j:[];
};

switch ($type) {
  case 'talk': {
    // 1) talk
    $rT = $sb->restSelect('talks', "id=eq.$id", false);
    $aT = $ok($rT);
    if (!$aT) fail(404,'not found');
    $t0 = $aT[0];

    // 2) conference (opcional)
    $conf = null;
    if (!empty($t0['conference_id'])) {
      $rC = $sb->restSelect('conferences', "id=eq.{$t0['conference_id']}", false);
      $aC = $ok($rC);
      $conf = $aC[0] ?? null;
      if ($conf) {
        $conf = [
          'id'    => $conf['id'] ?? null,
          'title' => $conf['title'] ?? ($conf['name'] ?? '(sin título)'),
          'location' => $conf['location'] ?? ($conf['city'] ?? null),
          'date'  => $conf['date'] ?? null
        ];
      }
    }

    $out = [
      'id'        => $t0['id'] ?? null,
      'title'     => $t0['title'] ?? '(sin título)',
      'description'=> $t0['description'] ?? null,
      'starts_at' => $t0['start_time'] ?? ($t0['starts_at'] ?? null),
      'ends_at'   => $t0['end_time']   ?? ($t0['ends_at']   ?? null),
      'room_name' => $t0['room_name'] ?? null,
      'conference'=> $conf
    ];
    out($out);
  }

  case 'course': {
    $r = $sb->restSelect('courses', "id=eq.$id", false);
    $a = $ok($r);
    if (!$a) fail(404,'not found');
    out($a[0]);
  }

  case 'webinar': {
    $r = $sb->restSelect('webinars', "id=eq.$id", false);
    $a = $ok($r);
    if (!$a) fail(404,'not found');
    out($a[0]);
  }

  default: fail(400,'type inválido');
}
