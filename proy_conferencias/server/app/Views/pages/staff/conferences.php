<?php $title="Admin • Conferencias"; $extra_js=["/js/admin.conferences.js"]; ?>
<nav class="row" style="margin-bottom:10px">
  <a class="btn outline" href="/index.php?route=/staff/conferences">Conferencias</a>
  <a class="btn outline" href="/index.php?route=/staff/rooms">Salas</a>
  <a class="btn outline" href="/index.php?route=/staff/talks">Charlas</a>
  <a class="btn outline" href="/index.php?route=/staff/announcements">Anuncios</a>
</nav>

<div class="grid">
  <section class="card">
    <h2 id="confFormTitle">Nueva conferencia</h2>
    <form id="formConf">
      <input type="hidden" id="cid" />
      <label>Nombre<input id="cname" /></label>
      <label>Ciudad<input id="ccity" /></label>
      <label>Inicio <input id="cstart" type="datetime-local" /></label>
      <label>Fin <input id="cend" type="datetime-local" /></label>
      <div class="row">
        <button class="btn" id="btnConfSave">Crear</button>
        <button class="btn outline" type="button" id="btnConfCancel" style="display:none">Cancelar edición</button>
      </div>
    </form>
  </section>

  <section class="card">
    <h2>Conferencias existentes</h2>
    <div id="confList" class="list"></div>
  </section>
</div>
