<?php
namespace App\Controllers;

use App\Core\View;

/** (CONTROLADOR) — Controla qué VISTA mostrar */
class PagesController {
  public function home(): void     { View::render('pages/home',     ['title'=>'Inicio']); }
  public function speaker(): void  { View::render('pages/speaker',  ['title'=>'Oradores']); }
  public function attendee(): void { View::render('pages/attendee', ['title'=>'Asistentes']); }
  public function staff(): void    { View::render('pages/staff',    ['title'=>'Staff']); }
}
