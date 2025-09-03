<?php $title="Admin • Salas"; $extra_js=["/js/admin.rooms.js"]; ?>
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
      <label>Conferencia
        <select id="selConf"></select>
      </label>
      <label>Nombre de la sala
        <input id="rname" />
      </label>
      <div class="row">
        <button class="btn" id="btnRoomSave">Crear</button>
        <button class="btn outline" type="button" id="btnRoomCancel" style="display:none">Cancelar edición</button>
      </div>
    </form>
  </section>

  <section class="card">
    <h2>Salas de la conferencia</h2>
    <div id="roomList" class="list"></div>
  </section>
</div>
