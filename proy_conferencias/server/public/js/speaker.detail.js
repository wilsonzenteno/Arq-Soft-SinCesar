// public/js/speaker.detail.js
(function(){
  const $ = s => document.querySelector(s);
  const qs = new URLSearchParams(location.search);
  const id = parseInt(qs.get('id') || '0', 10);

  async function api(route, method="GET"){
    const res = await fetch(`/index.php?route=${encodeURIComponent(route)}`, {
      method, credentials: 'include'
    });
    const txt = await res.text();
    let data = null; try{ data = txt ? JSON.parse(txt) : null; } catch { data = txt; }
    if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Request failed'));
    return data;
  }
  function dt(v){ return v ? new Date(v).toLocaleString() : '—'; }
  function mountActionArea(){
    // Crea contenedor de acciones bajo el bloque de estadísticas si no existe
    let host = document.getElementById('speakerActions');
    if (host) return host;

    // intenta ubicarlo después del primer <section> o al final del #content
    host = document.createElement('section');
    host.className = 'card';
    host.id = 'speakerActions';
    host.innerHTML = `
      <h3>Acciones</h3>
      <div class="row" style="gap:8px">
        <button class="btn" type="button" id="btnShowRegs">Ver inscritos</button>
        <button class="btn outline" type="button" id="btnShowEvals">Ver evaluaciones</button>
      </div>
      <div id="regsBox" class="item" style="display:none;margin-top:8px"></div>
      <div id="evalsBox" class="item" style="display:none;margin-top:8px"></div>
    `;
    // inserta
    const main = document.getElementById('content') || document.body;
    main.appendChild(host);
    return host;
  }

  function tableRegs(rows){
    if (!rows || !rows.length) return '<div class="muted">Sin inscritos</div>';
    return `
      <div class="item">
        <div class="muted" style="margin-bottom:6px">${rows.length} inscrito(s)</div>
        <table class="tbl">
          <thead><tr><th>Nombre</th><th>Usuario</th><th>Inscrito el</th></tr></thead>
          <tbody>
            ${rows.map(r=>`
              <tr>
                <td>${r.name ? escapeHtml(r.name) : '—'}</td>
                <td><code>${r.attendee_id}</code></td>
                <td>${dt(r.registered_at)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>`;
  }

  function tableEvals(rows){
    if (!rows || !rows.length) return '<div class="muted">Sin evaluaciones</div>';
    return `
      <div class="item">
        <div class="muted" style="margin-bottom:6px">${rows.length} evaluación(es)</div>
        <table class="tbl">
          <thead>
            <tr>
              <th>Nombre</th><th>Usuario</th>
              <th>Útil</th><th>Expectativas</th><th>Contenido</th><th>Logística</th>
              <th>Comentarios</th><th>Fecha</th>
            </tr>
          </thead>
          <tbody>
            ${rows.map(r=>`
              <tr>
                <td>${r.name ? escapeHtml(r.name) : '—'}</td>
                <td><code>${r.attendee_id}</code></td>
                <td>${num(r.q1_useful)}</td>
                <td>${num(r.q2_expectations)}</td>
                <td>${num(r.q3_content)}</td>
                <td>${num(r.q4_logistics)}</td>
                <td>${r.comments ? escapeHtml(r.comments) : '—'}</td>
                <td>${dt(r.updated_at)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>`;
  }

  function escapeHtml(s){
    return String(s??'').replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]));
  }
  function num(v){ return (v===null || v===undefined) ? '—' : String(v); }

  async function loadRegs(kind){
    const route = kind==='talk'    ? `/speaker.talk.registrations&id=${id}`
                : kind==='course'  ? `/speaker.course.registrations&id=${id}`
                :                     `/speaker.webinar.registrations&id=${id}`;
    return await api(route, 'GET');
  }
  async function loadEvals(kind){
    const route = kind==='talk'    ? `/speaker.talk.evaluations&id=${id}`
                : kind==='course'  ? `/speaker.course.evaluations&id=${id}`
                :                     `/speaker.webinar.evaluations&id=${id}`;
    return await api(route, 'GET');
  }

  function bind(kind){
    const host = mountActionArea();
    const btnRegs = $('#btnShowRegs');
    const btnEvals = $('#btnShowEvals');
    const regsBox = $('#regsBox');
    const evalsBox = $('#evalsBox');

    btnRegs?.addEventListener('click', async ()=>{
      try{
        btnRegs.disabled = true;
        regsBox.style.display = 'block';
        regsBox.innerHTML = '<div class="muted">Cargando inscritos…</div>';
        const rows = await loadRegs(kind);
        regsBox.innerHTML = tableRegs(rows);
      }catch(e){
        regsBox.innerHTML = `<div class="muted">No se pudo cargar: ${escapeHtml(e?.message||e)}</div>`;
      }finally{
        btnRegs.disabled = false;
      }
    });

    btnEvals?.addEventListener('click', async ()=>{
      try{
        btnEvals.disabled = true;
        evalsBox.style.display = 'block';
        evalsBox.innerHTML = '<div class="muted">Cargando evaluaciones…</div>';
        const rows = await loadEvals(kind);
        evalsBox.innerHTML = tableEvals(rows);
      }catch(e){
        evalsBox.innerHTML = `<div class="muted">No se pudo cargar: ${escapeHtml(e?.message||e)}</div>`;
      }finally{
        btnEvals.disabled = false;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', ()=>{
    if (!id) return;
    const path = location.pathname;
    if (path.includes('/speaker/talk'))      bind('talk');
    else if (path.includes('/speaker/course')) bind('course');
    else if (path.includes('/speaker/webinar')) bind('webinar');
  });
})();
