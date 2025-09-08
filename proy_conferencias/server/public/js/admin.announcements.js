// public/js/admin.announcements.js
(function(){
  'use strict';

  /* =================== Helpers básicos =================== */
  const $  = s => document.querySelector(s);
  const $$ = s => Array.from(document.querySelectorAll(s));
  const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);

  function escapeHtml(s){
    return String(s ?? '').replace(/[&<>"'`=\/]/g, c => ({
      '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'
    }[c]));
  }

  /* =================== Auth / API =================== */
  function getSupa(){
    if (!window.supabase || !window.ENV?.SUPABASE_URL || !window.ENV?.SUPABASE_ANON) return null;
    if (!window.__SUPA) window.__SUPA = window.supabase.createClient(window.ENV.SUPABASE_URL, window.ENV.SUPABASE_ANON);
    return window.__SUPA;
  }

  async function getJwt(){
    let jwt = localStorage.getItem('jwt');
    if (jwt) return jwt;
    const supa = getSupa();
    if (supa) {
      const { data } = await supa.auth.getSession();
      jwt = data?.session?.access_token || null;
      if (jwt) localStorage.setItem('jwt', jwt);
    }
    return jwt;
  }

  /**
   * api(route, method='GET', body=null, qs=null)
   * route: nombre de la ruta en tu router (ej: 'staff.announcements.list')
   * qs: objeto {k:v} -> querystring
   */
  async function api(route, method='GET', body=null, qs=null){
    let url = `/index.php?route=${encodeURIComponent(route)}`;
    if (qs && typeof qs === 'object') {
      const sp = new URLSearchParams();
      for (const [k,v] of Object.entries(qs)) if (v!==undefined && v!==null && v!=='') sp.append(k, String(v));
      const q = sp.toString(); if (q) url += `&${q}`;
    }
    const headers = { 'Content-Type':'application/json; charset=utf-8' };
    const jwt = await getJwt(); if (jwt) headers.Authorization = 'Bearer ' + jwt;

    const res = await fetch(url, {
      method, headers, credentials:'include',
      body: body ? JSON.stringify(body) : undefined
    });

    const txt = await res.text();
    let data = null; try { data = txt ? JSON.parse(txt) : null; } catch { data = txt; }
    if (!res.ok) {
      const msg = (data && data.error) ? data.error : (res.statusText || 'Request failed');
      throw new Error(msg);
    }
    return data;
  }

  /* =================== Estado =================== */
  const state = {
    list: [],           // anuncios cargados
    editingId: null,    // anuncio que se está editando (id)
    cache: { talks:[], courses:[], webinars:[] } // catálogos
  };

  /* =================== Carga de catálogos por tipo =================== */
  async function loadCatalog(kind){
    if (kind === 'talk') {
      if (!state.cache.talks.length) state.cache.talks = asArray(await api('attendee.talks.all'));
      return state.cache.talks.map(t => ({ id: t.id, label: `[Charla] ${t.title || '(sin título)'} (#${t.id})` }));
    }
    if (kind === 'course') {
      if (!state.cache.courses.length) state.cache.courses = asArray(await api('attendee.courses.all'));
      return state.cache.courses.map(w => ({ id: w.id, label: `[Curso] ${w.title || '(sin título)'} (#${w.id})` }));
    }
    if (kind === 'webinar') {
      if (!state.cache.webinars.length) state.cache.webinars = asArray(await api('attendee.webinars.all'));
      return state.cache.webinars.map(w => ({ id: w.id, label: `[Webinar] ${w.title || '(sin título)'} (#${w.id})` }));
    }
    return [];
  }

  async function fillRefSelect(kind, selectSelector, withAnyOption=false){
    const sel = $(selectSelector);
    if (!sel) return;

    sel.innerHTML = withAnyOption
      ? '<option value="">— Cualquiera —</option>'
      : '<option value="">— Selecciona un destino —</option>';

    if (!kind) return;

    try {
      const opts = await loadCatalog(kind);
      sel.innerHTML += opts.map(o => `<option value="${o.id}">${escapeHtml(o.label)}</option>`).join('');
    } catch (e) {
      console.error('Error cargando catálogo', e);
      if (!withAnyOption) sel.innerHTML = '<option value="">(Error al cargar)</option>';
    }
  }

  /* =================== Listado / Filtros =================== */
  function renderList(){
    const host = $('#annList');
    if (!host) return;

    const kindFilter = $('#annFilterKind')?.value || '';
    const refFilter  = $('#annFilterRef')?.value  || '';
    const q          = ($('#annSearch')?.value || '').toLowerCase().trim();

    const filtered = state.list.filter(a => {
      if (kindFilter && a.kind !== kindFilter) return false;
      if (refFilter  && String(a.ref_id) !== String(refFilter)) return false;
      if (q) {
        const hay = (a.title||'').toLowerCase() + ' ' + (a.body||'').toLowerCase();
        if (!hay.includes(q)) return false;
      }
      return true;
    });

    host.innerHTML = filtered.map(a => {
      const created = a.created_at ? new Date(a.created_at).toLocaleString() : '';
      const title = escapeHtml(a.title || '');
      const body  = escapeHtml(a.body  || '');
      const badge = `${a.kind||''} #${a.ref_id||''}`;

      return `
        <div class="item" data-id="${a.id}">
          <div class="row" style="justify-content:space-between;align-items:flex-start;gap:8px">
            <div class="col" style="gap:6px">
              <div><strong>${title}</strong> <span class="badge">${badge}</span></div>
              <div class="muted">${body}</div>
              <div class="muted" style="font-size:12px">${created}</div>
            </div>
            <div class="row" style="gap:6px">
              <button class="btn outline" type="button" data-act="edit" aria-label="Editar anuncio">Editar</button>
              <button class="btn outline" type="button" data-act="del"  aria-label="Eliminar anuncio">Eliminar</button>
            </div>
          </div>
        </div>
      `;
    }).join('') || '<div class="item muted">Sin anuncios</div>';

    // Bind acciones de fila
    host.querySelectorAll('[data-act="edit"]').forEach(btn => {
      btn.addEventListener('click', () => {
        const wrap = btn.closest('.item');
        const id = Number(wrap?.dataset.id || 0);
        const a  = state.list.find(x => Number(x.id) === id);
        if (!a) return;

        state.editingId = id;
        $('#annFormTitle').textContent = 'Editar anuncio';
        $('#btnAnnSave').textContent   = 'Guardar cambios';
        $('#btnAnnCancel').style.display = 'inline-block';

        $('#aid').value      = String(a.id);
        $('#anntitle').value = a.title || '';
        $('#annbody').value  = a.body  || '';
        $('#annkind').value  = a.kind  || 'talk';
        fillRefSelect($('#annkind').value, '#annref').then(() => {
          $('#annref').value = String(a.ref_id || '');
        });
        window.scrollTo({ top: 0, behavior: 'smooth' });
      });
    });

    host.querySelectorAll('[data-act="del"]').forEach(btn => {
      btn.addEventListener('click', async () => {
        const wrap = btn.closest('.item');
        const id = Number(wrap?.dataset.id || 0);
        if (!id) return;
        if (!confirm('¿Eliminar este anuncio?')) return;
        try {
          // Optimista: quita de UI primero
          state.list = state.list.filter(x => Number(x.id) !== id);
          renderList();
          await api('staff.announcement.delete', 'POST', { id });
        } catch (e) {
          alert(e?.message || 'No se pudo eliminar');
          // fallback: recargar lista
          await loadList();
        }
      });
    });
  }

  async function loadList(){
    const host = $('#annList');
    if (host) host.innerHTML = '<div class="item muted">Cargando…</div>';
    try{
      // Permite filtrar ya desde el backend si se desea
      const kind = $('#annFilterKind')?.value || '';
      const ref  = $('#annFilterRef')?.value  || '';
      const res  = await api('staff.announcements.list', 'GET', null, {
        kind: kind || undefined,
        ref_id: ref || undefined
      });
      // Puede venir como array plano o envuelto
      state.list = asArray(res) || (Array.isArray(res) ? res : []);
      renderList();
    }catch(e){
      console.error(e);
      if (host) host.textContent = 'Error al cargar anuncios';
    }
  }

  async function refreshFilterRefs(){
    const kind = $('#annFilterKind')?.value || '';
    await fillRefSelect(kind, '#annFilterRef', true);
  }

  /* =================== Formulario (crear/actualizar) =================== */
  function resetForm(){
    state.editingId = null;
    $('#aid').value      = '';
    $('#anntitle').value = '';
    $('#annbody').value  = '';
    $('#annkind').value  = 'talk';
    fillRefSelect('talk', '#annref');
    $('#annFormTitle').textContent = 'Nuevo anuncio';
    $('#btnAnnSave').textContent   = 'Publicar';
    $('#btnAnnCancel').style.display = 'none';
  }

  async function onSubmit(e){
    e.preventDefault();
    const id    = parseInt($('#aid').value || '0', 10);
    const title = $('#anntitle').value.trim();
    const body  = $('#annbody').value.trim();
    const kind  = $('#annkind').value;
    const refId = parseInt($('#annref').value || '0', 10);

    if (!title || !body || !kind || !refId) {
      alert('Completa título, mensaje, tipo y destino.');
      return;
    }

    try{
      if (id > 0) {
        await api('staff.announcement.update', 'POST', { id, title, body, kind, ref_id: refId });
        alert('Anuncio actualizado');
      } else {
        await api('staff.announcement.create', 'POST', { title, body, kind, ref_id: refId });
        alert('Anuncio publicado');
      }
      resetForm();
      await loadList();
    } catch (err) {
      alert(err?.message || 'No se pudo guardar el anuncio');
    }
  }

  /* =================== Bindings & Boot =================== */
  function bind(){
    // Formulario
    $('#formAnn')?.addEventListener('submit', onSubmit);
    $('#btnAnnCancel')?.addEventListener('click', resetForm);
    $('#annkind')?.addEventListener('change', () => fillRefSelect($('#annkind').value, '#annref'));

    // Filtros
    $('#annFilterKind')?.addEventListener('change', async ()=>{
      await refreshFilterRefs();
      renderList(); // re-filtra localmente
    });
    $('#annFilterRef')?.addEventListener('change', renderList);

    // Buscador con debounce
    let tSrch = null;
    $('#annSearch')?.addEventListener('input', ()=>{
      clearTimeout(tSrch);
      tSrch = setTimeout(renderList, 180);
    });

    // Reload manual
    $('#btnAnnReload')?.addEventListener('click', loadList);
  }

  document.addEventListener('DOMContentLoaded', async ()=>{
    bind();
    // Preparar selects
    await fillRefSelect($('#annkind')?.value || 'talk', '#annref');
    await refreshFilterRefs();
    await loadList();
  });
})();
