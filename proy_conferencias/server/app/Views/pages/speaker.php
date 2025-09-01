<?php
$title = "Oradores — ABRAHAM";
$extra_js = ["/js/speaker.js"];
?>
<section class="card">
  <h2>Crear/Editar — (Conferencia / Charla / Curso / Webinar)</h2>

  <form id="uniForm" class="row" style="gap:16px; align-items:flex-end">
    <input type="hidden" id="item_id" />
    <label>Tipo
      <select id="item_type">
        <option value="conference">Conferencia</option>
        <option value="talk">Charla</option>
        <option value="course">Curso</option>
        <option value="webinar">Webinar</option>
      </select>
    </label>

    <label>Título/Nombre <input id="f_title" required /></label>
    <label>Descripción <textarea id="f_desc" rows="2"></textarea></label>

    <label>Inicio <input id="f_start" type="datetime-local" /></label>
    <label>Fin <input id="f_end" type="datetime-local" /></label>

    <div id="blk_conf" class="row" style="gap:16px; align-items:flex-end">
      <label>Ciudad / Lugar <input id="f_city" /></label>
      <label>Fecha (si tu esquema usa 'date') <input id="f_date" type="datetime-local" /></label>
    </div>

    <div id="blk_talk" class="row" style="gap:16px; align-items:flex-end; display:none">
      <label>Conferencia
        <select id="f_conf_select"></select>
      </label>
      <label>Diapositivas (PDF opcional)
        <input type="file" id="f_pdf" accept="application/pdf" />
      </label>
    </div>

    <div id="blk_stream" class="row" style="gap:16px; align-items:flex-end; display:none">
      <label>Modalidad
        <select id="f_mod">
          <option value="presencial">Presencial</option>
          <option value="virtual">Virtual</option>
          <option value="hibrida">Híbrida</option>
        </select>
      </label>
      <label>Lugar <input id="f_venue" /></label>
      <label>Stream URL <input id="f_stream" type="url" /></label>
    </div>

    <div class="row">
      <button class="btn" id="btnSave">Guardar</button>
      <button class="btn outline" type="button" id="btnCancel" style="display:none">Cancelar</button>
    </div>
  </form>
</section>

<section class="card">
  <h2>Mis conferencias</h2>
  <div id="list_conferences" class="list"></div>
</section>

<section class="card">
  <h2>Mis charlas</h2>
  <div id="list_talks" class="list"></div>
</section>

<section class="card">
  <h2>Mis cursos</h2>
  <div id="list_courses" class="list"></div>
</section>

<section class="card">
  <h2>Mis webinars</h2>
  <div id="list_webinars" class="list"></div>
</section>
