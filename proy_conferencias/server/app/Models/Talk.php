<?php
namespace App\Models;

class Talk {
  public static function create(Supabase $sb, int $confId, ?int $roomId, string $title, string $start, string $end, ?string $speakerId): bool {
    $payload = [
      'conference_id'=>$confId, 'room_id'=>$roomId, 'title'=>$title,
      'starts_at'=>$start, 'ends_at'=>$end, 'speaker_id'=>$speakerId
    ];
    $res = $sb->restInsert('talks', $payload);
    return ($res['status'] ?? 500) < 300;
  }
  public static function update(Supabase $sb, int $id, array $fields): bool {
    if ($id<=0) return false;
    $payload = array_filter($fields, fn($v)=>$v!==null);
    if (!$payload) return false;
    $res = $sb->restUpdate('talks', $payload, "id=eq.$id");
    return ($res['status'] ?? 500) < 300;
  }
  public static function delete(Supabase $sb, int $id): bool {
    if ($id<=0) return false;
    $res = $sb->restDelete('talks', "id=eq.$id");
    return ($res['status'] ?? 500) < 300;
  }
  public static function listByConferenceAdmin(Supabase $sb, int $confId): array {
    // Service Role para listados de admin
    $res = $sb->restSelect('talks', "conference_id=eq.$confId&select=id,title,starts_at,ends_at,room_id,speaker_id&order=starts_at.asc", false);
    return json_decode($res['body'] ?? "[]", true) ?? [];
  }
  
}
