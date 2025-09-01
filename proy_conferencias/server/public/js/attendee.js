const $ = s => document.querySelector(s);
const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);
const fmtR = (st,en)=>`${st?new Date(st).toLocaleString():'—'} → ${en?new Date(en).toLocaleString():'—'}`;

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
  const res = await fetch(`/index.php?route=${route}`, { method, headers, body: body ? JSON.stringify(body) : undefined, credentials: 'include' });
  const text = await res.text(); let data = null; try { data = text ? JSON.parse(text) : null; } catch { data = text; }
  if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Request failed'));
  return data;
}

let state = { confs: [], myRegs:new Set(), myVotes:new Set(), myConfLikes:new Set(), activeConfId:null };

async function loadMyRegistrations(){ try { state.myRegs = new Set(asArray(await api('attendee.registrations.mine')).map(r=>r.conference_id)); } catch{} }
async function loadMyVotes(confId){ try { state.myVotes = new Set(asArray(await api(`attendee.votes.mine&conference_id=${confId}`)).map(v=>v.talk_id)); } catch{} }
async function loadMyConfLikes(){ try { state.myConfLikes = new Set(asArray(await api('attendee.conf.likes.mine')).map(v=>v.conference_id)); } catch{} }
function syncLikeBtn(){ const liked = state.myConfLikes.has(state.activeConfId); const b=$('#btnConfLike'); if(!b) return; b.textContent = liked? '♥ Te gusta':'♡ Me gusta'; b.classList.toggle('outline', !liked); }

async function toggleLike(){ const cid = state.activeConfId; if(!cid) return; const liked = state.myConfLikes.has(cid); await api('attendee.conf.like','POST',{ conference_id: cid, like: !liked }); liked ? state.myConfLikes.delete(cid) : state.myConfLikes.add(cid); syncLikeBtn(); }

async function loadConfs(){
  await Promise.all([loadMyRegistrations(), loadMyConfLikes()]);
  state.confs = asArray(await api('attendee.conferences.list'));

  $('#confSelect').innerHTML = state.confs.map(c=>{
    const title = c.title || '(sin título)';
    const city  = c.location || '';
    return `<option value="${c.id}">${title}${city?` • ${city}`:''}</option>`;
  }).join('');

  $('#confList').innerHTML = state.confs.map(c=>{
    const registered = state.myRegs.has(c.id);
    const st = c.date || c.starts_at || null; const en = c.ends_at || null;
    const title = c.title || '(sin título)'; const city = c.location || '';
    return `
      <div class="item">
        <div class="row" style="justify-content:space-between;align-items:center">
          <div>
            <div><strong>${title}</strong> ${city?`<span class="badge">${city}</span>`:''}</div>
            <div class="muted">${fmtR(st,en)}</div>
          </div>
          <div class="row">
            <button class="btn outline btnVer" data-id="${c.id}">Ver programa</button>
            <button class="btn ${registered?'outline':''} btnReg" data-id="${c.id}">${registered ? 'Registrado' : 'Registrarme'}</button>
          </div>
        </div>
      </div>`;
  }).join('') || '<div class="item muted">Sin conferencias</div>';

  $('#confList').querySelectorAll('.btnVer').forEach(b=>b.addEventListener('click', async ()=>{
    const id=b.dataset.id; $('#confSelect').value=String(id); await showConference(id);
    window.scrollTo({ top: $('#talksSection').offsetTop - 20, behavior:'smooth' });
  }));
  $('#confList').querySelectorAll('.btnReg').forEach(b=>b.addEventListener('click', async ()=>{
    const id=b.dataset.id; await registerToConference(id);
  }));

  if (state.confs.length){ const first = String(state.confs[0].id); $('#confSelect').value = first; await showConference(first); }
  else { $('#talkList').innerHTML = '<div class="item muted">Crea una conferencia o vuelve más tarde</div>'; }
}

async function loadTalks(confId){
  const arr = asArray(await api(`attendee.talks.list&conference_id=${confId}`));
  const registered = state.myRegs.has(confId);
  $('#talkList').innerHTML = arr.map(t=>{
    const href = `/index.php?route=/public/talk&id=${encodeURIComponent(t.id)}`;
    return `
      <div class="item">
        <div class="row" style="justify-content:space-between;align-items:flex-start">
          <div>
            <div><a href="${href}"><strong>${t.title}</strong></a></div>
            <div class="muted">${fmtR(t.starts_at, t.ends_at)}</div>
            <div class="muted">Sala: ${t.room_name ?? '—'}</div>
          </div>
          <div class="row">
            <button class="btn outline btnVote" data-id="${t.id}" ${state.myVotes.has(t.id)?'disabled':''}>${state.myVotes.has(t.id) ? '¡Votado!' : 'Votar'}</button>
            <a class="btn outline" href="/index.php?route=/slides.download&talk_id=${t.id}" ${registered?'':'title="Regístrate para descargar"'} ${registered?'':'disabled'}>Slides</a>
          </div>
        </div>
      </div>`;
  }).join('') || '<div class="item muted">No hay charlas en esta conferencia</div>';

  $('#talkList').querySelectorAll('.btnVote').forEach(btn=>btn.addEventListener('click', async ()=>{
    const id = btn.dataset.id; await api('attendee.vote','POST',{ talk_id:id, score:1 }); state.myVotes.add(id);
    btn.textContent='¡Votado!'; btn.disabled=true;
  }));
}

async function showConference(confId){ state.activeConfId = confId; await loadMyVotes(confId); await loadTalks(confId); syncLikeBtn(); await loadEvaluation(confId); }
async function registerToConference(confId){ await api('attendee.register','POST',{ conference_id: confId }); state.myRegs.add(confId); await loadConfs(); }

/* ===== Evaluación ===== */
async function loadEvaluation(confId){
  try{ const ev = await api(`attendee.conf.eval.get&conference_id=${confId}`); if (!ev) return;
    $('#q1').value=String(ev.q1_useful??3); $('#q2').value=String(ev.q2_expectations??3);
    $('#q3').value=String(ev.q3_content??3); $('#q4').value=String(ev.q4_logistics??3);
    $('#comments').value=ev.comments||'';
  }catch{}
}
async function saveEvaluation(e){
  e.preventDefault();
  const cid = state.activeConfId; if(!cid) return;
  await api('attendee.conf.eval.save','POST',{
    conference_id: cid,
    q1: parseInt($('#q1').value,10), q2: parseInt($('#q2').value,10),
    q3: parseInt($('#q3').value,10), q4: parseInt($('#q4').value,10),
    comments: $('#comments').value.trim()
  });
  alert('¡Gracias! Tu evaluación se guardó.');
}

/* ===== Explorar todo ===== */
async function loadExplore(){
  // Charlas
  try{
    const talks = asArray(await api('attendee.talks.all'));
    $('#allTalks').innerHTML = talks.map(t=>{
      const href = `/index.php?route=/public/talk&id=${encodeURIComponent(t.id)}`;
      return `<div class="item"><div><a href="${href}"><strong>${t.title}</strong></a></div><div class="muted">${fmtR(t.starts_at,t.ends_at)}</div></div>`;
    }).join('') || '<div class="item muted">Sin charlas</div>';
  }catch{ $('#allTalks').innerHTML = '<div class="item muted">Error cargando</div>'; }

  // Cursos
  try{
    const courses = asArray(await api('attendee.courses.all'));
    $('#allCourses').innerHTML = courses.map(w=>{
      const href = `/index.php?route=/public/course&id=${encodeURIComponent(w.id)}`;
      return `<div class="item"><div><a href="${href}"><strong>${w.title}</strong></a> ${w.modality?`<span class="badge">${w.modality}</span>`:''}</div><div class="muted">${fmtR(w.starts_at,w.ends_at)}</div></div>`;
    }).join('') || '<div class="item muted">Sin cursos</div>';
  }catch{ $('#allCourses').innerHTML = '<div class="item muted">Error cargando</div>'; }

  // Webinars
  try{
    const webs = asArray(await api('attendee.webinars.all'));
    $('#allWebinars').innerHTML = webs.map(w=>{
      const href = `/index.php?route=/public/webinar&id=${encodeURIComponent(w.id)}`;
      return `<div class="item"><div><a href="${href}"><strong>${w.title}</strong></a> ${w.modality?`<span class="badge">${w.modality}</span>`:''}</div><div class="muted">${fmtR(w.starts_at,w.ends_at)}</div></div>`;
    }).join('') || '<div class="item muted">Sin webinars</div>';
  }catch{ $('#allWebinars').innerHTML = '<div class="item muted">Error cargando</div>'; }
}

/* ===== binds ===== */
function bindUI(){
  $('#confSelect').addEventListener('change', async ()=>{ const id = $('#confSelect').value; await showConference(id); });
  $('#btnConfLike').addEventListener('click', toggleLike);
  $('#evalForm').addEventListener('submit', saveEvaluation);
}

async function boot(){ bindUI(); await loadConfs(); await loadExplore(); }
window.addEventListener('DOMContentLoaded', boot);
