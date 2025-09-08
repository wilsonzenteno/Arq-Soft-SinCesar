<section class="card">
  <div id="webBox">Cargando…</div>
</section>

<style>
  .pill{ border-radius:9999px;padding:4px 10px; }
  [data-theme="light"] .pill, .pill{ background:#f9fafb; border:1px solid #e5e7eb; }
  [data-theme="dark"] .pill{ background:#0b1220; border:1px solid #1f2937; }
  .meta-grid{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:10px;}
  .meta{ border-radius:10px;padding:10px; }
  [data-theme="light"] .meta, .meta{ background:#ffffff; border:1px solid #e5e7eb; }
  [data-theme="dark"] .meta{ background:#0b1220; border:1px solid #1f2937; }
  .k{ color: var(--muted); display:block; }
  .link{ text-decoration:underline; }
  .soft{ border:none;border-top:1px solid #e5e7eb;margin:12px 0; }
  [data-theme="dark"] .soft{ border-top-color:#1f2937; }
  .cnt { display:inline-block; min-width: 1.3em; text-align:center; }
  .btn[disabled]{ opacity:.6; cursor:not-allowed; }
  .btnLike--active{ filter:brightness(1.15); }
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
  const diff = (a,b)=>{ if(!a||!b) return '—'; const ms=Math.max(0, new Date(b)-new Date(a)); const m=Math.round(ms/60000); const h=Math.floor(m/60); const mm=m%60; return h?`${h}h ${mm}m`:`${mm} min`; };

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
  async function api(route, method="GET", body=null, {timeoutMs=9000}={}){
    const headers = { };
    const jwt = await getJwt(); if (jwt) headers['Authorization'] = 'Bearer ' + jwt;
    if (body && !(body instanceof FormData)) headers['Content-Type'] = 'application/json';
    const ctrl = new AbortController(); const t = setTimeout(()=>ctrl.abort(), timeoutMs);
    try{
      const res = await fetch(`/index.php?route=${route}`, {
        method, headers, body: body ? JSON.stringify(body) : undefined, credentials: 'include', keepalive: true, signal: ctrl.signal
      });
      const text = await res.text(); let data = null; try { data = text ? JSON.parse(text) : null; } catch { data = text; }
      if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Request failed'));
      return data;
    } finally { clearTimeout(t); }
  }
  async function safe(route, method="GET", body=null){ try { return await api(route,method,body); } catch { return null; } }

  function likeButtons(enabled, liked, counts){
    const dis = enabled ? '' : 'disabled title="Regístrate para votar"';
    const likeCls = liked === true ? 'btnLike--active' : '';
    const dislikeCls = liked === false ? 'btnLike--active' : '';
    const L = String(counts?.likes ?? 0), D = String(counts?.dislikes ?? 0);
    return `
      <button class="btn outline btnLike ${likeCls}" data-entity="webinar" data-id="${esc(id)}" data-like="1" ${dis}>👍 <span class="cnt" data-role="likes">${L}</span></button>
      <button class="btn outline btnLike ${dislikeCls}" data-entity="webinar" data-id="${esc(id)}" data-like="0" ${dis}>👎 <span class="cnt" data-role="dislikes">${D}</span></button>
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

    initOptimisticLikes('#webBox'); // << activar likes optimistas

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
          q1:+$('#q1').value, q2:+$('#q2').value, q3:+$('#q3').value, q4:+$('#q4').value,
          comments: $('#comments').value.trim()
        });
        alert('¡Gracias! Tu evaluación se guardó.');
      }catch(e){ alert(e?.message||'No se pudo guardar'); }
    });

  }catch(e){
    $('#webBox').innerText = 'No se pudo cargar: ' + e.message;
  }

  /* ===== módulo likes optimistas (webinar) ===== */
  function cooldownKey(entity,id){ return `vote.cooldown.${entity}.${id}`; }
  function inCooldown(entity,id, ms=3000){
    const k = cooldownKey(entity,id);
    const last = +localStorage.getItem(k) || 0;
    const now = Date.now();
    const ok = (now - last) < ms;
    if (!ok) localStorage.setItem(k, String(now));
    return ok;
  }
  function initOptimisticLikes(rootSel){
    const root = document.querySelector(rootSel);
    if (!root) return;
    root.querySelectorAll('.btnLike').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        const entity = btn.dataset.entity; // 'webinar'
        const refId  = btn.dataset.id;
        const willLike = btn.dataset.like === '1';
        if (!entity || !refId) return;

        if (btn.hasAttribute('disabled')) { alert('Regístrate para votar.'); return; }
        if (inCooldown(entity, refId)) return;

        const bLike  = root.querySelector('.btnLike[data-like="1"]');
        const bDis   = root.querySelector('.btnLike[data-like="0"]');
        const sLike  = root.querySelector('[data-role="likes"]');
        const sDis   = root.querySelector('[data-role="dislikes"]');

        const currLiked = bLike.classList.contains('btnLike--active') ? true :
                          (bDis.classList.contains('btnLike--active') ? false : null);
        let likes  = parseInt(sLike?.textContent || '0', 10);
        let dislikes = parseInt(sDis?.textContent || '0', 10);

        if (willLike) {
          if (currLiked === false) { dislikes = Math.max(0, dislikes-1); likes += 1; }
          else if (currLiked === null) { likes += 1; }
          bLike.classList.add('btnLike--active');
          bDis.classList.remove('btnLike--active');
        } else {
          if (currLiked === true) { likes = Math.max(0, likes-1); dislikes += 1; }
          else if (currLiked === null) { dislikes += 1; }
          bDis.classList.add('btnLike--active');
          bLike.classList.remove('btnLike--active');
        }
        if (sLike) sLike.textContent = String(likes);
        if (sDis)  sDis.textContent  = String(dislikes);

        bLike.setAttribute('disabled','disabled');
        bDis.setAttribute('disabled','disabled');

        try{
          await api('attendee.webinar.vote','POST',{ webinar_id: refId, like: willLike }, {timeoutMs:4000});
        }catch(e){
          // revert
          if (currLiked === true) { bLike.classList.add('btnLike--active'); bDis.classList.remove('btnLike--active'); }
          else if (currLiked === false) { bDis.classList.add('btnLike--active'); bLike.classList.remove('btnLike--active'); }
          else { bLike.classList.remove('btnLike--active'); bDis.classList.remove('btnLike--active'); }

          if (willLike) {
            if (currLiked === false) { dislikes += 1; likes = Math.max(0, likes-1); }
            else if (currLiked === null) { likes = Math.max(0, likes-1); }
          } else {
            if (currLiked === true) { likes += 1; dislikes = Math.max(0, dislikes-1); }
            else if (currLiked === null) { dislikes = Math.max(0, dislikes-1); }
          }
          if (sLike) sLike.textContent = String(likes);
          if (sDis)  sDis.textContent  = String(dislikes);
          alert(e?.message || 'No se pudo votar');
        } finally {
          bLike.removeAttribute('disabled');
          bDis.removeAttribute('disabled');
          setTimeout(async ()=>{
            try{
              const st = await safe(`/speaker.webinar.stats&id=${encodeURIComponent(refId)}`);
              const L = Number(st?.likes ?? likes), D = Number(st?.dislikes ?? dislikes);
              if (sLike) sLike.textContent = String(L);
              if (sDis)  sDis.textContent  = String(D);
            }catch{}
          }, 1500);
        }
      });
    });
  }
})();
</script>
