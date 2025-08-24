// (VISTA) — Utilidades del cliente para llamar a CONTROLADORES (endpoints PHP)
// Nota: Para demo de identidad, pegá tu UUID de usuario (o token) en el campo de la vista.
const API_BASE = "http://localhost:8081/index.php";

async function api(route, method = "GET", body = null, jwt = null) {
  const headers = { "Content-Type": "application/json" };
  if (jwt) headers["Authorization"] = `Bearer ${jwt}`;
  const res = await fetch(`${API_BASE}?route=${encodeURIComponent(route)}`, {
    method, headers, body: body ? JSON.stringify(body) : null
  });
  if (!res.ok) throw new Error(await res.text());
  const ct = res.headers.get("content-type") || "";
  return ct.includes("application/json") ? res.json() : res.text();
}

function el(sel){ return document.querySelector(sel); }
function els(sel){ return Array.from(document.querySelectorAll(sel)); }
function html(target, content){ target.innerHTML = content; }
function toast(msg){ alert(msg); }
