const API_BASE = (window.API_BASE || "../api").replace(/\/+$/, "");
const API_LOGIN = `${API_BASE}/login.php`;

const refs = {
  form: document.getElementById("loginForm"),
  username: document.getElementById("username"),
  password: document.getElementById("password"),
  mensaje: document.getElementById("mensajeEstado"),
};

document.addEventListener("DOMContentLoaded", () => {
  refs.form?.addEventListener("submit", iniciarSesion);
});

async function iniciarSesion(event) {
  event.preventDefault();

  const username = String(refs.username?.value || "").trim();
  const password = String(refs.password?.value || "");

  if (!username || !password) {
    setEstado("Usuario y clave son obligatorios.", true);
    return;
  }

  setEstado("Validando credenciales...");

  try {
    const response = await fetch(API_LOGIN, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ username, password }),
    });

    const data = await response.json();
    if (!response.ok || !data.ok) {
      throw new Error(data.mensaje || "No fue posible iniciar sesion.");
    }

    const redirectUrl = String(data.data?.redirect_url || "").trim();
    if (!redirectUrl) {
      throw new Error("Respuesta de login incompleta: falta redirect_url.");
    }

    setEstado("Login exitoso. Redirigiendo...");
    window.location.href = redirectUrl;
  } catch (error) {
    setEstado(error.message || "Error al iniciar sesion.", true);
  }
}

function setEstado(mensaje, isError = false) {
  if (!refs.mensaje) return;
  refs.mensaje.textContent = mensaje;
  refs.mensaje.style.color = isError ? "#b91c1c" : "#065f46";
}
