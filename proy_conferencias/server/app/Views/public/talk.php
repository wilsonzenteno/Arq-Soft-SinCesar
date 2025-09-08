<?php
/** @var array $talk */
/** @var array|null $conference */
?>
<section class="card">
  <h2>Charla</h2>
  <div id="talkBox" class="item">Cargando…</div>
</section>

<style>
  .btn[disabled], .btn.disabled { opacity: .6; pointer-events: none; }
  .kpi { display:grid; grid-template-columns: repeat(4,minmax(0,1fr)); gap:10px; margin-top:8px; }
  .kpi > div { background:#0b1220; border:1px solid #1f2937; border-radius:10px; padding:10px; }
  @media (max-width: 720px){ .kpi { grid-template-columns: 1fr 1fr; } }
  .cnt { display:inline-block; min-width: 1.3em; text-align:center; }
  .muted { color:#9ca3af; font-size:12px; }
  .badge { background:#1f2937; padding:2px 6px; border-radius:6px; font-size:12px; margin-left:6px; }
  .row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
  .item { margin-top:10px; }
</style>

<script>
(async () => {
  const $ = s => document.querySelector(s);
  const esc = s => String(s??'').replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]));
  const fmt = d => d ? new Date(d).toLocaleString() : '—';
  const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);

  async function getJwt(){
    let jwt = localStorage.getItem('jwt');
    if (jwt) return jwt;
    if (window.supabase && window.ENV?.SUPABASE_URL && window.ENV?.SUPABASE_ANON) {
      const supa = window.supabase.createClient(window.ENV.SUPABASE_URL, window.ENV.SUPABASE_ANON);
      const { data } = await supa.auth.getSession();
      jwt = data?.session?.access_token || null;
      if (jwt) localStorage.setItem('jwt', jwt);
    }
    return jwt;
  }
  async function api(route, method="GET", body=null){
    const headers = { 'Content-Type': 'application/json' };
    const jwt = await getJwt();
    if (jwt) headers['Authorization'] = 'Bearer ' + jwt;
    const res = await fetch(`/index.php?route=${route}`, {
      method, headers, body: body ? JSON.stringify(body) : undefined, credentials: 'include'
    });
    const txt = await res.text(); let data = null; try { data = txt ? JSON.parse(txt) : null; } catch { data = txt; }
    if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Request failed'));
    return data;
  }
  async function safe(route, method="GET", body=null){ try { return await api(route,method,body); } catch { return null; } }

  const qs = new URLSearchParams(location.search);
  const talkId = Number(qs.get('id') || 0);
  if (!talkId) { $('#talkBox').innerText = 'ID faltante'; return; }

  const state = {
    talk: null,
    registered: false,
    finished: false,
    myLike: null,               // true/false/null
    counts: { likes: 0, dislikes: 0 }
  };

  function likeButtons(enabled, liked, counts){
    const dis = enabled ? '' : 'disabled title="Regístrate en la charla para votar"';
    const likeActive = liked === true ? 'style="filter:brightness(1.15)"' : '';
    const dislikeActive = liked === false ? 'style="filter:brightness(1.15)"' : '';
    const L = String(counts?.likes ?? 0), D = String(counts?.dislikes ?? 0);
    return `
      <button class="btn outline btnLike" data-like="1" ${dis} ${likeActive}>
        👍 <span class="cnt" data-role="likes">${L}</span>
      </button>
      <button class="btn outline btnLike" data-like="0" ${dis} ${dislikeActive}>
        👎 <span class="cnt" data-role="dislikes">${D}</span>
      </button>
    `;
  }

  // ---------- cargas ----------
  async function loadTalk(){
    const t = await api(`attendee.talk.details&id=${encodeURIComponent(talkId)}`);
    state.talk = t;
    const end = t.ends_at || t.end_time || null;
    state.finished = end ? (new Date(end).getTime() < Date.now()) : false;
  }
  async function loadMine(){
    const regs = asArray(await safe('attendee.talk.registrations.mine')) || [];
    state.registered = regs.some(r => String(r.talk_id) === String(talkId));

    const votes = asArray(await safe('attendee.talk.votes.mine')) || [];
    const found = votes.find(v => String(v.talk_id) === String(talkId));
    state.myLike = (found ? !!found.liked : null);
  }
  async function loadCounts(){
    const st = await safe(`/speaker.talk.stats&id=${encodeURIComponent(talkId)}`);
    state.counts.likes = Number(st?.likes ?? 0);
    state.counts.dislikes = Number(st?.dislikes ?? 0);
  }

  // ---------- render ----------
  function render(){
    const t = state.talk || {};
    const st = t.starts_at || t.start_time || null;
    const en = t.ends_at   || t.end_time   || null;
    const modality = String(t.modality || '').toLowerCase(); // presencial | virtual | hibrida

    // Descarga de slides solo si inscrito
    const canDownload = !!state.registered;
    const slidesHref = canDownload ? `/index.php?route=/slides.download&talk_id=${encodeURIComponent(talkId)}` : '#';
    const slidesDis  = canDownload ? '' : 'disabled title="Regístrate en la charla para descargar"';

    const regTxt = state.registered ? 'Inscrito' : (state.finished ? 'No disponible' : 'Registrarme');
    const regDis = state.registered || state.finished ? 'disabled' : '';

    // Bloques por modalidad
    const showRoom = modality !== 'virtual';
    const showUrl  = modality === 'virtual' || modality === 'hibrida';
    const showVenueCity = modality !== 'virtual'; // mostrar ciudad/venue cuando hay presencialidad

    const kpiExtra = `
      <div><div class="muted">Modalidad</div><div>${esc(t.modality ?? '—')}</div></div>
      ${showRoom ? `<div><div class="muted">Sala</div><div>${esc(t.room_name ?? '—')}</div></div>` : ''}
      ${showVenueCity ? `<div><div class="muted">Ciudad</div><div>${esc(t.conference_city ?? '—')}</div></div>` : ''}
      ${showVenueCity ? `<div><div class="muted">Lugar</div><div>${esc(t.venue ?? '—')}</div></div>` : ''}
      ${showUrl ? `<div style="grid-column: span 2;"><div class="muted">URL</div><div>${t.stream_url ? `<a href="${esc(t.stream_url)}" target="_blank" rel="noopener">Abrir transmisión</a>` : '—'}</div></div>` : ''}
    `;

    $('#talkBox').innerHTML = `
      <div class="row" style="justify-content:space-between;align-items:center">
        <div><strong style="font-size:20px">${esc(t.title||'(sin título)')}</strong> ${state.finished?'<span class="badge">Finalizada</span>':''}</div>
      </div>

      <div class="kpi">
        <div><div class="muted">Inicio</div><div>${esc(fmt(st))}</div></div>
        <div><div class="muted">Fin</div><div>${esc(fmt(en))}</div></div>
        <div><div class="muted">Duración</div><div>${st&&en? humanDuration(st,en):'—'}</div></div>
        ${kpiExtra}
      </div>

      <div class="row" style="gap:8px;margin-top:12px">
        <button id="btnReg" class="btn ${regDis?'outline':''}" ${regDis}>${esc(regTxt)}</button>
        ${likeButtons(state.registered && !state.finished, state.myLike, state.counts)}
        <a id="btnSlides" class="btn outline ${slidesDis?'disabled':''}" href="${slidesHref}" ${slidesDis}>Slides</a>
        <a class="btn ghost" href="/index.php?route=/attendee">Volver</a>
      </div>

      <details class="item" ${state.registered ? '' : 'open=false'}>
        <summary>Evaluar</summary>
        <form class="row" id="evalForm" style="gap:12px;align-items:flex-end">
          <label>¿Te resultó útil?
            <select name="q1"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label>¿Cubrió tus expectativas?
            <select name="q2"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label>Calidad del contenido
            <select name="q3"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label>Logística/instalaciones
            <select name="q4"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label style="flex:1">Comentarios
            <input name="comments" placeholder="¿Algo a mejorar?" />
          </label>
          <button class="btn" ${(!state.registered || state.finished) ? 'disabled' : ''}>Guardar</button>
        </form>
      </details>
    `;

    // eventos
    $('#btnReg')?.addEventListener('click', onRegister);
    $('#talkBox').querySelectorAll('.btnLike').forEach(btn=>{
      btn.addEventListener('click', ()=> onLike(btn.dataset.like === '1'));
    });

    const det = $('#talkBox details');
    det?.addEventListener('toggle', async ev=>{
      if (!ev.target.open) return;
      try{
        const curr = await api(`attendee.talk.eval.get&talk_id=${encodeURIComponent(talkId)}`);
        if (curr) {
          const f = $('#evalForm');
          f.q1.value = String(curr.q1_useful ?? 3);
          f.q2.value = String(curr.q2_expectations ?? 3);
          f.q3.value = String(curr.q3_content ?? 3);
          f.q4.value = String(curr.q4_logistics ?? 3);
          f.comments.value = curr.comments || '';
        }
      }catch{}
    }, { once:true });

    $('#evalForm')?.addEventListener('submit', onSaveEval);
  }

  function humanDuration(a,b){
    try{
      const ms = new Date(b) - new Date(a);
      if (ms <= 0) return '—';
      const m = Math.floor(ms/60000), h = Math.floor(m/60), r = m%60;
      return (h?`${h}h `:'') + `${r}m`;
    }catch{ return '—'; }
  }

  // ---------- acciones ----------
  async function onRegister(){
    try{
      await api('attendee.talk.register','POST',{ talk_id: talkId });
      state.registered = true;
      render();
      alert('¡Inscripción realizada!');
    }catch(e){
      alert('No se pudo registrar: ' + (e?.message || e));
    }
  }

  async function refreshCountsUI(){
    await loadCounts();
    const likeSpan = $('#talkBox').querySelector('[data-role="likes"]');
    const disSpan  = $('#talkBox').querySelector('[data-role="dislikes"]');
    if (likeSpan) likeSpan.textContent = String(state.counts.likes);
    if (disSpan)  disSpan.textContent  = String(state.counts.dislikes);
    $('#talkBox').querySelectorAll('.btnLike').forEach(b=> b.style.filter='');
    const sel = state.myLike === true ? '.btnLike[data-like="1"]' : (state.myLike === false ? '.btnLike[data-like="0"]' : null);
    if (sel) { const el = $('#talkBox').querySelector(sel); if (el) el.style.filter='brightness(1.15)'; }
  }

  async function onLike(like){
    if (!state.registered || state.finished) { alert('Regístrate para votar.'); return; }
    try{
      await api('attendee.talk.vote','POST',{ talk_id: talkId, like: !!like });
      state.myLike = !!like;
      await refreshCountsUI();
    }catch(e){
      alert('No se pudo votar: ' + (e?.message || e));
    }
  }

  async function onSaveEval(ev){
    ev.preventDefault();
    if (!state.registered || state.finished) { alert('Regístrate para evaluar.'); return; }
    const f = ev.target;
    const q1 = parseInt(f.q1.value,10);
    const q2 = parseInt(f.q2.value,10);
    const q3 = parseInt(f.q3.value,10);
    const q4 = parseInt(f.q4.value,10);
    const comments = f.comments.value.trim();
    try{
      await api('attendee.talk.eval.save','POST',{ talk_id: talkId, q1,q2,q3,q4,comments });
      alert('¡Gracias! Tu evaluación se guardó.');
    }catch(e){
      alert('No se pudo guardar la evaluación: ' + (e?.message || e));
    }
  }

  // ---------- boot ----------
  try{
    await loadTalk();
    await Promise.all([loadMine(), loadCounts()]);
    render();
  }catch(e){
    $('#talkBox').innerText = 'No se pudo cargar: ' + (e.message || e);
  }
})();
</script>
