<?php $title="Admin • Anuncios"; $extra_js=["/js/admin.announcements.js"]; ?>
<nav class="row" style="margin-bottom:10px">
  <a class="btn outline" href="/index.php?route=/staff/conferences">Conferencias</a>
  <a class="btn outline" href="/index.php?route=/staff/rooms">Salas</a>
  <a class="btn outline" href="/index.php?route=/staff/talks">Charlas</a>
  <a class="btn outline" href="/index.php?route=/staff/announcements">Anuncios</a>
</nav>

<div class="grid">
  <section class="card">
    <h2 id="annFormTitle">Nuevo anuncio</h2>
    <form id="formAnn">
      <input type="hidden" id="aid" />
      <label>Título<input id="anntitle" /></label>
      <label>Mensaje<textarea id="annbody"></textarea></label>
      <label>Conferencia (opcional)
        <select id="annconf"><option value="">— Global —</option></select>
      </label>
      <div class="row">
        <button class="btn" id="btnAnnSave">Publicar</button>
        <button class="btn outline" type="button" id="btnAnnCancel" style="display:none">Cancelar edición</button>
      </div>
    </form>
  </section>

  <section class="card">
    <h2>Listado</h2>
    <label>Filtrar por conferencia
      <select id="annFilter"><option value="">— Todas —</option></select>
    </label>
    <div id="annList" class="list"></div>
  </section>
</div>
