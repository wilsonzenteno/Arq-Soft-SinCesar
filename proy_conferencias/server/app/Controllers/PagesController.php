<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\Supabase;

class PagesController {
  private Supabase $sb;

  public function __construct(){
    $this->sb = new Supabase();
  }

  /**
   * Requiere que el usuario tenga un rol permitido.
   * Usa el JWT de la cookie (sesión de tu backend) para no romper tus páginas actuales.
   */
  private function requireRole(array $allowed): void {
    $jwt = $_COOKIE['jwt'] ?? null;
    if (!$jwt) { header('Location: /index.php?route=/'); exit; }

    $uid  = $this->sb->userIdFromJwt($jwt);
    $role = $uid ? $this->sb->getUserRole($uid) : null;

    $isAllowed = ($role === 'admin') || in_array((string)$role, $allowed, true);
    if (!$uid || !$isAllowed) {
      http_response_code(403);
      View::render('errors/403', ['title'=>'Acceso denegado']);
      exit;
    }
  }

  /* ==================== VISTAS PRIVADAS ==================== */
  public function home(): void     { View::render('pages/home',     ['title'=>'Inicio']); }
  public function speaker(): void  { $this->requireRole(['speaker']);  View::render('pages/speaker',  ['title'=>'Oradores']); }
  public function attendee(): void { $this->requireRole(['attendee','speaker']); View::render('pages/attendee', ['title'=>'Asistentes']); }
  public function staff(): void    { $this->requireRole(['staff']);    View::render('pages/staff',    ['title'=>'Staff']); }

  /* ==================== ADMIN NUEVAS ==================== */
  public function staffConfs(): void { $this->requireRole(['staff']); View::render('pages/staff/conferences', ['title'=>'Admin • Conferencias']); }
  public function staffRooms(): void { $this->requireRole(['staff']); View::render('pages/staff/rooms',       ['title'=>'Admin • Salas']); }
  public function staffTalks(): void { $this->requireRole(['staff']); View::render('pages/staff/talks',       ['title'=>'Admin • Charlas']); }
  public function staffAnns(): void  { $this->requireRole(['staff']); View::render('pages/staff/announcements',['title'=>'Admin • Anuncios']); }
  public function staffUsers(): void { $this->requireRole(['staff']); View::render('pages/staff/users',        ['title'=>'Admin • Usuarios']); }

  /* ==================== PÚBLICAS DE DETALLE ==================== */

  /**
   * Página pública de charla. Funciona tanto si la charla NO tiene conference_id
   * como si SÍ tiene conference_id (trae info de la conferencia si existe).
   */
  public function publicTalk(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    if ($id <= 0) {
      http_response_code(400);
      View::render('errors/400', ['title'=>'Error', 'message'=>'ID de charla inválido']);
      return;
    }

    $sb = new Supabase();

    // Traer la charla
    $resTalk = $sb->restSelect('talks', "id=eq.$id&select=id,title,starts_at,ends_at,conference_id,room_id,speaker_id,speaker_email", true);
    $talkArr = json_decode($resTalk['body'] ?? '[]', true) ?? [];
    $talk = $talkArr[0] ?? null;

    if (!$talk) {
      http_response_code(404);
      View::render('errors/404', ['title'=>'No encontrado', 'message'=>'Charla no encontrada']);
      return;
    }

    // Traer conferencia si existe (opcional)
    $conference = null;
    if (!empty($talk['conference_id'])) {
      $cid = (int)$talk['conference_id'];
      $resConf = $sb->restSelect('conferences', "id=eq.$cid&select=id,name,city,starts_at,ends_at", true);
      $confArr = json_decode($resConf['body'] ?? '[]', true) ?? [];
      $conference = $confArr[0] ?? null;
    }

    View::render('public/talk', [
      'title'      => 'Detalle charla',
      'talk'       => $talk,
      'conference' => $conference,
    ]);
  }

  /** Página pública de curso (no depende de conferencia) */
  public function publicCourse(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $course = null;
    $error = null;

    if ($id <= 0) {
      http_response_code(400);
      $error = 'ID de curso inválido';
    } else {
      $res = $this->sb->restSelect(
        'courses',
        "id=eq.$id&select=id,title,description,modality,venue,stream_url,starts_at,ends_at,owner_id,owner_email,created_at",
        true
      );
      $arr = json_decode($res['body'] ?? '[]', true) ?? [];
      $course = $arr[0] ?? null;
      if (!$course) {
        http_response_code(404);
        $error = 'Curso no encontrado';
      }
    }

    View::render('public/course', [
      'title'  => 'Detalle curso',
      'course' => $course,
      'error'  => $error
    ]);
  }

  /** Página pública de webinar (no depende de conferencia) */
  public function publicWebinar(): void {
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    $webinar = null;
    $error = null;

    if ($id <= 0) {
      http_response_code(400);
      $error = 'ID de webinar inválido';
    } else {
      $res = $this->sb->restSelect(
        'webinars',
        "id=eq.$id&select=id,title,description,modality,venue,stream_url,starts_at,ends_at,owner_id,owner_email,created_at",
        true
      );
      $arr = json_decode($res['body'] ?? '[]', true) ?? [];
      $webinar = $arr[0] ?? null;
      if (!$webinar) {
        http_response_code(404);
        $error = 'Webinar no encontrado';
      }
    }

    View::render('public/webinar', [
      'title'   => 'Detalle webinar',
      'webinar' => $webinar,
      'error'   => $error
    ]);
  }

  /* ==================== VISTAS DETALLE PARA ORADOR ==================== */
  /** Estas son DISTINTAS a las públicas de asistentes. Requieren rol speaker. */

  public function speakerTalk(): void {
    $this->requireRole(['speaker']);
    // La vista usa JS para pedir /speaker.talk.stats y pintar los datos
    View::render('pages/speaker/talk', ['title' => 'Charla — Panel del orador']);
  }

  public function speakerCourse(): void {
    $this->requireRole(['speaker']);
    View::render('pages/speaker/course', ['title' => 'Curso — Panel del orador']);
  }

  public function speakerWebinar(): void {
    $this->requireRole(['speaker']);
    View::render('pages/speaker/webinar', ['title' => 'Webinar — Panel del orador']);
  }
}
