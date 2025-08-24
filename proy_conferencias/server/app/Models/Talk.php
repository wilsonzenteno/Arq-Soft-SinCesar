<?php
namespace App\Models;

/** (MODELO) — Charlas */
class Talk {
  public static function listByConferenceWithRoom(Supabase $sb, int $confId): array {
    $res = $sb->restSelect('talks', "conference_id=eq.$confId&select=id,title,starts_at,ends_at,room:rooms(name)&order=starts_at.asc");
    $list = json_decode($res['body'] ?? "[]", true) ?? [];
    // Aplanar room.name
    return array_map(fn($t)=>[
      'id'=>$t['id'], 'title'=>$t['title'], 'starts_at'=>$t['starts_at'], 'ends_at'=>$t['ends_at'],
      'room_name'=> $t['room']['name'] ?? null
    ], $list);
  }

  public static function listBySpeaker(Supabase $sb, string $speakerId): array {
    $res = $sb->restSelect('talks', "speaker_id=eq.$speakerId&select=id,title,starts_at,ends_at,conference:conferences(name)&order=starts_at.asc");
    $list = json_decode($res['body'] ?? "[]", true) ?? [];
    return array_map(fn($t)=>[
      'id'=>$t['id'], 'title'=>$t['title'], 'starts_at'=>$t['starts_at'], 'ends_at'=>$t['ends_at'],
      'conference_name'=>$t['conference']['name']??null
    ], $list);
  }

  public static function claimIfNull(Supabase $sb, int $talkId, string $uid): bool {
    $res = $sb->restUpdate('talks', ['speaker_id'=>$uid], "id=eq.$talkId&speaker_id=is.null");
    return ($res['status'] ?? 500) < 300;
  }

  public static function userRegisteredForTalkConference(Supabase $sb, int $talkId, string $userId): bool {
    $url = $sb->restSelect('talks', "id=eq.$talkId&select=id,conference_id,reg:registrations!inner(attendee_id)&reg.attendee_id=eq.$userId");
    $arr = json_decode($url['body'] ?? "[]", true) ?? [];
    return !empty($arr);
  }

  public static function create(Supabase $sb, int $confId, ?int $roomId, string $title, string $start, string $end, ?string $speakerId): bool {
    $payload = [
      'conference_id'=>$confId, 'room_id'=>$roomId, 'title'=>$title,
      'starts_at'=>$start, 'ends_at'=>$end, 'speaker_id'=>$speakerId
    ];
    $res = $sb->restInsert('talks', $payload);
    return ($res['status'] ?? 500) < 300;
  }
}
