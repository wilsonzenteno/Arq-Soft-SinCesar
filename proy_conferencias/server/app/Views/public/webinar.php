<section class="card">
  <h2>Webinar</h2>
  <div id="webBox" class="item">Cargando…</div>
</section>
<script>
(async()=>{
  const p = new URLSearchParams(location.search);
  const id = p.get('id');
  if(!id){ document.getElementById('webBox').innerText='ID faltante'; return; }
  try{
    const res = await fetch(`/rest.php?type=webinar&id=${encodeURIComponent(id)}`);
    if(!res.ok){ throw new Error('No encontrado'); }
    const w = await res.json();
    const box = document.getElementById('webBox');
    const fmt = (d)=> d ? new Date(d).toLocaleString() : '—';
    box.innerHTML = `
      <div><strong>${w.title||'(sin título)'}</strong> <span class="badge">${w.modality||''}</span></div>
      ${w.description? `<div class="muted" style="white-space:pre-wrap">${w.description}</div>`:''}
      <div class="muted">${fmt(w.starts_at)} → ${fmt(w.ends_at)}</div>
      ${w.venue? `<div class="muted">Lugar: ${w.venue}</div>`:''}
      ${w.stream_url? `<div class="muted">Stream: ${w.stream_url}</div>`:''}
      <div class="row" style="margin-top:8px">
        <a class="btn outline" href="/index.php?route=/attendee">Volver</a>
      </div>`;
  }catch(e){
    document.getElementById('webBox').innerText = 'No se pudo cargar: ' + e.message;
  }
})();
</script>
