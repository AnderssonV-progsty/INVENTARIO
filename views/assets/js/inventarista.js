const API_BASE = (window.API_BASE || "../api").replace(/\/+$/, "");
const API_PRODUCTOS = `${API_BASE}/productos_crud.php`;

const state = {
  productos: [],
  areas: [],
  usuarios: [],
  tabActiva: "productos",
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
  productoAreasCheckboxes: document.getElementById("productoAreasCheckboxes"),
  btnCancelarModal: document.getElementById("btnCancelarModal"),
  formArea: document.getElementById("formArea"),
  areaId: document.getElementById("areaId"),
  areaNombre: document.getElementById("areaNombre"),
  areaCodigo: document.getElementById("areaCodigo"),
  areaActiva: document.getElementById("areaActiva"),
  areasBody: document.getElementById("areasBody"),
  btnCancelarArea: document.getElementById("btnCancelarArea"),
  formUsuario: document.getElementById("formUsuario"),
  usuarioId: document.getElementById("usuarioId"),
  usuarioUsername: document.getElementById("usuarioUsername"),
  usuarioNombre: document.getElementById("usuarioNombre"),
  usuarioEmail: document.getElementById("usuarioEmail"),
  usuarioRol: document.getElementById("usuarioRol"),
  usuarioArea: document.getElementById("usuarioArea"),
  usuariosBody: document.getElementById("usuariosBody"),
  btnCancelarUsuario: document.getElementById("btnCancelarUsuario"),
  tabButtons: Array.from(document.querySelectorAll(".tab-btn")),
  tabPanels: Array.from(document.querySelectorAll(".tab-panel")),
};

document.addEventListener("DOMContentLoaded", () => {
  bindEvents();
  cargarAreas();
  cargarProductos();
  cargarUsuarios();
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

  refs.formArea.addEventListener("submit", async (event) => {
    event.preventDefault();
    await guardarArea();
  });

  refs.btnCancelarArea.addEventListener("click", resetearFormularioArea);

  refs.formUsuario.addEventListener("submit", async (event) => {
    event.preventDefault();
    await guardarUsuario();
  });

  refs.btnCancelarUsuario.addEventListener("click", resetearFormularioUsuario);

  refs.tabButtons.forEach((button) => {
    button.addEventListener("click", () => mostrarTab(button.dataset.tab));
  });
}

function mostrarTab(nombreTab) {
  state.tabActiva = nombreTab;

  refs.tabButtons.forEach((button) => {
    button.classList.toggle("active", button.dataset.tab === nombreTab);
  });

  refs.tabPanels.forEach((panel) => {
    panel.classList.toggle("hidden", panel.id !== `tab-${nombreTab}`);
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

async function cargarAreas() {
  try {
    const data = await requestJson(`${API_PRODUCTOS}?resource=areas`, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    state.areas = Array.isArray(data.data) ? data.data : [];
    renderAreas();
    renderAreaPickers();
    renderSelectorAreas();
  } catch (error) {
    console.error(error);
    setEstado("Error al cargar áreas: " + error.message, true);
  }
}

async function cargarUsuarios() {
  try {
    const data = await requestJson(`${API_PRODUCTOS}?resource=usuarios`, {
      method: "GET",
      headers: { Accept: "application/json" },
    });

    state.usuarios = Array.isArray(data.data) ? data.data : [];
    renderUsuarios();
  } catch (error) {
    console.error(error);
    setEstado("Error al cargar usuarios: " + error.message, true);
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

function renderAreas() {
  refs.areasBody.innerHTML = "";

  if (state.areas.length === 0) {
    const tr = document.createElement("tr");
    tr.innerHTML = '<td colspan="5">No hay áreas registradas.</td>';
    refs.areasBody.appendChild(tr);
    return;
  }

  for (const area of state.areas) {
    const tr = document.createElement("tr");
    const activa = Number(area.activa || 0) === 1;

    tr.innerHTML = `
      <td>${Number(area.id_area || 0)}</td>
      <td>${escapeHtml(area.nombre || "-")}</td>
      <td>${escapeHtml(area.codigo || "-")}</td>
      <td>
        <span class="${activa ? "tag-active" : "tag-inactive"}">
          ${activa ? "Activa" : "Inactiva"}
        </span>
      </td>
      <td class="actions">
        <button type="button" data-action="editar-area">Editar</button>
        <button type="button" data-action="eliminar-area">Eliminar</button>
      </td>
    `;

    tr.querySelector('[data-action="editar-area"]').addEventListener(
      "click",
      () => {
        abrirFormularioAreaEditar(area);
      },
    );

    tr.querySelector('[data-action="eliminar-area"]').addEventListener(
      "click",
      async () => {
        await eliminarArea(Number(area.id_area));
      },
    );

    refs.areasBody.appendChild(tr);
  }
}

function renderAreaPickers() {
  refs.productoAreasCheckboxes.innerHTML = "";

  if (state.areas.length === 0) {
    refs.productoAreasCheckboxes.innerHTML =
      "<span>No hay áreas disponibles.</span>";
    return;
  }

  for (const area of state.areas) {
    const label = document.createElement("label");
    label.className = "checkbox-chip";
    label.innerHTML = `<input type="checkbox" name="productoAreas[]" value="${Number(area.id_area || 0)}" /> ${escapeHtml(area.nombre || "-")}`;
    refs.productoAreasCheckboxes.appendChild(label);
  }
}

function renderSelectorAreas() {
  refs.usuarioArea.innerHTML = '<option value="">Sin área</option>';

  for (const area of state.areas) {
    const option = document.createElement("option");
    option.value = String(Number(area.id_area || 0));
    option.textContent = area.nombre || "-";
    refs.usuarioArea.appendChild(option);
  }
}

function renderUsuarios() {
  refs.usuariosBody.innerHTML = "";

  if (state.usuarios.length === 0) {
    const tr = document.createElement("tr");
    tr.innerHTML = '<td colspan="6">No hay usuarios registrados.</td>';
    refs.usuariosBody.appendChild(tr);
    return;
  }

  for (const usuario of state.usuarios) {
    const tr = document.createElement("tr");

    tr.innerHTML = `
      <td>${Number(usuario.id_usuario || 0)}</td>
      <td>${escapeHtml(usuario.username || "-")}</td>
      <td>${escapeHtml(usuario.nombre_completo || "-")}</td>
      <td>${escapeHtml(usuario.rol || "-")}</td>
      <td>${escapeHtml(usuario.area_nombre || "Sin área")}</td>
      <td class="actions">
        <button type="button" data-action="editar-usuario">Editar</button>
        <button type="button" data-action="eliminar-usuario">Eliminar</button>
      </td>
    `;

    tr.querySelector('[data-action="editar-usuario"]').addEventListener(
      "click",
      () => {
        abrirFormularioUsuarioEditar(usuario);
      },
    );

    tr.querySelector('[data-action="eliminar-usuario"]').addEventListener(
      "click",
      async () => {
        await eliminarUsuario(Number(usuario.id_usuario));
      },
    );

    refs.usuariosBody.appendChild(tr);
  }
}

function abrirModalCrear() {
  refs.modalTitulo.textContent = "Nuevo producto";
  refs.productoId.value = "";
  refs.productoSku.value = "";
  refs.productoNombre.value = "";
  refs.productoStock.value = "0";
  setProductoAreas([]);
  refs.modalBackdrop.classList.remove("hidden");
  refs.productoSku.focus();
}

function abrirModalEditar(producto) {
  refs.modalTitulo.textContent = "Editar producto";
  refs.productoId.value = String(Number(producto.id_producto || 0));
  refs.productoSku.value = String(producto.sku || "");
  refs.productoNombre.value = String(producto.nombre || "");
  refs.productoStock.value = String(Number(producto.stock_actual || 0));
  setProductoAreas(
    Array.isArray(producto.areas_asignadas) ? producto.areas_asignadas : [],
  );
  refs.modalBackdrop.classList.remove("hidden");
  refs.productoSku.focus();
}

function setProductoAreas(areaIds) {
  const ids = Array.isArray(areaIds) ? areaIds.map((item) => Number(item)) : [];
  const checks = refs.productoAreasCheckboxes.querySelectorAll(
    'input[name="productoAreas[]"]',
  );

  checks.forEach((checkbox) => {
    checkbox.checked = ids.includes(Number(checkbox.value));
  });
}

function cerrarModal() {
  refs.modalBackdrop.classList.add("hidden");
}

function resetearFormularioArea() {
  refs.areaId.value = "";
  refs.areaNombre.value = "";
  refs.areaCodigo.value = "";
  refs.areaActiva.checked = true;
}

function abrirFormularioAreaEditar(area) {
  refs.areaId.value = String(Number(area.id_area || 0));
  refs.areaNombre.value = String(area.nombre || "");
  refs.areaCodigo.value = String(area.codigo || "");
  refs.areaActiva.checked = Number(area.activa || 0) === 1;
  mostrarTab("areas");
  refs.areaNombre.focus();
}

function resetearFormularioUsuario() {
  refs.usuarioId.value = "";
  refs.usuarioUsername.value = "";
  refs.usuarioNombre.value = "";
  refs.usuarioEmail.value = "";
  refs.usuarioRol.value = "inventarista";
  refs.usuarioArea.value = "";
}

function abrirFormularioUsuarioEditar(usuario) {
  refs.usuarioId.value = String(Number(usuario.id_usuario || 0));
  refs.usuarioUsername.value = String(usuario.username || "");
  refs.usuarioNombre.value = String(usuario.nombre_completo || "");
  refs.usuarioEmail.value = String(usuario.email || "");
  refs.usuarioRol.value = String(usuario.rol || "inventarista");
  refs.usuarioArea.value = usuario.id_area
    ? String(Number(usuario.id_area))
    : "";
  mostrarTab("usuarios");
  refs.usuarioUsername.focus();
}

async function guardarProducto() {
  const idProducto = Number(refs.productoId.value || 0);
  const sku = refs.productoSku.value.trim().toUpperCase();
  const nombre = refs.productoNombre.value.trim();
  const stockActual = Number(refs.productoStock.value || -1);
  const areaIds = Array.from(
    refs.productoAreasCheckboxes.querySelectorAll(
      'input[name="productoAreas[]"]:checked',
    ),
  ).map((checkbox) => Number(checkbox.value));

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
    areas: areaIds,
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

async function guardarArea() {
  const idArea = Number(refs.areaId.value || 0);
  const nombre = refs.areaNombre.value.trim();
  const codigo = refs.areaCodigo.value.trim().toUpperCase();
  const activa = refs.areaActiva.checked;

  if (!nombre || !codigo) {
    setEstado("Nombre y código son obligatorios para el área.", true, true);
    return;
  }

  const method = idArea > 0 ? "PUT" : "POST";
  const payload = { nombre, codigo, activa };

  if (idArea > 0) {
    payload.id_area = idArea;
  }

  try {
    const data = await requestJson(`${API_PRODUCTOS}?resource=areas`, {
      method,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(payload),
    });

    resetearFormularioArea();
    await cargarAreas();
    setEstado(data.mensaje || "Área guardada correctamente.", false, true);
  } catch (error) {
    console.error(error);
    setEstado("Error al guardar el área: " + error.message, true, true);
  }
}

async function eliminarArea(idArea) {
  const confirmar = window.confirm("Se eliminará el área. ¿Deseas continuar?");
  if (!confirmar) {
    return;
  }

  try {
    const data = await requestJson(`${API_PRODUCTOS}?resource=areas`, {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ id_area: idArea }),
    });

    await cargarAreas();
    setEstado(data.mensaje || "Área eliminada correctamente.", false, true);
  } catch (error) {
    console.error(error);
    setEstado("Error al eliminar el área: " + error.message, true, true);
  }
}

async function guardarUsuario() {
  const idUsuario = Number(refs.usuarioId.value || 0);
  const username = refs.usuarioUsername.value.trim();
  const nombreCompleto = refs.usuarioNombre.value.trim();
  const email = refs.usuarioEmail.value.trim();
  const rol = refs.usuarioRol.value;
  const idArea = refs.usuarioArea.value ? Number(refs.usuarioArea.value) : null;

  if (!username || !nombreCompleto) {
    setEstado("Usuario y nombre completo son obligatorios.", true, true);
    return;
  }

  const method = idUsuario > 0 ? "PUT" : "POST";
  const payload = {
    username,
    nombre_completo: nombreCompleto,
    email,
    rol,
    id_area: idArea,
  };

  if (idUsuario > 0) {
    payload.id_usuario = idUsuario;
  }

  try {
    const data = await requestJson(`${API_PRODUCTOS}?resource=usuarios`, {
      method,
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify(payload),
    });

    resetearFormularioUsuario();
    await cargarUsuarios();
    setEstado(data.mensaje || "Usuario guardado correctamente.", false, true);
  } catch (error) {
    console.error(error);
    setEstado("Error al guardar el usuario: " + error.message, true, true);
  }
}

async function eliminarUsuario(idUsuario) {
  const confirmar = window.confirm(
    "Se eliminará el usuario. ¿Deseas continuar?",
  );
  if (!confirmar) {
    return;
  }

  try {
    const data = await requestJson(`${API_PRODUCTOS}?resource=usuarios`, {
      method: "DELETE",
      headers: {
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ id_usuario: idUsuario }),
    });

    await cargarUsuarios();
    setEstado(data.mensaje || "Usuario eliminado correctamente.", false, true);
  } catch (error) {
    console.error(error);
    setEstado("Error al eliminar el usuario: " + error.message, true, true);
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
