<section class="card">
  <div id="courseBox">Cargando…</div>
</section>
<script>
(async()=>{
  const $ = s => document.querySelector(s);
  const esc = s => String(s??'').replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]));
  const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);
  const p = new URLSearchParams(location.search);
  const id = p.get('id');

  if(!id){ document.getElementById('courseBox').innerText='ID faltante'; return; }

  const fmt = (d)=> d ? new Date(d).toLocaleString() : '—';
  const diff = (a,b)=>{
    if(!a||!b) return '—';
    const ms = Math.max(0, new Date(b)-new Date(a));
    const m = Math.round(ms/60000); const h = Math.floor(m/60); const mm = m%60;
    return h ? `${h}h ${mm}m` : `${mm} min`;
  };

  async function safeApi(route, method="GET", body=null){ try { return await api(route,method,body); } catch { return null; } }

  try{
    const res = await fetch(`/rest.php?type=course&id=${encodeURIComponent(id)}`);
    if(!res.ok) throw new Error('No encontrado');
    const w = await res.json();

    const myRegs   = asArray(await safeApi('attendee.course.registrations.mine')) || [];
    const reg = myRegs.some(r => String(r.course_id) === String(id));

    const myVotesArr = asArray(await safeApi('attendee.course.votes.mine')) || [];
    const myVotesMap = new Map(myVotesArr.map(o=>[String(o.course_id), !!o.liked]));
    const liked = myVotesMap.get(String(id));

    // ¿Ya terminó?
    const endRef = w.ends_at || w.starts_at || null;
    const finished = endRef ? (new Date(endRef).getTime() < Date.now()) : false;

    const hero = `
      <div class="hero">
        <h2>${esc(w.title||'(sin título)')}</h2>
        <div class="pills">
          <span class="pill">Modalidad: <strong>${esc(w.modality||'presencial')}</strong></span>
          ${w.venue ? `<span class="pill">Lugar: <strong>${esc(w.venue)}</strong></span>`:''}
          ${reg ? '<span class="pill"><strong>Inscrito</strong></span>' : ''}
        </div>
      </div>

      ${finished ? `<div class="notice">Este curso ya pasó. No es posible registrarse ni votar/evaluar.</div>` : ''}

      ${w.description ? `<div class="desc">${esc(w.description)}</div>`:''}
      <div class="meta-grid">
        <div class="meta"><span class="k">Inicio</span><span class="v">${esc(fmt(w.starts_at))}</span></div>
        <div class="meta"><span class="k">Fin</span><span class="v">${esc(fmt(w.ends_at))}</span></div>
        <div class="meta"><span class="k">Duración</span><span class="v">${esc(diff(w.starts_at,w.ends_at))}</span></div>
        ${w.stream_url ? `<div class="meta"><span class="k">Stream</span><span class="v"><a class="link" target="_blank" href="${esc(w.stream_url)}">${esc(w.stream_url)}</a></span></div>`:''}
      </div>
      <div class="actionbar">
        <button class="btn" id="btnReg" ${finished || reg ? 'disabled' : ''}>
          ${finished ? 'Ya pasó' : (reg ? 'Inscrito' : 'Registrarme')}
        </button>
        <button class="btn outline btnLike" data-like="1" ${(!reg || finished) ? 'disabled' : ''} ${liked===true?'style="filter:brightness(1.15)"':''}>Me gusta</button>
        <button class="btn outline btnLike" data-like="0" ${(!reg || finished) ? 'disabled' : ''} ${liked===false?'style="filter:brightness(1.15)"':''}>No me gusta</button>
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
          <button class="btn" id="btnEvalSave">Guardar</button>
        </form>
      </details>
    `;
    $('#courseBox').innerHTML = hero;

    // si ya pasó, bloqueo evaluación y botones
    if (finished){
      $('#evalForm').querySelectorAll('input,select,button').forEach(el=> el.disabled = true);
    } else if (!reg){
      // si no está inscrito, bloqueo evaluación también
      $('#evalForm').querySelectorAll('input,select,button').forEach(el=> el.disabled = true);
    }

    // precarga evaluación al abrir
    $('#courseBox').querySelector('details').addEventListener('toggle', async (ev)=>{
      if (!ev.target.open) return;
      try{
        const curr = await api(`attendee.course.eval.get&course_id=${encodeURIComponent(id)}`);
        if (curr){
          $('#q1').value = String(curr.q1_useful ?? 3);
          $('#q2').value = String(curr.q2_expectations ?? 3);
          $('#q3').value = String(curr.q3_content ?? 3);
          $('#q4').value = String(curr.q4_logistics ?? 3);
          $('#comments').value = curr.comments || '';
        }
      }catch{}
    }, { once:true });

    // acciones
    $('#btnReg')?.addEventListener('click', async ()=>{
      if (finished) { alert('Este curso ya finalizó.'); return; }
      try{ await api('attendee.course.register','POST',{ course_id:id }); alert('¡Inscripción registrada!'); location.reload(); }
      catch(e){ alert(e?.message||'No se pudo registrar'); }
    });

    $('#courseBox').querySelectorAll('.btnLike').forEach(btn=>{
      btn.addEventListener('click', async ()=>{
        if (finished) { alert('Este curso ya finalizó.'); return; }
        const like = btn.dataset.like === '1';
        try{
          await api('attendee.course.vote','POST',{ course_id:id, like });
          $('#courseBox').querySelectorAll('.btnLike').forEach(b=> b.style.filter='');
          btn.style.filter='brightness(1.15)';
        }catch(e){ alert(e?.message||'No se pudo votar'); }
      });
    });

    $('#evalForm')?.addEventListener('submit', async (e)=>{
      e.preventDefault();
      if (finished) { alert('Este curso ya finalizó.'); return; }
      if (!reg) { alert('Regístrate para poder evaluar.'); return; }
      try{
        await api('attendee.course.eval.save','POST',{
          course_id:id,
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
    $('#courseBox').innerText = 'No se pudo cargar: ' + e.message;
  }
})();
</script>
