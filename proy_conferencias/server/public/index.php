<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', '1');

$config = require __DIR__ . '/../config.php';

header('Content-Type: text/html; charset=utf-8');
header('Access-Control-Allow-Origin: ' . ($config['APP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Headers: Authorization, Content-Type');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') { exit; }

spl_autoload_register(function ($class) {
  $prefix = 'App\\'; $base_dir = __DIR__ . '/../app/'; $len = strlen($prefix);
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
use App\Controllers\AuthController;
use App\Controllers\EventsController;

$router = new Router();

/** VISTAS */
$router->get('/',         [PagesController::class, 'home']);
$router->get('/speaker',  [PagesController::class, 'speaker']);
$router->get('/attendee', [PagesController::class, 'attendee']);
$router->get('/staff',    [PagesController::class, 'staff']);

/** VISTAS ADMIN NUEVAS */
$router->get('/staff/conferences',   [PagesController::class, 'staffConfs']);
$router->get('/staff/rooms',         [PagesController::class, 'staffRooms']);
$router->get('/staff/talks',         [PagesController::class, 'staffTalks']);
$router->get('/staff/announcements', [PagesController::class, 'staffAnns']);
$router->get('/staff/users',         [PagesController::class, 'staffUsers']);

/** AUTH */
$router->get('/auth.role',     [AuthController::class, 'role']);
$router->post('/auth.session', [AuthController::class, 'session']);
$router->post('/auth.logout',  [AuthController::class, 'logout']);

/** Páginas públicas de detalle */
$router->get('/public/talk',    [PagesController::class, 'publicTalk']);
$router->get('/public/course',  [PagesController::class, 'publicCourse']);
$router->get('/public/webinar', [PagesController::class, 'publicWebinar']);

/** API Asistentes (conferencias) */
$router->get('/attendee.conferences.list',  [AttendeeController::class, 'listConferences']);
$router->get('/attendee.talks.list',        [AttendeeController::class, 'listTalksByConference']);
$router->post('/attendee.register',         [AttendeeController::class, 'registerToConference']);
$router->post('/attendee.vote',             [AttendeeController::class, 'voteTalk']);
$router->get('/attendee.registrations.mine',[AttendeeController::class, 'myRegistrations']);
$router->get('/attendee.votes.mine',        [AttendeeController::class, 'myVotes']);
$router->get('/attendee.conf.likes.mine',   [AttendeeController::class, 'myConferenceLikes']);
$router->post('/attendee.conf.like',        [AttendeeController::class, 'likeConference']);
$router->get('/attendee.conf.eval.get',     [AttendeeController::class, 'getConferenceEvaluation']);
$router->post('/attendee.conf.eval.save',   [AttendeeController::class, 'saveConferenceEvaluation']);

/** Inscripciones nuevas (charla/curso/webinar) */
$router->post('/attendee.talk.register',           [AttendeeController::class, 'registerToTalk']);
$router->get('/attendee.talk.registrations.mine',  [AttendeeController::class, 'myTalkRegistrations']);

$router->post('/attendee.course.register',          [AttendeeController::class, 'registerToCourse']);
$router->get('/attendee.course.registrations.mine', [AttendeeController::class, 'myCourseRegistrations']);

$router->post('/attendee.webinar.register',          [AttendeeController::class, 'registerToWebinar']);
$router->get('/attendee.webinar.registrations.mine', [AttendeeController::class, 'myWebinarRegistrations']);

/** Votos (likes/dislikes) cursos/webinars/charlas */
$router->post('/attendee.course.vote',      [AttendeeController::class, 'courseVote']);
$router->get('/attendee.course.votes.mine', [AttendeeController::class, 'myCourseVotes']);

$router->post('/attendee.webinar.vote',      [AttendeeController::class, 'webinarVote']);
$router->get('/attendee.webinar.votes.mine', [AttendeeController::class, 'myWebinarVotes']);

$router->post('/attendee.talk.vote',        [AttendeeController::class, 'talkVote']);
$router->get('/attendee.talk.votes.mine',   [AttendeeController::class, 'myTalkVotes']);

/** Evaluaciones cursos/webinars/charlas */
$router->get('/attendee.course.eval.get',   [AttendeeController::class, 'getCourseEvaluation']);
$router->post('/attendee.course.eval.save', [AttendeeController::class, 'saveCourseEvaluation']);

$router->get('/attendee.webinar.eval.get',  [AttendeeController::class, 'getWebinarEvaluation']);
$router->post('/attendee.webinar.eval.save',[AttendeeController::class, 'saveWebinarEvaluation']);

$router->get('/attendee.talk.eval.get',     [AttendeeController::class, 'getTalkEvaluation']);
$router->post('/attendee.talk.eval.save',   [AttendeeController::class, 'saveTalkEvaluation']);

/** Listas públicas “Explorar todo” */
$router->get('/attendee.talks.all',    [AttendeeController::class, 'listAllTalks']);
$router->get('/attendee.courses.all',  [AttendeeController::class, 'listAllCourses']);
$router->get('/attendee.webinars.all', [AttendeeController::class, 'listAllWebinars']);

/** Detalle para asistentes (faltaba esta ruta) */
$router->get('/attendee.talk.details', [AttendeeController::class, 'talkDetails']);

// Attendee (multi-evento si lo usas)
$router->get('/attendee.events.all',               [EventsController::class, 'listAll']);
$router->post('/attendee.event.register',          [EventsController::class, 'register']);
$router->get('/attendee.event.registrations.mine', [EventsController::class, 'myRegs']);
$router->post('/attendee.event.vote',              [EventsController::class, 'vote']);
$router->get('/attendee.event.votes.mine',         [EventsController::class, 'myVotes']);
$router->get('/attendee.event.eval.get',           [EventsController::class, 'getEval']);
$router->post('/attendee.event.eval.save',         [EventsController::class, 'saveEval']);

// Notificaciones para asistentes (campanita)
$router->get('/attendee.notifications', [AttendeeController::class, 'notifications']);

// Speaker (multi-evento genérico)
$router->get('/speaker.events.mine',   [EventsController::class, 'mine']);
$router->post('/speaker.event.create', [EventsController::class, 'create']);
$router->post('/speaker.event.update', [EventsController::class, 'update']);
$router->post('/speaker.event.delete', [EventsController::class, 'delete']);

/** SPEAKER – listados propios */
$router->get('/speaker.conferences.mine', [SpeakerController::class, 'myConferences']);
$router->get('/speaker.webinars.mine',    [SpeakerController::class, 'myWebinars']);
$router->get('/speaker.courses.mine',     [SpeakerController::class, 'myCourses']);
$router->get('/speaker.talks.mine',       [SpeakerController::class, 'myTalks']);

/** SPEAKER – conferencia (CRUD propio) */
$router->post('/speaker.conference.create', [SpeakerController::class, 'createConferenceBySpeaker']);
$router->post('/speaker.conference.update', [SpeakerController::class, 'updateConferenceBySpeaker']);
$router->post('/speaker.conference.delete', [SpeakerController::class, 'deleteConferenceBySpeaker']);

/** SPEAKER – charla (CRUD propio) */
$router->post('/speaker.talk.create',  [SpeakerController::class, 'createOwnTalk']);
$router->post('/speaker.talk.update',  [SpeakerController::class, 'updateOwnTalk']);
$router->post('/speaker.talk.delete',  [SpeakerController::class, 'deleteOwnTalk']);

/** SPEAKER – webinar (CRUD propio) */
$router->post('/speaker.webinar.create', [SpeakerController::class, 'myWebinarsCreate']);
$router->post('/speaker.webinar.update', [SpeakerController::class, 'myWebinarsUpdate']);
$router->post('/speaker.webinar.delete', [SpeakerController::class, 'myWebinarsDelete']);

/** SPEAKER – course (CRUD propio) */
$router->post('/speaker.course.create', [SpeakerController::class, 'myCoursesCreate']);
$router->post('/speaker.course.update', [SpeakerController::class, 'myCoursesUpdate']);
$router->post('/speaker.course.delete', [SpeakerController::class, 'myCoursesDelete']);

/** Slides */
$router->post('/speaker.slides.upload', [SpeakerController::class, 'uploadSlides']);
$router->get('/slides.download',        [SlidesController::class, 'download']);
$router->get('/slides.exists',          [SlidesController::class, 'exists']);

$router->post('/speaker.event.slides.upload', [SlidesController::class, 'uploadForEvent']);
$router->get('/slides.event.download',        [SlidesController::class, 'downloadEvent']);

/** VISTA DETALLE SPEAKER */
$router->get('/speaker/talk',    [PagesController::class, 'speakerTalk']);
$router->get('/speaker/course',  [PagesController::class, 'speakerCourse']);
$router->get('/speaker/webinar', [PagesController::class, 'speakerWebinar']);

/** APIs de estadísticas para SPEAKER (SpeakerController) */
$router->get('/speaker.talk.stats',    [SpeakerController::class, 'talkStats']);
$router->get('/speaker.course.stats',  [SpeakerController::class, 'courseStats']);
$router->get('/speaker.webinar.stats', [SpeakerController::class, 'webinarStats']);

/** SPEAKER – listados (inscritos / evaluaciones) */
$router->get('/speaker.talk.registrations',    [SpeakerController::class, 'talkRegistrations']);
$router->get('/speaker.course.registrations',  [SpeakerController::class, 'courseRegistrations']);
$router->get('/speaker.webinar.registrations', [SpeakerController::class, 'webinarRegistrations']);

$router->get('/speaker.talk.evaluations',      [SpeakerController::class, 'talkEvaluations']);
$router->get('/speaker.course.evaluations',    [SpeakerController::class, 'courseEvaluations']);
$router->get('/speaker.webinar.evaluations',   [SpeakerController::class, 'webinarEvaluations']);

/** Staff */
$router->post('/staff.conference.create',   [StaffController::class, 'createConference']);
$router->post('/staff.room.create',         [StaffController::class, 'createRoom']);
$router->post('/staff.talk.create',         [StaffController::class, 'createTalk']);

$router->get('/staff.rooms.all',            [StaffController::class, 'listAllRooms']);
$router->get('/staff.rooms.by_conf',        [StaffController::class, 'roomsByConference']);
$router->get('/staff.talks.list',           [StaffController::class, 'listTalks']);
$router->get('/staff.users.search',         [StaffController::class, 'searchUsersByEmail']);

/** Staff updates/deletes */
$router->post('/staff.conference.update',   [StaffController::class, 'updateConference']);
$router->post('/staff.conference.delete',   [StaffController::class, 'deleteConference']);
$router->post('/staff.room.update',         [StaffController::class, 'updateRoom']);
$router->post('/staff.room.delete',         [StaffController::class, 'deleteRoom']);
$router->post('/staff.talk.update',         [StaffController::class, 'updateTalk']);
$router->post('/staff.talk.delete',         [StaffController::class, 'deleteTalk']);
$router->post('/staff.announcement.create', [StaffController::class, 'createAnnouncement']);
$router->get('/staff.announcements.list',   [StaffController::class, 'listAnnouncements']);
$router->post('/staff.announcement.update', [StaffController::class, 'updateAnnouncement']);
$router->post('/staff.announcement.delete', [StaffController::class, 'deleteAnnouncement']);
$router->post('/staff.user.role.update',    [StaffController::class, 'updateUserRole']);

$router->dispatch();
