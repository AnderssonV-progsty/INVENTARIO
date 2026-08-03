const API_BASE = (window.API_BASE || "../api").replace(/\/+$/, "");
const API_CATALOGO = `${API_BASE}/catalogo.php`;
const API_GUARDAR_PEDIDO = `${API_BASE}/guardar_pedido.php`;
const API_LOGIN = `${API_BASE}/login.php`;
const API_HISTORIAL = `${API_BASE}/historial_pedidos.php`;

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
  idArea: document.getElementById("idArea"),
  observaciones: document.getElementById("observaciones"),
  historialBody: document.getElementById("historialBody"),
  btnRecargarHistorial: document.getElementById("btnRecargarHistorial"),
  mensajeEstado: document.getElementById("mensajeEstado"),
};

document.addEventListener("DOMContentLoaded", async () => {
  bindEvents();
  const autenticado = await inicializarSesion();
  if (!autenticado) return;
  cargarCatalogo();
  cargarHistorial();
});

function bindEvents() {
  refs.btnRecargar.addEventListener("click", cargarCatalogo);
  refs.btnConfirmarPedido.addEventListener("click", confirmarPedido);
  refs.filtroBusqueda.addEventListener("input", renderCatalogo);
  refs.filtroConStock.addEventListener("change", renderCatalogo);
  refs.btnLimpiarFiltros.addEventListener("click", limpiarFiltros);
  refs.btnRecargarHistorial.addEventListener("click", cargarHistorial);
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
    if (rol !== "operario") {
      window.location.href = rutaPorRol(rol);
      return false;
    }

    refs.idUsuario.value = String(Number(usuario.id_usuario || 0));
    refs.idArea.value = String(
      Number(usuario.id_area || usuario.id_oficina || 0),
    );
    refs.idUsuario.readOnly = true;
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

function limpiarFiltros() {
  refs.filtroBusqueda.value = "";
  refs.filtroConStock.checked = false;
  renderCatalogo();
  setEstado("Filtros limpiados.");
}

async function cargarCatalogo() {
  const idUsuario = Number(refs.idUsuario.value || 0);
  const idArea = Number(refs.idArea.value || 0);

  setEstado("Cargando catalogo del área...");

  try {
    const url = new URL(API_CATALOGO, window.location.href);
    if (idUsuario > 0) url.searchParams.set("id_usuario", String(idUsuario));
    if (idArea > 0) url.searchParams.set("id_area", String(idArea));

    const res = await fetch(url, {
      method: "GET",
      headers: {
        Accept: "application/json",
      },
    });

    const data = await res.json();

    if (!res.ok || !data.ok) {
      throw new Error(data.mensaje || "No fue posible cargar el catalogo.");
    }

    state.productos = Array.isArray(data.data) ? data.data : [];
    renderCatalogo();
    setEstado("Catalogo cargado correctamente para el área seleccionada.");
  } catch (error) {
    console.error(error);
    setEstado("Error al cargar el catalogo: " + error.message, true);
  }
}

function renderCatalogo() {
  const productosFiltrados = getProductosFiltrados();
  refs.grid.innerHTML = "";

  if (state.productos.length === 0) {
    refs.grid.innerHTML = "<p>No hay productos disponibles para esta área.</p>";
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
        <input class="qty-input" type="number" min="1" max="${maxQty}" value="1" ${stock <= 0 ? "disabled" : ""}>
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
  const observaciones = refs.observaciones.value.trim();
  const items = Array.from(state.carrito.values()).map((item) => ({
    id_producto: item.id_producto,
    cantidad: item.cantidad,
  }));

  if (items.length === 0) {
    setEstado("El carrito esta vacio.", true);
    return;
  }

  setEstado("Enviando pedido a aprobación...");

  try {
    const res = await fetch(API_GUARDAR_PEDIDO, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({
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
    setEstado(
      "Pedido creado y enviado a aprobación. ID: " + data.data.id_pedido,
    );
  } catch (error) {
    console.error(error);
    setEstado("Error al guardar pedido: " + error.message, true);
  }
}

async function cargarHistorial() {
  try {
    const data = await requestJson(API_HISTORIAL, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    renderHistorial(Array.isArray(data.data) ? data.data : []);
  } catch (error) {
    renderHistorial([]);
    setEstado("No fue posible cargar historial: " + error.message, true);
  }
}

function renderHistorial(pedidos) {
  refs.historialBody.innerHTML = "";

  if (!Array.isArray(pedidos) || pedidos.length === 0) {
    refs.historialBody.innerHTML =
      '<tr><td colspan="4">No hay solicitudes registradas.</td></tr>';
    return;
  }

  for (const pedido of pedidos) {
    const tr = document.createElement("tr");
    const items = Array.isArray(pedido.items)
      ? pedido.items
          .map(
            (item) =>
              `${escapeHtml(item.nombre_producto || "-")} (${Number(item.cantidad || 0)})`,
          )
          .join("<br>")
      : "Sin items";

    tr.innerHTML = `
      <td>#${Number(pedido.id_pedido || 0)}</td>
      <td>${escapeHtml(pedido.fecha_pedido || "-")}</td>
      <td>${escapeHtml(pedido.estado || "-")}</td>
      <td>${items}</td>
    `;
    refs.historialBody.appendChild(tr);
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
