<?php
$cfg = require __DIR__ . '/../../config.php';
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($title ?? 'ABRAHAM', ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/assets/styles.css" />
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
      <a class="btn outline" href="/index.php?route=/speaker">Oradores</a>
      <a class="btn outline" href="/index.php?route=/attendee">Asistentes</a>
      <a class="btn outline" href="/index.php?route=/staff">Staff</a>
      <button id="logoutBtn" class="btn ghost" type="button">Cerrar sesión</button>
    </nav>
  </header>
  <main class="container">
    <?= $content ?>
  </main>
  <footer class="footer muted"><small>MVC — Modelos, Vistas, Controladores</small></footer>

  <script>
  // Logout global
  window.addEventListener('DOMContentLoaded', ()=>{
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
