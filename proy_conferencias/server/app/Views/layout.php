<?php
/** (VISTA) — Layout principal. Inserta el contenido de cada vista específica en $content */
?><!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title><?= htmlspecialchars($title ?? 'ABRAHAM', ENT_QUOTES, 'UTF-8') ?></title>
  <link rel="stylesheet" href="/assets/styles.css" />
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
    </nav>
  </header>
  <main class="container">
    <?= $content ?>
  </main>
  <footer class="footer muted"><small>MVC: Modelos (app/Models), Vistas (app/Views), Controladores (app/Controllers)</small></footer>
</body>
</html>
