<section class="card">
  <div id="webBox">Cargando…</div>
</section>

<style>
  .pill{ background:#0b1220;border:1px solid #1f2937;border-radius:9999px;padding:4px 10px; }
  .meta-grid{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:10px;}
  .meta{ background:#0b1220;border:1px solid #1f2937;border-radius:10px;padding:10px; }
  .k{ color:#9aa4b2; display:block; }
  .link{ text-decoration:underline; }
  .soft{ border:none;border-top:1px solid #1f2937;margin:12px 0; }
  .cnt { display:inline-block; min-width: 1.3em; text-align:center; }
</style>

<script>
(async()=>{
  const $ = s => document.querySelector(s);
  const esc = s => String(s??'').replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]));
  const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);
  const p = new URLSearchParams(location.search);
  const id = p.get('id');

  if(!id){ $('#webBox').innerText='ID faltante'; return; }

  const fmt = d => d ? new Date(d).toLocaleString() : '—';
  const diff = (a,b)=>{
    if(!a||!b) return '—';
    const ms = Math.max(0, new Date(b)-new Date(a));
    const m = Math.round(ms/60000); const h = Math.floor(m/60); const mm = m%60;
    return h ? `${h}h ${mm}m` : `${mm} min`;
  };

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
  async function safe(route, method="GET", body=null){ try { return await api(route,method,body); } catch { return null; } }

  function likeButtons(enabled, liked, counts){
    const dis = enabled ? '' : 'disabled title="Regístrate para votar"';
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

  let w=null, reg=false, liked=null, counts={likes:0,dislikes:0}, finished=false;

  try{
    const res = await fetch(`/rest.php?type=webinar&id=${encodeURIComponent(id)}`);
    if(!res.ok) throw new Error('No encontrado');
    w = await res.json();

    const myRegs   = asArray(await safe('attendee.webinar.registrations.mine')) || [];
    reg = myRegs.some(r => String(r.webinar_id) === String(id));

    const myVotesArr = asArray(await safe('attendee.webinar.votes.mine')) || [];
    const myVotesMap = new Map(myVotesArr.map(o=>[String(o.webinar_id), !!o.liked]));
    liked = myVotesMap.get(String(id));

    const stStats = await safe(`/speaker.webinar.stats&id=${encodeURIComponent(id)}`);
    counts.likes    = Number(stStats?.likes ?? 0);
    counts.dislikes = Number(stStats?.dislikes ?? 0);

    const endRef = w.ends_at || w.starts_at || null;
    finished = endRef ? (new Date(endRef).getTime() < Date.now()) : false;

    $('#webBox').innerHTML = `
      <div class="hero">
        <h2>${esc(w.title||'(sin título)')}</h2>
        <div class="pills">
          <span class="pill">Modalidad: <strong>${esc(w.modality||'virtual')}</strong></span>
          ${w.venue ? `<span class="pill">Lugar: <strong>${esc(w.venue)}</strong></span>`:''}
          ${reg ? '<span class="pill"><strong>Inscrito</strong></span>' : ''}
        </div>
      </div>

      ${finished ? `<div class="notice">Este webinar ya pasó. No es posible registrarse ni votar/evaluar.</div>` : ''}

      ${w.description ? `<div class="desc">${esc(w.description)}</div>`:''}
      <div class="meta-grid">
        <div class="meta"><span class="k">Inicio</span><span class="v">${esc(fmt(w.starts_at))}</span></div>
        <div class="meta"><span class="k">Fin</span><span class="v">${esc(fmt(w.ends_at))}</span></div>
        <div class="meta"><span class="k">Duración</span><span class="v">${esc(diff(w.starts_at,w.ends_at))}</span></div>
        ${w.stream_url ? `<div class="meta"><span class="k">Stream</span><span class="v"><a class="link" target="_blank" href="${esc(w.stream_url)}">${esc(w.stream_url)}</a></span></div>`:''}
      </div>
      <div class="actionbar" style="display:flex;gap:8px;margin-top:10px">
        <button class="btn" id="btnReg" ${finished || reg ? 'disabled' : ''}>
          ${finished ? 'Ya pasó' : (reg ? 'Inscrito' : 'Registrarme')}
        </button>
        ${likeButtons(reg && !finished, liked, counts)}
        <a class="btn ghost" href="/index.php?route=/attendee">Volver</a>
      </div>
      <hr class="soft" />
      <details class="item">
        <summary>Evaluar</summary>
        <form id="evalForm" class="row" style="gap:12px;align-items:flex-end">
          <label>¿Te resultó útil?
            <select id="q1"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label>¿Cubrió tus expectativas?
            <select id="q2"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label>Calidad del contenido
            <select id="q3"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label>Logística/instalaciones
            <select id="q4"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label style="flex:1">Comentarios
            <input id="comments" placeholder="¿Algo a mejorar?" />
          </label>
          <button class="btn" ${(!reg || finished) ? 'disabled' : ''}>Guardar</button>
        </form>
      </details>
    `;

    $('#btnReg')?.addEventListener('click', async ()=>{
      if (finished) return;
      try{ await api('attendee.webinar.register','POST',{ webinar_id:id }); alert('¡Inscripción registrada!'); location.reload(); }
      catch(e){ alert(e?.message||'No se pudo registrar'); }
    });

    async function refreshCounts(){
      const st = await safe(`/speaker.webinar.stats&id=${encodeURIComponent(id)}`);
      const likes = Number(st?.likes ?? 0), dislikes = Number(st?.dislikes ?? 0);
      const likeSpan = $('#webBox').querySelector('[data-role="likes"]');
      const disSpan  = $('#webBox').querySelector('[data-role="dislikes"]');
      if (likeSpan) likeSpan.textContent = String(likes);
      if (disSpan)  disSpan.textContent  = String(dislikes);
      $('#webBox').querySelectorAll('.btnLike').forEach(b=> b.style.filter='');
      const sel = liked === true ? '.btnLike[data-like="1"]' : (liked===false ? '.btnLike[data-like="0"]' : null);
      if (sel) { const el = $('#webBox').querySelector(sel); if (el) el.style.filter='brightness(1.15)'; }
    }

    $('#webBox').querySelectorAll('.btnLike').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        if (finished || !reg) { alert('Regístrate para votar.'); return; }
        const willLike = btn.dataset.like === '1';
        try{
          await api('attendee.webinar.vote','POST',{ webinar_id:id, like: willLike });
          liked = willLike;                // solo 1 voto por usuario
          await refreshCounts();           // re-cargar del servidor
        }catch(e){ alert(e?.message||'No se pudo votar'); }
      });
    });

    $('#webBox').querySelector('details').addEventListener('toggle', async (ev)=>{
      if (!ev.target.open) return;
      try{
        const curr = await api(`attendee.webinar.eval.get&webinar_id=${encodeURIComponent(id)}`);
        if (curr){
          $('#q1').value = String(curr.q1_useful ?? 3);
          $('#q2').value = String(curr.q2_expectations ?? 3);
          $('#q3').value = String(curr.q3_content ?? 3);
          $('#q4').value = String(curr.q4_logistics ?? 3);
          $('#comments').value = curr.comments || '';
        }
      }catch{}
    }, { once:true });

    $('#evalForm')?.addEventListener('submit', async (e)=>{
      e.preventDefault();
      if (finished || !reg) { alert('Regístrate para evaluar.'); return; }
      try{
        await api('attendee.webinar.eval.save','POST',{
          webinar_id:id,
          q1:parseInt($('#q1').value,10),
          q2:parseInt($('#q2').value,10),
          q3:parseInt($('#q3').value,10),
          q4:parseInt($('#q4').value,10),
          comments: $('#comments').value.trim()
        });
        alert('¡Gracias! Tu evaluación se guardó.');
      }catch(e){ alert(e?.message||'No se pudo guardar'); }
    });

  }catch(e){
    $('#webBox').innerText = 'No se pudo cargar: ' + e.message;
  }
})();
</script>
