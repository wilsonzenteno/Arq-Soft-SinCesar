// public/js/speaker.detail.js
(function(){
  'use strict';

  const $ = s => document.querySelector(s);
  const qs = new URLSearchParams(location.search);
  const id = parseInt(qs.get('id') || '0', 10);

  /* ============ JWT / Supabase (para rutas protegidas) ============ */
  async function getJwt(){
    let jwt = localStorage.getItem('jwt');
    if (jwt) return jwt;
    try{
      if (window.supabase && window.ENV?.SUPABASE_URL && window.ENV?.SUPABASE_ANON) {
        const supa = window.supabase.createClient(window.ENV.SUPABASE_URL, window.ENV.SUPABASE_ANON);
        const { data } = await supa.auth.getSession();
        jwt = data?.session?.access_token || null;
        if (jwt) localStorage.setItem('jwt', jwt);
      }
    }catch{}
    return jwt;
  }

  /* ============ API helper ============ 
     IMPORTANTE: NO usar encodeURIComponent en toda la ruta porque rompe "&id=".
     Construimos ?route=/endpoint&param=... literal. */
  async function api(route, method='GET', body=null){
    const clean = route.startsWith('/') ? route : ('/' + route);
    const url   = `/index.php?route=${clean}`;

    const headers = {};
    const jwt = await getJwt();
    if (jwt) headers.Authorization = 'Bearer ' + jwt;
    if (body && !(body instanceof FormData)) headers['Content-Type'] = 'application/json; charset=utf-8';

    const res = await fetch(url, {
      method,
      headers,
      credentials: 'include',
      body: body ? (body instanceof FormData ? body : JSON.stringify(body)) : undefined
    });

    const txt = await res.text();
    let data = null; try { data = txt ? JSON.parse(txt) : null; } catch { data = txt; }

    if (!res.ok) {
      const msg = (data && data.error) ? data.error : (res.statusText || 'Request failed');
      throw new Error(msg);
    }
    return data;
  }

  /* ============ Utils / normalizadores ============ */
  function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"'`=\/]/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'
    }[c]));
  }
  function dt(v){ return v ? new Date(v).toLocaleString() : '—'; }
  function num(v){ return (v===null || v===undefined) ? '—' : String(v); }

  // Acepta {rows:[...]}, {data:[...]}, {items:[...]}, arrays planos, etc.
  function asArray(x){
    if (Array.isArray(x)) return x;
    if (!x || typeof x !== 'object') return [];
    const candidates = ['rows','data','items','list','registrations','evaluations','result'];
    for (const k of candidates){ if (Array.isArray(x[k])) return x[k]; }
    for (const v of Object.values(x)){ if (Array.isArray(v)) return v; }
    return [];
  }

  function toCSV(rows, headers){
    const esc = (v) => {
      const s = v == null ? '' : String(v);
      const needsQuotes = /[",\n]|(^\s)|(\s$)/.test(s);
      const q = s.replace(/"/g, '""');
      return needsQuotes ? `"${q}"` : q;
    };
    const head = headers.map(h => esc(h.title)).join(',');
    const body = rows.map(r => headers.map(h => esc(h.get(r))).join(',')).join('\n');
    return head + '\n' + body;
  }
  function downloadBlob(filename, content, mime='text/csv;charset=utf-8'){
    const blob = new Blob([content], { type: mime });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    setTimeout(()=>{ URL.revokeObjectURL(a.href); a.remove(); }, 0);
  }

  /* ============ Marcup contenedor de acciones ============ */
  function mountActionArea(){
    let host = document.getElementById('speakerActions');
    if (host) return host;

    host = document.createElement('section');
    host.className = 'card';
    host.id = 'speakerActions';
    host.innerHTML = `
      <h3>Acciones</h3>
      <div class="row" style="gap:8px;flex-wrap:wrap">
        <button class="btn" type="button" id="btnShowRegs">Ver inscritos</button>
        <button class="btn outline" type="button" id="btnRegsCsv" style="display:none">Exportar inscritos CSV</button>
        <button class="btn outline" type="button" id="btnShowEvals">Ver evaluaciones</button>
        <button class="btn outline" type="button" id="btnEvalsCsv" style="display:none">Exportar evaluaciones CSV</button>
        <button class="btn outline" type="button" id="btnRefreshStats">Refrescar estadísticas</button>
      </div>
      <div id="statsMirror" class="item" style="display:none;margin-top:8px"></div>
      <div id="regsBox" class="item" style="display:none;margin-top:8px"></div>
      <div id="evalsBox" class="item" style="display:none;margin-top:8px"></div>
    `;
    const main = document.getElementById('content') || document.body;
    main.appendChild(host);
    return host;
  }

  /* ============ Tablas (SIEMPRE usan asArray, nunca rows.map directo) ============ */
  function tableRegs(rows){
    const arr = asArray(rows);
    if (!arr.length) return '<div class="muted">Sin inscritos</div>';
    return `
      <div class="item">
        <div class="muted" style="margin-bottom:6px">${arr.length} inscrito(s)</div>
        <div style="overflow:auto">
          <table class="tbl">
            <thead><tr><th>Nombre</th><th>Usuario</th><th>Inscrito el</th></tr></thead>
            <tbody>
              ${arr.map(r=>`
                <tr>
                  <td>${r.name ? escapeHtml(r.name) : '—'}</td>
                  <td><code>${escapeHtml(r.attendee_id || '')}</code></td>
                  <td>${dt(r.registered_at)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>`;
  }

  function tableEvals(rows){
    const arr = asArray(rows);
    if (!arr.length) return '<div class="muted">Sin evaluaciones</div>';
    return `
      <div class="item">
        <div class="muted" style="margin-bottom:6px">${arr.length} evaluación(es)</div>
        <div style="overflow:auto">
          <table class="tbl">
            <thead>
              <tr>
                <th>Nombre</th><th>Usuario</th>
                <th>Útil</th><th>Expectativas</th><th>Contenido</th><th>Logística</th>
                <th>Comentarios</th><th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              ${arr.map(r=>`
                <tr>
                  <td>${r.name ? escapeHtml(r.name) : '—'}</td>
                  <td><code>${escapeHtml(r.attendee_id || '')}</code></td>
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
        </div>
      </div>`;
  }

  function statsBlock(st){
    if (!st || typeof st !== 'object') return '';
    const likes     = Number(st.likes ?? 0) || 0;
    const dislikes  = Number(st.dislikes ?? 0) || 0;
    const regs      = Number(st.registrations ?? 0) || 0;
    const evals     = Number(st.evaluations ?? 0) || 0;

    const avg = (v)=> v==null ? '—' : String(v);
    const avgU = st.avg_useful       ?? st.avg_q1;
    const avgE = st.avg_expectations ?? st.avg_q2;
    const avgC = st.avg_content      ?? st.avg_q3;
    const avgL = st.avg_logistics    ?? st.avg_q4;

    return `
      <div class="item">
        <div class="muted" style="margin-bottom:6px">Estadísticas (espejo)</div>
        <div class="meta-grid">
          <div class="meta"><span class="k">Inscritos</span><span class="v">${regs}</span></div>
          <div class="meta"><span class="k">Me gusta</span><span class="v">${likes}</span></div>
          <div class="meta"><span class="k">No me gusta</span><span class="v">${dislikes}</span></div>
          <div class="meta"><span class="k"># Evaluaciones</span><span class="v">${evals}</span></div>
        </div>
        <div class="meta-grid">
          <div class="meta"><span class="k">Promedio — útil</span><span class="v">${avg(avgU)}</span></div>
          <div class="meta"><span class="k">Promedio — expectativas</span><span class="v">${avg(avgE)}</span></div>
          <div class="meta"><span class="k">Promedio — contenido</span><span class="v">${avg(avgC)}</span></div>
          <div class="meta"><span class="k">Promedio — logística</span><span class="v">${avg(avgL)}</span></div>
        </div>
      </div>`;
  }

  /* ============ Cargas ============ */
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
  async function loadStats(kind){
    const route = kind==='talk'    ? `/speaker.talk.stats&id=${id}`
                : kind==='course'  ? `/speaker.course.stats&id=${id}`
                :                     `/speaker.webinar.stats&id=${id}`;
    return await api(route, 'GET');
  }

  /* ============ Bind principal ============ */
  function bind(kind){
    mountActionArea();

    const btnRegs      = $('#btnShowRegs');
    const btnRegsCsv   = $('#btnRegsCsv');
    const btnEvals     = $('#btnShowEvals');
    const btnEvalsCsv  = $('#btnEvalsCsv');
    const btnRefStats  = $('#btnRefreshStats');
    const regsBox      = $('#regsBox');
    const evalsBox     = $('#evalsBox');
    const statsMirror  = $('#statsMirror');

    let lastRegs  = [];
    let lastEvals = [];

    btnRegs?.addEventListener('click', async ()=>{
      try{
        btnRegs.disabled = true;
        regsBox.style.display = 'block';
        regsBox.innerHTML = '<div class="muted">Cargando inscritos…</div>';
        const rows = asArray(await loadRegs(kind));
        lastRegs = rows;
        regsBox.innerHTML = tableRegs(rows);
        btnRegsCsv.style.display = lastRegs.length ? '' : 'none';
      }catch(e){
        regsBox.innerHTML = `<div class="muted">No se pudo cargar: ${escapeHtml(e?.message||e)}</div>`;
        btnRegsCsv.style.display = 'none';
      }finally{
        btnRegs.disabled = false;
      }
    });

    btnRegsCsv?.addEventListener('click', ()=>{
      if (!lastRegs.length) return;
      const csv = toCSV(lastRegs, [
        { title: 'Nombre',           get: r => r.name ?? '' },
        { title: 'Usuario (UUID)',   get: r => r.attendee_id ?? '' },
        { title: 'Inscrito el',      get: r => r.registered_at ? new Date(r.registered_at).toISOString() : '' }
      ]);
      downloadBlob(`inscritos_${kind}_${id}.csv`, csv);
    });

    btnEvals?.addEventListener('click', async ()=>{
      try{
        btnEvals.disabled = true;
        evalsBox.style.display = 'block';
        evalsBox.innerHTML = '<div class="muted">Cargando evaluaciones…</div>';
        const rows = asArray(await loadEvals(kind));
        lastEvals = rows;
        evalsBox.innerHTML = tableEvals(rows);
        btnEvalsCsv.style.display = lastEvals.length ? '' : 'none';
      }catch(e){
        evalsBox.innerHTML = `<div class="muted">No se pudo cargar: ${escapeHtml(e?.message||e)}</div>`;
        btnEvalsCsv.style.display = 'none';
      }finally{
        btnEvals.disabled = false;
      }
    });

    btnEvalsCsv?.addEventListener('click', ()=>{
      if (!lastEvals.length) return;
      const csv = toCSV(lastEvals, [
        { title: 'Nombre',           get: r => r.name ?? '' },
        { title: 'Usuario (UUID)',   get: r => r.attendee_id ?? '' },
        { title: 'Útil',             get: r => r.q1_useful ?? '' },
        { title: 'Expectativas',     get: r => r.q2_expectations ?? '' },
        { title: 'Contenido',        get: r => r.q3_content ?? '' },
        { title: 'Logística',        get: r => r.q4_logistics ?? '' },
        { title: 'Comentarios',      get: r => r.comments ?? '' },
        { title: 'Fecha',            get: r => r.updated_at ? new Date(r.updated_at).toISOString() : '' }
      ]);
      downloadBlob(`evaluaciones_${kind}_${id}.csv`, csv);
    });

    btnRefStats?.addEventListener('click', async ()=>{
      try{
        btnRefStats.disabled = true;
        statsMirror.style.display = 'block';
        statsMirror.innerHTML = '<div class="muted">Actualizando estadísticas…</div>';
        const st = await loadStats(kind);
        statsMirror.innerHTML = statsBlock(st);
      }catch(e){
        statsMirror.innerHTML = `<div class="muted">No se pudo cargar estadísticas: ${escapeHtml(e?.message||e)}</div>`;
      }finally{
        btnRefStats.disabled = false;
      }
    });

    // Primera carga de stats espejo (likes/dislikes/averages)
    (async ()=>{
      try{
        const st = await loadStats(kind);
        if (st) {
          statsMirror.style.display = 'block';
          statsMirror.innerHTML = statsBlock(st);
        }
      }catch{}
    })();
  }

  /* ============ Boot ============ */
  document.addEventListener('DOMContentLoaded', ()=>{
    if (!id) return;
    const path = location.pathname;
    if (path.includes('/speaker/talk'))        bind('talk');
    else if (path.includes('/speaker/course')) bind('course');
    else if (path.includes('/speaker/webinar')) bind('webinar');
  });
})();
