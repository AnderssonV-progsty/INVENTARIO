const API_BASE = (window.API_BASE || "../api").replace(/\/+$/, "");
const API_PRODUCTOS = `${API_BASE}/productos_crud.php`;

const state = {
  productos: [],
};

const refs = {
  inventarioBody: document.getElementById("inventarioBody"),
  filtroInventario: document.getElementById("filtroInventario"),
  btnRecargarProductos: document.getElementById("btnRecargarProductos"),
  btnNuevoProducto: document.getElementById("btnNuevoProducto"),
  mensajeEstado: document.getElementById("mensajeEstado"),
  toastContainer: document.getElementById("toastContainer"),
  modalBackdrop: document.getElementById("modalProductoBackdrop"),
  modalTitulo: document.getElementById("modalProductoTitulo"),
  formProducto: document.getElementById("formProducto"),
  productoId: document.getElementById("productoId"),
  productoSku: document.getElementById("productoSku"),
  productoNombre: document.getElementById("productoNombre"),
  productoStock: document.getElementById("productoStock"),
  btnCancelarModal: document.getElementById("btnCancelarModal"),
};

document.addEventListener("DOMContentLoaded", () => {
  bindEvents();
  cargarProductos();
});

function bindEvents() {
  refs.btnRecargarProductos.addEventListener("click", cargarProductos);
  refs.btnNuevoProducto.addEventListener("click", () => abrirModalCrear());
  refs.filtroInventario.addEventListener("input", renderInventario);
  refs.btnCancelarModal.addEventListener("click", cerrarModal);
  refs.modalBackdrop.addEventListener("click", (event) => {
    if (event.target === refs.modalBackdrop) {
      cerrarModal();
    }
  });

  refs.formProducto.addEventListener("submit", async (event) => {
    event.preventDefault();
    await guardarProducto();
  });
}

async function cargarProductos() {
  setEstado("Cargando inventario...");

  try {
    const data = await requestJson(API_PRODUCTOS, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    state.productos = Array.isArray(data.data) ? data.data : [];
    renderInventario();
    setEstado("Inventario cargado correctamente.");
  } catch (error) {
    console.error(error);
    setEstado("Error al cargar inventario: " + error.message, true);
  }
}

function renderInventario() {
  const productos = getProductosFiltrados();
  refs.inventarioBody.innerHTML = "";

  if (state.productos.length === 0) {
    const tr = document.createElement("tr");
    tr.innerHTML = '<td colspan="6">No hay productos registrados.</td>';
    refs.inventarioBody.appendChild(tr);
    return;
  }

  if (productos.length === 0) {
    const tr = document.createElement("tr");
    tr.innerHTML =
      '<td colspan="6">No hay coincidencias para el filtro actual.</td>';
    refs.inventarioBody.appendChild(tr);
    return;
  }

  for (const producto of productos) {
    const tr = document.createElement("tr");
    const activo = Number(producto.activo || 0) === 1;

    tr.innerHTML = `
      <td>${Number(producto.id_producto || 0)}</td>
      <td>${escapeHtml(producto.sku || "-")}</td>
      <td>${escapeHtml(producto.nombre || "-")}</td>
      <td>${Number(producto.stock_actual || 0)}</td>
      <td>
        <span class="${activo ? "tag-active" : "tag-inactive"}">
          ${activo ? "Activo" : "Inactivo"}
        </span>
      </td>
      <td class="actions">
        <button type="button" data-action="editar">Editar</button>
        <button type="button" data-action="eliminar" ${activo ? "" : "disabled"}>
          Eliminar
        </button>
        <button type="button" data-action="reactivar" ${activo ? "disabled" : ""}>
          Reactivar
        </button>
      </td>
    `;

    tr.querySelector('[data-action="editar"]').addEventListener("click", () => {
      abrirModalEditar(producto);
    });

    tr.querySelector('[data-action="eliminar"]').addEventListener(
      "click",
      async () => {
        await eliminarProducto(Number(producto.id_producto));
      },
    );

    tr.querySelector('[data-action="reactivar"]').addEventListener(
      "click",
      async () => {
        await reactivarProducto(Number(producto.id_producto));
      },
    );

    refs.inventarioBody.appendChild(tr);
  }
}

function getProductosFiltrados() {
  const termino = refs.filtroInventario.value.trim().toLowerCase();

  if (!termino) {
    return state.productos;
  }

  return state.productos.filter((producto) => {
    const sku = String(producto.sku || "").toLowerCase();
    const nombre = String(producto.nombre || "").toLowerCase();
    return sku.includes(termino) || nombre.includes(termino);
  });
}

function abrirModalCrear() {
  refs.modalTitulo.textContent = "Nuevo producto";
  refs.productoId.value = "";
  refs.productoSku.value = "";
  refs.productoNombre.value = "";
  refs.productoStock.value = "0";
  refs.modalBackdrop.classList.remove("hidden");
  refs.productoSku.focus();
}

function abrirModalEditar(producto) {
  refs.modalTitulo.textContent = "Editar producto";
  refs.productoId.value = String(Number(producto.id_producto || 0));
  refs.productoSku.value = String(producto.sku || "");
  refs.productoNombre.value = String(producto.nombre || "");
  refs.productoStock.value = String(Number(producto.stock_actual || 0));
  refs.modalBackdrop.classList.remove("hidden");
  refs.productoSku.focus();
}

function cerrarModal() {
  refs.modalBackdrop.classList.add("hidden");
}

async function guardarProducto() {
  const idProducto = Number(refs.productoId.value || 0);
  const sku = refs.productoSku.value.trim().toUpperCase();
  const nombre = refs.productoNombre.value.trim();
  const stockActual = Number(refs.productoStock.value || -1);

  if (!sku || !nombre) {
    setEstado("SKU y nombre son obligatorios.", true, true);
    return;
  }

  if (!Number.isInteger(stockActual) || stockActual < 0) {
    setEstado(
      "El stock debe ser un numero entero mayor o igual a 0.",
      true,
      true,
    );
    return;
  }

  const isEdit = idProducto > 0;
  const method = isEdit ? "PUT" : "POST";

  const payload = {
    sku,
    nombre,
    stock_actual: stockActual,
  };

  if (isEdit) {
    payload.id_producto = idProducto;
  }

  setEstado(isEdit ? "Actualizando producto..." : "Creando producto...");

  try {
    const data = await requestJson(API_PRODUCTOS, {
      method,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(payload),
    });

    cerrarModal();
    await cargarProductos();
    setEstado(
      data.mensaje || "Operacion realizada correctamente.",
      false,
      true,
    );
  } catch (error) {
    console.error(error);
    setEstado("Error al guardar producto: " + error.message, true, true);
  }
}

async function eliminarProducto(idProducto) {
  if (idProducto <= 0) {
    setEstado("id_producto invalido.", true, true);
    return;
  }

  const confirmar = window.confirm(
    "Se desactivara el producto. Deseas continuar?",
  );

  if (!confirmar) {
    return;
  }

  setEstado("Eliminando producto...");

  try {
    const data = await requestJson(API_PRODUCTOS, {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ id_producto: idProducto }),
    });

    await cargarProductos();
    setEstado(data.mensaje || "Producto eliminado correctamente.", false, true);
  } catch (error) {
    console.error(error);
    setEstado("Error al eliminar producto: " + error.message, true, true);
  }
}

async function reactivarProducto(idProducto) {
  if (idProducto <= 0) {
    setEstado("id_producto invalido.", true, true);
    return;
  }

  setEstado("Reactivando producto...");

  try {
    const data = await requestJson(API_PRODUCTOS, {
      method: "PATCH",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ id_producto: idProducto }),
    });

    await cargarProductos();
    setEstado(
      data.mensaje || "Producto reactivado correctamente.",
      false,
      true,
    );
  } catch (error) {
    console.error(error);
    setEstado("Error al reactivar producto: " + error.message, true, true);
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

function setEstado(mensaje, isError = false, withToast = false) {
  refs.mensajeEstado.textContent = mensaje;
  refs.mensajeEstado.style.color = isError ? "#b91c1c" : "#065f46";

  if (withToast) {
    showToast(mensaje, isError);
  }
}

function showToast(mensaje, isError = false) {
  if (!refs.toastContainer) {
    return;
  }

  const toast = document.createElement("div");
  toast.className = `toast ${isError ? "toast-error" : "toast-success"}`;
  toast.textContent = mensaje;

  refs.toastContainer.appendChild(toast);

  window.setTimeout(() => {
    toast.remove();
  }, 2800);
}

function escapeHtml(value) {
  return String(value)
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#39;");
}
