<?php
namespace App\Models;

/** (MODELO) — Anuncios */
class Announcement {
  public static function create(Supabase $sb, string $title, string $body, ?int $confId): bool {
    $res = $sb->restInsert('announcements', [
      'title'=>$title, 'body'=>$body, 'conference_id'=>$confId
    ]);
    return ($res['status'] ?? 500) < 300;
  }
}
