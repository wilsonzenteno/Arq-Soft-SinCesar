<?php /** @var string $title */ ?>
<div class="container">
  <a href="/index.php?route=/speaker" class="btn outline" style="margin-bottom:12px">Volver</a>

  <h2>Webinar — Panel del orador</h2>
  <div class="muted">ID: <span id="d-id"><?php echo htmlspecialchars((string)($_GET['id'] ?? '')); ?></span></div>

  <section class="card" style="margin-top:12px">
    <h3>Título</h3>
    <div id="d-title">—</div>
  </section>

  <section class="grid2" style="margin-top:12px">
    <div class="card"><div><strong>Inicio</strong></div><div id="d-start">—</div></div>
    <div class="card"><div><strong>Fin</strong></div><div id="d-end">—</div></div>
    <div class="card"><div><strong>Modalidad</strong></div><div id="d-mod">—</div></div>
    <div class="card"><div><strong>Lugar</strong></div><div id="d-venue">—</div></div>
    <div class="card"><div><strong>Stream</strong></div><div id="d-stream">—</div></div>
  </section>

  <section class="card" style="margin-top:12px">
    <h3>Estadísticas</h3>
    <div class="grid4">
      <div><div class="muted">Inscritos</div><div id="st-registered">0</div></div>
      <div><div class="muted">Me gusta</div><div id="st-likes">0</div></div>
      <div><div class="muted">No me gusta</div><div id="st-dislikes">0</div></div>
      <div><div class="muted"># Evaluaciones</div><div id="st-evals">0</div></div>
    </div>
    <div class="grid4" style="margin-top:8px">
      <div><div class="muted">Promedio — útil</div><div id="st-avg-q1">—</div></div>
      <div><div class="muted">Promedio — expectativas</div><div id="st-avg-q2">—</div></div>
      <div><div class="muted">Promedio — contenido</div><div id="st-avg-q3">—</div></div>
      <div><div class="muted">Promedio — logística</div><div id="st-avg-q4">—</div></div>
    </div>
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

  async function loadWebinar(){
    try {
      const mine = await api('/speaker.webinars.mine','GET');
      const row = asArray(mine).find(x => Number(x.id) === id);
      if (row) {
        setTxt('#d-title', row.title || '(sin título)');
        setTxt('#d-start', dt(row.starts_at));
        setTxt('#d-end',   dt(row.ends_at));
        setTxt('#d-mod',   row.modality || '—');
        setTxt('#d-venue', row.venue || '—');
        setTxt('#d-stream',row.stream_url || '—');
      } else {
        setTxt('#d-title', '(webinar)');
      }
    } catch (e) { console.warn('speaker.webinars.mine falló:', e?.message || e); }

    const st = await api('/speaker.webinar.stats', { id }, 'GET');
    setTxt('#st-registered', String(st.registered ?? 0));
    setTxt('#st-likes',      String(st.likes ?? 0));
    setTxt('#st-dislikes',   String(st.dislikes ?? 0));
    setTxt('#st-evals',      String(st.evals_count ?? 0));
    setTxt('#st-avg-q1',     (st.avg && st.avg.q1 != null) ? String(st.avg.q1) : '—');
    setTxt('#st-avg-q2',     (st.avg && st.avg.q2 != null) ? String(st.avg.q2) : '—');
    setTxt('#st-avg-q3',     (st.avg && st.avg.q3 != null) ? String(st.avg.q3) : '—');
    setTxt('#st-avg-q4',     (st.avg && st.avg.q4 != null) ? String(st.avg.q4) : '—');
  }

  document.addEventListener('DOMContentLoaded', async ()=>{
    if (!id) return;
    try { await loadWebinar(); }
    catch (e) { console.error(e); alert('No se pudieron cargar los detalles/estadísticas: ' + (e?.message || e)); }
  });
})();
</script>
