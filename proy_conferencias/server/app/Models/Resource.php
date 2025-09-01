<?php
namespace App\Models;

class Resource {
  public static function upsert(Supabase $sb, int $talkId, string $path, string $uploadedBy): bool {
    $res = $sb->restUpsert('resources', [
      'talk_id'=>$talkId,'path'=>$path,'uploaded_by'=>$uploadedBy
    ], 'talk_id');
    return ($res['status'] ?? 500) < 300;
  }
  public static function getByTalk(Supabase $sb, int $talkId): ?array {
    $res = $sb->restSelect('resources', "talk_id=eq.$talkId&select=path,talk_id");
    $arr = json_decode($res['body'] ?? "[]", true) ?? [];
    return $arr[0] ?? null;
  }
}
