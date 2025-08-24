<?php
/** (VISTA) — Ventana de Staff/Admin */
$title = "Staff — ABRAHAM";
$extra_js = ["/js/staff.js"];
?>
<section class="card">
  <h2>Token JWT (demo)</h2>
  <form id="jwtForm" class="row">
    <input id="jwt" type="text" placeholder="Pega tu JWT (rol admin/staff)" />
    <button class="btn">Usar token</button>
  </form>
</section>

<div class="grid">
  <section class="card">
    <h2>Crear conferencia</h2>
    <form id="formConf">
      <label>Nombre<input id="cname" /></label>
      <label>Ciudad<input id="ccity" /></label>
      <label>Inicio<input id="cstart" type="datetime-local" /></label>
      <label>Fin<input id="cend" type="datetime-local" /></label>
      <button class="btn">Crear</button>
    </form>
  </section>

  <section class="card">
    <h2>Crear sala</h2>
    <form id="formRoom">
      <label>ID conferencia<input id="rid" type="number" /></label>
      <label>Nombre sala<input id="rname" /></label>
      <button class="btn">Crear</button>
    </form>
  </section>

  <section class="card">
    <h2>Crear charla</h2>
    <form id="formTalk">
      <label>ID conferencia<input id="tidc" type="number" /></label>
      <label>ID sala (opcional)<input id="troom" type="number" /></label>
      <label>Título<input id="ttitle" /></label>
      <label>Inicio<input id="tstart" type="datetime-local" /></label>
      <label>Fin<input id="tend" type="datetime-local" /></label>
      <label>Speaker UUID (opcional)<input id="tspeaker" /></label>
      <button class="btn">Crear</button>
    </form>
  </section>

  <section class="card">
    <h2>Nuevo anuncio</h2>
    <form id="formAnn">
      <label>Título<input id="anntitle" /></label>
      <label>Mensaje<textarea id="annbody"></textarea></label>
      <label>ID conferencia (opcional)<input id="annconf" type="number" /></label>
      <button class="btn">Publicar</button>
    </form>
  </section>
</div>
