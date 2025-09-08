<?php /** @var string $title */ ?>
<div class="container">
  <a href="/index.php?route=/speaker" class="btn outline" style="margin-bottom:12px">Volver</a>

  <h2>Webinar — Panel del orador</h2>
  <div class="muted">ID: <span id="d-id"><?php echo htmlspecialchars((string)($_GET['id'] ?? '')); ?></span></div>

  <section class="card" style="margin-top:12px">
    <h3>Título</h3>
    <div id="d-title">—</div>
  </section>

  <section class="grid2" style="margin-top:12px">
    <div class="card"><div><strong>Inicio</strong></div><div id="d-start">—</div></div>
    <div class="card"><div><strong>Fin</strong></div><div id="d-end">—</div></div>
    <div class="card"><div><strong>Modalidad</strong></div><div id="d-mod">—</div></div>
    <div class="card"><div><strong>Lugar</strong></div><div id="d-venue">—</div></div>
    <div class="card"><div><strong>Stream</strong></div><div id="d-stream">—</div></div>
  </section>

  <section class="card" style="margin-top:12px">
    <h3>Estadísticas</h3>
    <div class="grid4">
      <div><div class="muted">Inscritos</div><div id="st-registered">0</div></div>
      <div><div class="muted">Me gusta</div><div id="st-likes">0</div></div>
      <div><div class="muted">No me gusta</div><div id="st-dislikes">0</div></div>
      <div><div class="muted"># Evaluaciones</div><div id="st-evals">0</div></div>
    </div>
  </section>

  <section class="card" id="speakerActions" style="margin-top:12px">
    <h3>Acciones</h3>
    <div class="row" style="gap:8px;flex-wrap:wrap">
      <button class="btn" type="button" id="btnShowRegs">Ver inscritos</button>
      <button class="btn outline" type="button" id="btnShowEvals">Ver evaluaciones</button>
    </div>
    <div id="regsBox" class="item" style="display:none;margin-top:8px"></div>
    <div id="evalsBox" class="item" style="display:none;margin-top:8px"></div>
  </section>
</div>

<script>
(function(){
  'use strict';
  const $ = s => document.querySelector(s);
  const qs = new URLSearchParams(location.search);
  const id = parseInt(qs.get('id') || '0', 10);

  // ===== Supabase =====
  function getSupa(){
    if (!window.supabase || !window.ENV?.SUPABASE_URL || !window.ENV?.SUPABASE_ANON) return null;
    if (!window.__SUPA) window.__SUPA = window.supabase.createClient(window.ENV.SUPABASE_URL, window.ENV.SUPABASE_ANON);
    return window.__SUPA;
  }
  async function getJwt(){
    let jwt = localStorage.getItem('jwt');
    if (jwt) return jwt;
    try{
      const supa = getSupa();
      if (supa) {
        const { data } = await supa.auth.getSession();
        jwt = data?.session?.access_token || null;
        if (jwt) localStorage.setItem('jwt', jwt);
      }
    }catch{}
    return jwt;
  }

  // ===== API helper =====
  async function api(route, params=null, method='GET'){
    let url = `/index.php?route=${route.startsWith('/') ? route : ('/' + route)}`;
    if (params && typeof params === 'object') {
      const sp = new URLSearchParams();
      for (const [k,v] of Object.entries(params)) if (v != null) sp.append(k, String(v));
      url += `&${sp.toString()}`;
    }
    const headers = {};
    const jwt = await getJwt();
    if (jwt) headers['Authorization'] = 'Bearer ' + jwt;

    const res = await fetch(url, { method, headers, credentials: 'include' });
    const txt = await res.text();
    let data = null; try { data = txt ? JSON.parse(txt) : null; } catch { data = txt; }
    if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Request failed'));
    return data;
  }

  // ===== Utils =====
  function setTxt(sel, v){ const el = $(sel); if (el) el.textContent = (v ?? '—'); }
  function dt(v){ return v ? new Date(v).toLocaleString() : '—'; }
  function escapeHtml(s){ return String(s??'').replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c])); }
  function asArray(x){
    if (Array.isArray(x)) return x;
    if (!x || typeof x !== 'object') return [];
    const keys = ['rows','data','items','list','result','registrations','evaluations'];
    for (const k of keys){ if (Array.isArray(x[k])) return x[k]; }
    for (const v of Object.values(x)){ if (Array.isArray(v)) return v; }
    return [];
  }

  // ===== Estado =====
  let gStats = { registered:0, evalsCount:0, likes:0, dislikes:0 };

  // ===== Fallback votos =====
  async function supaVotesCount(){
    const supa = getSupa(); if (!supa) return null;
    const { data: yes, error: e1 } = await supa
      .from('webinar_votes')
      .select('liked', { count:'exact', head:true })
      .eq('webinar_id', id)
      .eq('liked', true);
    if (e1) throw e1;
    const likes = yes?.length ?? yes?.count ?? yes ?? 0;

    const { data: no, error: e2 } = await supa
      .from('webinar_votes')
      .select('liked', { count:'exact', head:true })
      .eq('webinar_id', id)
      .eq('liked', false);
    if (e2) throw e2;
    const dislikes = no?.length ?? no?.count ?? no ?? 0;

    return { likes: Number(likes||0), dislikes: Number(dislikes||0) };
  }

  // ===== Detalle + Stats =====
  async function loadWebinar(){
    try {
      const mine = await api('/speaker.webinars.mine');
      const row = asArray(mine).find(x => Number(x.id) === id);
      if (row) {
        setTxt('#d-title', row.title || '(sin título)');
        setTxt('#d-start', dt(row.starts_at));
        setTxt('#d-end',   dt(row.ends_at));
        setTxt('#d-mod',   row.modality || '—');
        setTxt('#d-venue', row.venue || '—');
        setTxt('#d-stream',row.stream_url || '—');
      } else {
        setTxt('#d-title', '(webinar)');
      }
    } catch (e) {
      console.warn('speaker.webinars.mine falló:', e?.message || e);
    }

    try{
      const st = await api('/speaker.webinar.stats', { id });
      gStats.registered = Number(st.registered ?? st.registrations ?? 0);
      gStats.evalsCount = Number(st.evals_count ?? st.evaluations ?? 0);
      gStats.likes    = Number(st.likes ?? 0);
      gStats.dislikes = Number(st.dislikes ?? 0);
    }catch(e){
      console.warn('speaker.webinar.stats falló:', e?.message || e);
    }

    try{
      const v = await supaVotesCount();
      if (v) { gStats.likes = v.likes; gStats.dislikes = v.dislikes; }
    }catch(e){
      console.warn('Fallback votos supabase (webinar) falló:', e?.message || e);
    }

    setTxt('#st-registered', String(gStats.registered ?? 0));
    setTxt('#st-likes',      String(gStats.likes ?? 0));
    setTxt('#st-dislikes',   String(gStats.dislikes ?? 0));
    setTxt('#st-evals',      String(gStats.evalsCount ?? 0));
  }

  // ===== Fallback inscritos/evaluaciones =====
  async function supaProfilesMap(ids){
    if (!ids.length) return new Map();
    const supa = getSupa(); if (!supa) return new Map();
    const { data, error } = await supa.from('profiles').select('id, full_name').in('id', ids);
    if (error) throw error;
    const map = new Map(); (data||[]).forEach(p => map.set(p.id, p.full_name || null));
    return map;
  }
  async function supaWebinarRegs(){
    const supa = getSupa(); if (!supa) return [];
    const { data, error } = await supa
      .from('webinar_registrations')
      .select('attendee_id, registered_at')
      .eq('webinar_id', id)
      .order('registered_at', { ascending:false });
    if (error) throw error;
    const rows = data || [];
    const ids = Array.from(new Set(rows.map(r => r.attendee_id).filter(Boolean)));
    const profMap = await supaProfilesMap(ids);
    return rows.map(r => ({ registered_at: r.registered_at, name: profMap.get(r.attendee_id) || null }));
  }
  async function supaWebinarEvals(){
    const supa = getSupa(); if (!supa) return [];
    const { data, error } = await supa
      .from('webinar_evaluations')
      .select('q1_useful, q2_expectations, q3_content, q4_logistics, comments, updated_at')
      .eq('webinar_id', id)
      .order('updated_at', { ascending:false });
    if (error) throw error;
    return data || [];
  }

  // ===== Tablas =====
  function tableRegs(rowsMaybe, note){
    const rows = asArray(rowsMaybe);
    if (!rows.length){
      const extra = note ? `<div class="muted" style="margin-top:6px">${escapeHtml(note)}</div>` : '';
      return '<div class="muted">Sin inscritos</div>' + extra;
    }
    return `
      <div class="item">
        <div class="muted" style="margin-bottom:6px">${rows.length} inscrito(s)</div>
        <div style="overflow:auto">
          <table class="tbl">
            <thead><tr><th>Nombre</th><th>Inscrito el</th></tr></thead>
            <tbody>
              ${rows.map(r=>`
                <tr>
                  <td>${r.name ? escapeHtml(r.name) : '—'}</td>
                  <td>${dt(r.registered_at)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>`;
  }
  function tableEvals(rowsMaybe, note){
    const rows = asArray(rowsMaybe);
    if (!rows.length){
      const extra = note ? `<div class="muted" style="margin-top:6px">${escapeHtml(note)}</div>` : '';
      return '<div class="muted">Sin evaluaciones</div>' + extra;
    }
    return `
      <div class="item">
        <div class="muted" style="margin-bottom:6px">${rows.length} evaluación(es)</div>
        <div style="overflow:auto">
          <table class="tbl">
            <thead>
              <tr>
                <th>Útil</th><th>Expectativas</th><th>Contenido</th><th>Logística</th>
                <th>Comentarios</th><th>Fecha</th>
              </tr>
            </thead>
            <tbody>
              ${rows.map(r=>`
                <tr>
                  <td>${r.q1_useful ?? '—'}</td>
                  <td>${r.q2_expectations ?? '—'}</td>
                  <td>${r.q3_content ?? '—'}</td>
                  <td>${r.q4_logistics ?? '—'}</td>
                  <td>${r.comments ? escapeHtml(r.comments) : '—'}</td>
                  <td>${dt(r.updated_at)}</td>
                </tr>
              `).join('')}
            </tbody>
          </table>
        </div>
      </div>`;
  }

  // ===== API listas =====
  async function loadRegsViaApi(){ return await api('/speaker.webinar.registrations', { id }); }
  async function loadEvalsViaApi(){ return await api('/speaker.webinar.evaluations',   { id }); }

  // ===== Bind =====
  function bindActions(){
    const btnRegs  = $('#btnShowRegs');
    const btnEvals = $('#btnShowEvals');
    const regsBox  = $('#regsBox');
    const evalsBox = $('#evalsBox');

    btnRegs?.addEventListener('click', async ()=>{
      try{
        btnRegs.disabled = true;
        regsBox.style.display = 'block';
        regsBox.innerHTML = '<div class="muted">Cargando inscritos…</div>';

        let apiResp = await loadRegsViaApi();
        let arr = asArray(apiResp);

        if (!arr.length && (gStats.registered ?? 0) > 0) {
          try{
            const rows = await supaWebinarRegs();
            if (rows.length) { regsBox.innerHTML = tableRegs(rows, 'Datos cargados desde Supabase (fallback).'); return; }
          }catch(e){
            regsBox.innerHTML = tableRegs([], `La API no devolvió filas pero hay ${gStats.registered} inscritos. Fallback falló: ${escapeHtml(e?.message||e)}`);
            return;
          }
        }
        regsBox.innerHTML = tableRegs(arr);
      }catch(e){
        regsBox.innerHTML = `<div class="muted">No se pudo cargar: ${escapeHtml(e?.message||e)}</div>`;
      }finally{
        btnRegs.disabled = false;
      }
    });

    btnEvals?.addEventListener('click', async ()=>{
      try{
        btnEvals.disabled = true;
        evalsBox.style.display = 'block';
        evalsBox.innerHTML = '<div class="muted">Cargando evaluaciones…</div>';

        let apiResp = await loadEvalsViaApi();
        let arr = asArray(apiResp);

        if (!arr.length && (gStats.evalsCount ?? 0) > 0) {
          try{
            const rows = await supaWebinarEvals();
            if (rows.length) { evalsBox.innerHTML = tableEvals(rows, 'Datos cargados desde Supabase (fallback).'); return; }
          }catch(e){
            evalsBox.innerHTML = tableEvals([], `La API no devolvió filas pero hay ${gStats.evalsCount} evaluación(es). Fallback falló: ${escapeHtml(e?.message||e)}`);
            return;
          }
        }
        evalsBox.innerHTML = tableEvals(arr);
      }catch(e){
        evalsBox.innerHTML = `<div class="muted">No se pudo cargar: ${escapeHtml(e?.message||e)}</div>`;
      }finally{
        btnEvals.disabled = false;
      }
    });
  }

  // Boot
  document.addEventListener('DOMContentLoaded', async ()=>{
    if (!id) return;
    await loadWebinar();
    bindActions();
  });
})();
</script>
