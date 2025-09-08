<?php
$cfg = require __DIR__ . '/../../config.php';

/* ===== Detectar usuario y rol desde la cookie HttpOnly 'jwt' ===== */
$role = null; $userName = null; $userEmail = null; $uid = null;

try {
  if (!empty($_COOKIE['jwt'])) {
    $jwt = $_COOKIE['jwt'];
    // Decodifica payload del JWT (base64url)
    $parts = explode('.', $jwt);
    if (count($parts) >= 2) {
      $b64 = $parts[1];
      $b64 = strtr($b64, '-_', '+/');
      $pad = (4 - strlen($b64) % 4) % 4;
      $payload = json_decode(base64_decode($b64 . str_repeat('=', $pad)) ?: "{}", true) ?? [];
      $userEmail = $payload['email'] ?? null;
      $userName  = $payload['user_metadata']['full_name'] ?? ($payload['user_metadata']['name'] ?? null);
      $uid       = $payload['sub'] ?? null;
    }
    // Obtener rol desde profiles con Service Role (vía nuestro modelo)
    if ($uid) {
      $sb = new \App\Models\Supabase();
      $role = $sb->getUserRole($uid) ?? null;
    }
  }
} catch (\Throwable $e) {
  // silencio: si algo falla, simplemente no mostramos datos de usuario
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($title ?? 'ABRAHAM', ENT_QUOTES, 'UTF-8') ?></title>
  <script>
    // Aplica el tema lo antes posible para evitar "flash"
    (function(){
      try{
        var pref = localStorage.getItem('theme');
        var osDark = window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches;
        var theme = pref || (osDark ? 'dark' : 'light');
        document.documentElement.setAttribute('data-theme', theme);
      }catch(e){}
    })();
  </script>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <?php $cssPath = __DIR__ . '/../../public/assets/styles.css'; $cssVer = is_file($cssPath) ? filemtime($cssPath) : time(); ?>
  <link rel="stylesheet" href="/assets/styles.css?v=<?= htmlspecialchars((string)$cssVer, ENT_QUOTES, 'UTF-8') ?>" />
  <style>
    /* Chip simple para el usuario en el header */
    .userchip{ padding:4px 8px; border:1px solid #1f2937; border-radius:999px; }
    @media (max-width: 640px){ .linkbar{ flex-wrap:wrap; gap:6px } }
  </style>
  <script>
    window.ENV = {
      SUPABASE_URL: <?= json_encode($cfg['SUPABASE_URL'] ?? '') ?>,
      SUPABASE_ANON: <?= json_encode($cfg['SUPABASE_ANON_KEY'] ?? '') ?>
    };
  </script>
  <script src="https://cdn.jsdelivr.net/npm/@supabase/supabase-js@2" defer></script>
  <script src="/js/common.js" defer></script>
  <?php if (!empty($extra_js)) : foreach ($extra_js as $src): ?>
    <script src="<?= htmlspecialchars($src,ENT_QUOTES,'UTF-8') ?>" defer></script>
  <?php endforeach; endif; ?>
</head>
<body>
  <header class="topbar">
    <h1>ABRAHAM</h1>
    <nav class="linkbar">
      <button id="themeToggle" class="btn outline small" type="button" title="Cambiar tema">Tema</button>
      <?php if ($role === 'admin'): ?>
        <a class="btn outline" href="/index.php?route=/speaker">Oradores</a>
        <a class="btn outline" href="/index.php?route=/attendee">Asistentes</a>
        <a class="btn outline" href="/index.php?route=/staff">Staff</a>
      <?php elseif ($role === 'speaker'): ?>
        <a class="btn outline" href="/index.php?route=/speaker">Oradores</a>
        <a class="btn outline" href="/index.php?route=/attendee">Asistentes</a>
      <?php elseif ($role === 'staff'): ?>
        <a class="btn outline" href="/index.php?route=/staff">Staff</a>
      <?php elseif ($role === 'attendee'): ?>
        <a class="btn outline" href="/index.php?route=/attendee">Asistentes</a>
      <?php endif; ?>

      <?php if ($uid): ?>
        <span class="muted userchip">
          👤 <?= htmlspecialchars($userName ?: $userEmail ?: substr($uid,0,8), ENT_QUOTES, 'UTF-8') ?>
          <?php if ($role): ?>
            <small class="badge" style="margin-left:6px"><?= htmlspecialchars($role, ENT_QUOTES, 'UTF-8') ?></small>
          <?php endif; ?>
        </span>
        <button id="logoutBtn" class="btn ghost" type="button">Cerrar sesión</button>
      <?php else: ?>
        <a class="btn outline" href="/index.php?route=/">Iniciar sesión</a>
      <?php endif; ?>
    </nav>
  </header>
  <main class="container">
    <?= $content ?>
  </main>
  <footer class="footer muted"><small>MVC — Modelos, Vistas, Controladores</small></footer>

  <script>
  // Logout global
  window.addEventListener('DOMContentLoaded', ()=>{
    // Tema: toggle y persistencia
    const tbtn = document.getElementById('themeToggle');
    const setTheme = (th)=>{ document.documentElement.setAttribute('data-theme', th); try{ localStorage.setItem('theme', th); }catch(e){} };
    const syncBtn = ()=>{
      const th = document.documentElement.getAttribute('data-theme') || 'light';
      if (tbtn) tbtn.textContent = (th === 'dark') ? 'Tema: Oscuro' : 'Tema: Claro';
    };
    tbtn && tbtn.addEventListener('click', ()=>{
      const curr = document.documentElement.getAttribute('data-theme') || 'light';
      setTheme(curr === 'dark' ? 'light' : 'dark');
      syncBtn();
    });
    syncBtn();

    const btn = document.getElementById('logoutBtn');
    if (!btn) return;
    btn.addEventListener('click', async ()=>{
      try {
        const supa = window.supabase.createClient(window.ENV.SUPABASE_URL, window.ENV.SUPABASE_ANON);
        await supa.auth.signOut();
        localStorage.removeItem('jwt');
        await fetch('/index.php?route=/auth.logout', { method:'POST' });
        window.location.href = '/index.php?route=/';
      } catch(e){ alert('No se pudo cerrar sesión'); }
    });
  });
  </script>
</body>
</html>
