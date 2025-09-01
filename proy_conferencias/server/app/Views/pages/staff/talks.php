<?php $title="Admin • Charlas"; $extra_js=["/js/admin.talks.js"]; ?>
<nav class="row" style="margin-bottom:10px">
  <a class="btn outline" href="/index.php?route=/staff/conferences">Conferencias</a>
  <a class="btn outline" href="/index.php?route=/staff/rooms">Salas</a>
  <a class="btn outline" href="/index.php?route=/staff/talks">Charlas</a>
  <a class="btn outline" href="/index.php?route=/staff/announcements">Anuncios</a>
</nav>

<div class="grid">
  <section class="card">
    <h2 id="talkFormTitle">Nueva charla</h2>
    <form id="formTalk">
      <input type="hidden" id="tid" />
      <label>Conferencia
        <select id="t_conf"></select>
      </label>
      <label>Sala (opcional)
        <select id="t_room"><option value="">— Sin sala —</option></select>
      </label>
      <label>Título <input id="t_title" /></label>
      <label>Inicio <input id="t_start" type="datetime-local" /></label>
      <label>Fin <input id="t_end" type="datetime-local" /></label>

      <details class="item">
        <summary>Asignar speaker (opcional)</summary>
        <div class="row">
          <input id="t_speaker_email" placeholder="buscar por email" />
          <button type="button" class="btn outline" id="btnSearchSpeaker">Buscar</button>
        </div>
        <div id="speakerResults" class="list"></div>
        <input type="hidden" id="t_speaker_uuid" />
      </details>

      <div class="row">
        <button class="btn" id="btnTalkSave">Crear charla</button>
        <button class="btn outline" type="button" id="btnTalkCancel" style="display:none">Cancelar edición</button>
      </div>
    </form>
  </section>

  <section class="card">
    <h2>Charlas programadas</h2>
    <label>Conferencia
      <select id="list_conf"></select>
    </label>
    <div id="talkList" class="list"></div>
  </section>
</div>
