# Inventario de Papeleria Interna

Proyecto base en PHP 8 + MySQL para gestionar catalogo de productos de oficina, carrito y pedidos internos.

## Estado actual (lo que ya se arreglo)

- Estructura de base de datos creada con tablas y relaciones:
  - oficinas
  - usuarios
  - productos
  - pedidos
  - detalle_pedidos
- Script SQL listo para importar: [schema_papeleria.sql](schema_papeleria.sql)
- Triggers en MySQL para reglas de negocio:
  - Validar detalle solo en pedidos pendientes.
  - Validar stock disponible.
  - Descontar stock automaticamente al marcar pedido como ENTREGADO.
- Conexion segura PDO (Singleton): [conexion.php](conexion.php)
- API MVC en PHP:
  - Catalogo: [api/catalogo.php](api/catalogo.php)
  - Guardar pedido: [api/guardar_pedido.php](api/guardar_pedido.php)
  - Modelos y controladores en carpetas [models](models) y [controllers](controllers)
- Vista frontend funcional con AJAX fetch:
  - [views/catalogo.html](views/catalogo.html)
  - [views/assets/js/catalogo.js](views/assets/js/catalogo.js)
  - [views/assets/css/catalogo.css](views/assets/css/catalogo.css)
- Filtros agregados en la vista:
  - Buscar por nombre/SKU.
  - Solo mostrar productos con stock.
  - Boton Limpiar filtros.
- Endpoints ajustados para devolver JSON incluso si hay error de inicializacion de servidor/base de datos.

## Requisitos

- XAMPP instalado (Apache + MySQL + PHP)
- Proyecto ubicado en:
  - C:/xampp/htdocs/INVENTARIO

## Configuracion de base de datos

1. Iniciar Apache y MySQL desde XAMPP.
2. Abrir phpMyAdmin: http://localhost/phpmyadmin
3. Importar [schema_papeleria.sql](schema_papeleria.sql)
4. Verificar que existe la base `almacen_papeleria` y sus tablas.

La conexion actual usa por defecto:

- host: 127.0.0.1
- port: 3306
- dbname: almacen_papeleria
- user: root
- pass: vacio

Esto esta definido en [conexion.php](conexion.php) y coincide con la configuracion comun de XAMPP.

## Como ejecutar

1. Abrir en navegador:
   - http://localhost/INVENTARIO/views/catalogo.html
2. Clic en Recargar para traer catalogo.
3. Agregar productos al carrito.
4. Confirmar pedido.

## Endpoints disponibles

- GET http://localhost/INVENTARIO/api/catalogo.php
- POST http://localhost/INVENTARIO/api/guardar_pedido.php

Ejemplo JSON para guardar pedido:

```json
{
  "id_usuario": 2,
  "id_oficina": 1,
  "observaciones": "Pedido de prueba",
  "items": [
    { "id_producto": 1, "cantidad": 2 },
    { "id_producto": 2, "cantidad": 5 }
  ]
}
```

## Pruebas realizadas

- Catalogo responde JSON correcto.
- Se creo pedido de prueba con respuesta:
  - ok: true
  - estado: PENDIENTE
- Flujo listo para probar entrega y descuento de stock por trigger SQL.

## Verificacion manual sugerida

En phpMyAdmin, ejecutar:

```sql
SELECT * FROM pedidos ORDER BY id_pedido DESC;
SELECT * FROM detalle_pedidos ORDER BY id_detalle DESC;
SELECT id_producto, nombre, stock_actual FROM productos ORDER BY id_producto;
```

Para validar descuento de stock al entregar:

```sql
UPDATE pedidos
SET estado = 'ENTREGADO', id_usuario_entrega = 1
WHERE id_pedido = 1;
```

## Problemas comunes

- Error en frontend: `Unexpected token '<'`
  - Causa: el endpoint devolvia HTML de error en lugar de JSON.
  - Estado: resuelto; los endpoints ahora encapsulan errores y devuelven JSON.

- El comando `php` no existe en terminal
  - En este proyecto no es obligatorio usar `php -S` si trabajas con XAMPP.
  - Usa Apache de XAMPP y abre por `http://localhost/...`.

## Estructura del proyecto

```text
INVENTARIO/
	api/
		catalogo.php
		guardar_pedido.php
	controllers/
		PedidoController.php
		ProductoController.php
	models/
		PedidoModel.php
		ProductoModel.php
	views/
		catalogo.html
		assets/
			css/catalogo.css
			js/catalogo.js
	conexion.php
	schema_papeleria.sql
	README.md
```
