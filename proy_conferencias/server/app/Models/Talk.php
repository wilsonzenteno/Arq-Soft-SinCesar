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
    $res = $sb->restSelect('talks', "conference_id=eq.$confId&select=id,title,starts_at,ends_at,room_id,speaker_id&order=starts_at.asc", false);
    return json_decode($res['body'] ?? "[]", true) ?? [];
  }

  /** Autorización para ver slides de una charla */
  public static function userRegisteredForTalkConference(Supabase $sb, int $talkId, string $uid): bool {
    // 1) Datos mínimos de la charla
    $r1 = $sb->restSelect('talks', "id=eq.$talkId&select=conference_id,speaker_id", false);
    $arr = json_decode($r1['body'] ?? "[]", true) ?? [];
    $t = $arr[0] ?? null;
    if (!$t) return false;

    // Admin/Staff siempre permitidos
    $role = $sb->getUserRole($uid);
    if (in_array($role, ['admin','staff'], true)) return true;

    // El speaker de la charla también
    if (!empty($t['speaker_id']) && $t['speaker_id'] === $uid) return true;

    // 2) ¿El usuario está inscrito a la conferencia?
    $cid = $t['conference_id'] ?? null;
    if (!$cid) return false;

    $r2 = $sb->restSelect('registrations', "conference_id=eq.$cid&attendee_id=eq.$uid&select=attendee_id", false);
    $a2 = json_decode($r2['body'] ?? "[]", true) ?? [];
    return !empty($a2);
  }
}
