<?php
namespace App\Models;

class Event {
  public static function listAll(Supabase $sb, ?string $type=null): array {
    $sel = "select=id,type,title,description,starts_at,ends_at,modality,venue,stream_url,brand_name,brand_color,brand_logo_url,created_by&order=starts_at.asc";
    $q = $type ? "type=eq.$type&$sel" : $sel;
    $r = $sb->restSelect('events', $q, false); // service role para admin vistas; si quieres público -> true
    return json_decode($r['body'] ?? "[]", true) ?? [];
  }

  public static function listMine(Supabase $sb, string $uid, ?string $type=null): array {
    $q = "created_by=eq.$uid";
    if ($type) $q .= "&type=eq.$type";
    $q .= "&select=id,type,title,description,starts_at,ends_at,modality,venue,stream_url,brand_name,brand_color,brand_logo_url,created_by&order=starts_at.desc";
    $r = $sb->restSelect('events', $q, false);
    return json_decode($r['body'] ?? "[]", true) ?? [];
  }

  public static function create(Supabase $sb, string $uid, array $payload): array {
    $p = [
      'type'        => $payload['type'] ?? null,
      'title'       => $payload['title'] ?? null,
      'description' => $payload['description'] ?? null,
      'starts_at'   => $payload['starts_at'] ?? null,
      'ends_at'     => $payload['ends_at'] ?? null,
      'modality'    => $payload['modality'] ?? null,
      'venue'       => $payload['venue'] ?? null,
      'stream_url'  => $payload['stream_url'] ?? null,
      'brand_name'  => $payload['brand_name'] ?? null,
      'brand_color' => $payload['brand_color'] ?? null,
      'brand_logo_url' => $payload['brand_logo_url'] ?? null,
      'created_by'  => $uid
    ];
    if (!$p['type'] || !$p['title']) return ['status'=>400,'body'=>json_encode(['error'=>'type y title son obligatorios'])];
    // si no mandan ends_at y sí starts_at -> +1h
    if (!$p['ends_at'] && !empty($p['starts_at'])) {
      $t = strtotime($p['starts_at']); if ($t) $p['ends_at'] = gmdate('c', $t + 3600);
    }
    $r = $sb->restInsert('events', $p);
    $arr = json_decode($r['body'] ?? "[]", true) ?: [];
    return ['status'=>$r['status'] ?? 500, 'data'=>$arr[0] ?? null, 'raw'=>$r];
  }

  public static function update(Supabase $sb, int $id, string $uid, array $fields): bool {
    if ($id<=0) return false;
    $payload = array_filter([
      'title'=>$fields['title'] ?? null,
      'description'=>$fields['description'] ?? null,
      'starts_at'=>$fields['starts_at'] ?? null,
      'ends_at'=>$fields['ends_at'] ?? null,
      'modality'=>$fields['modality'] ?? null,
      'venue'=>$fields['venue'] ?? null,
      'stream_url'=>$fields['stream_url'] ?? null,
      'brand_name'=>$fields['brand_name'] ?? null,
      'brand_color'=>$fields['brand_color'] ?? null,
      'brand_logo_url'=>$fields['brand_logo_url'] ?? null,
      'type'=>$fields['type'] ?? null
    ], fn($v)=>$v!==null);
    if (!$payload) return false;
    $r = $sb->restUpdate('events', $payload, "id=eq.$id");
    return (($r['status'] ?? 500) < 300);
  }

  public static function delete(Supabase $sb, int $id, string $uid): bool {
    if ($id<=0) return false;
    $r = $sb->restDelete('events', "id=eq.$id");
    return (($r['status'] ?? 500) < 300);
  }

  /* ===== Inscripciones / votos / evaluación ===== */
  public static function register(Supabase $sb, int $eventId, string $uid): bool {
    $r = $sb->restUpsert('event_registrations', ['event_id'=>$eventId, 'attendee_id'=>$uid], 'event_id,attendee_id');
    return (($r['status'] ?? 500) < 300);
  }
  public static function myRegistrations(Supabase $sb, string $uid): array {
    $r = $sb->restSelect('event_registrations', "attendee_id=eq.$uid&select=event_id,registered_at&order=registered_at.desc", false);
    return json_decode($r['body'] ?? "[]", true) ?? [];
  }

  public static function vote(Supabase $sb, int $eventId, string $uid, ?bool $liked, ?int $score): bool {
    $payload = ['event_id'=>$eventId, 'attendee_id'=>$uid];
    if ($liked !== null) $payload['liked'] = $liked;
    if ($score !== null) $payload['score'] = $score;
    $r = $sb->restUpsert('event_votes', $payload, 'event_id,attendee_id');
    return (($r['status'] ?? 500) < 300);
  }
  public static function myVotes(Supabase $sb, string $uid): array {
    $r = $sb->restSelect('event_votes', "attendee_id=eq.$uid&select=event_id,liked,score,created_at&order=created_at.desc", false);
    return json_decode($r['body'] ?? "[]", true) ?? [];
  }

  public static function getEvaluation(Supabase $sb, int $eventId, string $uid): ?array {
    $r = $sb->restSelect('event_evaluations', "event_id=eq.$eventId&attendee_id=eq.$uid&select=event_id,attendee_id,q1_useful,q2_expectations,q3_content,q4_logistics,comments", false);
    $arr = json_decode($r['body'] ?? "[]", true) ?? [];
    return $arr[0] ?? null;
  }
  public static function saveEvaluation(Supabase $sb, int $eventId, string $uid, array $p): bool {
    $payload = [
      'event_id'=>$eventId, 'attendee_id'=>$uid,
      'q1_useful'=>intval($p['q1'] ?? 3), 'q2_expectations'=>intval($p['q2'] ?? 3),
      'q3_content'=>intval($p['q3'] ?? 3), 'q4_logistics'=>intval($p['q4'] ?? 3),
      'comments'=>$p['comments'] ?? null
    ];
    $r = $sb->restUpsert('event_evaluations', $payload, 'event_id,attendee_id');
    return (($r['status'] ?? 500) < 300);
  }

  public static function userCanDownloadSlides(Supabase $sb, int $eventId, string $uid): bool {
    $role = $sb->getUserRole($uid);
    if (in_array($role, ['admin','staff'], true)) return true;
    // owner?
    $r = $sb->restSelect('events', "id=eq.$eventId&select=created_by", false);
    $arr = json_decode($r['body'] ?? "[]", true) ?? [];
    if (!empty($arr[0]['created_by']) && $arr[0]['created_by'] === $uid) return true;
    // registrado?
    $r2 = $sb->restSelect('event_registrations', "event_id=eq.$eventId&attendee_id=eq.$uid&select=event_id", false);
    $a2 = json_decode($r2['body'] ?? "[]", true) ?? [];
    return !empty($a2);
  }
}
