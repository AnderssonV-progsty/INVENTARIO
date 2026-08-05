const API_BASE = (window.API_BASE || "../api").replace(/\/+$/, "");
const API_PEDIDOS = `${API_BASE}/pedidos_director.php`;
const API_PROCESAR = `${API_BASE}/procesar_pedido_director.php`;

const state = {
  pedidos: [],
  pedidoActivo: null,
};

const refs = {
  btnRecargar: document.getElementById("btnRecargar"),
  btnAprobar: document.getElementById("btnAprobar"),
  btnRechazar: document.getElementById("btnRechazar"),
  pedidosBody: document.getElementById("pedidosBody"),
  detalleBody: document.getElementById("detalleBody"),
  pedidoSeleccionado: document.getElementById("pedidoSeleccionado"),
  motivoRechazo: document.getElementById("motivoRechazo"),
  mensajeEstado: document.getElementById("mensajeEstado"),
};

document.addEventListener("DOMContentLoaded", () => {
  bindEvents();
  cargarPedidos();
});

function bindEvents() {
  refs.btnRecargar?.addEventListener("click", cargarPedidos);
  refs.btnAprobar?.addEventListener("click", () => procesarPedido("aprobar"));
  refs.btnRechazar?.addEventListener("click", () => procesarPedido("rechazar"));
}

async function cargarPedidos() {
  setEstado("Cargando pedidos pendientes de direccion...");

  try {
    const response = await fetch(API_PEDIDOS, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    const data = await response.json();

    if (response.status === 401) {
      window.location.href = "login.html";
      return;
    }

    if (!response.ok || !data.ok) {
      throw new Error(data.mensaje || "No fue posible cargar los pedidos.");
    }

    state.pedidos = Array.isArray(data.data) ? data.data : [];
    state.pedidoActivo = null;
    renderPedidos();
    renderDetalle();
    setEstado("Pedidos cargados correctamente.");
  } catch (error) {
    setEstado("Error al cargar pedidos: " + error.message, true);
  }
}

function renderPedidos() {
  refs.pedidosBody.innerHTML = "";

  if (state.pedidos.length === 0) {
    refs.pedidosBody.innerHTML =
      '<tr><td colspan="5" class="muted">No hay pedidos en PENDIENTE_DIRECTOR.</td></tr>';
    return;
  }

  for (const pedido of state.pedidos) {
    const tr = document.createElement("tr");
    const idPedido = Number(pedido.id_pedido || 0);

    tr.innerHTML = `
      <td>#${idPedido}</td>
      <td>${escapeHtml(pedido.nombre_area || "-")}</td>
      <td>${escapeHtml(pedido.nombre_secretario || "-")}</td>
      <td>${escapeHtml(pedido.fecha_creacion || "-")}</td>
      <td><button type="button" data-id="${idPedido}">Ver detalle</button></td>
    `;

    tr.querySelector("button")?.addEventListener("click", () => {
      state.pedidoActivo = structuredClone(pedido);
      refs.motivoRechazo.value = "";
      renderDetalle();
    });

    refs.pedidosBody.appendChild(tr);
  }
}

function renderDetalle() {
  refs.detalleBody.innerHTML = "";

  if (!state.pedidoActivo) {
    refs.pedidoSeleccionado.textContent =
      "Selecciona un pedido para editar cantidades.";
    refs.detalleBody.innerHTML =
      '<tr><td colspan="3" class="muted">Sin pedido seleccionado.</td></tr>';
    return;
  }

  refs.pedidoSeleccionado.textContent = `Pedido #${Number(state.pedidoActivo.id_pedido || 0)} - Area ${state.pedidoActivo.nombre_area || "-"}`;

  const items = Array.isArray(state.pedidoActivo.items)
    ? state.pedidoActivo.items
    : [];
  if (items.length === 0) {
    refs.detalleBody.innerHTML =
      '<tr><td colspan="3" class="muted">Sin items.</td></tr>';
    return;
  }

  for (const item of items) {
    const tr = document.createElement("tr");
    const idProducto = Number(item.id_producto || 0);

    tr.innerHTML = `
      <td>${escapeHtml(item.sku || "-")}</td>
      <td>${escapeHtml(item.nombre || "-")}</td>
      <td>
        <input class="qty-input" type="number" min="1" value="${Number(item.cantidad || 1)}" data-id="${idProducto}" />
      </td>
    `;

    tr.querySelector("input")?.addEventListener("change", (event) => {
      const input = event.target;
      const cantidad = Number(input.value || 0);
      if (cantidad <= 0) {
        input.value = "1";
        return;
      }

      const itemActivo = state.pedidoActivo.items.find(
        (x) => Number(x.id_producto) === idProducto,
      );
      if (itemActivo) {
        itemActivo.cantidad = cantidad;
      }
    });

    refs.detalleBody.appendChild(tr);
  }
}

async function procesarPedido(accion) {
  if (!state.pedidoActivo) {
    setEstado("Debes seleccionar un pedido.", true);
    return;
  }

  const esAprobacion = accion === "aprobar";
  const confirmar = window.confirm(
    esAprobacion
      ? "¿Aprobar pedido y enviarlo a almacen?"
      : "¿Rechazar pedido?",
  );

  if (!confirmar) {
    return;
  }

  const cantidades = (
    Array.isArray(state.pedidoActivo.items) ? state.pedidoActivo.items : []
  ).map((item) => ({
    id_producto: Number(item.id_producto || 0),
    cantidad: Number(item.cantidad || 0),
  }));

  const payload = {
    id_pedido: Number(state.pedidoActivo.id_pedido || 0),
    accion,
    cantidades,
    motivo_rechazo: String(refs.motivoRechazo.value || "").trim(),
  };

  setEstado(esAprobacion ? "Aprobando pedido..." : "Rechazando pedido...");

  try {
    const response = await fetch(API_PROCESAR, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();

    if (response.status === 401) {
      window.location.href = "login.html";
      return;
    }

    if (!response.ok || !data.ok) {
      throw new Error(data.mensaje || "No fue posible procesar el pedido.");
    }

    state.pedidoActivo = null;
    await cargarPedidos();
    setEstado(data.mensaje || "Pedido procesado correctamente.");
  } catch (error) {
    setEstado("Error al procesar pedido: " + error.message, true);
  }
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
