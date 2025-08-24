<?php
declare(strict_types=1);
/**
 * (CONTROLADOR FRONTAL) — Punto único de entrada y ruteo.
 * Arquitecura MVC: Router -> Controladores -> Modelos y Vistas.
 */

error_reporting(E_ALL);
ini_set('display_errors', '1');

$config = require __DIR__ . '/../config.php';
header('Access-Control-Allow-Origin: ' . ($config['APP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }

// Autoload muy simple PSR-4 para espacio de nombres App\
spl_autoload_register(function ($class) {
  $prefix = 'App\\';
  $base_dir = __DIR__ . '/../app/';
  $len = strlen($prefix);
  if (strncmp($prefix, $class, $len) !== 0) return;
  $relative_class = substr($class, $len);
  $file = $base_dir . str_replace('\\', '/', $relative_class) . '.php';
  if (file_exists($file)) require $file;
});

use App\Core\Router;
use App\Controllers\PagesController;
use App\Controllers\AttendeeController;
use App\Controllers\SpeakerController;
use App\Controllers\StaffController;
use App\Controllers\SlidesController;

$router = new Router();

// ========= RUTAS DE VISTAS (V) =========
$router->get('/',                     [PagesController::class, 'home']);
$router->get('/speaker',              [PagesController::class, 'speaker']);
$router->get('/attendee',             [PagesController::class, 'attendee']);
$router->get('/staff',                [PagesController::class, 'staff']);

// ========= RUTAS DE API (C -> M) =========
// Asistentes
$router->get('/attendee.conferences.list', [AttendeeController::class, 'listConferences']);
$router->get('/attendee.talks.list',       [AttendeeController::class, 'listTalksByConference']);
$router->post('/attendee.register',        [AttendeeController::class, 'registerToConference']);
$router->post('/attendee.vote',            [AttendeeController::class, 'voteTalk']);

// Oradores
$router->get('/speaker.talks.mine',        [SpeakerController::class, 'myTalks']);
$router->post('/speaker.talks.claim',      [SpeakerController::class, 'claimTalk']);
$router->post('/speaker.slides.upload',    [SpeakerController::class, 'uploadSlides']);

// Staff/Admin
$router->post('/staff.conference.create',  [StaffController::class, 'createConference']);
$router->post('/staff.room.create',        [StaffController::class, 'createRoom']);
$router->post('/staff.talk.create',        [StaffController::class, 'createTalk']);
$router->post('/staff.announcement.create',[StaffController::class, 'createAnnouncement']);

// Descarga protegida de diapositivas
$router->get('/slides.download',           [SlidesController::class, 'download']);

$router->dispatch();
