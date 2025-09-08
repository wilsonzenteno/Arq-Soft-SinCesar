<?php
$title = "Inicio — ABRAHAM";
$extra_js = [];
?>
<section class="card">
  <h2>Iniciar sesión</h2>
  <p class="muted">Accede con email/contraseña o con Google. Luego te redirijo según tu rol.</p>

  <form id="loginForm" novalidate>
    <label>Email
      <input type="email" id="login_email" autocomplete="email" required />
    </label>
    <label>Contraseña
      <input type="password" id="login_password" autocomplete="current-password" required minlength="6" />
    </label>
    <div class="row">
      <button class="btn" type="submit">Entrar</button>
      <button class="btn outline" type="button" id="openSignup">Crear cuenta</button>
      <button class="btn" type="button" id="google">Entrar con Google</button>
    </div>
  </form>
</section>

<!-- FORMULARIO DE REGISTRO (se muestra al pulsar "Crear cuenta") -->
<section class="card" id="signupCard" style="display:none">
  <h2>Crear cuenta</h2>
  <p class="muted">Regístrate con email y contraseña. Tu perfil se creará con rol <strong>attendee</strong> por defecto.</p>

  <form id="signupForm" novalidate>
    <label>Nombre completo (opcional)
      <input type="text" id="su_fullname" autocomplete="name" />
    </label>
    <label>Email
      <input type="email" id="su_email" autocomplete="email" required />
    </label>
    <label>Contraseña
      <input type="password" id="su_password" autocomplete="new-password" required minlength="6" />
    </label>
    <label>Repite la contraseña
      <input type="password" id="su_password2" autocomplete="new-password" required minlength="6" />
    </label>
    <div class="row">
      <button class="btn" type="submit">Crear cuenta</button>
      <button class="btn outline" type="button" id="cancelSignup">Cancelar</button>
    </div>
  </form>
</section>

<section class="card">
  <h2>Abrir ventanas manualmente</h2>
  <div class="row">
    <a class="btn outline" href="/index.php?route=/speaker" target="_blank">Oradores</a>
    <a class="btn outline" href="/index.php?route=/attendee" target="_blank">Asistentes</a>
    <a class="btn outline" href="/index.php?route=/staff"   target="_blank">Staff</a>
  </div>
</section>

<script>

window.addEventListener('DOMContentLoaded', () => {
  if (!window.supabase || !window.ENV?.SUPABASE_URL || !window.ENV?.SUPABASE_ANON) {
    alert('Falta configurar Supabase (URL/Anon key o script supabase-js).');
    return;
  }
  const supa = window.supabase.createClient(window.ENV.SUPABASE_URL, window.ENV.SUPABASE_ANON);

  // Helpers
  const $ = s => document.querySelector(s);

  function showSignup(show){
    $('#signupCard').style.display = show ? 'block' : 'none';
    if (show) $('#su_email').focus();
  }
  $('#openSignup').addEventListener('click', ()=> showSignup(true));
  $('#cancelSignup').addEventListener('click', ()=> showSignup(false));

  async function setServerCookie(jwt){
    // Guarda cookie HttpOnly en el servidor (protege vistas por rol)
    await fetch('/index.php?route=/auth.session', {
      method:'POST',
      headers: { 'Authorization': 'Bearer ' + jwt }
    }).catch(()=>{});
  }

  async function redirectByRole(){
    const { data, error } = await supa.auth.getSession();
    if (error) { alert(error.message); return; }
    const jwt = data?.session?.access_token;
    if (!jwt) return;

    localStorage.setItem('jwt', jwt);
    await setServerCookie(jwt);

    const me = await fetch('/index.php?route=/auth.role', {
      headers: { 'Authorization': 'Bearer ' + jwt }
    });
    if (!me.ok) { alert('No se pudo obtener el rol'); return; }
    const j = await me.json();
    const role = j.role || 'attendee';

    if (role === 'admin' || role === 'staff')      window.location.href = '/index.php?route=/staff';
    else if (role === 'speaker')                   window.location.href = '/index.php?route=/speaker';
    else                                           window.location.href = '/index.php?route=/attendee';
  }

  // ==========================
  // LOGIN (email / password)
  // ==========================
  $('#loginForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
      const email = $('#login_email').value.trim();
      const password = $('#login_password').value;

      if (!email || !/^[^@]+@[^@]+\.[^@]+$/.test(email)) { alert('Ingresa un email válido.'); return; }
      if (!password || password.length < 6) { alert('La contraseña debe tener al menos 6 caracteres.'); return; }

      const { error } = await supa.auth.signInWithPassword({ email, password });
      if (error) throw error;

      await redirectByRole();
    } catch (err) {
      alert('Login error: ' + (err?.message || err));
    }
  });

  // ==========================
  // SIGNUP (email / password)
  // ==========================
  $('#signupForm').addEventListener('submit', async (e)=>{
    e.preventDefault();
    try {
      const full_name = $('#su_fullname').value.trim();
      const email = $('#su_email').value.trim();
      const pass1 = $('#su_password').value;
      const pass2 = $('#su_password2').value;

      if (!email || !/^[^@]+@[^@]+\.[^@]+$/.test(email)) { alert('Ingresa un email válido.'); return; }
      if (!pass1 || pass1.length < 6) { alert('La contraseña debe tener al menos 6 caracteres.'); return; }
      if (pass1 !== pass2) { alert('Las contraseñas no coinciden.'); return; }

      const { data, error } = await supa.auth.signUp({
        email, password: pass1,
        options: { data: { full_name } } // guarda nombre en user metadata
      });

      if (error) {
        const msg = String(error.message || '').toLowerCase();
        if (msg.includes('anonymous sign-ins are disabled')) {
          alert('El registro por email está deshabilitado en Supabase.\nActiva Authentication → Providers → Email → Enable y Allow email signups.');
        } else if (msg.includes('signups not allowed') || msg.includes('signup not allowed')) {
          alert('Los registros están deshabilitados.\nRevisa Authentication → Providers → Email y Auth → Settings.');
        } else if (msg.includes('email provider disabled')) {
          alert('El proveedor de Email está desactivado.\nActívalo en Authentication → Providers → Email.');
        } else {
          alert('Signup error: ' + error.message);
        }
        return;
      }

      // Si confirmación de email está activada, no habrá sesión hasta confirmar
      if (data?.user && !data?.session) {
        showSignup(false);
        alert('Cuenta creada. Revisa tu correo para confirmar y luego inicia sesión.');
        return;
      }

      // Si viene con sesión, redirige
      showSignup(false);
      await redirectByRole();
    } catch (err) {
      alert('Signup error: ' + (err?.message || err));
    }
  });

  // ==========================
  // LOGIN con Google (force selector)
  // ==========================
  $('#google').addEventListener('click', async ()=>{
    const { error } = await supa.auth.signInWithOAuth({
      provider: 'google',
      options: {
        redirectTo: window.location.origin + '/index.php?route=/',
        queryParams: {
          prompt: 'select_account',        // muestra "Elegir cuenta"
          access_type: 'offline',
          include_granted_scopes: 'true'
        }
      }
    });
    if (error) alert(error.message);
  });

  // Si ya venimos de OAuth (o hay sesión previa), redirigir por rol
  supa.auth.getSession().then(({ data }) => {
    if (data?.session) redirectByRole();
  });
});
</script>
