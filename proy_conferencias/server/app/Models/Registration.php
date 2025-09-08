<?php
namespace App\Models;

class Registration {
  public static function upsert(Supabase $sb, string $uid, int $confId): bool {
    $res = $sb->restUpsert('registrations', ['attendee_id'=>$uid, 'conference_id'=>$confId], 'attendee_id,conference_id');
    return ($res['status'] ?? 500) < 300;
  }
}
