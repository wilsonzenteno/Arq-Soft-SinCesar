const $ = s => document.querySelector(s);
async function apiR(r, m="GET", b=null){ return api(r,m,b); }
const asArray = x => Array.isArray(x) ? x : (x && Array.isArray(x.data) ? x.data : []);

const ROLES = ["admin","staff","speaker","attendee"];

function userItem(u){
  const roleOptions = ROLES.map(r => `<option value="${r}" ${u.role===r?'selected':''}>${r}</option>`).join('');
  return `
    <div class="item" data-id="${u.id}">
      <div class="row" style="justify-content:space-between;align-items:flex-start">
        <div>
          <div><strong>${u.name || u.full_name || '(sin nombre)'}</strong> <span class="badge">${u.role || 'attendee'}</span></div>
          <div class="muted">${u.email || ''}</div>
          <div class="muted" style="font-size:12px">${u.id}</div>
        </div>
        <div class="row" style="align-items:flex-end">
          <label>Rol
            <select class="roleSel">${roleOptions}</select>
          </label>
          <button class="btn outline btnRoleSave" type="button">Guardar</button>
        </div>
      </div>
    </div>`;
}

async function doSearch(e){
  e?.preventDefault?.();
  const q = ($('#userEmail').value || '').trim();
  if (!q) { $('#userList').innerHTML = '<div class="item muted">Ingresa un email para buscar</div>'; return; }
  try{
    const res = await apiR(`staff.users.search&email=${encodeURIComponent(q)}`,"GET");
    const arr = asArray(res);
    $('#userList').innerHTML = arr.map(userItem).join('') || '<div class="item muted">Sin resultados</div>';

    // binds guardar
    $('#userList').querySelectorAll('.btnRoleSave').forEach(btn=>{
      btn.addEventListener('click', async ev=>{
        const wrap = ev.target.closest('.item');
        const id = wrap.dataset.id;
        const role = wrap.querySelector('.roleSel').value;
        try {
          await apiR('staff.user.role.update','POST',{ id, role });
          alert('Rol actualizado');
          // refrescar badge
          wrap.querySelector('.badge').textContent = role;
        } catch (e) {
          alert(e.message || 'Error actualizando rol');
        }
      });
    });
  } catch(e){
    console.error(e);
    $('#userList').innerHTML = '<div class="item muted">Error en la búsqueda</div>';
  }
}

window.addEventListener('DOMContentLoaded', ()=>{
  $('#userSearchForm').addEventListener('submit', doSearch);
  $('#btnUserSearch').addEventListener('click', doSearch);
});
