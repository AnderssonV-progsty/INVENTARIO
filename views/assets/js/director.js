const API_BASE = (window.API_BASE || "../api").replace(/\/+$/, "");
const API_PEDIDOS = `${API_BASE}/pedidos_pendientes.php`;
const API_APROBAR_UNIFICAR = `${API_BASE}/aprobar_unificar.php`;
const API_LOGIN = `${API_BASE}/login.php`;

const state = {
  pedidos: [],
  seleccionados: new Set(),
};

const refs = {
  btnRecargar: document.getElementById("btnRecargar"),
  btnAprobar: document.getElementById("btnAprobar"),
  selectAll: document.getElementById("selectAll"),
  idArea: document.getElementById("idArea"),
  pedidosBody: document.getElementById("pedidosBody"),
  mensajeEstado: document.getElementById("mensajeEstado"),
  seleccionResumen: document.getElementById("seleccionResumen"),
};

document.addEventListener("DOMContentLoaded", async () => {
  bindEvents();
  const autenticado = await inicializarSesion();
  if (!autenticado) return;
  cargarPedidos();
});

function bindEvents() {
  refs.btnRecargar.addEventListener("click", cargarPedidos);
  refs.btnAprobar.addEventListener("click", aprobarYUnificar);
  refs.selectAll.addEventListener("change", toggleSeleccionTodos);
  refs.idArea.addEventListener("change", cargarPedidos);
}

async function inicializarSesion() {
  try {
    const data = await requestJson(API_LOGIN, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    const usuario = data.data?.usuario || {};
    const rol = String(usuario.rol || "")
      .trim()
      .toLowerCase();
    if (!["director", "directivo"].includes(rol)) {
      window.location.href = rutaPorRol(rol);
      return false;
    }

    refs.idArea.value = String(
      Number(usuario.id_area || usuario.id_oficina || 0),
    );
    refs.idArea.readOnly = true;
    return true;
  } catch (error) {
    window.location.href = "login.html";
    return false;
  }
}

function rutaPorRol(rol) {
  const rolNormalizado = String(rol || "")
    .trim()
    .toLowerCase();
  const mapa = {
    inventarista: "inventarista.html",
    director: "directivos.html",
    directivo: "directivos.html",
    operario: "operario.html",
    paqueteria: "paqueteria.html",
  };

  return mapa[rolNormalizado] || "login.html";
}

async function cargarPedidos() {
  const idArea = Number(refs.idArea.value || 0);
  setEstado("Cargando pedidos pendientes...");

  try {
    const url = new URL(API_PEDIDOS, window.location.href);
    if (idArea > 0) url.searchParams.set("id_oficina", String(idArea));

    const response = await fetch(url, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    const data = await response.json();
    if (!response.ok || !data.ok) {
      throw new Error(data.mensaje || "No fue posible cargar los pedidos.");
    }

    state.pedidos = Array.isArray(data.data) ? data.data : [];
    state.seleccionados.clear();
    renderPedidos();
    setEstado("Pedidos cargados correctamente.");
  } catch (error) {
    console.error(error);
    setEstado("Error al cargar pedidos: " + error.message, true);
  }
}

function renderPedidos() {
  refs.pedidosBody.innerHTML = "";

  if (state.pedidos.length === 0) {
    refs.pedidosBody.innerHTML =
      '<tr><td colspan="5" class="muted">No hay pedidos pendientes de aprobación para este área.</td></tr>';
    refs.selectAll.checked = false;
    actualizarResumen();
    return;
  }

  for (const pedido of state.pedidos) {
    const tr = document.createElement("tr");
    const idPedido = Number(pedido.id_pedido || 0);
    const checked = state.seleccionados.has(idPedido);

    tr.innerHTML = `
      <td><input type="checkbox" class="pedido-check" data-id="${idPedido}" ${checked ? "checked" : ""} /></td>
      <td>#${idPedido}<br /><span class="muted">${escapeHtml(pedido.nombre_oficina || "-")}</span></td>
      <td>${escapeHtml(pedido.observaciones || "Sin observaciones")}</td>
      <td>${escapeHtml(pedido.fecha_pedido || "-")}</td>
      <td>${renderItems(pedido.items || [])}</td>
    `;

    tr.querySelector(".pedido-check").addEventListener("change", (event) => {
      const target = event.target;
      const id = Number(target.dataset.id || 0);
      if (target.checked) {
        state.seleccionados.add(id);
      } else {
        state.seleccionados.delete(id);
      }
      actualizarResumen();
      refs.selectAll.checked =
        state.seleccionados.size === state.pedidos.length;
    });

    refs.pedidosBody.appendChild(tr);
  }

  refs.selectAll.checked =
    state.seleccionados.size === state.pedidos.length &&
    state.pedidos.length > 0;
  actualizarResumen();
}

function renderItems(items) {
  if (!Array.isArray(items) || items.length === 0) {
    return '<span class="muted">Sin items</span>';
  }

  return items
    .map(
      (item) =>
        `${escapeHtml(item.nombre_producto || "-")} (${Number(item.cantidad || 0)})`,
    )
    .join("<br />");
}

function toggleSeleccionTodos() {
  const checked = refs.selectAll.checked;
  state.seleccionados.clear();
  if (checked) {
    for (const pedido of state.pedidos) {
      state.seleccionados.add(Number(pedido.id_pedido || 0));
    }
  }
  renderPedidos();
}

function actualizarResumen() {
  refs.seleccionResumen.textContent = `${state.seleccionados.size} seleccionados`;
}

async function aprobarYUnificar() {
  if (state.seleccionados.size === 0) {
    setEstado("Selecciona al menos un pedido para aprobar.", true);
    return;
  }

  const confirmar = window.confirm(
    "¿Deseas aprobar y unificar los pedidos seleccionados?",
  );
  if (!confirmar) {
    return;
  }

  setEstado("Aprobando y unificando pedidos...");

  try {
    const response = await fetch(API_APROBAR_UNIFICAR, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ id_pedidos: Array.from(state.seleccionados) }),
    });

    const data = await response.json();
    if (!response.ok || !data.ok) {
      throw new Error(
        data.mensaje || "No fue posible aprobar y unificar los pedidos.",
      );
    }

    state.seleccionados.clear();
    await cargarPedidos();
    setEstado("Pedidos aprobados y unificados correctamente.");
    window.alert(
      `Pedido unificado creado correctamente. ID: ${data.data.id_pedido_unificado}`,
    );
  } catch (error) {
    console.error(error);
    setEstado("Error al aprobar y unificar: " + error.message, true);
    window.alert(
      "No fue posible aprobar y unificar los pedidos: " + error.message,
    );
  }
}

async function requestJson(url, options) {
  const response = await fetch(url, options);
  const text = await response.text();

  let data;
  try {
    data = text ? JSON.parse(text) : {};
  } catch (error) {
    throw new Error("La API devolvio una respuesta no valida en JSON.");
  }

  if (!response.ok || !data.ok) {
    throw new Error(data.mensaje || "Error en la operacion con la API.");
  }

  return data;
}

function setEstado(mensaje, isError = false) {
  refs.mensajeEstado.textContent = mensaje;
  refs.mensajeEstado.style.color = isError ? "#b91c1c" : "#065f46";
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}
