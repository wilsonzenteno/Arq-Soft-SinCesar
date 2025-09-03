<?php
$title = "Asistentes — ABRAHAM";
$extra_js = ["/js/attendee.js"];
?>
<div class="drawer-layout">
  <aside id="drawer" class="drawer">
    <div class="drawer-header">
      <div class="profileSmall" id="attProfile">
        <div class="muted">Conectado</div>
        <div><strong>—</strong></div>
      </div>
    </div>
    <nav class="drawer-nav">
      <button class="drawer-link active" data-view="explore">Explorar</button>
      <button class="drawer-link" data-view="mine">Mis inscripciones</button>
    </nav>
  </aside>

  <div class="drawer-backdrop" id="drawerBackdrop"></div>

  <section class="drawer-content">
    <div class="row" style="justify-content:space-between;align-items:center;margin-bottom:8px">
      <button id="menuToggle" class="btn outline" type="button">☰ Menú</button>
      <h2 id="viewTitle" style="margin:8px 0">Explorar</h2>
    </div>

    <!-- ======= EXPLORAR ======= -->
    <div id="view-explore" class="view active">
      <section class="card">
        <h3>Charlas</h3>
        <p class="muted">Explora todas las charlas del sistema.</p>
        <div id="talkCards" class="cards"></div>
      </section>

      <section class="card">
        <h3>Cursos</h3>
        <p class="muted">Cursos disponibles.</p>
        <div id="courseCards" class="cards"></div>
      </section>

      <section class="card">
        <h3>Webinars</h3>
        <p class="muted">Webinars disponibles.</p>
        <div id="webinarCards" class="cards"></div>
      </section>
    </div>

    <!-- ======= MIS INSCRIPCIONES ======= -->
    <div id="view-mine" class="view">
      <section class="card">
        <h3>Mis conferencias</h3>
        <div id="myConfs" class="cards"></div>
      </section>

      <section class="card">
        <h3>Mis charlas</h3>
        <div id="myTalks" class="cards"></div>
      </section>

      <section class="card">
        <h3>Mis cursos</h3>
        <div id="myCourses" class="cards"></div>
      </section>

      <section class="card">
        <h3>Mis webinars</h3>
        <div id="myWebinars" class="cards"></div>
      </section>
    </div>

  </section>
</div>
