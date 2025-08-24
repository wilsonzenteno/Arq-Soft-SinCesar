// (VISTA) — Lógica UI para oradores. Llama a CONTROLADORES SpeakerController (PHP)
let JWT = "";

async function loadMyTalks(){
  const data = await api("speaker.talks.mine","GET",null,JWT);
  html(el("#myTalks"), (data||[]).map(t => `
    <div class="item">
      <div><strong>${t.title}</strong> <span class="badge">${t.conference_name||''}</span></div>
      <div class="muted">${new Date(t.starts_at).toLocaleString()} → ${new Date(t.ends_at).toLocaleString()}</div>
      <div class="row">
        <a class="btn outline" href="${API_BASE}?route=slides.download&talk_id=${t.id}" target="_blank">Ver PDF</a>
      </div>
    </div>`).join('') || '<div class="item muted">Sin charlas.</div>');
}

async function claimTalk(talkId){
  await api("speaker.talks.claim","POST",{ talk_id: talkId },JWT);
  toast("Charla reclamada"); loadMyTalks();
}

async function uploadSlides(e){
  e.preventDefault();
  const talkId = parseInt(el("#talkId").value,10);
  const file = el("#file").files[0];
  if (!talkId || !file) return toast("Completa los campos.");
  const form = new FormData();
  form.append("talk_id", talkId);
  form.append("file", file);

  const res = await fetch(`${API_BASE}?route=speaker.slides.upload`, {
    method: "POST",
    headers: JWT ? { "Authorization": `Bearer ${JWT}` } : {},
    body: form
  });
  if (!res.ok) throw new Error(await res.text());
  toast("Diapositivas subidas");
}

window.addEventListener("DOMContentLoaded", () => {
  el("#jwtForm")?.addEventListener("submit",(e)=>{ e.preventDefault(); JWT = el("#jwt").value.trim(); toast("Token guardado."); });
  el("#uploadForm")?.addEventListener("submit", uploadSlides);
  el("#btnClaim")?.addEventListener("click", ()=> {
    const id = parseInt(el("#claimTalkId").value,10); if(!id) return toast("ID inválido");
    claimTalk(id);
  });
  loadMyTalks();
});
