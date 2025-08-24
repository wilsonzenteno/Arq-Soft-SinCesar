<?php
namespace App\Models;

/** (MODELO) — Acceso a datos de conferencias */
class Conference {
  public static function listAll(Supabase $sb): array {
    $res = $sb->restSelect('conferences', 'select=id,name,city,starts_at,ends_at,brand_primary&order=starts_at.asc');
    return json_decode($res['body'] ?? "[]", true) ?? [];
  }
  public static function create(Supabase $sb, string $name, string $city, string $start, string $end): bool {
    $res = $sb->restInsert('conferences', [
      'name'=>$name, 'city'=>$city, 'starts_at'=>$start, 'ends_at'=>$end
    ]);
    return ($res['status'] ?? 500) < 300;
  }
}
