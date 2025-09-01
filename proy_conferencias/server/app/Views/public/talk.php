<section class="card">
  <h2>Charla</h2>
  <div id="talkBox" class="item">Cargando…</div>
</section>
<script>
(async()=>{
  const p = new URLSearchParams(location.search);
  const id = p.get('id');
  if(!id){ document.getElementById('talkBox').innerText='ID faltante'; return; }
  try{
    const res = await fetch(`/rest.php?type=talk&id=${encodeURIComponent(id)}`);
    if(!res.ok){ throw new Error('No encontrado'); }
    const t = await res.json();
    const box = document.getElementById('talkBox');
    const fmt = (d)=> d ? new Date(d).toLocaleString() : '—';
    box.innerHTML = `
      <div><strong>${t.title||'(sin título)'}</strong></div>
      ${t.description? `<div class="muted" style="white-space:pre-wrap">${t.description}</div>`:''}
      <div class="muted">Inicio: ${fmt(t.starts_at)} — Fin: ${fmt(t.ends_at)}</div>
      ${t.room_name? `<div class="muted">Sala: ${t.room_name}</div>`:''}
      ${t.conference? `<div class="muted">Conferencia: ${t.conference.title||t.conference.name||''} ${t.conference.location? '• '+t.conference.location:''}</div>`:''}
      <div class="row" style="margin-top:8px">
        <a class="btn outline" href="/index.php?route=/attendee">Volver</a>
        <a class="btn outline" href="/index.php?route=/slides.download&talk_id=${encodeURIComponent(id)}" target="_blank">Slides</a>
      </div>`;
  }catch(e){
    document.getElementById('talkBox').innerText = 'No se pudo cargar: ' + e.message;
  }
})();
</script>
