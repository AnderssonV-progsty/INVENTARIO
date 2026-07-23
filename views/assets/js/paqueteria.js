const API_BASE = (window.API_BASE || "../api").replace(/\/+$/, "");
const API_PEDIDOS_PENDIENTES = `${API_BASE}/pedidos_pendientes.php`;
const API_ENTREGAR_PEDIDO = `${API_BASE}/entregar_pedido.php`;

const state = {
  pedidos: [],
};

const refs = {
  pedidosContainer: document.getElementById("pedidosContainer"),
  btnRecargar: document.getElementById("btnRecargar"),
  mensajeEstado: document.getElementById("mensajeEstado"),
};

document.addEventListener("DOMContentLoaded", () => {
  bindEvents();
  cargarPedidosPendientes();
});

function bindEvents() {
  refs.btnRecargar.addEventListener("click", cargarPedidosPendientes);
}

async function cargarPedidosPendientes() {
  setEstado("Cargando pedidos pendientes...");

  try {
    const data = await requestJson(API_PEDIDOS_PENDIENTES, {
      method: "GET",
      headers: {
        Accept: "application/json",
      },
    });

    state.pedidos = Array.isArray(data.data) ? data.data : [];
    renderPedidos();
    setEstado("Pedidos pendientes cargados correctamente.");
  } catch (error) {
    console.error(error);
    setEstado("Error al cargar pedidos pendientes: " + error.message, true);
  }
}

function renderPedidos() {
  refs.pedidosContainer.innerHTML = "";

  if (state.pedidos.length === 0) {
    refs.pedidosContainer.innerHTML =
      '<p class="empty-list">No hay pedidos en estado PENDIENTE.</p>';
    return;
  }

  for (const pedido of state.pedidos) {
    const card = document.createElement("article");
    card.className = "pedido-card";

    const itemsHtml = Array.isArray(pedido.items)
      ? pedido.items
          .map(
            (item) =>
              `<li>${escapeHtml(item.nombre_producto)} (${escapeHtml(item.sku)}) - Cantidad: ${Number(item.cantidad || 0)}</li>`,
          )
          .join("")
      : "";

    card.innerHTML = `
      <div class="pedido-header">
        <div>
          <h3 class="pedido-title">Pedido #${Number(pedido.id_pedido || 0)}</h3>
          <p class="pedido-meta">
            Area/Oficina: ${escapeHtml(pedido.nombre_oficina || "-")} (ID: ${Number(pedido.id_oficina || 0)})
          </p>
          <p class="pedido-meta">Fecha: ${escapeHtml(pedido.fecha_pedido || "-")}</p>
        </div>
        <button type="button" class="btn-primary" data-action="despachar">Despachar</button>
      </div>
      <ul class="items-list">${itemsHtml || "<li>Sin items.</li>"}</ul>
    `;

    card
      .querySelector('[data-action="despachar"]')
      .addEventListener("click", async () => {
        await despacharPedido(Number(pedido.id_pedido || 0));
      });

    refs.pedidosContainer.appendChild(card);
  }
}

async function despacharPedido(idPedido) {
  if (idPedido <= 0) {
    setEstado("ID de pedido invalido.", true);
    return;
  }

  const confirmar = window.confirm(
    `Confirma que deseas despachar el pedido #${idPedido}?`,
  );

  if (!confirmar) {
    return;
  }

  setEstado(`Despachando pedido #${idPedido}...`);

  try {
    const data = await requestJson(API_ENTREGAR_PEDIDO, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ id_pedido: idPedido }),
    });

    window.alert(
      data.mensaje || `Pedido #${idPedido} despachado correctamente.`,
    );
    await cargarPedidosPendientes();
  } catch (error) {
    console.error(error);
    setEstado("Error al despachar pedido: " + error.message, true);
    window.alert("No fue posible despachar el pedido: " + error.message);
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
