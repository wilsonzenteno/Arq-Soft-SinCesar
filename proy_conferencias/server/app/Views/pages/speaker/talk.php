<?php /** @var string $title */ ?>
<div class="container">
  <a href="/index.php?route=/speaker" class="btn outline" style="margin-bottom:12px">Volver</a>

  <h2>Charla — Panel del orador</h2>
  <div class="muted">ID: <span id="d-id"><?php echo htmlspecialchars((string)($_GET['id'] ?? '')); ?></span></div>

  <section class="card" style="margin-top:12px">
    <h3>Título</h3>
    <div id="d-title">—</div>
  </section>

  <section class="grid2" style="margin-top:12px">
    <div class="card">
      <div><strong>Inicio</strong></div>
      <div id="d-start">—</div>
    </div>
    <div class="card">
      <div><strong>Fin</strong></div>
      <div id="d-end">—</div>
    </div>
    <div class="card">
      <div><strong>Sala</strong></div>
      <div id="d-room">—</div>
    </div>
    <div class="card">
      <div><strong>Conferencia</strong></div>
      <div id="d-conf">—</div>
    </div>
  </section>

  <section class="card" style="margin-top:12px">
    <h3>Estadísticas</h3>
    <div class="grid4">
      <div>
        <div class="muted">Inscritos</div>
        <div id="st-registered">0</div>
      </div>
      <div>
        <div class="muted">Me gusta</div>
        <div id="st-likes">0</div>
      </div>
      <div>
        <div class="muted">No me gusta</div>
        <div id="st-dislikes">0</div>
      </div>
      <div>
        <div class="muted"># Evaluaciones</div>
        <div id="st-evals">0</div>
      </div>
    </div>

    <div class="grid4" style="margin-top:8px">
      <div>
        <div class="muted">Promedio — útil</div>
        <div id="st-avg-q1">—</div>
      </div>
      <div>
        <div class="muted">Promedio — expectativas</div>
        <div id="st-avg-q2">—</div>
      </div>
      <div>
        <div class="muted">Promedio — contenido</div>
        <div id="st-avg-q3">—</div>
      </div>
      <div>
        <div class="muted">Promedio — logística</div>
        <div id="st-avg-q4">—</div>
      </div>
    </div>
  </section>

  <!-- Acciones: Ver inscritos / Ver evaluaciones -->
  <section class="card" id="speakerActions" style="margin-top:12px">
    <h3>Acciones</h3>
    <div class="row" style="gap:8px">
      <button class="btn" type="button" id="btnShowRegs">Ver inscritos</button>
      <button class="btn outline" type="button" id="btnShowEvals">Ver evaluaciones</button>
    </div>
    <div id="regsBox" class="item" style="display:none;margin-top:8px"></div>
    <div id="evalsBox" class="item" style="display:none;margin-top:8px"></div>
  </section>
</div>

<script>
(function(){
  const $ = s => document.querySelector(s);
  const qs = new URLSearchParams(location.search);
  const id = parseInt(qs.get('id') || '0', 10);

  function getSupa(){
    if (!window.supabase || !window.ENV?.SUPABASE_URL || !window.ENV?.SUPABASE_ANON) return null;
    if (!window.__SUPA) {
      window.__SUPA = window.supabase.createClient(window.ENV.SUPABASE_URL, window.ENV.SUPABASE_ANON);
    }
    return window.__SUPA;
  }
  async function getJwt(){
    let jwt = localStorage.getItem('jwt');
    if (jwt) return jwt;
    const supa = getSupa();
    if (supa) {
      const { data } = await supa.auth.getSession();
      jwt = data?.session?.access_token || null;
      if (jwt) localStorage.setItem('jwt', jwt);
    }
    return jwt;
  }
  // route limpio + query params
  async function api(route, paramsOrMethod='GET', methodOpt){
    let params = null, method = 'GET';
    if (typeof paramsOrMethod === 'string') { method = paramsOrMethod; }
    else if (paramsOrMethod && typeof paramsOrMethod === 'object') { params = paramsOrMethod; }
    if (typeof methodOpt === 'string') method = methodOpt;

    let url = `/index.php?route=${encodeURIComponent(route)}`;
    if (params && Object.keys(params).length) {
      const sp = new URLSearchParams();
      for (const [k,v] of Object.entries(params)) if (v !== undefined && v !== null) sp.append(k, String(v));
      url += `&${sp.toString()}`;
    }

    const headers = {};
    const jwt = await getJwt();
    if (jwt) headers['Authorization'] = 'Bearer ' + jwt;

    const res = await fetch(url, { method, headers, credentials: 'include' });
    const txt = await res.text();
    let data = null; try { data = txt ? JSON.parse(txt) : null; } catch { data = txt; }
    if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Request failed'));
    return data;
  }

  function setTxt(sel, v){ const el = $(sel); if (el) el.textContent = (v ?? '—'); }
  function dt(v){ return v ? new Date(v).toLocaleString() : '—'; }
  const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);

  async function loadTalk(){
    try {
      const mine = await api('/speaker.talks.mine','GET');
      const row = asArray(mine).find(x => Number(x.id) === id);
      if (row) {
        setTxt('#d-title', row.title || '(sin título)');
        setTxt('#d-start', dt(row.starts_at || row.start_time));
        setTxt('#d-end',   dt(row.ends_at   || row.end_time));
        setTxt('#d-room',  (row.room_id != null && row.room_id !== '') ? String(row.room_id) : '—');
        setTxt('#d-conf',  (row.conference_id != null && row.conference_id !== '') ? String(row.conference_id) : '—');
      } else {
        setTxt('#d-title', '(charla)');
      }
    } catch (e) {
      console.warn('speaker.talks.mine falló:', e?.message || e);
    }

    const st = await api('/speaker.talk.stats', { id }, 'GET');
    setTxt('#st-registered', String(st.registered ?? 0));
    setTxt('#st-likes',      String(st.likes ?? 0));
    setTxt('#st-dislikes',   String(st.dislikes ?? 0));
    setTxt('#st-evals',      String(st.evals_count ?? 0));
    setTxt('#st-avg-q1',     (st.avg && st.avg.q1 != null) ? String(st.avg.q1) : '—');
    setTxt('#st-avg-q2',     (st.avg && st.avg.q2 != null) ? String(st.avg.q2) : '—');
    setTxt('#st-avg-q3',     (st.avg && st.avg.q3 != null) ? String(st.avg.q3) : '—');
    setTxt('#st-avg-q4',     (st.avg && st.avg.q4 != null) ? String(st.avg.q4) : '—');
  }

  // ==== Acciones (inscritos / evaluaciones) ====
  function escapeHtml(s){
    return String(s??'').replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]));
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
                <td>${r.q1_useful ?? '—'}</td>
                <td>${r.q2_expectations ?? '—'}</td>
                <td>${r.q3_content ?? '—'}</td>
                <td>${r.q4_logistics ?? '—'}</td>
                <td>${r.comments ? escapeHtml(r.comments) : '—'}</td>
                <td>${dt(r.updated_at)}</td>
              </tr>
            `).join('')}
          </tbody>
        </table>
      </div>`;
  }
  async function loadRegs(){ return await api('/speaker.talk.registrations', { id }, 'GET'); }
  async function loadEvals(){ return await api('/speaker.talk.evaluations', { id }, 'GET'); }

  function bindActions(){
    const btnRegs = $('#btnShowRegs');
    const btnEvals = $('#btnShowEvals');
    const regsBox = $('#regsBox');
    const evalsBox = $('#evalsBox');

    btnRegs?.addEventListener('click', async ()=>{
      try{
        btnRegs.disabled = true;
        regsBox.style.display = 'block';
        regsBox.innerHTML = '<div class="muted">Cargando inscritos…</div>';
        const rows = await loadRegs();
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
        const rows = await loadEvals();
        evalsBox.innerHTML = tableEvals(rows);
      }catch(e){
        evalsBox.innerHTML = `<div class="muted">No se pudo cargar: ${escapeHtml(e?.message||e)}</div>`;
      }finally{
        btnEvals.disabled = false;
      }
    });
  }

  document.addEventListener('DOMContentLoaded', async ()=>{
    if (!id) return;
    try {
      await loadTalk();
      bindActions();
    } catch (e) {
      console.error(e);
      alert('No se pudieron cargar los detalles/estadísticas: ' + (e?.message || e));
    }
  });
})();
</script>
