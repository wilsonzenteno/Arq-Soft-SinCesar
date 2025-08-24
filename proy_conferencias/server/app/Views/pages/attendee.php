<?php
/** (VISTA) — Ventana de Asistentes */
$title = "Asistentes — ABRAHAM";
$extra_js = ["/js/attendee.js"];
?>
<section class="card">
  <h2>Token JWT (demo)</h2>
  <form id="jwtForm" class="row">
    <input id="jwt" type="text" placeholder="Pega tu JWT de Supabase (opcional para demo)" />
    <button class="btn">Usar token</button>
  </form>
</section>

<div class="grid">
  <section class="card">
    <h2>Conferencias</h2>
    <div id="confs" class="list"></div>
  </section>
  <section class="card">
    <h2>Charlas y votos</h2>
    <div id="talks" class="list"></div>
  </section>
</div>
