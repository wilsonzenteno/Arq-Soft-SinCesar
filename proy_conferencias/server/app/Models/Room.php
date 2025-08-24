<?php
namespace App\Models;

/** (MODELO) — Salas */
class Room {
  public static function create(Supabase $sb, int $conferenceId, string $name): bool {
    $res = $sb->restInsert('rooms', ['conference_id'=>$conferenceId, 'name'=>$name]);
    return ($res['status'] ?? 500) < 300;
  }
}
