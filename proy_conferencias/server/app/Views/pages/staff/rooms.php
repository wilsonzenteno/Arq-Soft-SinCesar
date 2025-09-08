<?php
// Vista: Staff • Salas
$title = "Admin • Salas";
$extra_js = ["/js/admin.rooms.js"];
?>
<nav class="row" style="margin-bottom:10px">
  <a class="btn outline" href="/index.php?route=/staff/conferences">Conferencias</a>
  <a class="btn outline" href="/index.php?route=/staff/rooms">Salas</a>
  <a class="btn outline" href="/index.php?route=/staff/talks">Charlas</a>
  <a class="btn outline" href="/index.php?route=/staff/announcements">Anuncios</a>
  <a class="btn outline" href="/index.php?route=/staff/users">Usuarios</a>
</nav>

<div class="grid">
  <section class="card">
    <h2 id="roomFormTitle">Nueva sala</h2>
    <form id="formRoom">
      <input type="hidden" id="rid" />

      <label>Nombre de la sala
        <input id="rname" required placeholder="Ej. Sala Azul" />
      </label>

      <label>Número (opcional)
        <input id="rnumber" placeholder="Ej. A-101" />
      </label>

      <label>Capacidad (opcional)
        <input id="rcap" type="number" min="0" step="1" placeholder="Ej. 120" />
      </label>

      <div class="row">
        <button class="btn" id="btnRoomSave" type="submit">Crear</button>
        <button class="btn outline" type="button" id="btnRoomCancel" style="display:none">Cancelar edición</button>
      </div>
    </form>
  </section>

  <section class="card">
    <h2>Salas por conferencia (filtro opcional)</h2>
    <label>Filtrar por conferencia
      <select id="selConf"></select>
    </label>
    <div id="roomList" class="list" style="margin-top:8px"></div>
  </section>

  <section class="card">
    <h2>Todas las salas</h2>
    <div id="roomAllList" class="list"></div>
  </section>
</div>
