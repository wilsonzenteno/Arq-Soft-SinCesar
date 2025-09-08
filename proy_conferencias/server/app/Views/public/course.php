<section class="card">
  <div id="courseBox">Cargando…</div>
</section>

<style>
  .pill{ background:#0b1220;border:1px solid #1f2937;border-radius:9999px;padding:4px 10px; display:inline-flex; gap:6px; align-items:center;}
  .pills{ display:flex; flex-wrap:wrap; gap:8px; margin-top:8px;}
  .meta-grid{ display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:10px; margin-top:10px;}
  .meta{ background:#0b1220;border:1px solid #1f2937;border-radius:10px;padding:10px; }
  .k{ color:#9aa4b2; display:block; font-size:12px; }
  .v{ display:block; margin-top:2px; }
  .link{ text-decoration:underline; }
  .soft{ border:none;border-top:1px solid #1f2937;margin:12px 0; }
  .cnt { display:inline-block; min-width: 1.3em; text-align:center; }
  .notice{ background:#0b1220;border:1px solid #1f2937;border-radius:10px;padding:10px;margin-top:10px; }
  .desc{ margin:12px 0; line-height:1.45; }
  .hero h2{ margin:0; }
  .btn.ghost{ background:transparent; border:1px dashed #374151; }
  .btn[disabled]{ opacity:.6; cursor:not-allowed; }
  .btnLike--active{ filter:brightness(1.15); }
  .hint{ color:#9aa4b2; font-size:12px; }
  @media (max-width:640px){ .meta-grid{ grid-template-columns:1fr; } }
</style>

<script>
(async()=>{
  /* =============== helpers comunes =============== */
  const $ = s => document.querySelector(s);
  const esc = s => String(s??'').replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]));
  const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);
  const qs = new URLSearchParams(location.search);
  const id = qs.get('id');
  if(!id){ $('#courseBox').innerText='ID faltante'; return; }

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
    const headers = {};
    const jwt = await getJwt(); if (jwt) headers['Authorization'] = 'Bearer ' + jwt;
    if (body && !(body instanceof FormData)) headers['Content-Type'] = 'application/json';
    const ctrl = new AbortController(); const t = setTimeout(()=>ctrl.abort(), timeoutMs);
    try{
      const res = await fetch(`/index.php?route=${encodeURIComponent(route)}`, {
        method, headers, body: body ? JSON.stringify(body) : undefined, credentials: 'include', keepalive: true, signal: ctrl.signal
      });
      const text = await res.text(); let data=null; try{ data = text ? JSON.parse(text) : null; }catch{ data = text; }
      if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Request failed'));
      return data;
    } finally { clearTimeout(t); }
  }
  async function safe(route, method="GET", body=null){ try { return await api(route,method,body); } catch { return null; } }

  function likeButtons(enabled, liked, counts){
    const disAttr = enabled ? '' : 'disabled title="Regístrate para votar"';
    const likeCls = liked === true ? 'btnLike--active' : '';
    const dislikeCls = liked === false ? 'btnLike--active' : '';
    const L = String(counts?.likes ?? 0), D = String(counts?.dislikes ?? 0);
    return `
      <button class="btn outline btnLike ${likeCls}" data-entity="course" data-id="${esc(id)}" data-like="1" ${disAttr}>👍 <span class="cnt" data-role="likes">${L}</span></button>
      <button class="btn outline btnLike ${dislikeCls}" data-entity="course" data-id="${esc(id)}" data-like="0" ${disAttr}>👎 <span class="cnt" data-role="dislikes">${D}</span></button>
    `;
  }

  // ====== estado ======
  let course=null, reg=false, liked=null, counts={likes:0,dislikes:0}, finished=false;

  // ====== carga ======
  try{
    const res = await fetch(`/rest.php?type=course&id=${encodeURIComponent(id)}`);
    if(!res.ok) throw new Error('No encontrado');
    course = await res.json();

    const certEnabled = !!(course.cert_enabled === true || course.cert_enabled === 'true');
    const certUrl = typeof course.cert_form_url === 'string' ? course.cert_form_url : (course.certificate_url || null);

    const myRegs   = asArray(await safe('/attendee.course.registrations.mine')) || [];
    reg = myRegs.some(r => String(r.course_id) === String(id));

    const myVotesArr = asArray(await safe('/attendee.course.votes.mine')) || [];
    const myVotesMap = new Map(myVotesArr.map(o=>[String(o.course_id), !!o.liked]));
    liked = myVotesMap.get(String(id));

    const stStats = await safe(`/speaker.course.stats&id=${encodeURIComponent(id)}`);
    counts.likes    = Number(stStats?.likes ?? 0);
    counts.dislikes = Number(stStats?.dislikes ?? 0);

    const endRef = course.ends_at || course.starts_at || null;
    finished = endRef ? (new Date(endRef).getTime() < Date.now()) : false;

    const canUseCert = Boolean(certEnabled && certUrl && finished && reg);

    const certGridRow = (() => {
      if (!certEnabled) return '';
      if (canUseCert) {
        return `<div class="meta"><span class="k">Formulario de certificado</span><span class="v"><a class="link" target="_blank" href="${esc(certUrl)}">${esc(certUrl)}</a></span></div>`;
      }
      const why = !finished ? 'Disponible al finalizar el curso.' : (!reg ? 'Solo para inscritos.' : '');
      return `<div class="meta"><span class="k">Formulario de certificado</span><span class="v"><span class="hint">${esc(why || 'No disponible')}</span></span></div>`;
    })();

    $('#courseBox').innerHTML = `
      <div class="hero">
        <h2>${esc(course.title||'(sin título)')}</h2>
        <div class="pills">
          <span class="pill">Modalidad: <strong>${esc(course.modality||'presencial')}</strong></span>
          ${course.venue ? `<span class="pill">Lugar: <strong>${esc(course.venue)}</strong></span>`:''}
          ${reg ? '<span class="pill"><strong>Inscrito</strong></span>' : ''}
          ${course.cert_enabled ? '<span class="pill">🎓 <strong>Con certificado</strong></span>' : '<span class="pill">🎓 Sin certificado</span>'}
        </div>
      </div>

      ${finished ? `<div class="notice">Este curso ya pasó.</div>` : ''}

      ${course.description ? `<div class="desc">${esc(course.description)}</div>`:''}

      <div class="meta-grid">
        <div class="meta"><span class="k">Inicio</span><span class="v">${esc(fmt(course.starts_at))}</span></div>
        <div class="meta"><span class="k">Fin</span><span class="v">${esc(fmt(course.ends_at))}</span></div>
        <div class="meta"><span class="k">Duración</span><span class="v">${esc(diff(course.starts_at,course.ends_at))}</span></div>
        ${course.stream_url ? `<div class="meta"><span class="k">Stream</span><span class="v"><a class="link" target="_blank" href="${esc(course.stream_url)}">${esc(course.stream_url)}</a></span></div>`:''}
        ${certGridRow}
      </div>

      <div class="actionbar" style="display:flex;gap:8px;margin-top:10px;flex-wrap:wrap">
        <button class="btn" id="btnReg" ${finished || reg ? 'disabled' : ''}>${finished ? 'Ya pasó' : (reg ? 'Inscrito' : 'Registrarme')}</button>
        ${likeButtons(reg && !finished, liked, counts)}
        ${course.cert_enabled ? `<button class="btn" id="btnCert" ${canUseCert ? '' : 'disabled'} ${canUseCert ? '' : 'title="Disponible al finalizar y solo para inscritos"'}>Completar cuestionario</button>` : ''}
        <a class="btn ghost" href="/index.php?route=/attendee">Volver</a>
      </div>

      <hr class="soft" />
      <details class="item">
        <summary>Evaluar</summary>
        <form id="evalForm" class="row" style="gap:12px;align-items:flex-end">
          <label>¿Te resultó útil?
            <select id="q1" name="q1"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label>¿Cubrió tus expectativas?
            <select id="q2" name="q2"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label>Calidad del contenido
            <select id="q3" name="q3"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label>Logística/instalaciones
            <select id="q4" name="q4"><option>1</option><option>2</option><option selected>3</option><option>4</option><option>5</option></select>
          </label>
          <label style="flex:1">Comentarios
            <input id="comments" name="comments" placeholder="¿Algo a mejorar?" />
          </label>
          <button class="btn" ${(!reg || finished) ? 'disabled' : ''}>Guardar</button>
        </form>
      </details>
    `;

    // ===== registro
    $('#btnReg')?.addEventListener('click', async ()=>{
      if (finished) return;
      try{
        await api('/attendee.course.register','POST',{ course_id:id });
        alert('¡Inscripción registrada!');
        location.reload();
      }catch(e){ alert(e?.message||'No se pudo registrar'); }
    });

    // ===== certificado
    $('#btnCert')?.addEventListener('click', (ev)=>{
      const certUrl = typeof course.cert_form_url === 'string' ? course.cert_form_url : (course.certificate_url || null);
      const canUse = Boolean(course.cert_enabled && certUrl && finished && reg);
      if (!canUse) { ev.preventDefault(); alert('Disponible al finalizar y solo para inscritos.'); return; }
      window.open(certUrl, '_blank', 'noopener');
    });

    // ===== likes optimistas
    initOptimisticLikes('#courseBox');

    // ===== evaluación
    $('#courseBox').querySelector('details').addEventListener('toggle', async (ev)=>{
      if (!ev.target.open) return;
      try{
        const curr = await api(`/attendee.course.eval.get&course_id=${encodeURIComponent(id)}`);
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
        await api('/attendee.course.eval.save','POST',{
          course_id:id,
          q1:+$('#q1').value, q2:+$('#q2').value, q3:+$('#q3').value, q4:+$('#q4').value,
          comments: $('#comments').value.trim()
        });
        alert('¡Gracias! Tu evaluación se guardó.');
      }catch(e){ alert(e?.message||'No se pudo guardar'); }
    });

  }catch(e){
    $('#courseBox').innerText = 'No se pudo cargar: ' + e.message;
  }

  /* =============== módulo de likes optimistas =============== */
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
        const entity = btn.dataset.entity;   // 'course'
        const refId  = btn.dataset.id;
        const willLike = btn.dataset.like === '1';
        if (!entity || !refId) return;

        // bloquea si no habilitado
        if (btn.hasAttribute('disabled')) { alert('Regístrate para votar.'); return; }

        // cooldown
        if (inCooldown(entity, refId)) return;

        const parent = root; // mismo contenedor
        const bLike  = parent.querySelector('.btnLike[data-like="1"]');
        const bDis   = parent.querySelector('.btnLike[data-like="0"]');
        const sLike  = parent.querySelector('[data-role="likes"]');
        const sDis   = parent.querySelector('[data-role="dislikes"]');

        // estado actual (desde DOM)
        const currLiked = bLike.classList.contains('btnLike--active') ? true :
                          (bDis.classList.contains('btnLike--active') ? false : null);
        let likes  = parseInt(sLike?.textContent || '0', 10);
        let dislikes = parseInt(sDis?.textContent || '0', 10);

        // === UI optimista ===
        // Si cambia de estado, ajustamos contadores
        if (willLike === true) {
          if (currLiked === false) { dislikes = Math.max(0, dislikes-1); likes += 1; }
          else if (currLiked === null) { likes += 1; }
          // UI toggle
          bLike.classList.add('btnLike--active');
          bDis.classList.remove('btnLike--active');
        } else { // dislike
          if (currLiked === true) { likes = Math.max(0, likes-1); dislikes += 1; }
          else if (currLiked === null) { dislikes += 1; }
          bDis.classList.add('btnLike--active');
          bLike.classList.remove('btnLike--active');
        }
        if (sLike) sLike.textContent = String(likes);
        if (sDis)  sDis.textContent  = String(dislikes);

        // Deshabilitar momentáneamente
        bLike.setAttribute('disabled','disabled');
        bDis.setAttribute('disabled','disabled');

        try{
          // POST rápido (timeout agresivo)
          await api('/attendee.course.vote','POST',{ course_id: refId, like: willLike }, {timeoutMs:4000});
        }catch(e){
          // Revertir si falla
          // revert UI
          if (currLiked === true) {
            bLike.classList.add('btnLike--active');
            bDis.classList.remove('btnLike--active');
          } else if (currLiked === false) {
            bDis.classList.add('btnLike--active');
            bLike.classList.remove('btnLike--active');
          } else { // null
            bLike.classList.remove('btnLike--active');
            bDis.classList.remove('btnLike--active');
          }
          // revert counters
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
          // Rehabilitar botones enseguida
          bLike.removeAttribute('disabled');
          bDis.removeAttribute('disabled');
          // Pequeño refresh diferido (no bloquea la UX)
          setTimeout(async ()=>{
            try{
              const st = await safe(`/speaker.course.stats&id=${encodeURIComponent(refId)}`);
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
