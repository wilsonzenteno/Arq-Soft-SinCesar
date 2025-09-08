<?php $title="Admin • Usuarios"; $extra_js=["/js/admin.users.js"]; ?>
<nav class="row" style="margin-bottom:10px">
  <a class="btn outline" href="/index.php?route=/staff/conferences">Conferencias</a>
  <a class="btn outline" href="/index.php?route=/staff/rooms">Salas</a>
  <a class="btn outline" href="/index.php?route=/staff/talks">Charlas</a>
  <a class="btn outline" href="/index.php?route=/staff/announcements">Anuncios</a>
  <a class="btn outline" href="/index.php?route=/staff/users">Usuarios</a>
</nav>

<div class="grid">
  <section class="card">
    <h2>Buscar usuarios</h2>
    <form id="userSearchForm" class="row" style="gap:12px; align-items:flex-end">
      <label style="flex:1">Email (parcial o completo)
        <input id="userEmail" placeholder="ej: @dominio.com o nombre@dominio.com" />
      </label>
      <button class="btn" id="btnUserSearch">Buscar</button>
    </form>
  </section>

  <section class="card">
    <h2>Resultados</h2>
    <div id="userList" class="list"></div>
  </section>
</div>
