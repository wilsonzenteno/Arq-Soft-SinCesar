<?php
namespace App\Models;

class Conference {
  public static function create(Supabase $sb, string $name, string $city, string $starts, string $ends): bool {
    $res = $sb->restInsert('conferences', [
      'name'=>$name, 'city'=>$city, 'starts_at'=>$starts, 'ends_at'=>$ends
    ]);
    return ($res['status'] ?? 500) < 300;
  }

  public static function update(Supabase $sb, int $id, array $fields): bool {
    if ($id<=0) return false;
    $payload = array_filter($fields, fn($v)=>$v!==null);
    if (!$payload) return false;
    $res = $sb->restUpdate('conferences', $payload, "id=eq.$id");
    return ($res['status'] ?? 500) < 300;
  }

  public static function delete(Supabase $sb, int $id): bool {
    if ($id<=0) return false;
    $res = $sb->restDelete('conferences', "id=eq.$id");
    return ($res['status'] ?? 500) < 300;
  }
}
