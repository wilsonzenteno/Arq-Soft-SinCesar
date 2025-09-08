// Obtiene (o rehidrata) el JWT desde supabase si no está en localStorage
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

// Wrapper de fetch para API del backend
async function api(route, method="GET", body=null){
  const headers = { 'Content-Type': 'application/json' };
  const jwt = await getJwt();
  if (jwt) headers['Authorization'] = 'Bearer ' + jwt;

  const res = await fetch(`/index.php?route=${route}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
    credentials: 'include' // por si necesitas enviar la cookie de todas formas
  });

  const text = await res.text();
  let data = null;
  try { data = text ? JSON.parse(text) : null; } catch { data = text; }

  if (!res.ok) {
    const msg = (data && data.error) ? data.error : (res.statusText || 'Request failed');
    throw new Error(msg);
  }
  return data;
}
