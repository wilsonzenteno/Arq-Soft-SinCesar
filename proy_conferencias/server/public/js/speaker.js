const $ = s => document.querySelector(s);
const esc = s => String(s??'').replace(/[&<>"'`=\/]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'}[c]));
const toLocal = dt => { if(!dt) return ''; const d=new Date(dt); if(Number.isNaN(+d)) return ''; const p=n=>String(n).padStart(2,'0'); return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}T${p(d.getHours())}:${p(d.getMinutes())}`; };
const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);

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
  const headers = {};
  const jwt = await getJwt();
  if (jwt) headers['Authorization'] = 'Bearer ' + jwt;

  let fetchBody = undefined;
  if (body && !(body instanceof FormData)) { headers['Content-Type'] = 'application/json'; fetchBody = JSON.stringify(body); }
  else if (body instanceof FormData) { fetchBody = body; }

  const res = await fetch(`/index.php?route=${route}`, { method, headers, body: fetchBody, credentials: 'include' });
  const txt = await res.text();
  let data = null; try { data = txt ? JSON.parse(txt) : null; } catch { data = txt; }
  if (!res.ok) throw new Error((data && data.error) ? data.error : (res.statusText || 'Request failed'));
  return data;
}

/* ---------- CARGAS ---------- */
async function loadConferences(){
  let arr = [];
  try { arr = asArray(await api('speaker.conferences.mine','GET')); } catch(e){ console.warn('conferences.mine:', e.message); }
  $('#f_conf_select').innerHTML = arr.map(c=>{
    const id = c.id; const title = c.title || c.name || '(sin título)'; const loc = c.location || c.city || '';
    return `<option value="${id}">${esc(title)}${loc ? ' • ' + esc(loc) : ''}</option>`;
  }).join('');
  $('#list_conferences').innerHTML = arr.map(c => {
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

  $('#list_conferences').querySelectorAll('[data-act="edit-conf"]').forEach(b=>{
    b.addEventListener('click', ev=>{
      const it = ev.target.closest('.item');
      $('#item_type').value = 'conference';
      $('#item_id').value   = it.dataset.id;
      $('#f_title').value   = it.querySelector('strong').textContent;
      $('#f_desc').value    = '';
      $('#f_city').value    = it.querySelector('.badge')?.textContent || '';
      $('#f_date').value    = toLocal(it.dataset.dateiso||'');
      $('#f_start').value   = $('#f_date').value; $('#f_end').value='';
      toggleBlocks(); $('#btnCancel').style.display='inline-block';
    });
  });
  $('#list_conferences').querySelectorAll('[data-act="del-conf"]').forEach(b=>{
    b.addEventListener('click', async ev=>{
      const id = ev.target.closest('.item').dataset.id;
      if (!confirm('¿Eliminar esta conferencia?')) return;
      await api('speaker.conference.delete','POST',{ id });
      await loadConferences();
    });
  });
}

async function loadTalks(){
  let arr = [];
  try { arr = asArray(await api('speaker.talks.mine','GET')); } catch(e){ console.warn('talks.mine:', e.message); }
  $('#list_talks').innerHTML = arr.map(t => {
    const stIso = t.start_time || t.starts_at || '';
    const enIso = t.end_time   || t.ends_at   || '';
    return `
      <div class="item" data-id="${t.id}" data-conf="${t.conference_id}" data-stiso="${esc(stIso||'')}" data-eniso="${esc(enIso||'')}">
        <div class="row" style="justify-content:space-between;align-items:flex-start">
          <div>
            <div><strong>${esc(t.title)}</strong></div>
            <div class="muted">${stIso?new Date(stIso).toLocaleString():'—'} → ${enIso?new Date(enIso).toLocaleString():'—'}</div>
          </div>
          <div class="row">
            <button class="btn outline" data-act="edit-talk">Editar</button>
            <button class="btn outline" data-act="del-talk">Eliminar</button>
          </div>
        </div>
      </div>
    `;
  }).join('') || '<div class="item muted">Sin charlas</div>';

  $('#list_talks').querySelectorAll('[data-act="edit-talk"]').forEach(b=>{
    b.addEventListener('click', ev=>{
      const it = ev.target.closest('.item');
      $('#item_type').value = 'talk';
      $('#item_id').value   = it.dataset.id;
      $('#f_conf_select').value = it.dataset.conf;
      $('#f_title').value   = it.querySelector('strong').textContent;
      $('#f_desc').value    = '';
      $('#f_start').value   = toLocal(it.dataset.stiso||'');
      $('#f_end').value     = toLocal(it.dataset.eniso||'');
      $('#f_pdf').value     = '';
      toggleBlocks(); $('#btnCancel').style.display='inline-block';
    });
  });
  $('#list_talks').querySelectorAll('[data-act="del-talk"]').forEach(b=>{
    b.addEventListener('click', async ev=>{
      const id = ev.target.closest('.item').dataset.id;
      if (!confirm('¿Eliminar esta charla?')) return;
      await api('speaker.talk.delete','POST',{ id });
      await loadTalks();
    });
  });
}

async function loadCourses(){
  let arr = []; try { arr = asArray(await api('speaker.courses.mine','GET')); } catch(e){ console.warn('courses.mine:', e.message); }
  $('#list_courses').innerHTML = arr.map(w => `
    <div class="item" data-id="${w.id}" data-stiso="${esc(w.starts_at||'')}" data-eniso="${esc(w.ends_at||'')}" data-mod="${esc(w.modality||'presencial')}" data-venue="${esc(w.venue||'')}" data-stream="${esc(w.stream_url||'')}">
      <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
          <div><strong>${esc(w.title)}</strong> <span class="badge">${esc(w.modality||'presencial')}</span></div>
          <div class="muted">${w.starts_at?new Date(w.starts_at).toLocaleString():'—'} → ${w.ends_at?new Date(w.ends_at).toLocaleString():'—'}</div>
          ${w.venue? `<div class="muted">Lugar: ${esc(w.venue)}</div>`:''}
          ${w.stream_url? `<div class="muted">Stream: ${esc(w.stream_url)}</div>`:''}
        </div>
        <div class="row">
          <button class="btn outline" data-act="edit-course">Editar</button>
          <button class="btn outline" data-act="del-course">Eliminar</button>
        </div>
      </div>
    </div>
  `).join('') || '<div class="item muted">Sin cursos</div>';

  $('#list_courses').querySelectorAll('[data-act="edit-course"]').forEach(b=>{
    b.addEventListener('click', ev=>{
      const it = ev.target.closest('.item');
      $('#item_type').value = 'course'; $('#item_id').value = it.dataset.id;
      $('#f_title').value = it.querySelector('strong').textContent; $('#f_desc').value  = '';
      $('#f_start').value = toLocal(it.dataset.stiso||''); $('#f_end').value   = toLocal(it.dataset.eniso||'');
      $('#f_mod').value   = it.dataset.mod || 'presencial';
      $('#f_venue').value = it.dataset.venue || '';
      $('#f_stream').value= it.dataset.stream || '';
      toggleBlocks(); $('#btnCancel').style.display='inline-block';
    });
  });
  $('#list_courses').querySelectorAll('[data-act="del-course"]').forEach(b=>{
    b.addEventListener('click', async ev=>{
      const id = parseInt(ev.target.closest('.item').dataset.id,10);
      if (!confirm('¿Eliminar este curso?')) return;
      await api('speaker.course.delete','POST',{ id });
      await loadCourses();
    });
  });
}

async function loadWebinars(){
  let arr = []; try { arr = asArray(await api('speaker.webinars.mine','GET')); } catch(e){ console.warn('webinars.mine:', e.message); }
  $('#list_webinars').innerHTML = arr.map(w => `
    <div class="item" data-id="${w.id}" data-stiso="${esc(w.starts_at||'')}" data-eniso="${esc(w.ends_at||'')}" data-mod="${esc(w.modality||'virtual')}" data-venue="${esc(w.venue||'')}" data-stream="${esc(w.stream_url||'')}">
      <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
          <div><strong>${esc(w.title)}</strong> <span class="badge">${esc(w.modality||'virtual')}</span></div>
          <div class="muted">${w.starts_at?new Date(w.starts_at).toLocaleString():'—'} → ${w.ends_at?new Date(w.ends_at).toLocaleString():'—'}</div>
          ${w.venue? `<div class="muted">Lugar: ${esc(w.venue)}</div>`:''}
          ${w.stream_url? `<div class="muted">Stream: ${esc(w.stream_url)}</div>`:''}
        </div>
        <div class="row">
          <button class="btn outline" data-act="edit-web">Editar</button>
          <button class="btn outline" data-act="del-web">Eliminar</button>
        </div>
      </div>
    </div>
  `).join('') || '<div class="item muted">Sin webinars</div>';

  $('#list_webinars').querySelectorAll('[data-act="edit-web"]').forEach(b=>{
    b.addEventListener('click', ev=>{
      const it = ev.target.closest('.item');
      $('#item_type').value = 'webinar'; $('#item_id').value = it.dataset.id;
      $('#f_title').value = it.querySelector('strong').textContent; $('#f_desc').value  = '';
      $('#f_start').value = toLocal(it.dataset.stiso||''); $('#f_end').value   = toLocal(it.dataset.eniso||'');
      $('#f_mod').value   = it.dataset.mod || 'virtual';
      $('#f_venue').value = it.dataset.venue || ''; $('#f_stream').value= it.dataset.stream || '';
      toggleBlocks(); $('#btnCancel').style.display='inline-block';
    });
  });
  $('#list_webinars').querySelectorAll('[data-act="del-web"]').forEach(b=>{
    b.addEventListener('click', async ev=>{
      const id = parseInt(ev.target.closest('.item').dataset.id,10);
      if (!confirm('¿Eliminar este webinar?')) return;
      await api('speaker.webinar.delete','POST',{ id });
      await loadWebinars();
    });
  });
}

/* ---------- UI ---------- */
function toggleBlocks(){
  const type = $('#item_type').value;
  $('#blk_conf').style.display   = (type==='conference') ? 'flex' : 'none';
  $('#blk_talk').style.display   = (type==='talk') ? 'flex' : 'none';
  $('#blk_stream').style.display = (type==='course' || type==='webinar') ? 'flex' : 'none';
}
function resetForm(){
  $('#item_id').value=''; $('#f_title').value=''; $('#f_desc').value='';
  $('#f_start').value=''; $('#f_end').value=''; $('#f_city').value=''; $('#f_date').value='';
  $('#f_mod').value='presencial'; $('#f_venue').value=''; $('#f_stream').value='';
  const pdf = $('#f_pdf'); if (pdf) pdf.value='';
  $('#btnCancel').style.display='none';
}

/* ---------- slides ---------- */
async function uploadPdf(talkId, file){
  if (!talkId || !file) return;
  const form = new FormData();
  form.append('talk_id', talkId);
  form.append('file', file);
  const jwt = await getJwt();
  const res = await fetch('/index.php?route=/speaker.slides.upload', {
    method: 'POST',
    headers: jwt ? { 'Authorization': 'Bearer ' + jwt } : {},
    body: form, credentials: 'include'
  });
  if (!res.ok) throw new Error((await res.text()) || 'Upload fail');
}

/* ---------- submit ---------- */
async function submitUnified(e){
  e.preventDefault();
  const id   = $('#item_id').value;
  const type = $('#item_type').value;

  if (type === 'conference') {
    const payload = {
      title: $('#f_title').value.trim(),
      description: $('#f_desc').value.trim() || null,
      location: $('#f_city').value.trim() || null,
      date: $('#f_date').value ? new Date($('#f_date').value).toISOString() : ($('#f_start').value ? new Date($('#f_start').value).toISOString() : null),
      name:  $('#f_title').value.trim(),
      city:  $('#f_city').value.trim() || null,
      starts_at: $('#f_start').value ? new Date($('#f_start').value).toISOString() : ($('#f_date').value ? new Date($('#f_date').value).toISOString() : null),
      ends_at:   $('#f_end').value ? new Date($('#f_end').value).toISOString()   : null
    };
    if (id) await api('speaker.conference.update','POST',{ id, ...payload });
    else    await api('speaker.conference.create','POST', payload);
    await loadConferences(); resetForm(); return;
  }

  if (type === 'talk') {
    const payload = {
      conference_id: $('#f_conf_select').value,
      title: $('#f_title').value.trim(),
      description: $('#f_desc').value.trim() || null,
      start_time: $('#f_start').value ? new Date($('#f_start').value).toISOString() : null,
      end_time:   $('#f_end').value ? new Date($('#f_end').value).toISOString()   : null,
      starts_at:  $('#f_start').value ? new Date($('#f_start').value).toISOString() : null,
      ends_at:    $('#f_end').value ? new Date($('#f_end').value).toISOString()   : null
    };

    let talkId = id || null;
    if (talkId) { await api('speaker.talk.update','POST',{ id: talkId, ...payload }); }
    else { const resp = await api('speaker.talk.create','POST', payload); talkId = resp?.id || null; }

    const file = $('#f_pdf')?.files?.[0];
    if (file && talkId) await uploadPdf(talkId, file);
    alert('Charla guardada' + (file && talkId ? ' + PDF subido' : ''));
    await loadTalks(); resetForm(); return;
  }

  if (type === 'course') {
    const payload = {
      title: $('#f_title').value.trim(),
      description: $('#f_desc').value.trim() || null,
      modality: $('#f_mod').value,
      venue: $('#f_venue').value.trim() || null,
      stream_url: $('#f_stream').value.trim() || null,
      starts_at: $('#f_start').value ? new Date($('#f_start').value).toISOString() : null,
      ends_at:   $('#f_end').value ? new Date($('#f_end').value).toISOString()   : null
    };
    if (id) await api('speaker.course.update','POST',{ id: parseInt(id,10), ...payload });
    else    await api('speaker.course.create','POST', payload);
    await loadCourses(); resetForm(); return;
  }

  if (type === 'webinar') {
    const payload = {
      title: $('#f_title').value.trim(),
      description: $('#f_desc').value.trim() || null,
      modality: $('#f_mod').value,
      venue: $('#f_venue').value.trim() || null,
      stream_url: $('#f_stream').value.trim() || null,
      starts_at: $('#f_start').value ? new Date($('#f_start').value).toISOString() : null,
      ends_at:   $('#f_end').value ? new Date($('#f_end').value).toISOString()   : null
    };
    if (id) await api('speaker.webinar.update','POST',{ id: parseInt(id,10), ...payload });
    else    await api('speaker.webinar.create','POST', payload);
    await loadWebinars(); resetForm(); return;
  }
}

/* ---------- init ---------- */
function bindUI(){
  $('#item_type').addEventListener('change', toggleBlocks);
  $('#btnCancel').addEventListener('click', resetForm);
  $('#uniForm').addEventListener('submit', submitUnified);
}
async function boot(){ bindUI(); toggleBlocks(); await loadConferences(); await loadTalks(); await loadCourses(); await loadWebinars(); }
window.addEventListener('DOMContentLoaded', boot);
