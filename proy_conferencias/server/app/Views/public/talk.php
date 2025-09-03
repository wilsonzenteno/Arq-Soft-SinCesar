<section class="card">
  <h2>Charla</h2>
  <div id="talkBox" class="item">Cargando…</div>
</section>

<style>
  /* mejora visual y estados */
  .btn[disabled], .btn.disabled { opacity: .6; pointer-events: none; }
  .kpi { display:grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap:10px; margin-top:8px; }
  .kpi > div { background:#0b1220; border:1px solid #1f2937; border-radius:10px; padding:10px; }
  @media (max-width: 720px){ .kpi { grid-template-columns: 1fr 1fr; } }
  .meta { display:grid; gap:6px; margin-top:8px; }
</style>

<script>
(async () => {
  // Helpers
  const $ = s => document.querySelector(s);
  const fmt = (d)=> d ? new Date(d).toLocaleString() : '—';
  const iso = (d)=> d ? new Date(d).toISOString() : null;
  const nowISO = ()=> new Date().toISOString();

  // Usa el wrapper común si existe (lo carga layout.php)
  async function api(route, method="GET", body=null){
    if (window.api) return window.api(route, method, body);
    // fallback mínimo (por si alguien quitó common.js)
    const headers = { 'Content-Type': 'application/json' };
    const res = await fetch(`/index.php?route=${route}`, {
      method, headers,
      body: body ? JSON.stringify(body) : undefined,
      credentials: 'include'
    });
    const txt = await res.text();
    let data = null; try { data = txt ? JSON.parse(txt) : null; } catch { data = txt; }
    if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Request failed'));
    return data;
  }

  const params = new URLSearchParams(location.search);
  const talkId = params.get('id');
  if (!talkId) { $('#talkBox').innerText = 'ID faltante'; return; }

  // ---- Estado local de la vista ----
  const state = {
    talk: null,
    confId: null,
    registered: false,
    ended: false,
    role: null,      // admin | staff | speaker | attendee
    userId: null
  };

  // Carga detalle de la charla (rest.php de tu proyecto)
  async function loadTalk(){
    const res = await fetch(`/rest.php?type=talk&id=${encodeURIComponent(talkId)}`);
    if (!res.ok) throw new Error('No encontrado');
    const t = await res.json();
    // intenta inferir conference_id de varias formas
    let confId = t.conference_id || (t.conference && t.conference.id) || null;
    // si no lo trae, lo buscamos en el catálogo global (attendee.talks.all)
    if (!confId) {
      try {
        const all = await api('attendee.talks.all','GET');
        const arr = Array.isArray(all) ? all : (all?.data ?? []);
        const hit = arr.find(x => String(x.id) === String(talkId));
        if (hit && hit.conference_id) confId = hit.conference_id;
      } catch(e) { /* noop */ }
    }
    state.talk = t;
    state.confId = confId || null;
    const end = t.end_time || t.ends_at || null;
    state.ended = end ? (new Date(end).toISOString() < nowISO()) : false;
  }

  // Carga rol/usuario (si hay sesión)
  async function loadMe(){
    try {
      const me = await api('auth.role','GET');
      state.role = me.role || null;
      state.userId = me.user_id || null;
    } catch(e){
      // 401 si no hay sesión
      state.role = null; state.userId = null;
    }
  }

  // Consulta inscripciones del usuario y marca "registered" si está inscrito a la conferencia de esta charla
  async function loadMyRegs(){
    if (!state.confId) { state.registered = false; return; }
    try{
      const regs = await api('attendee.registrations.mine','GET');
      const arr = Array.isArray(regs) ? regs : (regs?.data ?? []);
      const set = new Set(arr.map(r => r.conference_id));
      state.registered = set.has(state.confId);
    }catch(e){
      state.registered = false;
    }
  }

  function canDownloadSlides(){
    // El servidor validará de todos modos, pero permitimos el intento si:
    // - está inscrito, o
    // - es admin/staff, o
    // - (opcional) speaker de la charla (si JSON trae speaker_id y coincide)
    if (state.registered) return true;
    if (state.role === 'admin' || state.role === 'staff') return true;
    if (state.talk && state.talk.speaker_id && state.userId && String(state.talk.speaker_id) === String(state.userId)) return true;
    return false;
  }

  function render(){
    const t = state.talk || {};
    const title = t.title || '(sin título)';
    const desc  = t.description ? `<div class="muted" style="white-space:pre-wrap">${t.description}</div>` : '';
    const st = t.start_time || t.starts_at || null;
    const en = t.end_time   || t.ends_at   || null;

    const confTitle = t.conference
      ? (t.conference.title || t.conference.name || '')
      : '';
    const confLoc = t.conference
      ? (t.conference.location || t.conference.city || '')
      : '';

    // Estados UI
    const endedMsg = state.ended ? `<div class="badge" title="La charla ya finalizó">Finalizada</div>` : '';
    const regTxt   = state.registered ? 'Inscrito' : (state.ended ? 'No disponible' : 'Registrarme');
    const regDis   = state.registered || state.ended ? 'disabled' : '';
    const voteDis  = (!state.registered || state.ended) ? 'disabled' : '';
    const slideOk  = canDownloadSlides();
    const slidesHref = slideOk ? `/index.php?route=/slides.download&talk_id=${encodeURIComponent(talkId)}` : '#';
    const slidesDis  = slideOk ? '' : 'disabled';
    const slidesTitle = slideOk ? '' : (state.registered ? 'No tienes permiso' : 'Regístrate para descargar');

    $('#talkBox').innerHTML = `
      <div class="row" style="justify-content:space-between;align-items:center">
        <div><strong style="font-size:20px">${title}</strong> ${endedMsg}</div>
      </div>
      ${desc}
      <div class="kpi">
        <div><div class="muted">Inicio</div><div>${fmt(st)}</div></div>
        <div><div class="muted">Fin</div><div>${fmt(en)}</div></div>
        <div><div class="muted">Duración</div><div>${st && en ? humanDuration(st,en) : '—'}</div></div>
        <div><div class="muted">Sala</div><div>${t.room_name ?? '—'}</div></div>
      </div>
      <div class="meta">
        <div class="muted">Conferencia: ${confTitle || '—'} ${confLoc ? '• '+confLoc : ''}</div>
      </div>
      <div class="row" style="margin-top:12px">
        <button id="btnReg" class="btn ${regDis?'outline':''}" ${regDis}>${regTxt}</button>
        <button id="btnVote" class="btn outline" ${voteDis}>Votar</button>
        <a id="btnSlides" class="btn outline ${slidesDis?'disabled':''}" href="${slidesHref}" ${slidesDis} title="${slidesTitle}">Slides</a>
        <a class="btn outline" href="/index.php?route=/attendee">Volver</a>
      </div>
    `;

    // Binds
    $('#btnReg')?.addEventListener('click', onRegister);
    $('#btnVote')?.addEventListener('click', onVote);
  }

  function humanDuration(st,en){
    try{
      const ms = new Date(en) - new Date(st);
      if (ms <= 0) return '—';
      const m = Math.floor(ms/60000), h = Math.floor(m/60), r = m%60;
      return (h?`${h}h `:'') + `${r}m`;
    }catch{ return '—'; }
  }

  async function onRegister(){
    if (!state.confId) { alert('No se pudo determinar la conferencia de esta charla.'); return; }
    try{
      await api('attendee.register','POST',{ conference_id: state.confId });
      state.registered = true;
      render();
      alert('¡Inscripción realizada!');
    }catch(e){
      if (String(e.message || '').includes('No autenticado') || String(e.message || '').includes('401')) {
        alert('Inicia sesión para registrarte.');
      } else {
        alert('No se pudo registrar: ' + (e.message || e));
      }
    }
  }

  async function onVote(){
    try{
      await api('attendee.vote','POST',{ talk_id: talkId, score: 1 });
      $('#btnVote').textContent = '¡Votado!'; $('#btnVote').disabled = true;
    }catch(e){
      if (String(e.message || '').includes('No autenticado') || String(e.message || '').includes('401')) {
        alert('Inicia sesión para votar.');
      } else {
        alert('No se pudo votar: ' + (e.message || e));
      }
    }
  }

  // ---- bootstrap ----
  try{
    await loadTalk();
    await Promise.all([loadMe(), loadMyRegs()]);
    // Si ya terminó, aseguramos bloqueos de registro/voto
    if (state.ended) state.registered = state.registered; // no cambiamos, solo renderizamos bloqueo
    render();
  }catch(e){
    $('#talkBox').innerText = 'No se pudo cargar: ' + (e.message || e);
  }
})();
</script>
