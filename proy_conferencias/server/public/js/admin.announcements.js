const $ = s => document.querySelector(s);
async function apiR(r, m="GET", b=null){ return api(r,m,b); }
const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);

async function loadConfsInto(sel){
  const list = await apiR("attendee.conferences.list","GET");
  const arr = asArray(list);
  sel.innerHTML = `<option value="">— Global —</option>` + arr.map(c => `<option value="${c.id}">${c.name} • ${c.city}</option>`).join('');
}

async function loadAnnList(){
  try{
    const confId = $('#annFilter').value;
    const list = await apiR(`staff.announcements.list${confId?`&conference_id=${confId}`:''}`,"GET");
    const arr = asArray(list);
    $('#annList').innerHTML = arr.map(a => `
      <div class="item" data-id="${a.id}">
        <div class="row" style="justify-content:space-between;align-items:center">
          <div>
            <div><strong>${a.title}</strong> ${a.conference_id ? `<span class="badge">Conf #${a.conference_id}</span>` : `<span class="badge">Global</span>`}</div>
            <div class="muted" style="white-space:pre-wrap">${a.body}</div>
            <div class="muted" style="font-size:12px">${new Date(a.created_at).toLocaleString()}</div>
          </div>
          <div class="row">
            <button class="btn outline btnEdit">Editar</button>
            <button class="btn outline btnDel">Eliminar</button>
          </div>
        </div>
      </div>`).join('') || '<div class="item muted">Sin anuncios</div>';

    // binds editar/eliminar (igual que ya tenías)...
    $('#annList').querySelectorAll('.btnEdit').forEach(btn=>{
      btn.addEventListener('click',(ev)=>{
        const it = ev.target.closest('.item');
        $('#aid').value = it.dataset.id;
        $('#anntitle').value = it.querySelector('strong').textContent;
        $('#annbody').value  = it.querySelectorAll('.muted')[0].textContent;
        const badge = it.querySelector('.badge').textContent;
        if (badge.startsWith('Conf #')) $('#annconf').value = badge.replace('Conf #','').trim();
        else $('#annconf').value = '';
        $('#annFormTitle').textContent='Editar anuncio';
        $('#btnAnnSave').textContent='Guardar cambios';
        $('#btnAnnCancel').style.display='inline-block';
      });
    });
    $('#annList').querySelectorAll('.btnDel').forEach(btn=>{
      btn.addEventListener('click', async (ev)=>{
        const it = ev.target.closest('.item');
        const id = parseInt(it.dataset.id,10);
        if (!confirm('¿Eliminar este anuncio?')) return;
        await apiR('staff.announcement.delete','POST',{ id });
        await loadAnnList();
        resetForm();
      });
    });
  }catch(e){
    console.error(e);
    $('#annList').innerHTML = '<div class="item muted">Error cargando anuncios</div>';
  }
}

function resetForm(){
  $('#aid').value='';
  $('#anntitle').value='';
  $('#annbody').value='';
  $('#annconf').value='';
  $('#annFormTitle').textContent='Nuevo anuncio';
  $('#btnAnnSave').textContent='Publicar';
  $('#btnAnnCancel').style.display='none';
}

window.addEventListener('DOMContentLoaded', async ()=>{
  await loadConfsInto($('#annconf'));
  await loadConfsInto($('#annFilter'));
  $('#annFilter').addEventListener('change', loadAnnList);

  $('#formAnn').addEventListener('submit', async (e)=>{
    e.preventDefault();
    const id = $('#aid').value;
    const payload = {
      title: $('#anntitle').value,
      body:  $('#annbody').value,
      conference_id: $('#annconf').value ? parseInt($('#annconf').value,10) : null
    };
    if (id) {
      await apiR('staff.announcement.update','POST', { id: parseInt(id,10), ...payload });
      alert('Anuncio actualizado');
    } else {
      await apiR('staff.announcement.create','POST', payload);
      alert('Anuncio publicado');
    }
    await loadAnnList();
    resetForm();
  });

  $('#btnAnnCancel').addEventListener('click', resetForm);

  await loadAnnList();
});
