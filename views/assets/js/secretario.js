const API_BASE = (window.API_BASE || "../api").replace(/\/+$/, "");
const API_CATALOGO = `${API_BASE}/catalogo_area.php`;
const API_CREAR_PEDIDO = `${API_BASE}/crear_pedido.php`;

const state = {
  catalogo: [],
  carrito: new Map(),
};

const refs = {
  catalogoBody: document.getElementById("catalogoBody"),
  carritoBody: document.getElementById("carritoBody"),
  observaciones: document.getElementById("observaciones"),
  btnRecargarCatalogo: document.getElementById("btnRecargarCatalogo"),
  btnLimpiar: document.getElementById("btnLimpiar"),
  btnEnviarPedido: document.getElementById("btnEnviarPedido"),
  mensajeEstado: document.getElementById("mensajeEstado"),
};

document.addEventListener("DOMContentLoaded", () => {
  refs.btnRecargarCatalogo?.addEventListener("click", cargarCatalogo);
  refs.btnLimpiar?.addEventListener("click", limpiarCarrito);
  refs.btnEnviarPedido?.addEventListener("click", enviarPedido);
  cargarCatalogo();
});

async function cargarCatalogo() {
  setEstado("Cargando catalogo...");
  try {
    const response = await fetch(API_CATALOGO, {
      method: "GET",
      headers: { Accept: "application/json" },
    });
    const data = await response.json();

    if (response.status === 401) {
      window.location.href = "login.html";
      return;
    }

    if (response.status === 403) {
      throw new Error("Tu sesion no pertenece al rol Secretario.");
    }

    if (!response.ok || !data.ok) {
      throw new Error(data.mensaje || "No se pudo cargar el catalogo.");
    }

    state.catalogo = Array.isArray(data.data) ? data.data : [];
    renderCatalogo();
    renderCarrito();
    setEstado("Catalogo cargado correctamente.");
  } catch (error) {
    setEstado(error.message || "Error al cargar catalogo.", true);
  }
}

function renderCatalogo() {
  refs.catalogoBody.innerHTML = "";

  if (state.catalogo.length === 0) {
    refs.catalogoBody.innerHTML =
      '<tr><td colspan="5">No hay productos habilitados con stock para tu area.</td></tr>';
    return;
  }

  for (const producto of state.catalogo) {
    const tr = document.createElement("tr");
    const idProducto = Number(producto.id_producto || 0);

    tr.innerHTML = `
      <td>${escapeHtml(producto.sku || "-")}</td>
      <td>${escapeHtml(producto.nombre || "-")}</td>
      <td>${Number(producto.stock_actual || 0)}</td>
      <td>
        <input class="qty-input" type="number" min="1" max="${Number(producto.stock_actual || 0)}" value="1" id="qty-${idProducto}" />
      </td>
      <td>
        <button type="button" data-id="${idProducto}">Agregar</button>
      </td>
    `;

    tr.querySelector("button")?.addEventListener("click", () => {
      const qtyInput = document.getElementById(`qty-${idProducto}`);
      const cantidad = Number(qtyInput?.value || 0);
      agregarAlCarrito(producto, cantidad);
    });

    refs.catalogoBody.appendChild(tr);
  }
}

function agregarAlCarrito(producto, cantidad) {
  const idProducto = Number(producto.id_producto || 0);
  const stock = Number(producto.stock_actual || 0);

  if (idProducto <= 0 || cantidad <= 0) {
    setEstado("Cantidad invalida.", true);
    return;
  }

  if (cantidad > stock) {
    setEstado("La cantidad supera el stock disponible.", true);
    return;
  }

  state.carrito.set(idProducto, {
    id_producto: idProducto,
    sku: String(producto.sku || ""),
    nombre: String(producto.nombre || ""),
    cantidad,
  });

  renderCarrito();
  setEstado(`Producto ${producto.nombre} agregado al carrito.`);
}

function renderCarrito() {
  refs.carritoBody.innerHTML = "";
  const items = Array.from(state.carrito.values());

  if (items.length === 0) {
    refs.carritoBody.innerHTML =
      '<tr><td colspan="4">No hay productos en el carrito.</td></tr>';
    return;
  }

  for (const item of items) {
    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${escapeHtml(item.sku)}</td>
      <td>${escapeHtml(item.nombre)}</td>
      <td>${item.cantidad}</td>
      <td><button type="button" data-remove="${item.id_producto}">Quitar</button></td>
    `;

    tr.querySelector("button")?.addEventListener("click", () => {
      state.carrito.delete(item.id_producto);
      renderCarrito();
    });

    refs.carritoBody.appendChild(tr);
  }
}

function limpiarCarrito() {
  state.carrito.clear();
  renderCarrito();
  setEstado("Carrito limpiado.");
}

async function enviarPedido() {
  const carrito = Array.from(state.carrito.values()).map((item) => ({
    id_producto: item.id_producto,
    cantidad: item.cantidad,
  }));

  if (carrito.length === 0) {
    setEstado("Agrega productos al carrito antes de enviar.", true);
    return;
  }

  const observaciones = String(refs.observaciones?.value || "").trim();

  setEstado("Creando pedido...");

  try {
    const response = await fetch(API_CREAR_PEDIDO, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ carrito, observaciones }),
    });

    const data = await response.json();

    if (response.status === 401) {
      window.location.href = "login.html";
      return;
    }

    if (!response.ok || !data.ok) {
      throw new Error(data.mensaje || "No se pudo crear el pedido.");
    }

    state.carrito.clear();
    refs.observaciones.value = "";
    renderCarrito();
    setEstado(
      `Pedido #${Number(data.data?.id_pedido || 0)} creado en PENDIENTE_DIRECTOR.`,
    );
  } catch (error) {
    setEstado(error.message || "Error al crear el pedido.", true);
  }
}

function setEstado(texto, isError = false) {
  if (!refs.mensajeEstado) return;
  refs.mensajeEstado.textContent = texto;
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
