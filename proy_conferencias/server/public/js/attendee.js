// (VISTA) — Lógica UI para asistentes. Llama a CONTROLADORES AttendeeController (PHP)
let JWT = ""; // pega aquí tu Supabase JWT si lo usas; o deja vacío si el backend no lo requiere

async function loadConfs(){
  const data = await api("attendee.conferences.list","GET",null,JWT);
  const wrap = el("#confs");
  html(wrap, data.map(c => `
    <div class="item">
      <div><strong>${c.name}</strong> <span class="badge">${c.city}</span></div>
      <div class="muted">${new Date(c.starts_at).toLocaleString()} → ${new Date(c.ends_at).toLocaleString()}</div>
      <div class="row">
        <button class="btn outline" onclick="loadTalks(${c.id})">Ver charlas</button>
        <button class="btn" onclick="register(${c.id})">Registrarme</button>
      </div>
    </div>`).join(''));
}

async function loadTalks(confId){
  const data = await api(`attendee.talks.list&conference_id=${confId}`,"GET",null,JWT);
  html(el("#talks"), data.map(t => `
    <div class="item">
      <div><strong>${t.title}</strong> <span class="badge">${t.room_name||''}</span></div>
      <div class="muted">${new Date(t.starts_at).toLocaleString()} → ${new Date(t.ends_at).toLocaleString()}</div>
      <div class="row">
        <button class="btn" onclick="vote(${t.id})">Votar</button>
        <a class="btn outline" href="${API_BASE}?route=slides.download&talk_id=${t.id}" target="_blank">Diapositivas</a>
      </div>
    </div>`).join(''));
}

async function register(confId){
  await api("attendee.register","POST",{ conference_id: confId },JWT);
  toast("Registro realizado");
}

async function vote(talkId){
  await api("attendee.vote","POST",{ talk_id: talkId },JWT);
  toast("Voto registrado");
}

window.addEventListener("DOMContentLoaded", () => {
  el("#jwtForm")?.addEventListener("submit", (e)=>{ e.preventDefault(); JWT = el("#jwt").value.trim(); toast("Token guardado."); });
  loadConfs();
});
