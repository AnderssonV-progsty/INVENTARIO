const API_BASE = (window.API_BASE || "../api").replace(/\/+$/, "");
const API_CATALOGO = `${API_BASE}/catalogo.php`;
const API_GUARDAR_PEDIDO = `${API_BASE}/guardar_pedido.php`;

const state = {
  productos: [],
  carrito: new Map(),
};

const refs = {
  grid: document.getElementById("catalogoGrid"),
  btnRecargar: document.getElementById("btnRecargar"),
  filtroBusqueda: document.getElementById("filtroBusqueda"),
  filtroConStock: document.getElementById("filtroConStock"),
  btnLimpiarFiltros: document.getElementById("btnLimpiarFiltros"),
  carritoVacio: document.getElementById("carritoVacio"),
  carritoContenido: document.getElementById("carritoContenido"),
  carritoBody: document.getElementById("carritoBody"),
  totalItems: document.getElementById("totalItems"),
  btnConfirmarPedido: document.getElementById("btnConfirmarPedido"),
  idUsuario: document.getElementById("idUsuario"),
  idOficina: document.getElementById("idOficina"),
  observaciones: document.getElementById("observaciones"),
  mensajeEstado: document.getElementById("mensajeEstado"),
};

document.addEventListener("DOMContentLoaded", () => {
  bindEvents();
  cargarCatalogo();
});

function bindEvents() {
  refs.btnRecargar.addEventListener("click", cargarCatalogo);
  refs.btnConfirmarPedido.addEventListener("click", confirmarPedido);
  refs.filtroBusqueda.addEventListener("input", renderCatalogo);
  refs.filtroConStock.addEventListener("change", renderCatalogo);
  refs.btnLimpiarFiltros.addEventListener("click", limpiarFiltros);
}

function limpiarFiltros() {
  refs.filtroBusqueda.value = "";
  refs.filtroConStock.checked = false;
  renderCatalogo();
  setEstado("Filtros limpiados.");
}

async function cargarCatalogo() {
  setEstado("Cargando catalogo...");

  try {
    const res = await fetch(API_CATALOGO, {
      method: "GET",
      headers: {
        Accept: "application/json",
      },
    });

    const data = await res.json();

    if (!res.ok || !data.ok) {
      throw new Error(data.mensaje || "No fue posible cargar catalogo.");
    }

    state.productos = Array.isArray(data.data) ? data.data : [];
    renderCatalogo();
    setEstado("Catalogo cargado correctamente.");
  } catch (error) {
    console.error(error);
    setEstado("Error al cargar catalogo: " + error.message, true);
  }
}

function renderCatalogo() {
  const productosFiltrados = getProductosFiltrados();
  refs.grid.innerHTML = "";

  if (state.productos.length === 0) {
    refs.grid.innerHTML = "<p>No hay productos disponibles.</p>";
    return;
  }

  if (productosFiltrados.length === 0) {
    refs.grid.innerHTML =
      "<p>No hay productos que coincidan con el filtro.</p>";
    return;
  }

  for (const producto of productosFiltrados) {
    const card = document.createElement("article");
    card.className = "card";

    const stock = Number(producto.stock_actual || 0);
    const maxQty = stock > 0 ? stock : 0;

    card.innerHTML = `
      <h3>${escapeHtml(producto.nombre)}</h3>
      <div class="meta">SKU: ${escapeHtml(producto.sku || "-")}</div>
      <div class="meta">Stock: ${stock}</div>
      <div class="meta">Unidad: ${escapeHtml(producto.unidad_medida || "UND")}</div>
      <div class="card-actions">
        <input
          class="qty-input"
          type="number"
          min="1"
          max="${maxQty}"
          value="1"
          ${stock <= 0 ? "disabled" : ""}
        >
        <button type="button" ${stock <= 0 ? "disabled" : ""}>Agregar al carrito</button>
      </div>
    `;

    const inputQty = card.querySelector(".qty-input");
    const btnAgregar = card.querySelector("button");

    btnAgregar.addEventListener("click", () => {
      const cantidad = Number(inputQty.value || 0);
      agregarAlCarrito(producto, cantidad);
    });

    refs.grid.appendChild(card);
  }
}

function getProductosFiltrados() {
  const termino = refs.filtroBusqueda.value.trim().toLowerCase();
  const soloConStock = refs.filtroConStock.checked;

  return state.productos.filter((producto) => {
    const nombre = String(producto.nombre || "").toLowerCase();
    const sku = String(producto.sku || "").toLowerCase();
    const stock = Number(producto.stock_actual || 0);

    const coincideTexto =
      termino === "" || nombre.includes(termino) || sku.includes(termino);
    const coincideStock = !soloConStock || stock > 0;

    return coincideTexto && coincideStock;
  });
}

function agregarAlCarrito(producto, cantidad) {
  const idProducto = Number(producto.id_producto);
  const stock = Number(producto.stock_actual || 0);

  if (cantidad <= 0) {
    setEstado("La cantidad debe ser mayor a 0.", true);
    return;
  }

  const existente = state.carrito.get(idProducto);
  const cantidadActual = existente ? existente.cantidad : 0;
  const nuevaCantidad = cantidadActual + cantidad;

  if (nuevaCantidad > stock) {
    setEstado(
      "No puedes superar el stock disponible para " + producto.nombre + ".",
      true,
    );
    return;
  }

  state.carrito.set(idProducto, {
    id_producto: idProducto,
    nombre: producto.nombre,
    cantidad: nuevaCantidad,
  });

  renderCarrito();
  setEstado("Producto agregado al carrito.");
}

function quitarDelCarrito(idProducto) {
  state.carrito.delete(idProducto);
  renderCarrito();
  setEstado("Producto eliminado del carrito.");
}

function renderCarrito() {
  const items = Array.from(state.carrito.values());
  refs.carritoBody.innerHTML = "";

  if (items.length === 0) {
    refs.carritoVacio.classList.remove("hidden");
    refs.carritoContenido.classList.add("hidden");
    refs.totalItems.textContent = "0";
    return;
  }

  refs.carritoVacio.classList.add("hidden");
  refs.carritoContenido.classList.remove("hidden");

  let total = 0;

  for (const item of items) {
    total += item.cantidad;

    const tr = document.createElement("tr");
    tr.innerHTML = `
      <td>${escapeHtml(item.nombre)}</td>
      <td>${item.cantidad}</td>
      <td><button type="button">Quitar</button></td>
    `;

    tr.querySelector("button").addEventListener("click", () =>
      quitarDelCarrito(item.id_producto),
    );

    refs.carritoBody.appendChild(tr);
  }

  refs.totalItems.textContent = String(total);
}

async function confirmarPedido() {
  const idUsuario = Number(refs.idUsuario.value || 0);
  const idOficina = Number(refs.idOficina.value || 0);
  const observaciones = refs.observaciones.value.trim();
  const items = Array.from(state.carrito.values()).map((item) => ({
    id_producto: item.id_producto,
    cantidad: item.cantidad,
  }));

  if (idUsuario <= 0 || idOficina <= 0) {
    setEstado("Debes ingresar ID de usuario y oficina validos.", true);
    return;
  }

  if (items.length === 0) {
    setEstado("El carrito esta vacio.", true);
    return;
  }

  setEstado("Enviando pedido...");

  try {
    const res = await fetch(API_GUARDAR_PEDIDO, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
        id_usuario: idUsuario,
        id_oficina: idOficina,
        observaciones,
        items,
      }),
    });

    const data = await res.json();

    if (!res.ok || !data.ok) {
      throw new Error(data.mensaje || "No fue posible guardar el pedido.");
    }

    state.carrito.clear();
    renderCarrito();
    setEstado("Pedido creado. ID: " + data.data.id_pedido);
  } catch (error) {
    console.error(error);
    setEstado("Error al guardar pedido: " + error.message, true);
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
