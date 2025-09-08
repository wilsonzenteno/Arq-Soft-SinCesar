const $ = s => document.querySelector(s);
async function apiR(r, m="GET", b=null){ return api(r,m,b); }
const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);

function toLocalInput(dtISO){
  if (!dtISO) return '';
  const d = new Date(dtISO), pad = n => String(n).padStart(2,'0');
  return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}T${pad(d.getHours())}:${pad(d.getMinutes())}`;
}

async function loadConfs(){
  try{
    const list = await apiR("attendee.conferences.list","GET");
    const arr = asArray(list);
    $('#confList').innerHTML = arr.map(c => `
      <div class="item" data-id="${c.id}">
        <div class="row" style="justify-content:space-between;align-items:center">
          <div>
            <div><strong>${c.name}</strong> <span class="badge">${c.city}</span></div>
            <div class="muted">${new Date(c.starts_at).toLocaleString()} → ${new Date(c.ends_at).toLocaleString()}</div>
          </div>
          <div class="row">
            <button class="btn outline btnEdit">Editar</button>
            <button class="btn outline btnDel">Eliminar</button>
          </div>
        </div>
      </div>`).join('') || '<div class="item muted">Sin conferencias</div>';

    // binds...
    $('#confList').querySelectorAll('.btnEdit').forEach(btn=>{
      btn.addEventListener('click', (ev)=>{
        const it = ev.target.closest('.item');
        const id = parseInt(it.dataset.id,10);
        const name = it.querySelector('strong').textContent;
        const city = it.querySelector('.badge').textContent;
        const times = it.querySelector('.muted').textContent.split('→').map(s=>s.trim());
        $('#cid').value = id;
        $('#cname').value = name;
        $('#ccity').value = city;
        $('#cstart').value = toLocalInput(new Date(times[0]).toISOString());
        $('#cend').value   = toLocalInput(new Date(times[1]).toISOString());
        $('#confFormTitle').textContent = 'Editar conferencia';
        $('#btnConfSave').textContent = 'Guardar cambios';
        $('#btnConfCancel').style.display = 'inline-block';
      });
    });
    $('#confList').querySelectorAll('.btnDel').forEach(btn=>{
      btn.addEventListener('click', async (ev)=>{
        const it = ev.target.closest('.item');
        const id = parseInt(it.dataset.id,10);
        if (!confirm('¿Eliminar esta conferencia?')) return;
        await apiR('staff.conference.delete','POST',{ id });
        await loadConfs();
        resetForm();
      });
    });
  } catch(e){
    console.error(e);
    alert('Error cargando conferencias. ¿Sesión expirada?');
  }
}

function resetForm(){
  $('#cid').value=''; $('#cname').value=''; $('#ccity').value='';
  $('#cstart').value=''; $('#cend').value='';
  $('#confFormTitle').textContent='Nueva conferencia';
  $('#btnConfSave').textContent='Crear';
  $('#btnConfCancel').style.display='none';
}

window.addEventListener('DOMContentLoaded', ()=>{
  $('#formConf').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const id = $('#cid').value;
    const payload = {
      name: $('#cname').value,
      city: $('#ccity').value,
      starts_at: new Date($('#cstart').value).toISOString(),
      ends_at:   new Date($('#cend').value).toISOString()
    };
    if (id) await apiR('staff.conference.update','POST', { id: parseInt(id,10), ...payload });
    else     await apiR('staff.conference.create','POST', payload);
    alert(id ? 'Conferencia actualizada' : 'Conferencia creada');
    await loadConfs();
    resetForm();
  });
  $('#btnConfCancel').addEventListener('click', resetForm);
  loadConfs();
});
