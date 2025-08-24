<?php
namespace App\Models;

/** (MODELO) — Votos */
class Vote {
  public static function upsert(Supabase $sb, int $talkId, string $uid): bool {
    $res = $sb->restUpsert('votes', ['talk_id'=>$talkId, 'attendee_id'=>$uid], 'talk_id,attendee_id');
    return ($res['status'] ?? 500) < 300;
  }
}
