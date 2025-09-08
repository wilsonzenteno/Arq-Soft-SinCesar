<?php
namespace App\Models;

class Announcement {
  public static function listAdmin(Supabase $sb, ?int $conferenceId=null): array {
    $q = "select=id,title,body,conference_id,created_at&order=created_at.desc";
    if ($conferenceId !== null) $q = "conference_id=eq.$conferenceId&" . $q;
    // Service Role para listados de admin
    $res = $sb->restSelect('announcements', $q, false);
    return json_decode($res['body'] ?? "[]", true) ?? [];
  }
}
