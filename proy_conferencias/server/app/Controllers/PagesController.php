<?php
namespace App\Controllers;

use App\Core\View;
use App\Models\Supabase;

class PagesController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

  private function requireRole(array $allowed): void {
    $jwt = $_COOKIE['jwt'] ?? null;
    if (!$jwt) { header('Location: /index.php?route=/'); exit; }
    $uid  = $this->sb->userIdFromJwt($jwt);
    $role = $uid ? $this->sb->getUserRole($uid) : null;
    $isAllowed = $role === 'admin' || in_array((string)$role, $allowed, true);
    if (!$uid || !$isAllowed) {
      http_response_code(403);
      View::render('errors/403', ['title'=>'Acceso denegado']);
      exit;
    }
  }

  public function home(): void     { View::render('pages/home',     ['title'=>'Inicio']); }
  public function speaker(): void  { $this->requireRole(['speaker']);  View::render('pages/speaker',  ['title'=>'Oradores']); }
  public function attendee(): void { $this->requireRole(['attendee']); View::render('pages/attendee', ['title'=>'Asistentes']); }
  public function staff(): void    { $this->requireRole(['staff']);    View::render('pages/staff',    ['title'=>'Staff']); }

  /** NUEVAS páginas admin */
  public function staffConfs(): void { $this->requireRole(['staff']); View::render('pages/staff/conferences', ['title'=>'Admin • Conferencias']); }
  public function staffRooms(): void { $this->requireRole(['staff']); View::render('pages/staff/rooms',       ['title'=>'Admin • Salas']); }
  public function staffTalks(): void { $this->requireRole(['staff']); View::render('pages/staff/talks',       ['title'=>'Admin • Charlas']); }
  public function staffAnns(): void  { $this->requireRole(['staff']); View::render('pages/staff/announcements',['title'=>'Admin • Anuncios']); }

    /** ====== PÁGINAS PÚBLICAS DE DETALLE ====== */
  public function publicTalk(): void  { View::render('public/talk',   ['title'=>'Detalle charla']); }
  public function publicCourse(): void{ View::render('public/course', ['title'=>'Detalle curso']); }
  public function publicWebinar(): void{View::render('public/webinar',['title'=>'Detalle webinar']); }

}
