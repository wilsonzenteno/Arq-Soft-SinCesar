// speaker.js — versión corregida (error "An invalid form control ... is not focusable")

const $ = s => document.querySelector(s);
const esc = s => String(s??'').replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]));
const toLocal = dt => { if(!dt) return ''; const d=new Date(dt); if(Number.isNaN(+d)) return ''; const p=n=>String(n).padStart(2,'0'); return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`; };
const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);

/* ===== helpers UI (visibilidad / validación nativa) ===== */
const showBlock = (el, v) => { if (!el) return; el.style.display = v ? 'flex' : 'none'; setBlockEnabled(el, v); };
const showEl    = (el, v) => { if (!el) return; el.style.display = v ? '' : 'none'; };
const enableField = (el, v) => { if (!el) return; if (v) el.removeAttribute('disabled'); else el.setAttribute('disabled','disabled'); };
const requireField = (el, v) => { if (!el) return; if (v) el.setAttribute('required','required'); else el.removeAttribute('required'); };
const clearField = (el) => { if (!el) return; if (el.tagName === 'SELECT') el.value = ''; else el.value = ''; };

function setBlockEnabled(block, enabled){
  if (!block) return;
  block.querySelectorAll('input,select,textarea').forEach(el=>{
    if (!enabled) {
      el.setAttribute('disabled','disabled');
      el.removeAttribute('required');
    } else {
      el.removeAttribute('disabled');
      // el "required" lo define toggleBlocks según modalidad
    }
  });
}

// Detecta visibilidad efectiva (no display:none, no visibility:hidden)
function isVisible(el){
  if (!el) return false;
  if (el.disabled) return false;
  const style = window.getComputedStyle(el);
  if (style.display === 'none' || style.visibility === 'hidden') return false;
  // offsetParent = null cuando está en display:none (salvo position:fixed)
  if (el.offsetParent === null && style.position !== 'fixed') return false;
  return true;
}

// Quita "required" de campos que no sean visibles; devuelve lista para restaurar si quisieras
function stripHiddenRequired(form){
  const removed = [];
  form.querySelectorAll('[required]').forEach(el=>{
    if (!isVisible(el) || el.disabled) {
      el.removeAttribute('required');
      removed.push(el);
    }
  });
  return removed;
}

function showLabel(inputEl, v){
  if (!inputEl) return;
  const wrap = inputEl.closest('label') || inputEl;
  wrap.style.display = v ? '' : 'none';
  enableField(inputEl, v);
  if (!v) inputEl.removeAttribute('required');
}

/* ================= Supabase / JWT ================= */
function getSupa(){
  if (!window.supabase || !window.ENV?.SUPABASE_URL || !window.ENV?.SUPABASE_ANON) return null;
  if (!window.__SUPA) {
    window.__SUPA = window.supabase.createClient(window.ENV.SUPABASE_URL, window.ENV.SUPABASE_ANON);
  }
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

/* ================= API helper ================= */
async function api(route, method="GET", body=null){
  const path = route.startsWith('/') ? route : ('/' + route);
  const headers = {};
  const jwt = await getJwt();
  if (jwt) headers['Authorization'] = 'Bearer ' + jwt;

  let fetchBody = undefined;
  if (body && !(body instanceof FormData)) { headers['Content-Type'] = 'application/json'; fetchBody = JSON.stringify(body); }
  else if (body instanceof FormData) { fetchBody = body; }

  const res = await fetch(`/index.php?route=${encodeURIComponent(path)}`, { method, headers, body: fetchBody, credentials: 'include' });
  const txt = await res.text();
  let data = null; try { data = txt ? JSON.parse(txt) : null; } catch { data = txt; }
  if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Not Found'));
  return data;
}

/* ================= ROOMS (SALA) ================= */
let __ALL_ROOMS__ = []; // cache global

function populateRoomSelect(rooms){
  const sel = $('#f_room_select');
  if (!sel) return;
  sel.innerHTML = '<option value="">— Sin sala —</option>' +
    rooms.map(r=>{
      const confTxt = r.conference_name ? ` • ${r.conference_name}` : (r.conference_id ? ` • conf #${r.conference_id}` : '');
      const capTxt  = (r.capacity ?? '') !== '' && r.capacity !== null ? ` (${r.capacity})` : '';
      const number  = r.number ? ` ${r.number}` : '';
      return `<option value="${r.id}">${esc(r.name)}${esc(number)}${esc(capTxt)}${esc(confTxt)}</option>`;
    }).join('');
}

async function loadRoomsAll(){
  let arr = [];
  try {
    arr = asArray(await api('/staff.rooms.all','GET'));
  } catch(e1){
    try {
      const confSel = $('#f_conf_select');
      if (confSel && confSel.value) {
        const byConf = asArray(await api(`/staff.rooms.by_conf&conference_id=${encodeURIComponent(confSel.value)}`,'GET'));
        arr = byConf;
      }
    } catch(e2){
      console.warn('No se pudieron cargar salas (staff/public). Continuando sin salas.', e1?.message || '', e2?.message || '');
      arr = [];
    }
  }

  __ALL_ROOMS__ = arr.map(r=>({
    id: r.id,
    name: r.name,
    number: r.number || null,
    capacity: (r.capacity ?? null),
    conference_id: r.conference_id ?? null,
    conference_name: r.conference_name || r.conf_name || null
  }));

  populateRoomSelect(__ALL_ROOMS__);
}

/* ================= CARGAS de datos de orador ================= */
async function loadConferences(){
  const selectEl = $('#f_conf_select');
  const listEl   = $('#list_conferences');
  if (!selectEl && !listEl) return [];

  let arr = [];
  try { arr = asArray(await api('/speaker.conferences.mine','GET')); } catch(e){ console.warn('conferences.mine:', e.message); }

  if (selectEl) {
    selectEl.innerHTML =
      '<option value="">— Sin conferencia —</option>' +
      arr.map(c=>{
        const id = c.id; const title = c.title || c.name || '(sin título)'; const loc = c.location || c.city || '';
        return `<option value="${id}">${esc(title)}${loc ? ' • ' + esc(loc) : ''}</option>`;
      }).join('');

    selectEl.addEventListener('change', ()=>{
      if (!__ALL_ROOMS__.length) return;
      const cid = selectEl.value ? parseInt(selectEl.value,10) : null;
      const filtered = cid ? __ALL_ROOMS__.filter(r => (r.conference_id === cid) || (r.conference_id === null))
                           : __ALL_ROOMS__;
      populateRoomSelect(filtered);
    });
  }

  if (listEl) {
    listEl.innerHTML = arr.map(c => {
      const title = c.title || c.name || '(sin título)';
      const dateIso = c.date || c.starts_at || '';
      const loc   = c.location || c.city || null;
      return `
        <div class="item" data-id="${c.id}" data-dateiso="${esc(dateIso||'')}">
          <div class="row" style="justify-content:space-between;align-items:flex-start">
            <div>
              <div><strong>${esc(title)}</strong> ${loc?`<span class="badge">${esc(loc)}</span>`:''}</div>
              <div class="muted">${dateIso ? new Date(dateIso).toLocaleString() : '—'}</div>
            </div>
            <div class="row">
              <button class="btn outline" data-act="edit-conf">Editar</button>
              <button class="btn outline" data-act="del-conf">Eliminar</button>
            </div>
          </div>
        </div>`;
    }).join('') || '<div class="item muted">Sin conferencias</div>';

    listEl.querySelectorAll('[data-act="edit-conf"]').forEach(b=>{
      b.addEventListener('click', ev=>{
        const it = ev.target.closest('.item');
        const itemType = $('#item_type'); const itemId = $('#item_id');
        if (!itemType || !itemId) return;

        itemType.value = 'conference';
        itemId.value   = it.dataset.id;
        if ($('#f_title')) $('#f_title').value   = it.querySelector('strong').textContent;
        if ($('#f_desc'))  $('#f_desc').value    = '';
        if ($('#f_city'))  $('#f_city').value    = it.querySelector('.badge')?.textContent || '';
        if ($('#f_date'))  $('#f_date').value    = toLocal(it.dataset.dateiso||'');
        if ($('#f_start')) $('#f_start').value   = $('#f_date') ? $('#f_date').value : '';
        if ($('#f_end'))   $('#f_end').value     = '';
        toggleBlocks(); if ($('#btnCancel')) $('#btnCancel').style.display='inline-block';
      });
    });
    listEl.querySelectorAll('[data-act="del-conf"]').forEach(b=>{
      b.addEventListener('click', async ev=>{
        const id = ev.target.closest('.item').dataset.id;
        if (!confirm('¿Eliminar esta conferencia?')) return;
        await api('/speaker.conference.delete','POST',{ id });
        await loadConferences();
      });
    });
  }

  return arr;
}

async function loadTalks(){
  let arr = [];
  try { arr = asArray(await api('/speaker.talks.mine','GET')); } catch(e){ console.warn('talks.mine:', e.message); }
  const list = $('#list_talks');
  if (list) {
    list.innerHTML = arr.map(t => {
      const stIso = t.start_time || t.starts_at || '';
      const enIso = t.end_time   || t.ends_at   || '';
      const roomId = t.room_id ?? '';
      const mod = t.modality || 'presencial';
      const venue = t.venue || '';
      const sUrl  = t.stream_url || '';
      return `
        <div class="item"
             data-id="${t.id}" data-conf="${t.conference_id ?? ''}" data-room="${roomId}"
             data-mod="${esc(mod)}" data-venue="${esc(venue)}" data-stream="${esc(sUrl)}"
             data-stiso="${esc(stIso||'')}" data-eniso="${esc(enIso||'')}">
          <div class="row" style="justify-content:space-between;align-items:flex-start">
            <div>
              <div><strong>${esc(t.title)}</strong> <span class="badge">${esc(mod)}</span></div>
              <div class="muted">${stIso?new Date(stIso).toLocaleString():'—'} → ${enIso?new Date(enIso).toLocaleString():'—'}</div>
              ${venue? `<div class="muted">Lugar: ${esc(venue)}</div>`:''}
              ${sUrl? `<div class="muted">Stream: ${esc(sUrl)}</div>`:''}
            </div>
            <div class="row">
              <a class="btn" href="/index.php?route=/speaker/talk&id=${encodeURIComponent(t.id)}" target="_blank">Ver</a>
              <button class="btn outline" data-act="edit-talk">Editar</button>
              <button class="btn outline" data-act="del-talk">Eliminar</button>
            </div>
          </div>
        </div>
      `;
    }).join('') || '<div class="item muted">Sin charlas</div>';

    list.querySelectorAll('[data-act="edit-talk"]').forEach(b=>{
      b.addEventListener('click', ev=>{
        const it = ev.target.closest('.item');
        const itemType = $('#item_type'); const itemId = $('#item_id');
        if (!itemType || !itemId) return;

        itemType.value = 'talk';
        itemId.value   = it.dataset.id;
        if ($('#f_conf_select')) $('#f_conf_select').value = it.dataset.conf || '';
        if ($('#f_title')) $('#f_title').value   = it.querySelector('strong').textContent;
        if ($('#f_desc'))  $('#f_desc').value    = '';
        if ($('#f_start')) $('#f_start').value   = toLocal(it.dataset.stiso||'');
        if ($('#f_end'))   $('#f_end').value     = toLocal(it.dataset.eniso||'');
        if ($('#f_pdf'))   $('#f_pdf').value     = '';
        if ($('#f_room_select') && it.dataset.room) $('#f_room_select').value = String(it.dataset.room);
        if ($('#f_mod_talk')) $('#f_mod_talk').value = it.dataset.mod || 'presencial';
        if ($('#f_venue_talk'))  $('#f_venue_talk').value = it.dataset.venue || '';
        if ($('#f_stream_talk')) $('#f_stream_talk').value= it.dataset.stream || '';
        toggleBlocks(); if ($('#btnCancel')) $('#btnCancel').style.display='inline-block';
      });
    });
    list.querySelectorAll('[data-act="del-talk"]').forEach(b=>{
      b.addEventListener('click', async ev=>{
        const id = ev.target.closest('.item').dataset.id;
        if (!confirm('¿Eliminar esta charla?')) return;
        await api('/speaker.talk.delete','POST',{ id });
        await loadTalks();
      });
    });
  }
}

async function loadCourses(){
  let arr = []; 
  try { arr = asArray(await api('/speaker.courses.mine','GET')); } catch(e){ console.warn('courses.mine:', e.message); }
  const list = $('#list_courses');
  if (list) {
    list.innerHTML = arr.map(w => {
      const cert = !!(w.cert_enabled || w.has_certificate);
      const certUrl = w.cert_form_url || w.certificate_url || '';
      return `
      <div class="item"
           data-id="${w.id}"
           data-room="${w.room_id ?? ''}"
           data-stiso="${esc(w.starts_at||'')}"
           data-eniso="${esc(w.ends_at||'')}"
           data-mod="${esc(w.modality||'presencial')}"
           data-venue="${esc(w.venue||'')}"
           data-stream="${esc(w.stream_url||'')}"
           data-cert="${cert ? '1':'0'}"
           data-certurl="${esc(certUrl)}">
        <div class="row" style="justify-content:space-between;align-items:flex-start">
          <div>
            <div>
              <strong>${esc(w.title)}</strong>
              <span class="badge">${esc(w.modality||'presencial')}</span>
              ${cert ? '<span class="badge" style="background:#0a6">Certificado</span>' : ''}
            </div>
            <div class="muted">${w.starts_at?new Date(w.starts_at).toLocaleString():'—'} → ${w.ends_at?new Date(w.ends_at).toLocaleString():'—'}</div>
            ${w.venue? `<div class="muted">Lugar: ${esc(w.venue)}</div>`:''}
            ${w.stream_url? `<div class="muted">Stream: ${esc(w.stream_url)}</div>`:''}
            ${cert && certUrl ? `<div class="muted">Formulario: ${esc(certUrl)}</div>`:''}
          </div>
          <div class="row">
            <a class="btn" href="/index.php?route=/speaker/course&id=${encodeURIComponent(w.id)}" target="_blank">Ver</a>
            <button class="btn outline" data-act="edit-course">Editar</button>
            <button class="btn outline" data-act="del-course">Eliminar</button>
          </div>
        </div>
      </div>`;
    }).join('') || '<div class="item muted">Sin cursos</div>';

    list.querySelectorAll('[data-act="edit-course"]').forEach(b=>{
      b.addEventListener('click', ev=>{
        const it = ev.target.closest('.item');
        const itemType = $('#item_type'); const itemId = $('#item_id');
        if (!itemType || !itemId) return;

        itemType.value = 'course'; itemId.value = it.dataset.id;
        if ($('#f_title'))  $('#f_title').value = it.querySelector('strong').textContent;
        if ($('#f_desc'))   $('#f_desc').value  = '';
        if ($('#f_start'))  $('#f_start').value = toLocal(it.dataset.stiso||'');
        if ($('#f_end'))    $('#f_end').value   = toLocal(it.dataset.eniso||'');
        if ($('#f_mod'))    $('#f_mod').value   = it.dataset.mod || 'presencial';
        if ($('#f_venue'))  $('#f_venue').value = it.dataset.venue || '';
        if ($('#f_stream')) $('#f_stream').value= it.dataset.stream || '';
        if ($('#f_room_select') && it.dataset.room) $('#f_room_select').value = String(it.dataset.room);

        if ($('#f_cert')) $('#f_cert').value = (it.dataset.cert === '1' ? 'si' : 'no');
        if ($('#f_cert_url')) $('#f_cert_url').value = it.dataset.certurl || '';
        toggleBlocks(); if ($('#btnCancel')) $('#btnCancel').style.display='inline-block';
      });
    });
    list.querySelectorAll('[data-act="del-course"]').forEach(b=>{
      b.addEventListener('click', async ev=>{
        const id = parseInt(ev.target.closest('.item').dataset.id,10);
        if (!confirm('¿Eliminar este curso?')) return;
        await api('/speaker.course.delete','POST',{ id });
        await loadCourses();
      });
    });
  }
}

async function loadWebinars(){
  let arr = []; try { arr = asArray(await api('/speaker.webinars.mine','GET')); } catch(e){ console.warn('webinars.mine:', e.message); }
  const list = $('#list_webinars');
  if (list) {
    list.innerHTML = arr.map(w => `
      <div class="item" data-id="${w.id}" data-room="${w.room_id ?? ''}" data-stiso="${esc(w.starts_at||'')}" data-eniso="${esc(w.ends_at||'')}" data-mod="${esc(w.modality||'virtual')}" data-venue="${esc(w.venue||'')}" data-stream="${esc(w.stream_url||'')}">
        <div class="row" style="justify-content:space-between;align-items:flex-start">
          <div>
            <div><strong>${esc(w.title)}</strong> <span class="badge">${esc(w.modality||'virtual')}</span></div>
            <div class="muted">${w.starts_at?new Date(w.starts_at).toLocaleString():'—'} → ${w.ends_at?new Date(w.ends_at).toLocaleString():'—'}</div>
            ${w.venue? `<div class="muted">Lugar: ${esc(w.venue)}</div>`:''}
            ${w.stream_url? `<div class="muted">Stream: ${esc(w.stream_url)}</div>`:''}
          </div>
          <div class="row">
            <a class="btn" href="/index.php?route=/speaker/webinar&id=${encodeURIComponent(w.id)}" target="_blank">Ver</a>
            <button class="btn outline" data-act="edit-web">Editar</button>
            <button class="btn outline" data-act="del-web">Eliminar</button>
          </div>
        </div>
      </div>
    `).join('') || '<div class="item muted">Sin webinars</div>';

    list.querySelectorAll('[data-act="edit-web"]').forEach(b=>{
      b.addEventListener('click', ev=>{
        const it = ev.target.closest('.item');
        const itemType = $('#item_type'); const itemId = $('#item_id');
        if (!itemType || !itemId) return;

        itemType.value = 'webinar'; itemId.value = it.dataset.id;
        if ($('#f_title'))  $('#f_title').value = it.querySelector('strong').textContent;
        if ($('#f_desc'))   $('#f_desc').value  = '';
        if ($('#f_start'))  $('#f_start').value = toLocal(it.dataset.stiso||'');
        if ($('#f_end'))    $('#f_end').value   = toLocal(it.dataset.eniso||'');
        if ($('#f_mod'))    $('#f_mod').value   = it.dataset.mod || 'virtual';
        if ($('#f_venue'))  $('#f_venue').value = it.dataset.venue || '';
        if ($('#f_stream')) $('#f_stream').value= it.dataset.stream || '';
        if ($('#f_room_select') && it.dataset.room) $('#f_room_select').value = String(it.dataset.room);
        toggleBlocks(); if ($('#btnCancel')) $('#btnCancel').style.display='inline-block';
      });
    });
    list.querySelectorAll('[data-act="del-web"]').forEach(b=>{
      b.addEventListener('click', async ev=>{
        const id = parseInt(ev.target.closest('.item').dataset.id,10);
        if (!confirm('¿Eliminar este webinar?')) return;
        await api('/speaker.webinar.delete','POST',{ id });
        await loadWebinars();
      });
    });
  }
}

/* ================= UI ================= */
function toggleBlocks(){
  const type = $('#item_type') ? $('#item_type').value : '';

  const blkConf       = $('#blk_conf');
  const blkTalk       = $('#blk_talk');
  const blkStream     = $('#blk_stream');       // cursos/webinars
  const blkRoom       = $('#blk_room');
  const blkStreamTalk = $('#blk_stream_talk');
  const blkCertCourse = $('#blk_cert_course');
  const lblCertUrl    = $('#lbl_cert_url');

  const fModTalk   = $('#f_mod_talk');
  const fVenueTalk = $('#f_venue_talk');
  const fStreamTalk= $('#f_stream_talk');

  const fMod   = $('#f_mod');
  const fVenue = $('#f_venue');
  const fStream= $('#f_stream');

  const fCert    = $('#f_cert');
  const fCertUrl = $('#f_cert_url');

  // bloques por tipo (showBlock también habilita/deshabilita inputs internos)
  showBlock(blkConf,   (type==='conference'));
  showBlock(blkTalk,   (type==='talk'));
  showBlock(blkStream, (type==='course' || type==='webinar'));
  showBlock(blkCertCourse, (type==='course')); // certificado solo en curso

  // ---------- TALK ----------
  if (type === 'talk') {
    const mod = (fModTalk?.value || 'presencial').toLowerCase(); // presencial | virtual | hibrida

    // Sala visible solo si NO es virtual
    showBlock(blkRoom, (mod !== 'virtual'));

    // Bloque de venue/stream visible si NO es presencial
    const showBlk = (mod !== 'presencial');
    showBlock(blkStreamTalk, showBlk);

    // Dentro del bloque: venue solo si no es virtual; stream si no es presencial
    showLabel(fVenueTalk, (mod !== 'virtual'));
    showLabel(fStreamTalk,(mod !== 'presencial'));

    // requeridos
    requireField(fVenueTalk, (mod === 'presencial' || mod === 'hibrida'));
    requireField(fStreamTalk,(mod === 'virtual' || mod === 'hibrida'));

    if (mod === 'virtual') { clearField(fVenueTalk); }
    if (mod === 'presencial') { clearField(fStreamTalk); }
  } else {
    showBlock(blkStreamTalk, false);
    showBlock(blkRoom,       false);
  }

  // ---------- COURSE / WEBINAR ----------
  if (type === 'course' || type === 'webinar') {
    const mod = (fMod?.value || (type==='webinar'?'virtual':'presencial')).toLowerCase();

    showBlock(blkRoom, (mod !== 'virtual'));

    showLabel(fVenue,  (mod !== 'virtual'));
    showLabel(fStream, (mod === 'virtual' || mod === 'hibrida'));

    requireField(fVenue,  (mod === 'presencial' || mod === 'hibrida'));
    requireField(fStream, (mod === 'virtual' || mod === 'hibrida'));

    if (mod === 'virtual') { clearField(fVenue); }
    if (mod === 'presencial') { clearField(fStream); }
  }

  // ---------- Certificado (solo curso) ----------
  if (type === 'course') {
    const enabled = (fCert?.value || 'no') === 'si';
    showEl(lblCertUrl, enabled);
    enableField(fCertUrl, enabled);
    requireField(fCertUrl, enabled);
    if (!enabled) clearField(fCertUrl);
  } else {
    showBlock(blkCertCourse, false);
  }
}

function resetForm(){
  if ($('#item_id')) $('#item_id').value='';
  if ($('#f_title')) $('#f_title').value='';
  if ($('#f_desc'))  $('#f_desc').value='';
  if ($('#f_start')) $('#f_start').value='';
  if ($('#f_end'))   $('#f_end').value='';
  if ($('#f_city'))  $('#f_city').value='';
  if ($('#f_date'))  $('#f_date').value='';
  if ($('#f_mod'))   $('#f_mod').value='presencial';
  if ($('#f_mod_talk')) $('#f_mod_talk').value='presencial';
  if ($('#f_venue')) $('#f_venue').value='';
  if ($('#f_stream'))$('#f_stream').value='';
  if ($('#f_venue_talk'))  $('#f_venue_talk').value='';
  if ($('#f_stream_talk')) $('#f_stream_talk').value='';
  if ($('#f_pdf'))   $('#f_pdf').value='';
  if ($('#f_conf_select')) $('#f_conf_select').value='';
  if ($('#f_room_select')) $('#f_room_select').value='';
  if ($('#f_cert')) $('#f_cert').value='no';
  if ($('#f_cert_url')) $('#f_cert_url').value='';
  if ($('#btnCancel')) $('#btnCancel').style.display='none';
  toggleBlocks();
}

/* ================= Slides (PDF) ================= */
async function uploadPdf(talkId, file){
  if (!talkId || !file) return;
  const form = new FormData();
  form.append('talk_id', talkId);
  form.append('file', file);
  await api('/speaker.slides.upload','POST', form);
}

/* ================= Submit unificado ================= */
async function submitUnified(e){
  e.preventDefault();

  // --- Validación manual: desactivar required de ocultos y validar visibles
  const form = $('#uniForm');
  toggleBlocks();                 // asegurar estados (required/disabled/show)
  stripHiddenRequired(form);      // quitar required de todo lo no visible
  if (form && !form.checkValidity()){
    form.reportValidity();
    return;
  }

  const itemTypeEl = $('#item_type'); if (!itemTypeEl) return;
  const idEl = $('#item_id'); const id = idEl ? idEl.value : '';
  const type = itemTypeEl.value;

  const add1hIfMissing = (isoStart, isoEnd) => {
    if (isoEnd) return isoEnd;
    if (!isoStart) return null;
    const t = new Date(isoStart).getTime();
    if (Number.isNaN(t)) return null;
    return new Date(t + 3600*1000).toISOString();
  };

  const getRoomId = () => {
    const v = $('#f_room_select')?.value || '';
    if (!v.trim()) return null;
    const n = parseInt(v,10);
    return Number.isNaN(n) ? null : n;
  };

  if (type === 'conference') {
    const starts = $('#f_start')?.value ? new Date($('#f_start').value).toISOString()
                 : ($('#f_date')?.value ? new Date($('#f_date').value).toISOString() : null);
    const ends   = add1hIfMissing(starts, $('#f_end')?.value ? new Date($('#f_end').value).toISOString() : null);

    const payload = {
      title: $('#f_title')?.value.trim() || '',
      description: $('#f_desc')?.value.trim() || null,
      location: $('#f_city')?.value.trim() || null,
      date: $('#f_date')?.value ? new Date($('#f_date').value).toISOString() : null,
      name:  $('#f_title')?.value.trim() || '',
      city:  $('#f_city')?.value.trim() || null,
      starts_at: starts,
      ends_at:   ends
    };
    if (!payload.name || !payload.city || !payload.starts_at) { alert('Nombre, ciudad e inicio son obligatorios'); return; }

    if (id) await api('/speaker.conference.update','POST',{ id, ...payload });
    else    await api('/speaker.conference.create','POST', payload);
    await loadConferences(); resetForm(); return;
  }

  if (type === 'talk') {
    const starts = $('#f_start')?.value ? new Date($('#f_start').value).toISOString() : null;
    const ends   = add1hIfMissing(starts, $('#f_end')?.value ? new Date($('#f_end').value).toISOString() : null);

    const selVal = $('#f_conf_select') ? ($('#f_conf_select').value || '').trim() : '';
    const confId = selVal ? parseInt(selVal,10) : null;

    const mod = ($('#f_mod_talk')?.value || 'presencial').toLowerCase();
    const roomId = (mod==='virtual') ? null : getRoomId();

    const payload = {
      conference_id: confId || null,
      room_id: roomId,
      modality: mod,
      venue: $('#f_venue_talk')?.value.trim() || null,
      stream_url: $('#f_stream_talk')?.value.trim() || null,
      title: $('#f_title')?.value.trim() || '',
      starts_at:  starts,
      ends_at:    ends
    };
    if (!payload.title || !payload.starts_at) { alert('Título e inicio son obligatorios'); return; }

    let talkId = id || null;
    if (talkId) { await api('/speaker.talk.update','POST',{ id: talkId, ...payload }); }
    else { const resp = await api('/speaker.talk.create','POST', payload); talkId = resp?.id || null; }

    const file = $('#f_pdf')?.files?.[0];
    if (file && talkId) await uploadPdf(talkId, file);
    alert('Charla guardada' + (file && talkId ? ' + PDF subido' : ''));
    await loadTalks(); resetForm(); return;
  }

  if (type === 'course') {
    const mod = ($('#f_mod')?.value || 'presencial').toLowerCase();
    const certEnabled = ($('#f_cert')?.value || 'no') === 'si';
    const certUrl = ($('#f_cert_url')?.value || '').trim() || null;

    if (certEnabled && !certUrl) { alert('Debes ingresar el link del formulario de certificación.'); return; }

    const payload = {
      title: $('#f_title')?.value.trim() || '',
      description: $('#f_desc')?.value.trim() || null,
      modality: mod,
      venue: $('#f_venue')?.value.trim() || null,
      stream_url: $('#f_stream')?.value.trim() || null,
      room_id: (mod==='virtual') ? null : getRoomId(),
      starts_at: $('#f_start')?.value ? new Date($('#f_start').value).toISOString() : null,
      ends_at:   $('#f_end')?.value ? new Date($('#f_end').value).toISOString()   : null,
      cert_enabled: certEnabled,
      cert_form_url: certUrl
    };
    if (!payload.title) { alert('Título es obligatorio'); return; }
    if (id) await api('/speaker.course.update','POST',{ id: parseInt(id,10), ...payload });
    else    await api('/speaker.course.create','POST', payload);
    await loadCourses(); resetForm(); return;
  }

  if (type === 'webinar') {
    const mod = ($('#f_mod')?.value || 'virtual').toLowerCase();
    const payload = {
      title: $('#f_title')?.value.trim() || '',
      description: $('#f_desc')?.value.trim() || null,
      modality: mod,
      venue: $('#f_venue')?.value.trim() || null,
      stream_url: $('#f_stream')?.value.trim() || null,
      room_id: (mod==='virtual') ? null : getRoomId(),
      starts_at: $('#f_start')?.value ? new Date($('#f_start').value).toISOString() : null,
      ends_at:   $('#f_end')?.value ? new Date($('#f_end').value).toISOString()   : null
    };
    if (!payload.title) { alert('Título es obligatorio'); return; }
    if (id) await api('/speaker.webinar.update','POST',{ id: parseInt(id,10), ...payload });
    else    await api('/speaker.webinar.create','POST', payload);
    await loadWebinars(); resetForm(); return;
  }
}

/* ================= Boot ================= */
function bindUI(){
  if ($('#item_type')) $('#item_type').addEventListener('change', toggleBlocks);
  if ($('#f_mod_talk')) $('#f_mod_talk').addEventListener('change', toggleBlocks);
  if ($('#f_mod')) $('#f_mod').addEventListener('change', toggleBlocks);
  if ($('#f_cert')) $('#f_cert').addEventListener('change', toggleBlocks);
  if ($('#btnCancel')) $('#btnCancel').addEventListener('click', resetForm);
  if ($('#uniForm'))  $('#uniForm').addEventListener('submit', submitUnified);
}
async function boot(){
  bindUI();
  // Desactiva validación nativa para evitar el "not focusable" y validar nosotros
  const form = $('#uniForm');
  if (form) form.setAttribute('novalidate','novalidate');

  toggleBlocks();           // asegura disabled/required correcto al cargar
  await loadRoomsAll();
  await loadConferences();
  await loadTalks();
  await loadCourses();
  await loadWebinars();
}
window.addEventListener('DOMContentLoaded', boot);
