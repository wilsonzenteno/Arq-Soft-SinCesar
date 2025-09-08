// server/public/js/admin.rooms.js
const $ = s => document.querySelector(s);
async function apiR(r, m="GET", b=null){ return api(r,m,b); }

function asArray(x){
  if (Array.isArray(x)) return x;
  if (x && Array.isArray(x.data)) return x.data; // por si algún proxy devuelve {data:[...]}
  return [];
}
function escapeHtml(s){
  return String(s ?? '').replace(/[&<>"'`=\/]/g, c => ({
    '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;','/':'&#x2F;','`':'&#x60;','=':'&#x3D;'
  }[c]));
}

function renderRoomsInto(containerSel, rooms){
  const host = $(containerSel);
  if (!host) return;
  host.innerHTML = rooms.map(r => `
    <div class="item" data-id="${r.id}"
         data-name="${escapeHtml(r.name || '')}"
         data-number="${escapeHtml(r.number || '')}"
         data-capacity="${(r.capacity ?? '')}">
      <div class="row" style="justify-content:space-between;align-items:center">
        <div>
          <strong>#${r.id}</strong> ${escapeHtml(r.name || '')}
          ${r.number ? `<span class="badge">Nº ${escapeHtml(r.number)}</span>` : ''}
          ${Number.isFinite(r.capacity) ? `<span class="badge">${r.capacity|0} pax</span>` : ''}
          ${r.conference_id ? `<span class="badge">Conf #${r.conference_id}</span>` : ''}
        </div>
        <div class="row">
          <button class="btn outline btnEdit" type="button">Editar</button>
          <button class="btn outline btnDel"  type="button">Eliminar</button>
        </div>
      </div>
    </div>
  `).join('') || '<div class="item muted">Sin salas</div>';

  // Editar
  host.querySelectorAll('.btnEdit').forEach(btn=>{
    btn.addEventListener('click', (ev)=>{
      const it = ev.target.closest('.item');
      $('#rid').value = it.dataset.id;
      $('#rname').value = it.dataset.name || '';
      $('#rnumber').value = it.dataset.number || '';
      $('#rcap').value = it.dataset.capacity !== '' ? String(it.dataset.capacity) : '';
      $('#roomFormTitle').textContent = 'Editar sala';
      $('#btnRoomSave').textContent = 'Guardar cambios';
      $('#btnRoomCancel').style.display = 'inline-block';
      window.scrollTo({ top: 0, behavior: 'smooth' });
    });
  });

  // Eliminar
  host.querySelectorAll('.btnDel').forEach(btn=>{
    btn.addEventListener('click', async (ev)=>{
      const it = ev.target.closest('.item');
      const id = parseInt(it.dataset.id,10);
      if (!id) return;
      if (!confirm('¿Eliminar esta sala?')) return;
      try{
        await apiR('staff.room.delete','POST',{ id });
        // refrescar ambas listas
        const confId = parseInt($('#selConf').value,10);
        if (confId) await loadRooms(confId);
        await loadAllRooms();
        resetForm();
      }catch(e){
        alert(e?.message || 'No se pudo eliminar la sala');
      }
    });
  });
}

/* ====== Cargar conferencias (para filtro) ====== */
async function loadConfs(sel){
  try {
    const list = await apiR("attendee.conferences.list","GET");
    const arr = asArray(list);

    if (!arr.length) {
      sel.innerHTML = '<option value="">— No hay conferencias —</option>';
      $('#roomList').innerHTML = '<div class="item muted">No hay conferencias disponibles</div>';
      return;
    }

    sel.innerHTML = arr.map(c => `<option value="${c.id}">${escapeHtml(c.name)} • ${escapeHtml(c.city)}</option>`).join('');
    await loadRooms(arr[0].id);
  } catch (e) {
    console.error(e);
    sel.innerHTML = '<option value="">— Error cargando —</option>';
    $('#roomList').innerHTML = '<div class="item muted">Error cargando conferencias</div>';
  }
}

/* ====== Salas por conferencia (filtro) ====== */
async function loadRooms(confId){
  try {
    if (!confId) {
      $('#roomList').innerHTML = '<div class="item muted">Elige una conferencia para listar sus salas</div>';
      return;
    }
    const rooms = await apiR(`staff.rooms.by_conf&conference_id=${confId}`, "GET");
    renderRoomsInto('#roomList', asArray(rooms));
  } catch (e) {
    console.error(e);
    $('#roomList').innerHTML = '<div class="item muted">Error cargando salas</div>';
  }
}

/* ====== Todas las salas ====== */
async function loadAllRooms(){
  try{
    const rooms = await apiR('staff.rooms.all','GET');
    renderRoomsInto('#roomAllList', asArray(rooms));
  }catch(e){
    console.error(e);
    $('#roomAllList').innerHTML = '<div class="item muted">Error cargando todas las salas</div>';
  }
}

/* ====== Form ====== */
function resetForm(){
  $('#rid').value = '';
  $('#rname').value = '';
  $('#rnumber').value = '';
  $('#rcap').value = '';
  $('#roomFormTitle').textContent = 'Nueva sala';
  $('#btnRoomSave').textContent = 'Crear';
  $('#btnRoomCancel').style.display = 'none';
}

window.addEventListener('DOMContentLoaded', ()=>{
  const selConf = $('#selConf');
  loadConfs(selConf);
  loadAllRooms();

  selConf.addEventListener('change', ()=> loadRooms(parseInt(selConf.value,10)));

  $('#formRoom').addEventListener('submit', async (e)=>{
    e.preventDefault();

    const id       = $('#rid').value ? parseInt($('#rid').value,10) : null;
    const name     = ($('#rname').value || '').trim();
    const number   = ($('#rnumber').value || '').trim() || null;
    const capRaw   = ($('#rcap').value || '').trim();
    const capacity = capRaw === '' ? null : Math.max(0, parseInt(capRaw,10));

    if (!name) { alert('Nombre es obligatorio'); return; }

    try{
      if (id) {
        await apiR('staff.room.update','POST',{ id, name, number, capacity });
        alert('Sala actualizada');
      } else {
        await apiR('staff.room.create','POST',{ name, number, capacity }); // sin conference_id
        alert('Sala creada');
      }
      // refrescar ambas listas
      const confId = parseInt($('#selConf').value,10);
      if (confId) await loadRooms(confId);
      await loadAllRooms();
      resetForm();
    }catch(e2){
      alert(e2?.message || 'No se pudo guardar la sala');
    }
  });

  $('#btnRoomCancel').addEventListener('click', resetForm);
});
