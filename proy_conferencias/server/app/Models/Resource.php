<?php
namespace App\Models;

class Resource
{
  /**
   * Obtiene un registro de resources por talk_id.
   * Devuelve array asociativo o null si no existe.
   */
  public static function getByTalk(Supabase $sb, int $talkId): ?array
  {
    $res = $sb->restSelect(
      'resources',
      "talk_id=eq.$talkId&select=talk_id,filename,mime_type,content_base64,path,uploaded_by,created_at",
      false
    );
    if (($res['status'] ?? 500) >= 300) return null;
    $arr = json_decode($res['body'] ?? '[]', true) ?? [];
    return $arr[0] ?? null;
  }

  /**
   * Inserta o actualiza (upsert) el recurso para una charla.
   * La tabla resources tiene PK talk_id; usaremos select -> insert/update.
   * IMPORTANTE: La columna path es NOT NULL en tu esquema: la llenamos.
   */
  public static function upsert(
    Supabase $sb,
    int $talkId,
    string $filename,
    string $mime,
    string $base64,
    string $uploaderId
  ): array {
    // path requerido por NOT NULL (puedes cambiar el formato si quieres)
    $path = "talks/$talkId/" . $filename;

    // ¿existe?
    $existing = self::getByTalk($sb, $talkId);

    $payload = [
      'filename'       => $filename,
      'mime_type'      => $mime,
      'content_base64' => $base64,
      'path'           => $path,
      'uploaded_by'    => $uploaderId
    ];

    if ($existing) {
      // update
      $res = $sb->restUpdate('resources', $payload, "talk_id=eq.$talkId", false);
      if (($res['status'] ?? 500) < 300) return $res;
      return ['status' => $res['status'] ?? 500, 'body' => $res['body'] ?? null];
    } else {
      // insert (incluye talk_id)
      $payload['talk_id'] = $talkId;
      $res = $sb->restInsert('resources', $payload, false);
      if (($res['status'] ?? 500) < 300) return $res;
      return ['status' => $res['status'] ?? 500, 'body' => $res['body'] ?? null];
    }
  }
}
