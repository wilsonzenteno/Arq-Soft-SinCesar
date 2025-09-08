<?php
/** Vista: Admin • Anuncios */
$title = "Admin • Anuncios";
$extra_js = ["/js/admin.announcements.js"];
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
    <h2 id="annFormTitle">Nuevo anuncio</h2>
    <form id="formAnn" autocomplete="off">
      <input type="hidden" id="aid" />

      <label>Título
        <input id="anntitle" required placeholder="Título del anuncio" />
      </label>

      <label>Mensaje
        <textarea id="annbody" rows="3" required placeholder="Contenido breve del anuncio…"></textarea>
      </label>

      <div class="grid2">
        <label>Tipo de destino
          <select id="annkind">
            <option value="talk">Charla</option>
            <option value="course">Curso</option>
            <option value="webinar">Webinar</option>
          </select>
        </label>

        <label>Elemento (según tipo)
          <select id="annref" required>
            <option value="">Seleccione…</option>
          </select>
        </label>
      </div>

      <div class="row" style="gap:8px;margin-top:10px">
        <button class="btn" id="btnAnnSave" type="submit">Publicar</button>
        <button class="btn outline" type="button" id="btnAnnCancel" style="display:none">Cancelar edición</button>
      </div>
    </form>
  </section>

  <section class="card">
    <h2>Anuncios recientes</h2>

    <div class="row" style="gap:8px; align-items:flex-end">
      <label style="min-width:180px">Filtrar por tipo
        <select id="annFilterKind">
          <option value="">— Todos —</option>
          <option value="talk">Charla</option>
          <option value="course">Curso</option>
          <option value="webinar">Webinar</option>
        </select>
      </label>

      <label style="min-width:220px">Filtrar por elemento
        <select id="annFilterRef">
          <option value="">— Cualquiera —</option>
        </select>
      </label>

      <label style="flex:1">Buscar
        <input id="annSearch" placeholder="Título o mensaje…" />
      </label>

      <button class="btn outline" type="button" id="btnAnnReload">Recargar</button>
    </div>

    <div id="annList" class="list" style="margin-top:10px"></div>
  </section>
</div>
