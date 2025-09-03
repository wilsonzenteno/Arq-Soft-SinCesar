<?php
namespace App\Models;

use App\Models\Supabase;

class Resource {

  public static function upsert(Supabase $sb, int $talkId, string $filename, string $mime, string $base64, string $uploaderId): array {
    $payload = [
      'talk_id'        => $talkId,
      'filename'       => $filename,
      'mime_type'      => $mime ?: 'application/pdf',
      'content_base64' => $base64,
      'uploaded_by'    => $uploaderId,
      'path'           => $filename, // compatibilidad con tu esquema original
    ];
    return $sb->restUpsert('resources', $payload, 'talk_id', false);
  }

  public static function getByTalk(Supabase $sb, int $talkId): ?array {
    $r = $sb->restSelect('resources', "talk_id=eq.$talkId&select=talk_id,filename,mime_type,content_base64,uploaded_by", false);
    $arr = json_decode($r['body'] ?? "[]", true) ?? [];
    return $arr[0] ?? null;
  }
}
