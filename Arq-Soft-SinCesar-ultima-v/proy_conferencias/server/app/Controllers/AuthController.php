<?php
namespace App\Controllers;

use App\Core\Http;
use App\Models\Supabase;

class AuthController {
  private Supabase $sb;
  public function __construct(){ $this->sb = new Supabase(); }

  private function jwtFromRequest(): ?string {
    $bearer = Http::bearer();
    if ($bearer) return $bearer;
    return $_COOKIE['jwt'] ?? null;
  }

  public function role(): void {
    $jwt = $this->jwtFromRequest();
    $uid = $this->sb->userIdFromJwt($jwt);
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }
    $role = $this->sb->getUserRole($uid);
    Http::json(['user_id'=>$uid, 'role'=>$role]);
  }

  public function session(): void {
    $jwt = Http::bearer();
    if (!$jwt) {
      $p = Http::jsonInput();
      $jwt = $p['access_token'] ?? '';
    }
    $uid = $this->sb->userIdFromJwt($jwt);
    if (!$uid) { Http::json(['error'=>'No auth'], 401); return; }

    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('jwt', $jwt, [
      'expires'=>0, 'path'=>'/', 'secure'=>$secure, 'httponly'=>true, 'samesite'=>'Lax'
    ]);
    Http::json(['ok'=>true, 'user_id'=>$uid]);
  }

  public function logout(): void {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('jwt','',[
      'expires'=>time()-3600,'path'=>'/','secure'=>$secure,'httponly'=>true,'samesite'=>'Lax'
    ]);
    Http::json(['ok'=>true]);
  }
}
