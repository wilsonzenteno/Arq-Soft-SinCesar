// server/public/js/attendee.js

/* ===== Utilidades DOM/format ===== */
const $  = s => document.querySelector(s);
const $$ = s => Array.from(document.querySelectorAll(s));
const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);
const fmtR = (st,en)=>`${st?new Date(st).toLocaleString():'—'} → ${en?new Date(en).toLocaleString():'—'}`;

/* ===== Auth/API ===== */
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
  const headers = { 'Content-Type':'application/json; charset=utf-8' };
  const jwt = await getJwt(); if (jwt) headers['Authorization'] = 'Bearer ' + jwt;
  const res = await fetch(`/index.php?route=${route}`, {
    method, headers, body: body ? JSON.stringify(body) : undefined, credentials: 'include'
  });
  const text = await res.text(); let data = null; try { data = text ? JSON.parse(text) : null; } catch { data = text; }
  if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Request failed'));
  return data;
}
async function fetchWithTimeout(url, opts={}, ms=8000){
  const ctrl = new AbortController();
  const t = setTimeout(()=>ctrl.abort(), ms);
  try {
    const res = await fetch(url, { ...opts, signal: ctrl.signal });
    const txt = await res.text(); let data=null; try{ data = txt ? JSON.parse(txt) : null; } catch { data = txt; }
    if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText||'Error'));
    return data;
  } finally { clearTimeout(t); }
}

/* ===== Estado global ===== */
const state = {
  myConfRegs: new Set(),   // conference_id
  myTalkRegs: new Set(),   // talk_id
  myCourseRegs: new Set(), // course_id
  myWebRegs: new Set(),    // webinar_id

  talksAll: [], coursesAll: [], websAll: [],

  myTalkVotes:   new Map(), // talk_id   -> liked (true/false)
  myCourseVotes: new Map(), // course_id -> liked (true/false)
  myWebVotes:    new Map(), // webinar_id-> liked (true/false)

  talkCounts:   new Map(), // talk_id -> {likes, dislikes}
  courseCounts: new Map(), // course_id -> {likes, dislikes}
  webCounts:    new Map(), // webinar_id -> {likes, dislikes}

  // búsqueda
  searchQuery: '',

  // notificaciones
  notif: {
    items: [],
    readIds: new Set(),
    enabled: true,
    pollingTimer: null,
  }
};

/* ===== Perfil ===== */
function b64urlToStr(s){ s=s.replace(/-/g,'+').replace(/_/g,'/'); const pad=s.length%4? '='.repeat(4-(s.length%4)) : ''; try{return atob(s+pad);}catch{return ''; } }
function decodeJwt(token){ try { return JSON.parse(b64urlToStr(token.split('.')[1]||'')) || {}; } catch { return {}; } }
async function paintProfile(){
  const jwt = await getJwt();
  const box = $('#attProfile');
  if (!box) return;
  if (!jwt) { box.innerHTML = '<div class="muted">Conectado</div><div><strong>Invitado</strong></div>'; return; }
  const p = decodeJwt(jwt);
  const email = p.email || p.user_metadata?.email || 'Usuario';
  const name  = p.user_metadata?.full_name || p.user_metadata?.name || '';
  box.innerHTML = `<div class="muted">Conectado</div><div><strong>${name?name:email}</strong></div>`;
}

/* ===== Helpers UI ===== */
function evalBox(kind, id, enabled){
  if (!enabled) return `<div class="muted" style="margin-top:6px">Regístrate para evaluar.</div>`;
  return `
  <details class="item" style="margin-top:8px">
    <summary>Evaluar</summary>
    <form class="row evalForm" data-kind="${kind}" data-id="${id}" style="gap:12px;align-items:flex-end">
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
      <button class="btn" type="submit">Guardar</button>
    </form>
  </details>`;
}
function likeButtons(kind, id, registered, liked){
  const dis = registered ? '' : 'disabled title="Regístrate para votar"';
  const likeActive    = liked === true  ? 'style="filter:brightness(1.15)"' : '';
  const dislikeActive = liked === false ? 'style="filter:brightness(1.15)"' : '';
  return `
    <div class="row">
      <button class="btn outline btnLike" type="button" data-kind="${kind}" data-id="${id}" data-like="1" ${dis} ${likeActive}>
        👍 <span class="cnt" data-role="likes">0</span>
      </button>
      <button class="btn outline btnLike" type="button" data-kind="${kind}" data-id="${id}" data-like="0" ${dis} ${dislikeActive}>
        👎 <span class="cnt" data-role="dislikes">0</span>
      </button>
    </div>
  `;
}

/* ===== Tarjetas ===== */
function talkCard(t, {registeredTalk=false}={}){
  const href = `/index.php?route=/public/talk&id=${encodeURIComponent(t.id)}`;
  const slides = `/index.php?route=/slides.download&talk_id=${encodeURIComponent(t.id)}`;
  const canDownload = !!registeredTalk;
  const liked = state.myTalkVotes.get(t.id);
  return `
  <div class="item cardish searchable" data-kind="talk" data-id="${t.id}" data-title="${(t.title||'').toLowerCase()}">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
      <div>
        <div style="display:flex;gap:8px;align-items:center">
          <a href="${href}"><strong>${t.title||'(sin título)'}</strong></a>
          ${registeredTalk ? '<span class="badge">Inscrito</span>' : ''}
        </div>
        <div class="muted">${fmtR(t.starts_at, t.ends_at)}</div>
        ${evalBox('talk', t.id, registeredTalk)}
      </div>
      <div class="col" style="gap:8px">
        <div class="row">
          ${likeButtons('talk', t.id, registeredTalk, liked)}
          <a class="btn outline" href="${slides}" ${canDownload?'':'title="Regístrate en la charla para descargar" disabled'}>Slides</a>
        </div>
      </div>
    </div>
  </div>`;
}
function courseCard(w, {registered=false, liked=null}={}){
  const href = `/index.php?route=/public/course&id=${encodeURIComponent(w.id)}`;
  return `
  <div class="item cardish searchable" data-kind="course" data-id="${w.id}" data-title="${(w.title||'').toLowerCase()}">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
      <div>
        <div style="display:flex;gap:8px;align-items:center">
          <a href="${href}"><strong>${w.title||'(sin título)'}</strong></a>
          <span class="badge">${w.modality||''}</span>
          ${registered ? '<span class="badge">Inscrito</span>' : ''}
        </div>
        ${w.description? `<div class="muted" style="white-space:pre-wrap">${w.description}</div>`:''}
        <div class="muted">${fmtR(w.starts_at,w.ends_at)}</div>
        ${w.venue? `<div class="muted">Lugar: ${w.venue}</div>`:''}
        ${w.stream_url? `<div class="muted">Stream: ${w.stream_url}</div>`:''}
        ${evalBox('course', w.id, registered)}
      </div>
      <div class="col">
        ${likeButtons('course', w.id, registered, liked)}
      </div>
    </div>
  </div>`;
}
function webinarCard(w, {registered=false, liked=null}={}){
  const href = `/index.php?route=/public/webinar&id=${encodeURIComponent(w.id)}`;
  return `
  <div class="item cardish searchable" data-kind="webinar" data-id="${w.id}" data-title="${(w.title||'').toLowerCase()}">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
      <div>
        <div style="display:flex;gap:8px;align-items:center">
          <a href="${href}"><strong>${w.title||'(sin título)'}</strong></a>
          <span class="badge">${w.modality||''}</span>
          ${registered ? '<span class="badge">Inscrito</span>' : ''}
        </div>
        ${w.description? `<div class="muted" style="white-space:pre-wrap">${w.description}</div>`:''}
        <div class="muted">${fmtR(w.starts_at,w.ends_at)}</div>
        ${w.venue? `<div class="muted">Lugar: ${w.venue}</div>`:''}
        ${w.stream_url? `<div class="muted">Stream: ${w.stream_url}</div>`:''}
        ${evalBox('webinar', w.id, registered)}
      </div>
      <div class="col">
        ${likeButtons('webinar', w.id, registered, liked)}
      </div>
    </div>
  </div>`;
}

/* ===== Cargas “mis registros” + votos ===== */
async function loadMyConferenceRegs(){ try { state.myConfRegs = new Set(asArray(await api('attendee.registrations.mine')).map(x=>x.conference_id)); } catch { state.myConfRegs = new Set(); } }
async function loadMyTalkRegs(){ try { state.myTalkRegs = new Set(asArray(await api('attendee.talk.registrations.mine')).map(x=>x.talk_id)); } catch { state.myTalkRegs = new Set(); } }
async function loadMyCourseRegs(){ try { state.myCourseRegs = new Set(asArray(await api('attendee.course.registrations.mine')).map(x=>x.course_id)); } catch { state.myCourseRegs = new Set(); } }
async function loadMyWebinarRegs(){ try { state.myWebRegs = new Set(asArray(await api('attendee.webinar.registrations.mine')).map(x=>x.webinar_id)); } catch { state.myWebRegs = new Set(); } }
async function loadMyTalkVotes(){ try { const arr = asArray(await api('attendee.talk.votes.mine')); state.myTalkVotes = new Map(arr.map(o => [o.talk_id, !!o.liked])); } catch { state.myTalkVotes = new Map(); } }
async function loadMyCourseVotes(){ try { const arr = asArray(await api('attendee.course.votes.mine')); state.myCourseVotes = new Map(arr.map(o => [o.course_id, !!o.liked])); } catch { state.myCourseVotes = new Map(); } }
async function loadMyWebinarVotes(){ try { const arr = asArray(await api('attendee.webinar.votes.mine')); state.myWebVotes = new Map(arr.map(o => [o.webinar_id, !!o.liked])); } catch { state.myWebVotes = new Map(); } }

/* ===== Estadísticas (likes/dislikes) ===== */
async function fetchStats(kind, id){
  let route = '';
  if (kind === 'talk')    route = `speaker.talk.stats&id=${encodeURIComponent(id)}`;
  if (kind === 'course')  route = `speaker.course.stats&id=${encodeURIComponent(id)}`;
  if (kind === 'webinar') route = `speaker.webinar.stats&id=${encodeURIComponent(id)}`;
  if (!route) return {likes:0, dislikes:0};
  const st = await api(route, 'GET');
  return { likes: Number(st.likes ?? 0), dislikes: Number(st.dislikes ?? 0) };
}
function setCounts(wrap, likes, dislikes){
  const likeSpan = wrap.querySelector('[data-role="likes"]');
  const disSpan  = wrap.querySelector('[data-role="dislikes"]');
  if (likeSpan) likeSpan.textContent = String(likes ?? 0);
  if (disSpan)  disSpan.textContent  = String(dislikes ?? 0);
}
function getCountsFromState(kind, id){
  if (kind==='talk')    return state.talkCounts.get(id)   || {likes:0,dislikes:0};
  if (kind==='course')  return state.courseCounts.get(id) || {likes:0,dislikes:0};
  if (kind==='webinar') return state.webCounts.get(id)    || {likes:0,dislikes:0};
  return {likes:0,dislikes:0};
}
function saveCountsToState(kind, id, counts){
  if (kind==='talk')    state.talkCounts.set(id, counts);
  if (kind==='course')  state.courseCounts.set(id, counts);
  if (kind==='webinar') state.webCounts.set(id, counts);
}
async function populateStats(scopeEl){
  if (!scopeEl) return;
  const items = scopeEl.querySelectorAll('.item.cardish');
  for (const it of items){
    let kind=null, id=null;
    if (it.dataset.talk)    { kind='talk';    id=parseInt(it.dataset.talk,10); }
    if (it.dataset.course)  { kind='course';  id=parseInt(it.dataset.course,10); }
    if (it.dataset.webinar) { kind='webinar'; id=parseInt(it.dataset.webinar,10); }
    if (!kind || !id){
      kind = it.getAttribute('data-kind');
      id = parseInt(it.getAttribute('data-id')||'0',10);
    }
    if (!kind || !id) continue;

    try{
      const counts = await fetchStats(kind, id);
      saveCountsToState(kind, id, counts);
      setCounts(it, counts.likes, counts.dislikes);
    }catch{
      setCounts(it, 0, 0);
    }
  }
}

/* ===== Listas públicas ===== */
async function loadAllTalks(){
  const arr = asArray(await api('attendee.talks.all'));
  state.talksAll = arr;
  const host = $('#talkCards');
  if (!host) return;
  if (!arr.length){ host.innerHTML = '<div class="item muted">Sin charlas</div>'; return; }
  host.innerHTML = arr.map(t => talkCard(t, {registeredTalk: state.myTalkRegs.has(t.id)})).join('');
  bindActions(host);
  await populateStats(host);
  applySearchToScope(host);
}
async function loadAllCourses(){
  const arr = asArray(await api('attendee.courses.all'));
  state.coursesAll = arr;
  const host = $('#courseCards');
  if (!host) return;
  if (!arr.length){ host.innerHTML = '<div class="item muted">Sin cursos</div>'; return; }
  host.innerHTML = arr.map(w => courseCard(w, {
    registered: state.myCourseRegs.has(w.id),
    liked: state.myCourseVotes.get(w.id) ?? null
  })).join('');
  bindActions(host);
  await populateStats(host);
  applySearchToScope(host);
}
async function loadAllWebinars(){
  const arr = asArray(await api('attendee.webinars.all'));
  state.websAll = arr;
  const host = $('#webinarCards');
  if (!host) return;
  if (!arr.length){ host.innerHTML = '<div class="item muted">Sin webinars</div>'; return; }
  host.innerHTML = arr.map(w => webinarCard(w, {
    registered: state.myWebRegs.has(w.id),
    liked: state.myWebVotes.get(w.id) ?? null
  })).join('');
  bindActions(host);
  await populateStats(host);
  applySearchToScope(host);
}

/* ===== Mis inscripciones (render) ===== */
async function renderMyConfs(){
  const host = $('#myConfs');
  if (!host) return;
  const confs = asArray(await api('attendee.conferences.list'));
  const mine  = confs.filter(c => state.myConfRegs.has(c.id));
  host.innerHTML = mine.map(c => `
    <div class="item cardish">
      <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
          <div><strong>${c.title || c.name || '(sin título)'}</strong> ${ (c.location||c.city)?`<span class="badge">${c.location||c.city}</span>`:''}</div>
          <div class="muted">${fmtR(c.date || c.starts_at, c.ends_at)}</div>
        </div>
        <div class="row">
          <button class="btn outline btnShowProg" type="button">Ver programa</button>
        </div>
      </div>
      <div class="sublist" style="display:none"></div>
    </div>`).join('') || '<div class="item muted">—</div>';

  $('#myConfs').querySelectorAll('.btnShowProg').forEach(btn=>{
    btn.addEventListener('click', async (ev)=>{
      const wrap = ev.target.closest('.item');
      const titleText = wrap.querySelector('strong')?.textContent || '';
      const conf = (state.talksAll.find(t => (t.conference_title||'')===titleText) || {});
      const confIdAttr = wrap.getAttribute('data-conf');
      const confId = conf?.conference_id || confIdAttr;
      const box = wrap.querySelector('.sublist');
      const visible = box.style.display !== 'none';
      if (visible){ box.style.display='none'; btn.textContent='Ver programa'; return; }
      const talks = asArray(await api(`attendee.talks.list&conference_id=${encodeURIComponent(confId)}`));
      box.innerHTML = talks.map(t => talkCard(t, { registeredTalk: state.myTalkRegs.has(t.id) })).join('') || '<div class="item muted">No hay charlas programadas</div>';
      bindActions(box);
      await populateStats(box);
      applySearchToScope(box);
      box.style.display='block';
      btn.textContent='Ocultar programa';
    });
  });
}
function renderMyTalks(){
  const host = $('#myTalks'); if (!host) return;
  const mine = state.talksAll.filter(t => state.myTalkRegs.has(t.id));
  host.innerHTML = mine.map(t => talkCard(t, { registeredTalk: true })).join('') || '<div class="item muted">—</div>';
  bindActions(host);
  populateStats(host);
  applySearchToScope(host);
}
function renderMyCourses(){
  const host = $('#myCourses'); if (!host) return;
  const mine = state.coursesAll.filter(w => state.myCourseRegs.has(w.id));
  host.innerHTML = mine.map(w => courseCard(w, { registered:true, liked: state.myCourseVotes.get(w.id) ?? null })).join('') || '<div class="item muted">—</div>';
  bindActions(host);
  populateStats(host);
  applySearchToScope(host);
}
function renderMyWebinars(){
  const host = $('#myWebinars'); if (!host) return;
  const mine = state.websAll.filter(w => state.myWebRegs.has(w.id));
  host.innerHTML = mine.map(w => webinarCard(w, { registered:true, liked: state.myWebVotes.get(w.id) ?? null })).join('') || '<div class="item muted">—</div>';
  bindActions(host);
  populateStats(host);
  applySearchToScope(host);
}

/* ===== Acciones (likes + evaluación) ===== */
function highlightChoice(wrap, like){
  wrap.querySelectorAll('.btnLike').forEach(b=> b.style.filter = '');
  const selector = like ? '.btnLike[data-like="1"]' : '.btnLike[data-like="0"]';
  const chosen = wrap.querySelector(selector);
  if (chosen) chosen.style.filter = 'brightness(1.15)';
}
function adjustCountsLocally(kind, id, prev, next){
  const counts = getCountsFromState(kind, id);
  let {likes, dislikes} = counts;
  if (prev === true)  likes--;
  if (prev === false) dislikes--;
  if (next === true)  likes++;
  if (next === false) dislikes++;
  likes = Math.max(0, likes|0);
  dislikes = Math.max(0, dislikes|0);
  const updated = {likes, dislikes};
  saveCountsToState(kind, id, updated);
  return updated;
}
function bindActions(scopeEl){
  if (!scopeEl) return;

  scopeEl.querySelectorAll('.btnLike').forEach(btn=>{
    btn.addEventListener('click', async ()=>{
      const kind = btn.dataset.kind;
      const id   = parseInt(btn.dataset.id,10);
      const like = btn.dataset.like === '1';
      try {
        const prev = (kind==='talk') ? state.myTalkVotes.get(id)
                   : (kind==='course') ? state.myCourseVotes.get(id)
                   : state.myWebVotes.get(id);

        if (kind === 'talk') {
          await api('attendee.talk.vote','POST',{ talk_id:id, like });
          state.myTalkVotes.set(id, like);
        } else if (kind === 'course') {
          await api('attendee.course.vote','POST',{ course_id:id, like });
          state.myCourseVotes.set(id, like);
        } else {
          await api('attendee.webinar.vote','POST',{ webinar_id:id, like });
          state.myWebVotes.set(id, like);
        }

        const wrap = btn.closest('.item');
        const updated = adjustCountsLocally(kind, id, prev, like);
        setCounts(wrap, updated.likes, updated.dislikes);
        highlightChoice(wrap, like);
      } catch(e) {
        alert('No se pudo votar: ' + (e?.message || e));
      }
    });
  });

  scopeEl.querySelectorAll('form.evalForm').forEach(form=>{
    form.addEventListener('submit', async (e)=>{
      e.preventDefault();
      const kind = form.dataset.kind; const id = form.dataset.id;
      const q1 = parseInt(form.q1.value,10);
      const q2 = parseInt(form.q2.value,10);
      const q3 = parseInt(form.q3.value,10);
      const q4 = parseInt(form.q4.value,10);
      const comments = form.comments.value.trim();
      try{
        if (kind === 'talk')       await api('attendee.talk.eval.save','POST',{ talk_id:id, q1,q2,q3,q4,comments });
        else if (kind === 'course')await api('attendee.course.eval.save','POST',{ course_id:id, q1,q2,q3,q4,comments });
        else                       await api('attendee.webinar.eval.save','POST',{ webinar_id:id, q1,q2,q3,q4,comments });
        alert('¡Gracias! Tu evaluación se guardó.');
      }catch(e){
        alert('No se pudo guardar la evaluación: ' + (e?.message || e));
      }
    });

    form.closest('details')?.addEventListener('toggle', async (ev)=>{
      if (!ev.target.open) return;
      const kind = form.dataset.kind; const id = form.dataset.id;
      try{
        let curr = null;
        if (kind === 'talk')       curr = await api(`attendee.talk.eval.get&talk_id=${encodeURIComponent(id)}`);
        else if (kind === 'course')curr = await api(`attendee.course.eval.get&course_id=${encodeURIComponent(id)}`);
        else                       curr = await api(`attendee.webinar.eval.get&webinar_id=${encodeURIComponent(id)}`);
        if (curr) {
          form.q1.value = String(curr.q1_useful ?? 3);
          form.q2.value = String(curr.q2_expectations ?? 3);
          form.q3.value = String(curr.q3_content ?? 3);
          form.q4.value = String(curr.q4_logistics ?? 3);
          form.comments.value = curr.comments || '';
        }
      }catch{}
    }, { once:true });
  });
}

/* ===== Drawer/Menu ===== */
function openDrawer(){ $('#drawer')?.classList.add('open'); $('#drawerBackdrop')?.classList.add('show'); }
function closeDrawer(){ $('#drawer')?.classList.remove('open'); $('#drawerBackdrop')?.classList.remove('show'); }
function bindDrawer(){
  $('#menuToggle')?.addEventListener('click', openDrawer);
  $('#drawerBackdrop')?.addEventListener('click', closeDrawer);
  document.querySelectorAll('.drawer-link').forEach(b=>{
    b.addEventListener('click', async ()=>{
      document.querySelectorAll('.drawer-link').forEach(x=>x.classList.remove('active'));
      b.classList.add('active');
      const v = b.dataset.view;
      switchView(v);
      if (v === 'explore'){
        $('#viewTitle') && ($('#viewTitle').textContent = 'Explorar');
        await Promise.all([
          loadMyConferenceRegs(), loadMyTalkRegs(), loadMyCourseRegs(), loadMyWebinarRegs(),
          loadMyTalkVotes(), loadMyCourseVotes(), loadMyWebinarVotes()
        ]);
        await Promise.all([loadAllTalks(), loadAllCourses(), loadAllWebinars()]);
      } else {
        $('#viewTitle') && ($('#viewTitle').textContent = 'Mis inscripciones');
        await Promise.all([
          loadMyConferenceRegs(), loadMyTalkRegs(), loadMyCourseRegs(), loadMyWebinarRegs(),
          loadMyTalkVotes(), loadMyCourseVotes(), loadMyWebinarVotes()
        ]);
        renderMyConfs(); renderMyTalks(); renderMyCourses(); renderMyWebinars();
      }
    });
  });
}
function switchView(name){
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  $(`#view-${name}`)?.classList.add('active');
}

/* ===== Búsqueda global ===== */
function setSearchQuery(q){
  state.searchQuery = (q || '').trim().toLowerCase();
  ['#talkCards','#courseCards','#webinarCards','#myTalks','#myCourses','#myWebinars'].forEach(sel=>{
    const host = $(sel); if (host) applySearchToScope(host);
  });
}
function applySearchToScope(scope){
  const q = state.searchQuery;
  const items = scope.querySelectorAll('.searchable');
  if (!items.length) return;
  if (!q){
    items.forEach(it=> it.style.display = '');
    return;
  }
  items.forEach(it=>{
    const t = (it.getAttribute('data-title') || '').toLowerCase();
    it.style.display = t.includes(q) ? '' : 'none';
  });
}
function bindSearch(){
  const input = $('#globalSearch'); if (!input) return;
  let t = null;
  input.addEventListener('input', ()=>{
    clearTimeout(t);
    t = setTimeout(()=> setSearchQuery(input.value), 250);
  });
}

/* ===== Notificaciones (rápidas: cache + RPC + since) ===== */
function loadNotifRead(){
  try{
    const raw = localStorage.getItem('notif_read_ids');
    state.notif.readIds = new Set(raw ? JSON.parse(raw) : []);
  }catch{ state.notif.readIds = new Set(); }
}
function saveNotifRead(){
  try{
    localStorage.setItem('notif_read_ids', JSON.stringify(Array.from(state.notif.readIds)));
  }catch{}
}
function notifBadgeUpdate(){
  const badge = $('#notifBadge'); if (!badge) return;
  const unread = state.notif.items.filter(it => !state.notif.readIds.has(String(it.id))).length;
  if (unread > 0){ badge.hidden = false; badge.textContent = unread > 99 ? '99+' : String(unread); }
  else { badge.hidden = true; badge.textContent = ''; }
}
function escapeHtml(s){
  return String(s??'').replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]));
}
function renderNotifPanel(){
  const panel = $('#notifPanel'); const list = $('#notifList');
  if (!panel || !list) return;
  if (!state.notif.items.length){
    list.innerHTML = '<div class="muted">Sin notificaciones</div>';
    return;
  }
  list.innerHTML = state.notif.items.map(it=>`
    <div class="item">
      <div class="t"><strong>${escapeHtml(it.title || 'Notificación')}</strong></div>
      <div class="b" style="white-space:pre-wrap">${escapeHtml(it.body || '')}</div>
      <div class="d muted" style="font-size:12px">${it.created_at ? new Date(it.created_at).toLocaleString() : ''}</div>
    </div>
  `).join('');
}
function getNotifCache(){
  try {
    const items = JSON.parse(localStorage.getItem('notif_cache') || '[]');
    const since = localStorage.getItem('notif_last_ts') || null;
    return { items: Array.isArray(items) ? items : [], since };
  } catch { return { items: [], since: null }; }
}
function setNotifCache(items){
  try{
    const sorted = [...items].sort((a,b)=> new Date(b.created_at) - new Date(a.created_at)).slice(0,200);
    localStorage.setItem('notif_cache', JSON.stringify(sorted));
    if (sorted.length) localStorage.setItem('notif_last_ts', sorted[0].created_at);
  }catch{}
}
async function fetchNotifications({incremental=true, limit=50} = {}){
  try{
    // usa cache inmediatamente si el panel se va a abrir
    const { since, items: cached } = getNotifCache();
    if (!state.notif.items.length && cached.length){
      state.notif.items = cached;
      notifBadgeUpdate();
      const panel = $('#notifPanel');
      if (panel && !panel.hidden) renderNotifPanel();
    }

    // construye URL con params (RPC optimizada del backend)
    const jwt = await getJwt();
    const headers = { 'Content-Type':'application/json' };
    if (jwt) headers.Authorization = 'Bearer ' + jwt;

    const qp = new URLSearchParams();
    qp.set('limit', String(Math.max(1, Math.min(200, limit))));
    if (incremental && since) qp.set('since', since);

    const url = `/index.php?route=attendee.notifications&${qp.toString()}`;
    const data = await fetchWithTimeout(url, { headers }, 8000);

    const incoming = Array.isArray(data?.items) ? data.items : (Array.isArray(data) ? data : []);
    if (!incoming.length && cached.length){
      // nada nuevo; mantén cache
      state.notif.items = cached;
      notifBadgeUpdate();
      return;
    }

    // merge incremental por id
    const map = new Map((cached || []).map(x => [x.id, x]));
    for (const it of incoming) map.set(it.id, it);
    const merged = Array.from(map.values()).sort((a,b)=> new Date(b.created_at) - new Date(a.created_at)).slice(0,200);

    state.notif.items = merged;
    setNotifCache(merged);
    notifBadgeUpdate();

    const panel = $('#notifPanel');
    if (panel && !panel.hidden) renderNotifPanel();
  }catch(e){
    // si falla, no rompas la UI
    console.warn('notifications error:', e);
    state.notif.enabled = false;
    stopNotifPolling();
  }
}
function startNotifPolling(){
  if (!state.notif.enabled) return;
  stopNotifPolling();
  // cada 60s, incremental
  state.notif.pollingTimer = setInterval(()=>fetchNotifications({incremental:true, limit:50}), 60000);
}
function stopNotifPolling(){
  if (state.notif.pollingTimer){ clearInterval(state.notif.pollingTimer); state.notif.pollingTimer = null; }
}
function bindNotificationsUI(){
  loadNotifRead();
  notifBadgeUpdate();

  $('#notifBtn')?.addEventListener('click', async ()=>{
    const panel = $('#notifPanel'); if (!panel) return;
    const isOpen = !panel.hidden;

    if (isOpen) {
      panel.hidden = true;
      return;
    }

    // abrir: pinta cache al instante y refresca en background
    const { items: cached } = getNotifCache();
    state.notif.items = cached || [];
    panel.hidden = false;
    renderNotifPanel();
    notifBadgeUpdate();

    // refresco rápido (incremental)
    await fetchNotifications({incremental:true, limit:50});

    // marcar todo como leído al abrir
    state.notif.items.forEach(it => state.notif.readIds.add(String(it.id)));
    saveNotifRead();
    notifBadgeUpdate();
  });

  $('#notifClose')?.addEventListener('click', ()=> { $('#notifPanel') && ($('#notifPanel').hidden = true); });

  // clic fuera para cerrar
  document.addEventListener('click', (ev)=>{
    const p = $('#notifPanel'); const b = $('#notifBtn');
    if (!p || p.hidden) return;
    if (p.contains(ev.target) || b.contains(ev.target)) return;
    p.hidden = true;
  });

  // arranca polling silencioso
  startNotifPolling();
}

/* ===== Boot ===== */
async function boot(){
  bindDrawer();
  bindSearch();
  bindNotificationsUI();
  paintProfile();
  await Promise.all([
    loadMyConferenceRegs(), loadMyTalkRegs(), loadMyCourseRegs(), loadMyWebinarRegs(),
    loadMyTalkVotes(), loadMyCourseVotes(), loadMyWebinarVotes()
  ]);
  await Promise.all([loadAllTalks(), loadAllCourses(), loadAllWebinars()]);
}
window.addEventListener('DOMContentLoaded', boot);
