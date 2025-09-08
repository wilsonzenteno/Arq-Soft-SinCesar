async function call(route, payload){ return api(route, "POST", payload); }

window.addEventListener("DOMContentLoaded", () => {
  el("#formConf")?.addEventListener("submit", async (e)=>{
    e.preventDefault();
    await call("staff.conference.create", {
      name: el("#cname").value, city: el("#ccity").value,
      starts_at: new Date(el("#cstart").value).toISOString(),
      ends_at: new Date(el("#cend").value).toISOString()
    }); toast("Conferencia creada");
  });

  el("#formRoom")?.addEventListener("submit", async (e)=>{
    e.preventDefault();
    await call("staff.room.create", {
      conference_id: parseInt(el("#rid").value,10),
      name: el("#rname").value
    }); toast("Sala creada");
  });

  el("#formTalk")?.addEventListener("submit", async (e)=>{
    e.preventDefault();
    await call("staff.talk.create", {
      conference_id: parseInt(el("#tidc").value,10),
      room_id: el("#troom").value ? parseInt(el("#troom").value,10) : null,
      title: el("#ttitle").value,
      starts_at: new Date(el("#tstart").value).toISOString(),
      ends_at: new Date(el("#tend").value).toISOString(),
      speaker_id: el("#tspeaker").value || null
    }); toast("Charla creada");
  });

  el("#formAnn")?.addEventListener("submit", async (e)=>{
    e.preventDefault();
    await call("staff.announcement.create", {
      title: el("#anntitle").value,
      body: el("#annbody").value,
      conference_id: el("#annconf").value ? parseInt(el("#annconf").value,10) : null
    }); toast("Anuncio publicado");
  });
});
