<?php
$title = "Oradores — ABRAHAM";
$extra_js = ["/js/speaker.js"];
?>
<section class="card">
  <h2>Crear/Editar — (Charla / Curso / Webinar)</h2>

  <form id="uniForm" class="row" style="gap:16px; align-items:flex-end">
    <input type="hidden" id="item_id" name="item_id" />
    <label>Tipo
      <select id="item_type" name="item_type">
        <!-- <option value="conference">Conferencia</option> -->
        <option value="talk">Charla</option>
        <option value="course">Curso</option>
        <option value="webinar">Webinar</option>
      </select>
    </label>

    <label>Título/Nombre <input id="f_title" name="title" required /></label>
    <label>Descripción <textarea id="f_desc" name="description" rows="2"></textarea></label>

    <label>Inicio <input id="f_start" name="starts_at_local" type="datetime-local" /></label>
    <label>Fin <input id="f_end" name="ends_at_local" type="datetime-local" /></label>

    <!-- Metadatos de conferencia (opcionales) -->
    <div id="blk_conf" class="row" style="gap:16px; align-items:flex-end; display:none">
      <label>Ciudad / Lugar <input id="f_city" name="conf_city" /></label>
      <label>Fecha (si tu esquema usa 'date') <input id="f_date" name="conf_date_local" type="datetime-local" /></label>
    </div>

    <!-- Bloque específico de CHARLA -->
    <div id="blk_talk" class="row" style="gap:16px; align-items:flex-end; display:none">
      <label>Conferencia
        <select id="f_conf_select" name="talk_conf_id"></select>
      </label>
      <label>Diapositivas (PDF opcional)
        <input type="file" id="f_pdf" name="slides_pdf" accept="application/pdf" />
      </label>
      <label>Modalidad
        <select id="f_mod_talk" name="talk_modality">
          <option value="presencial">Presencial</option>
          <option value="virtual">Virtual</option>
          <option value="hibrida">Híbrida</option>
        </select>
      </label>
    </div>

    <!-- Campos de stream para CHARLA (dependen de modalidad) -->
    <div id="blk_stream_talk" class="row" style="gap:16px; align-items:flex-end; display:none">
      <label>Lugar <input id="f_venue_talk" name="talk_venue" /></label>
      <label>Stream URL <input id="f_stream_talk" name="talk_stream_url" type="url" /></label>
    </div>

    <!-- Selección de sala (charla/curso/webinar) -->
    <div id="blk_room" class="row" style="gap:16px; align-items:flex-end; display:none">
      <label>Sala
        <select id="f_room_select" name="room_id"></select>
      </label>
    </div>

    <!-- Bloque de modalidad/stream (curso/webinar) -->
    <div id="blk_stream" class="row" style="gap:16px; align-items:flex-end; display:none">
      <label>Modalidad
        <select id="f_mod" name="modality">
          <option value="presencial">Presencial</option>
          <option value="virtual">Virtual</option>
          <option value="hibrida">Híbrida</option>
        </select>
      </label>
      <label>Lugar <input id="f_venue" name="venue" /></label>
      <label>Stream URL <input id="f_stream" name="stream_url" type="url" /></label>
    </div>

    <!-- NUEVO: Certificado (SOLO para CURSO) -->
    <div id="blk_cert_course" class="row" style="gap:16px; align-items:flex-end; display:none">
      <label>Certificado
        <select id="f_cert" name="cert_enabled">
          <option value="no">Sin certificado</option>
          <option value="si">Con certificado</option>
        </select>
      </label>
      <label id="lbl_cert_url" style="display:none">Formulario de certificación (URL)
        <input id="f_cert_url" name="cert_form_url" type="url" placeholder="https://forms..." />
      </label>
    </div>

    <div class="row">
      <button class="btn" id="btnSave">Guardar</button>
      <button class="btn outline" type="button" id="btnCancel" style="display:none">Cancelar</button>
    </div>
  </form>
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
