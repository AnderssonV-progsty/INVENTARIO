const API_BASE = (window.API_BASE || "../api").replace(/\/+$/, "");
const API_PRODUCTOS = `${API_BASE}/productos_admin.php`;
const API_PEDIDOS_ALMACEN = `${API_BASE}/pedidos_almacen.php`;
const API_DESPACHAR = `${API_BASE}/despachar_pedido.php`;

const state = {
  areas: [],
  productos: [],
  despachos: [],
};

const refs = {
  tabButtons: document.querySelectorAll(".tab-btn"),
  tabProductos: document.getElementById("tab-productos"),
  tabDespachos: document.getElementById("tab-despachos"),

  idProducto: document.getElementById("idProducto"),
  sku: document.getElementById("sku"),
  nombre: document.getElementById("nombre"),
  unidadMedida: document.getElementById("unidadMedida"),
  stockActual: document.getElementById("stockActual"),
  stockMinimo: document.getElementById("stockMinimo"),
  activo: document.getElementById("activo"),
  descripcion: document.getElementById("descripcion"),
  areasContainer: document.getElementById("areasContainer"),
  btnNuevo: document.getElementById("btnNuevo"),
  btnGuardar: document.getElementById("btnGuardar"),
  productosBody: document.getElementById("productosBody"),

  btnRecargarDespachos: document.getElementById("btnRecargarDespachos"),
  despachosBody: document.getElementById("despachosBody"),

  mensajeEstado: document.getElementById("mensajeEstado"),
};

document.addEventListener("DOMContentLoaded", () => {
  refs.tabButtons.forEach((btn) => {
    btn.addEventListener("click", () => cambiarTab(btn.dataset.tab));
  });

  refs.btnNuevo?.addEventListener("click", limpiarFormulario);
  refs.btnGuardar?.addEventListener("click", guardarProducto);
  refs.btnRecargarDespachos?.addEventListener("click", cargarDespachos);

  cargarCatalogoAdmin();
  cargarDespachos();
});

function cambiarTab(tab) {
  const esProductos = tab === "productos";
  refs.tabProductos.classList.toggle("active", esProductos);
  refs.tabDespachos.classList.toggle("active", !esProductos);
  refs.tabButtons.forEach((btn) => {
    btn.classList.toggle("active", btn.dataset.tab === tab);
  });
}

async function cargarCatalogoAdmin() {
  setEstado("Cargando productos y areas...");
  try {
    const response = await fetch(API_PRODUCTOS, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    const data = await response.json();

    if (response.status === 401 || response.status === 403) {
      window.location.href = "login.html";
      return;
    }

    if (!response.ok || !data.ok) {
      throw new Error(
        data.mensaje || "No se pudo cargar el modulo administrativo.",
      );
    }

    state.areas = Array.isArray(data.data?.areas) ? data.data.areas : [];
    state.productos = Array.isArray(data.data?.productos)
      ? data.data.productos
      : [];

    renderAreasCheckboxes();
    renderProductos();
    setEstado("Datos administrativos cargados correctamente.");
  } catch (error) {
    setEstado(error.message || "Error al cargar datos administrativos.", true);
  }
}

function renderAreasCheckboxes() {
  refs.areasContainer.innerHTML = "";

  if (state.areas.length === 0) {
    refs.areasContainer.innerHTML = "<p>No hay areas activas.</p>";
    return;
  }

  for (const area of state.areas) {
    const idArea = Number(area.id_area || 0);
    const label = document.createElement("label");
    label.style.marginRight = "0.8rem";
    label.innerHTML = `<input type="checkbox" class="area-checkbox" value="${idArea}" /> ${escapeHtml(area.nombre || "")}`;
    refs.areasContainer.appendChild(label);
  }
}

function renderProductos() {
  refs.productosBody.innerHTML = "";

  if (state.productos.length === 0) {
    refs.productosBody.innerHTML =
      '<tr><td colspan="6">No hay productos registrados.</td></tr>';
    return;
  }

  for (const producto of state.productos) {
    const tr = document.createElement("tr");
    const areasIds = Array.isArray(producto.areas_ids)
      ? producto.areas_ids
      : [];
    const nombresAreas = state.areas
      .filter((area) => areasIds.includes(Number(area.id_area)))
      .map((area) => area.nombre)
      .join(", ");

    tr.innerHTML = `
      <td>${Number(producto.id_producto || 0)}</td>
      <td>${escapeHtml(producto.sku || "")}</td>
      <td>${escapeHtml(producto.nombre || "")}</td>
      <td>${Number(producto.stock_actual || 0)}</td>
      <td class="area-tags">${escapeHtml(nombresAreas || "Sin areas")}</td>
      <td>
        <button type="button" data-edit="${Number(producto.id_producto || 0)}">Editar</button>
        <button type="button" data-delete="${Number(producto.id_producto || 0)}">Eliminar</button>
      </td>
    `;

    tr.querySelector("[data-edit]")?.addEventListener("click", () =>
      cargarProductoEnFormulario(producto),
    );
    tr.querySelector("[data-delete]")?.addEventListener("click", () =>
      eliminarProducto(Number(producto.id_producto || 0)),
    );

    refs.productosBody.appendChild(tr);
  }
}

function cargarProductoEnFormulario(producto) {
  refs.idProducto.value = String(Number(producto.id_producto || 0));
  refs.sku.value = String(producto.sku || "");
  refs.nombre.value = String(producto.nombre || "");
  refs.unidadMedida.value = String(producto.unidad_medida || "UND");
  refs.stockActual.value = String(Number(producto.stock_actual || 0));
  refs.stockMinimo.value = String(Number(producto.stock_minimo || 0));
  refs.activo.value = String(Number(producto.activo || 1));
  refs.descripcion.value = String(producto.descripcion || "");

  const assigned = new Set(
    (Array.isArray(producto.areas_ids) ? producto.areas_ids : []).map(Number),
  );
  document.querySelectorAll(".area-checkbox").forEach((cb) => {
    cb.checked = assigned.has(Number(cb.value));
  });

  setEstado(`Editando producto #${refs.idProducto.value}.`);
}

function limpiarFormulario() {
  refs.idProducto.value = "";
  refs.sku.value = "";
  refs.nombre.value = "";
  refs.unidadMedida.value = "UND";
  refs.stockActual.value = "0";
  refs.stockMinimo.value = "0";
  refs.activo.value = "1";
  refs.descripcion.value = "";
  document.querySelectorAll(".area-checkbox").forEach((cb) => {
    cb.checked = false;
  });
  setEstado("Formulario listo para nuevo producto.");
}

function construirPayloadProducto() {
  const areasIds = Array.from(
    document.querySelectorAll(".area-checkbox:checked"),
  ).map((cb) => Number(cb.value));

  return {
    id_producto: refs.idProducto.value
      ? Number(refs.idProducto.value)
      : undefined,
    sku: String(refs.sku.value || "").trim(),
    nombre: String(refs.nombre.value || "").trim(),
    descripcion: String(refs.descripcion.value || "").trim(),
    unidad_medida: String(refs.unidadMedida.value || "UND").trim() || "UND",
    stock_actual: Number(refs.stockActual.value || 0),
    stock_minimo: Number(refs.stockMinimo.value || 0),
    activo: Number(refs.activo.value || 1) === 1,
    areas_ids: areasIds,
  };
}

async function guardarProducto() {
  const payload = construirPayloadProducto();
  const isEdit = Number(payload.id_producto || 0) > 0;

  setEstado(isEdit ? "Actualizando producto..." : "Creando producto...");

  try {
    const response = await fetch(API_PRODUCTOS, {
      method: isEdit ? "PUT" : "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(payload),
    });

    const data = await response.json();
    if (!response.ok || !data.ok) {
      throw new Error(data.mensaje || "No se pudo guardar el producto.");
    }

    limpiarFormulario();
    await cargarCatalogoAdmin();
    setEstado(data.mensaje || "Producto guardado correctamente.");
  } catch (error) {
    setEstado(error.message || "Error al guardar producto.", true);
  }
}

async function eliminarProducto(idProducto) {
  if (idProducto <= 0) return;

  const confirmar = window.confirm(`¿Eliminar producto #${idProducto}?`);
  if (!confirmar) return;

  setEstado("Eliminando producto...");

  try {
    const response = await fetch(API_PRODUCTOS, {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ id_producto: idProducto }),
    });

    const data = await response.json();
    if (!response.ok || !data.ok) {
      throw new Error(data.mensaje || "No se pudo eliminar el producto.");
    }

    await cargarCatalogoAdmin();
    setEstado(data.mensaje || "Producto eliminado correctamente.");
  } catch (error) {
    setEstado(error.message || "Error al eliminar producto.", true);
  }
}

async function cargarDespachos() {
  setEstado("Cargando pedidos listos para despacho...");

  try {
    const response = await fetch(API_PEDIDOS_ALMACEN, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    const data = await response.json();

    if (response.status === 401 || response.status === 403) {
      window.location.href = "login.html";
      return;
    }

    if (!response.ok || !data.ok) {
      throw new Error(data.mensaje || "No se pudo cargar los despachos.");
    }

    state.despachos = Array.isArray(data.data) ? data.data : [];
    renderDespachos();
    setEstado("Despachos cargados correctamente.");
  } catch (error) {
    setEstado(error.message || "Error al cargar despachos.", true);
  }
}

function renderDespachos() {
  refs.despachosBody.innerHTML = "";

  if (state.despachos.length === 0) {
    refs.despachosBody.innerHTML =
      '<tr><td colspan="4">No hay pedidos en LISTO_DESPACHO.</td></tr>';
    return;
  }

  for (const pedido of state.despachos) {
    const tr = document.createElement("tr");
    const items = Array.isArray(pedido.items) ? pedido.items : [];

    tr.innerHTML = `
      <td>#${Number(pedido.id_pedido || 0)}</td>
      <td>${escapeHtml(pedido.nombre_area || "-")}</td>
      <td>${items
        .map(
          (item) =>
            `${escapeHtml(item.nombre || "")}: ${Number(item.cantidad || 0)}`,
        )
        .join("<br>")}</td>
      <td><button type="button" data-despachar="${Number(pedido.id_pedido || 0)}">Despachar</button></td>
    `;

    tr.querySelector("button")?.addEventListener("click", () =>
      despacharPedido(Number(pedido.id_pedido || 0)),
    );
    refs.despachosBody.appendChild(tr);
  }
}

async function despacharPedido(idPedido) {
  if (idPedido <= 0) {
    return;
  }

  const confirmar = window.confirm(
    `¿Confirmas el despacho del pedido #${idPedido}?`,
  );
  if (!confirmar) {
    return;
  }

  setEstado(`Despachando pedido #${idPedido}...`);

  try {
    const response = await fetch(API_DESPACHAR, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ id_pedido: idPedido }),
    });

    const data = await response.json();
    if (!response.ok || !data.ok) {
      throw new Error(data.mensaje || "No se pudo despachar el pedido.");
    }

    await cargarDespachos();
    setEstado(data.mensaje || `Pedido #${idPedido} despachado.`);
  } catch (error) {
    setEstado(error.message || "Error al despachar pedido.", true);
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
