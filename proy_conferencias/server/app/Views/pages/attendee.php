<?php
$title = "Asistentes — ABRAHAM";
$extra_js = ["/js/attendee.js"];
?>
<section class="card">
  <h2>Conferencias</h2>
  <div id="confList" class="list"></div>
</section>

<section class="card" id="talksSection">
  <div class="row" style="justify-content:space-between;align-items:center">
    <h2>Programa</h2>
    <label class="row" style="gap:8px">Conferencia
      <select id="confSelect"></select>
    </label>
  </div>

  <div class="row" style="gap:12px;align-items:center;margin-bottom:10px">
    <button id="btnConfLike" class="btn outline" type="button">♥ Me gusta</button>
    <span class="muted" id="likeHint">El voto será como dar likes en una post; la evaluación mide si la conferencia te sirvió, cubrió expectativas y qué optimizar.</span>
  </div>

  <details class="item" id="evalBox">
    <summary>Evaluar conferencia</summary>
    <form id="evalForm" class="row" style="gap:12px;align-items:flex-end">
      <label>¿Te resultó útil?
        <select id="q1"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
      </label>
      <label>¿Cubrió tus expectativas?
        <select id="q2"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
      </label>
      <label>Calidad del contenido
        <select id="q3"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
      </label>
      <label>Logística/instalaciones
        <select id="q4"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
      </label>
      <label style="flex:1">Comentarios
        <input id="comments" placeholder="¿Algo a mejorar?" />
      </label>
      <button class="btn">Guardar evaluación</button>
    </form>
  </details>

  <div id="talkList" class="list"></div>
</section>

<section class="card">
  <h2>Explorar todo</h2>
  <div class="grid">
    <div>
      <h3>Charlas</h3>
      <div id="allTalks" class="list"></div>
    </div>
    <div>
      <h3>Cursos</h3>
      <div id="allCourses" class="list"></div>
    </div>
    <div>
      <h3>Webinars</h3>
      <div id="allWebinars" class="list"></div>
    </div>
  </div>
</section>
