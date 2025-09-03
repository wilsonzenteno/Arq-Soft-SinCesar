const $ = s => document.querySelector(s);
const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);
const fmtR = (st,en)=>`${st?new Date(st).toLocaleString():'—'} → ${en?new Date(en).toLocaleString():'—'}`;

/* ================= Auth/API ================= */
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
  const headers = { 'Content-Type':'application/json' };
  const jwt = await getJwt(); if (jwt) headers['Authorization'] = 'Bearer ' + jwt;
  const res = await fetch(`/index.php?route=${route}`, {
    method, headers, body: body ? JSON.stringify(body) : undefined, credentials: 'include'
  });
  const text = await res.text(); let data = null; try { data = text ? JSON.parse(text) : null; } catch { data = text; }
  if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Request failed'));
  return data;
}

/* ================= Estado ================= */
const state = {
  myConfRegs: new Set(),   // conference_id
  myTalkRegs: new Set(),   // talk_id
  myCourseRegs: new Set(), // course_id
  myWebRegs: new Set(),    // webinar_id

  talksAll: [], coursesAll: [], websAll: [],

  // votos (likes/dislikes)
  myCourseVotes: new Map(), // course_id -> liked (true/false)
  myWebVotes:    new Map(), // webinar_id -> liked (true/false)
};

/* ================= Perfil ================= */
function b64urlToStr(s){ s=s.replace(/-/g,'+').replace(/_/g,'/'); const pad=s.length%4? '='.repeat(4-(s.length%4)) : ''; try{return atob(s+pad);}catch{return '';} }
function decodeJwt(token){ try { return JSON.parse(b64urlToStr(token.split('.')[1]||'')) || {}; } catch { return {}; } }
async function paintProfile(){
  const jwt = await getJwt();
  const box = $('#attProfile');
  if (!jwt) { box.innerHTML = '<div class="muted">Conectado</div><div><strong>Invitado</strong></div>'; return; }
  const p = decodeJwt(jwt);
  const email = p.email || p.user_metadata?.email || 'Usuario';
  const name  = p.user_metadata?.full_name || p.user_metadata?.name || '';
  box.innerHTML = `<div class="muted">Conectado</div><div><strong>${name?name:email}</strong></div>`;
}

/* ================= Render tarjetas ================= */
function evalBox(kind, id, enabled){
  if (!enabled) return `<div class="muted" style="margin-top:6px">Regístrate para evaluar.</div>`;
  const tag = `${kind}-${id}`;
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
      <button class="btn">Guardar</button>
    </form>
  </details>`;
}

function likeButtons(kind, id, registered, liked){
  const dis = registered ? '' : 'disabled title="Regístrate para votar"';
  const likeActive = liked === true ? 'style="filter:brightness(1.15)"' : '';
  const dislikeActive = liked === false ? 'style="filter:brightness(1.15)"' : '';
  return `
    <div class="row">
      <button class="btn outline btnLike" data-kind="${kind}" data-id="${id}" data-like="1" ${dis} ${likeActive}>Me gusta</button>
      <button class="btn outline btnLike" data-kind="${kind}" data-id="${id}" data-like="0" ${dis} ${dislikeActive}>No me gusta</button>
    </div>
  `;
}

function talkCard(t, {registeredConf=false, registeredTalk=false, voted=false}={}){
  const href = `/index.php?route=/public/talk&id=${encodeURIComponent(t.id)}`;
  const slides = `/index.php?route=/slides.download&talk_id=${encodeURIComponent(t.id)}`;
  return `
  <div class="item cardish" data-talk="${t.id}" data-conf="${t.conference_id??''}">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
      <div>
        <div style="display:flex;gap:8px;align-items:center">
          <a href="${href}"><strong>${t.title||'(sin título)'}</strong></a>
          ${registeredTalk ? '<span class="badge">Inscrito</span>' : ''}
        </div>
        <div class="muted">${fmtR(t.starts_at, t.ends_at)}</div>
      </div>
      <div class="row">
        <button class="btn outline btnVote" ${voted?'disabled':''}>${voted?'¡Votado!':'Votar'}</button>
        <a class="btn outline" href="${slides}" ${registeredConf?'':'title="Regístrate en la conferencia para descargar"'} ${registeredConf?'':'disabled'}>Slides</a>
      </div>
    </div>
  </div>`;
}

function courseCard(w, {registered=false, liked=null}={}){
  const href = `/index.php?route=/public/course&id=${encodeURIComponent(w.id)}`;
  return `
  <div class="item cardish" data-course="${w.id}">
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
  <div class="item cardish" data-webinar="${w.id}">
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

function confCard(c){
  const st = c.date || c.starts_at || null; const en = c.ends_at || null;
  const title = c.title || c.name || '(sin título)'; const city = c.location || c.city || '';
  return `
  <div class="item cardish" data-conf="${c.id}">
    <div class="row" style="justify-content:space-between;align-items:flex-start">
      <div>
        <div><strong>${title}</strong> ${city?`<span class="badge">${city}</span>`:''}</div>
        <div class="muted">${fmtR(st,en)}</div>
      </div>
      <div class="row">
        <button class="btn outline btnShowProg" type="button">Ver programa</button>
      </div>
    </div>
    <div class="sublist" style="display:none"></div>
  </div>`;
}

/* ================= Cargas “mis registros” + votos ================= */
async function loadMyConferenceRegs(){
  try { state.myConfRegs = new Set(asArray(await api('attendee.registrations.mine')).map(x=>x.conference_id)); } catch { state.myConfRegs = new Set(); }
}
async function loadMyTalkRegs(){
  try { state.myTalkRegs = new Set(asArray(await api('attendee.talk.registrations.mine')).map(x=>x.talk_id)); } catch { state.myTalkRegs = new Set(); }
}
async function loadMyCourseRegs(){
  try { state.myCourseRegs = new Set(asArray(await api('attendee.course.registrations.mine')).map(x=>x.course_id)); } catch { state.myCourseRegs = new Set(); }
}
async function loadMyWebinarRegs(){
  try { state.myWebRegs = new Set(asArray(await api('attendee.webinar.registrations.mine')).map(x=>x.webinar_id)); } catch { state.myWebRegs = new Set(); }
}
async function loadMyCourseVotes(){
  try {
    const arr = asArray(await api('attendee.course.votes.mine'));
    state.myCourseVotes = new Map(arr.map(o => [o.course_id, !!o.liked]));
  } catch { state.myCourseVotes = new Map(); }
}
async function loadMyWebinarVotes(){
  try {
    const arr = asArray(await api('attendee.webinar.votes.mine'));
    state.myWebVotes = new Map(arr.map(o => [o.webinar_id, !!o.liked]));
  } catch { state.myWebVotes = new Map(); }
}

/* ================= Listas públicas ================= */
async function loadAllTalks(){
  const arr = asArray(await api('attendee.talks.all'));
  state.talksAll = arr;
  const host = $('#talkCards');
  if (!arr.length){ host.innerHTML = '<div class="item muted">Sin charlas</div>'; return; }
  host.innerHTML = arr.map(t => {
    const regConf = t.conference_id ? state.myConfRegs.has(t.conference_id) : false;
    const regTalk = state.myTalkRegs.has(t.id);
    return talkCard(t, {registeredConf: regConf, registeredTalk: regTalk, voted:false});
  }).join('');
  bindActions(host);
}
async function loadAllCourses(){
  const arr = asArray(await api('attendee.courses.all'));
  state.coursesAll = arr;
  const host = $('#courseCards');
  if (!arr.length){ host.innerHTML = '<div class="item muted">Sin cursos</div>'; return; }
  host.innerHTML = arr.map(w => courseCard(w, {
    registered: state.myCourseRegs.has(w.id),
    liked: state.myCourseVotes.get(w.id) ?? null
  })).join('');
  bindActions(host);
}
async function loadAllWebinars(){
  const arr = asArray(await api('attendee.webinars.all'));
  state.websAll = arr;
  const host = $('#webinarCards');
  if (!arr.length){ host.innerHTML = '<div class="item muted">Sin webinars</div>'; return; }
  host.innerHTML = arr.map(w => webinarCard(w, {
    registered: state.myWebRegs.has(w.id),
    liked: state.myWebVotes.get(w.id) ?? null
  })).join('');
  bindActions(host);
}

/* ================= Mis inscripciones (render) ================= */
async function renderMyConfs(){
  const confs = asArray(await api('attendee.conferences.list'));
  const mine  = confs.filter(c => state.myConfRegs.has(c.id));
  $('#myConfs').innerHTML = mine.map(confCard).join('') || '<div class="item muted">—</div>';

  $('#myConfs').querySelectorAll('.btnShowProg').forEach(btn=>{
    btn.addEventListener('click', async (ev)=>{
      const wrap = ev.target.closest('.item');
      const confId = wrap.dataset.conf;
      const box = wrap.querySelector('.sublist');
      const visible = box.style.display !== 'none';
      if (visible){ box.style.display='none'; btn.textContent='Ver programa'; return; }
      const talks = asArray(await api(`attendee.talks.list&conference_id=${encodeURIComponent(confId)}`));
      box.innerHTML = talks.map(t => talkCard(t, {
        registeredConf: true,
        registeredTalk: state.myTalkRegs.has(t.id),
        voted: false
      })).join('') || '<div class="item muted">No hay charlas programadas</div>';
      bindActions(box);
      box.style.display='block';
      btn.textContent='Ocultar programa';
    });
  });
}
function renderMyTalks(){
  const mine = state.talksAll.filter(t => state.myTalkRegs.has(t.id));
  $('#myTalks').innerHTML = mine.map(t => talkCard(t, {
    registeredConf: t.conference_id ? state.myConfRegs.has(t.conference_id) : false,
    registeredTalk: true, voted: false
  })).join('') || '<div class="item muted">—</div>';
  bindActions($('#myTalks'));
}
function renderMyCourses(){
  const mine = state.coursesAll.filter(w => state.myCourseRegs.has(w.id));
  $('#myCourses').innerHTML = mine.map(w => courseCard(w, {
    registered:true, liked: state.myCourseVotes.get(w.id) ?? null
  })).join('') || '<div class="item muted">—</div>';
  bindActions($('#myCourses'));
}
function renderMyWebinars(){
  const mine = state.websAll.filter(w => state.myWebRegs.has(w.id));
  $('#myWebinars').innerHTML = mine.map(w => webinarCard(w, {
    registered:true, liked: state.myWebVotes.get(w.id) ?? null
  })).join('') || '<div class="item muted">—</div>';
  bindActions($('#myWebinars'));
}

/* ================= Actions (votos / evaluación / talk vote) ================= */
function bindActions(scopeEl){
  if (!scopeEl) return;

  // Votar charla (botón único existente)
  scopeEl.querySelectorAll('.btnVote').forEach(btn=>{
    btn.addEventListener('click', async (ev)=>{
      const wrap = ev.target.closest('[data-talk]');
      const talkId = wrap?.dataset?.talk;
      if (!talkId) return;
      try{
        await api('attendee.vote','POST',{ talk_id: talkId, score: 1 });
        ev.target.textContent = '¡Votado!';
        ev.target.disabled = true;
      }catch(e){ alert('No se pudo votar: ' + (e?.message || e)); }
    });
  });

  // Likes/dislikes (cursos/webinars)
  scopeEl.querySelectorAll('.btnLike').forEach(btn=>{
    btn.addEventListener('click', async (ev)=>{
      const kind = btn.dataset.kind; // 'course' | 'webinar'
      const id   = btn.dataset.id;
      const like = btn.dataset.like === '1';
      try {
        if (kind === 'course') {
          await api('attendee.course.vote','POST',{ course_id:id, like });
          state.myCourseVotes.set(parseInt(id,10), like);
        } else {
          await api('attendee.webinar.vote','POST',{ webinar_id:id, like });
          state.myWebVotes.set(parseInt(id,10), like);
        }
        // refresca botones del par
        const wrap = btn.closest('.item');
        wrap.querySelectorAll('.btnLike').forEach(b=> b.style.filter = '');
        btn.style.filter = 'brightness(1.15)';
      } catch(e) {
        alert('No se pudo votar: ' + (e?.message || e));
      }
    });
  });

  // Envío de evaluación (cursos/webinars)
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
        if (kind === 'course') {
          await api('attendee.course.eval.save','POST',{ course_id:id, q1,q2,q3,q4,comments });
        } else {
          await api('attendee.webinar.eval.save','POST',{ webinar_id:id, q1,q2,q3,q4,comments });
        }
        alert('¡Gracias! Tu evaluación se guardó.');
      }catch(e){
        alert('No se pudo guardar la evaluación: ' + (e?.message || e));
      }
    });

    // Precarga valores si existe evaluación
    form.closest('details')?.addEventListener('toggle', async (ev)=>{
      if (!ev.target.open) return;
      const kind = form.dataset.kind; const id = form.dataset.id;
      try{
        let curr = null;
        if (kind === 'course') {
          curr = await api(`attendee.course.eval.get&course_id=${encodeURIComponent(id)}`);
        } else {
          curr = await api(`attendee.webinar.eval.get&webinar_id=${encodeURIComponent(id)}`);
        }
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

/* ================= Drawer/Menu ================= */
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
      closeDrawer();
      if (v === 'explore'){
        $('#viewTitle').textContent = 'Explorar';
        await Promise.all([
          loadMyConferenceRegs(), loadMyTalkRegs(), loadMyCourseRegs(), loadMyWebinarRegs(),
          loadMyCourseVotes(), loadMyWebinarVotes()
        ]);
        await Promise.all([loadAllTalks(), loadAllCourses(), loadAllWebinars()]);
      } else {
        $('#viewTitle').textContent = 'Mis inscripciones';
        await Promise.all([
          loadMyConferenceRegs(), loadMyTalkRegs(), loadMyCourseRegs(), loadMyWebinarRegs(),
          loadMyCourseVotes(), loadMyWebinarVotes()
        ]);
        renderMyConfs(); renderMyTalks(); renderMyCourses(); renderMyWebinars();
      }
    });
  });
}

/* ================= Cambiar vista ================= */
function switchView(name){
  document.querySelectorAll('.view').forEach(v=>v.classList.remove('active'));
  $(`#view-${name}`).classList.add('active');
}

/* ================= Boot ================= */
async function boot(){
  bindDrawer();
  paintProfile();
  await Promise.all([
    loadMyConferenceRegs(), loadMyTalkRegs(), loadMyCourseRegs(), loadMyWebinarRegs(),
    loadMyCourseVotes(), loadMyWebinarVotes()
  ]);
  await Promise.all([loadAllTalks(), loadAllCourses(), loadAllWebinars()]);
}
window.addEventListener('DOMContentLoaded', boot);
