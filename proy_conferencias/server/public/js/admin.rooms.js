const $ = s => document.querySelector(s);
async function apiR(r, m="GET", b=null){ return api(r,m,b); }

function asArray(x){
  if (Array.isArray(x)) return x;
  if (x && Array.isArray(x.data)) return x.data; // por si algún proxy devuelve {data:[...]}
  return [];
}

async function loadConfs(sel){
  try {
    const list = await apiR("attendee.conferences.list","GET");
    const arr = asArray(list);
    if (!arr.length && list && list.error) throw new Error(list.error);

    sel.innerHTML = arr.map(c => `<option value="${c.id}">${c.name} • ${c.city}</option>`).join('');
    if (arr.length) await loadRooms(arr[0].id);
    else {
      sel.innerHTML = '';
      $('#roomList').innerHTML = '<div class="item muted">No hay conferencias disponibles</div>';
    }
  } catch (e) {
    console.error(e);
    alert('No se pudieron cargar las conferencias. ¿Sesión expirada? Vuelve a iniciar sesión.');
    $('#roomList').innerHTML = '<div class="item muted">Error cargando conferencias</div>';
  }
}

async function loadRooms(confId){
  try {
    const rooms = await apiR(`staff.rooms.by_conf&conference_id=${confId}`, "GET");
    const arr = asArray(rooms);
    if (!arr.length && rooms && rooms.error) throw new Error(rooms.error);

    $('#roomList').innerHTML = arr.map(r => `
      <div class="item" data-id="${r.id}">
        <div class="row" style="justify-content:space-between;align-items:center">
          <div><strong>#${r.id}</strong> ${r.name}</div>
          <div class="row">
            <button class="btn outline btnEdit">Editar</button>
            <button class="btn outline btnDel">Eliminar</button>
          </div>
        </div>
      </div>`).join('') || '<div class="item muted">Sin salas</div>';

    // bind editar/eliminar
    $('#roomList').querySelectorAll('.btnEdit').forEach(btn=>{
      btn.addEventListener('click',(ev)=>{
        const it = ev.target.closest('.item');
        $('#rid').value = it.dataset.id;
        $('#rname').value = it.querySelector('div > strong').nextSibling.textContent.trim();
        $('#roomFormTitle').textContent = 'Editar sala';
        $('#btnRoomSave').textContent = 'Guardar cambios';
        $('#btnRoomCancel').style.display = 'inline-block';
      });
    });
    $('#roomList').querySelectorAll('.btnDel').forEach(btn=>{
      btn.addEventListener('click', async (ev)=>{
        const it = ev.target.closest('.item');
        const id = parseInt(it.dataset.id,10);
        if (!confirm('¿Eliminar esta sala?')) return;
        await apiR('staff.room.delete','POST',{ id });
        await loadRooms(parseInt($('#selConf').value,10));
        resetForm();
      });
    });
  } catch (e) {
    console.error(e);
    alert('No se pudieron cargar las salas. ¿Sesión expirada o sin permisos?');
    $('#roomList').innerHTML = '<div class="item muted">Error cargando salas</div>';
  }
}

function resetForm(){
  $('#rid').value='';
  $('#rname').value='';
  $('#roomFormTitle').textContent='Nueva sala';
  $('#btnRoomSave').textContent='Crear';
  $('#btnRoomCancel').style.display='none';
}

window.addEventListener('DOMContentLoaded', ()=>{
  const selConf = $('#selConf');
  loadConfs(selConf);

  selConf.addEventListener('change', ()=> loadRooms(parseInt(selConf.value,10)));

  $('#formRoom').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const confId = parseInt(selConf.value,10);
    const id = $('#rid').value;
    if (id) {
      await apiR('staff.room.update','POST',{ id: parseInt(id,10), name: $('#rname').value });
      alert('Sala actualizada');
    } else {
      await apiR('staff.room.create','POST',{ conference_id: confId, name: $('#rname').value });
      alert('Sala creada');
    }
    await loadRooms(confId);
    resetForm();
  });

  $('#btnRoomCancel').addEventListener('click', resetForm);
});
