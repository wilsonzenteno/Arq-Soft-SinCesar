const $ = s => document.querySelector(s);
async function apiR(r, m="GET", b=null){ return api(r,m,b); }
const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);

function toLocalInput(dtISO){
  if (!dtISO) return '';
  const d = new Date(dtISO), pad = n => String(n).padStart(2,'0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

async function loadConfs(sel){
  const list = await apiR("attendee.conferences.list","GET");
  const arr = asArray(list);
  sel.innerHTML = arr.map(c => `<option value="${c.id}">${c.name} • ${c.city}</option>`).join('');
  return arr;
}
async function loadRoomsInto(confId, sel){
  try{
    const rooms = await apiR(`staff.rooms.by_conf&conference_id=${confId}`,"GET");
    const arr = asArray(rooms);
    sel.innerHTML = `<option value="">— Sin sala —</option>` + arr.map(r=>`<option value="${r.id}">${r.name}</option>`).join('');
  }catch(e){ console.error(e); sel.innerHTML = `<option value="">— Sin sala —</option>`; }
}
async function loadTalksList(confId){
  try{
    const talks = await apiR(`staff.talks.list&conference_id=${confId}`,"GET");
    const arr = asArray(talks);
    $('#talkList').innerHTML = arr.map(t => `
      <div class="item" data-id="${t.id}" data-conf="${confId}">
        <div class="row" style="justify-content:space-between;align-items:center">
          <div>
            <div><strong>${t.title}</strong></div>
            <div class="muted">${new Date(t.starts_at).toLocaleString()} → ${new Date(t.ends_at).toLocaleString()}</div>
            <div class="muted">Sala: ${t.room_id ?? '—'} | Speaker: ${t.speaker_id ?? '—'}</div>
          </div>
          <div class="row">
            <button class="btn outline btnEdit">Editar</button>
            <button class="btn outline btnDel">Eliminar</button>
          </div>
        </div>
      </div>`).join('') || '<div class="item muted">No hay charlas</div>';

    // binds editar / eliminar (igual que ya tenías) ...
    $('#talkList').querySelectorAll('.btnEdit').forEach(btn=>{
      btn.addEventListener('click', async (ev)=>{
        const it = ev.target.closest('.item');
        const id = parseInt(it.dataset.id,10);
        const confId = parseInt(it.dataset.conf,10);
        const title = it.querySelector('strong').textContent;
        const times = it.querySelectorAll('.muted')[0].textContent.split('→').map(s=>s.trim());
        const roomTxt = it.querySelectorAll('.muted')[1].textContent;
        const roomId = roomTxt.match(/Sala:\s(\d+|—)/)?.[1];
        const spkId  = roomTxt.match(/Speaker:\s([^\s]+|—)/)?.[1];

        $('#tid').value = id;
        $('#t_title').value = title;
        $('#t_conf').value = String(confId);
        await loadRoomsInto(confId, $('#t_room'));
        if (roomId && roomId !== '—') $('#t_room').value = String(parseInt(roomId,10)); else $('#t_room').value='';
        $('#t_start').value = toLocalInput(new Date(times[0]).toISOString());
        $('#t_end').value   = toLocalInput(new Date(times[1]).toISOString());
        $('#t_speaker_uuid').value = (spkId && spkId !== '—') ? spkId : '';
        $('#t_speaker_email').value = '';

        $('#talkFormTitle').textContent = 'Editar charla';
        $('#btnTalkSave').textContent = 'Guardar cambios';
        $('#btnTalkCancel').style.display = 'inline-block';
      });
    });
    $('#talkList').querySelectorAll('.btnDel').forEach(btn=>{
      btn.addEventListener('click', async (ev)=>{
        const it = ev.target.closest('.item');
        const id = parseInt(it.dataset.id,10);
        const confId = parseInt(it.dataset.conf,10);
        if (!confirm('¿Eliminar esta charla?')) return;
        await apiR('staff.talk.delete','POST',{ id });
        await loadTalksList(confId);
        resetForm();
      });
    });
  }catch(e){
    console.error(e);
    $('#talkList').innerHTML = '<div class="item muted">Error cargando charlas</div>';
  }
}

async function searchSpeaker(){
  const q = $('#t_speaker_email').value.trim();
  if (!q) { $('#speakerResults').innerHTML=''; $('#t_speaker_uuid').value=''; return; }
  try{
    const res = await apiR(`staff.users.search&email=${encodeURIComponent(q)}`,"GET");
    const arr = asArray(res);
    $('#speakerResults').innerHTML = arr.map(u => `
      <div class="item row" style="justify-content:space-between;align-items:center">
        <div>
          <div><strong>${u.name || '(sin nombre)'}</strong></div>
          <div class="muted">${u.email || ''}</div>
          <div class="muted" style="font-size:12px">${u.id}</div>
        </div>
        <button class="btn outline" type="button" data-id="${u.id}" data-email="${u.email}">Elegir</button>
      </div>`).join('') || '<div class="item muted">Sin resultados</div>';

    $('#speakerResults').querySelectorAll('button[data-id]').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        $('#t_speaker_uuid').value = btn.dataset.id;
        $('#t_speaker_email').value = btn.dataset.email || btn.dataset.id;
        alert('Speaker seleccionado');
      });
    });
  }catch(e){ console.error(e); alert('Error buscando usuarios'); }
}

function resetForm(){
  $('#tid').value='';
  $('#t_title').value='';
  $('#t_start').value='';
  $('#t_end').value='';
  $('#t_room').value='';
  $('#t_speaker_uuid').value='';
  $('#t_speaker_email').value='';
  $('#talkFormTitle').textContent='Nueva charla';
  $('#btnTalkSave').textContent='Crear charla';
  $('#btnTalkCancel').style.display='none';
}

window.addEventListener('DOMContentLoaded', async ()=>{
  const confSel = $('#t_conf');
  const roomSel = $('#t_room');
  const listConfSel = $('#list_conf');

  const confs = await loadConfs(confSel);
  listConfSel.innerHTML = confs.map(c => `<option value="${c.id}">${c.name} • ${c.city}</option>`).join('');

  if (confs.length){
    await loadRoomsInto(confs[0].id, roomSel);
    await loadTalksList(confs[0].id);
  }

  confSel.addEventListener('change', async ()=>{
    await loadRoomsInto(parseInt(confSel.value,10), roomSel);
  });
  listConfSel.addEventListener('change', async ()=>{
    await loadTalksList(parseInt(listConfSel.value,10));
  });

  $('#btnSearchSpeaker').addEventListener('click', searchSpeaker);

  $('#formTalk').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const id     = $('#tid').value;
    const confId = parseInt(confSel.value,10);
    const roomId = $('#t_room').value ? parseInt($('#t_room').value,10) : null;
    const title  = $('#t_title').value;
    const st     = new Date($('#t_start').value).toISOString();
    const en     = new Date($('#t_end').value).toISOString();
    const spk    = $('#t_speaker_uuid').value || null;

    if (id) {
      await apiR("staff.talk.update","POST",{ id: parseInt(id,10), conference_id: confId, room_id: roomId, title, starts_at: st, ends_at: en, speaker_id: spk });
      alert('Charla actualizada');
    } else {
      await apiR("staff.talk.create","POST",{ conference_id: confId, room_id: roomId, title, starts_at: st, ends_at: en, speaker_id: spk });
      alert('Charla creada');
    }
    await loadTalksList(parseInt($('#list_conf').value||confId,10));
    resetForm();
  });

  $('#btnTalkCancel').addEventListener('click', resetForm);
});
