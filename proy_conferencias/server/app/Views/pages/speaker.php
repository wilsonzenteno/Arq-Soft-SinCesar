<?php
/** (VISTA) — Ventana de Oradores */
$title = "Oradores — ABRAHAM";
$extra_js = ["/js/speaker.js"];
?>
<section class="card">
  <h2>Token JWT (demo)</h2>
  <form id="jwtForm" class="row">
    <input id="jwt" type="text" placeholder="Pega tu JWT de Supabase" />
    <button class="btn">Usar token</button>
  </form>
</section>

<section class="card">
  <h2>Mis charlas</h2>
  <div class="row">
    <label>Reclamar charla por ID
      <div class="row">
        <input id="claimTalkId" type="number" placeholder="ID charla" />
        <button id="btnClaim" class="btn">Reclamar</button>
      </div>
    </label>
  </div>
  <div id="myTalks" class="list"></div>
</section>

<section class="card">
  <h2>Subir diapositivas (PDF)</h2>
  <form id="uploadForm">
    <label>ID charla<input type="number" id="talkId" required /></label>
    <label>PDF<input type="file" id="file" accept="application/pdf" required /></label>
    <button class="btn" type="submit">Subir</button>
  </form>
</section>
