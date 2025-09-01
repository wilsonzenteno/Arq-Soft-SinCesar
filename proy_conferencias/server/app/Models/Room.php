<?php
namespace App\Models;

class Room {
  public static function create(Supabase $sb, int $conferenceId, string $name): bool {
    $res = $sb->restInsert('rooms', ['conference_id'=>$conferenceId, 'name'=>$name]);
    return ($res['status'] ?? 500) < 300;
  }
  public static function listByConference(Supabase $sb, int $confId): array {
    // Service Role para listados de admin
    $res = $sb->restSelect('rooms', "conference_id=eq.$confId&select=id,name&order=name.asc", false);
    return json_decode($res['body'] ?? "[]", true) ?? [];
  }
}
