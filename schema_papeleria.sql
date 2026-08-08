-- Clean MySQL 8.x script for "Almacén Papelería"
-- ------------------------------------------------------------
-- Drop existing database (optional, confirmed by user)
DROP DATABASE IF EXISTS almacen_papeleria;
CREATE DATABASE almacen_papeleria
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;
USE almacen_papeleria;

-- ------------------------------------------------------------
-- Table: oficinas (departments)
CREATE TABLE oficinas (
  id_oficina INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  codigo VARCHAR(20) NOT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_oficinas_nombre (nombre),
  UNIQUE KEY uq_oficinas_codigo (codigo)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: areas (business areas)
CREATE TABLE areas (
  id_area INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  codigo VARCHAR(20) NOT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_areas_nombre (nombre),
  UNIQUE KEY uq_areas_codigo (codigo)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: usuarios (users)
CREATE TABLE usuarios (
  id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_oficina INT UNSIGNED NULL,
  id_area INT UNSIGNED NULL,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nombre_completo VARCHAR(120) NOT NULL,
  email VARCHAR(120) NULL,
  rol ENUM('inventarista','director','operario','paqueteria','secretario','director_general','almacenista') NOT NULL DEFAULT 'operario',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_username (username),
  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_id_oficina (id_oficina),
  KEY idx_usuarios_id_area (id_area),
  CONSTRAINT fk_usuarios_oficinas FOREIGN KEY (id_oficina) REFERENCES oficinas(id_oficina) ON UPDATE CASCADE ON DELETE SET NULL,
  CONSTRAINT fk_usuarios_area FOREIGN KEY (id_area) REFERENCES areas(id_area) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: productos (catalogue of products)
CREATE TABLE productos (
  id_producto INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  sku VARCHAR(40) NOT NULL,
  nombre VARCHAR(150) NOT NULL,
  descripcion VARCHAR(255) NULL,
  unidad_medida VARCHAR(20) NOT NULL DEFAULT 'UND',
  stock_actual INT UNSIGNED NOT NULL DEFAULT 0,
  stock_minimo INT UNSIGNED NOT NULL DEFAULT 0,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_productos_sku (sku)
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: pedidos (minimal definition for FK references)
CREATE TABLE pedidos (
  id_pedido BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  estado ENUM('PENDIENTE_APROBACION','PENDIENTE_DIRECTOR','LISTO_DESPACHO','PENDIENTE','ENTREGADO','RECHAZADO','CANCELADO','FUSIONADO') NOT NULL DEFAULT 'PENDIENTE_APROBACION',
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: detalle_pedidos (order details)
CREATE TABLE detalle_pedidos (
  id_detalle INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pedido BIGINT UNSIGNED NOT NULL,
  id_producto INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL CHECK (cantidad > 0),
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_detalle_pedido_producto (id_pedido, id_producto),
  KEY idx_detalle_id_producto (id_producto),
  CONSTRAINT fk_detalle_pedido FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Table: producto_area (many‑to‑many linking products to areas)
CREATE TABLE producto_area (
  id_producto INT UNSIGNED NOT NULL,
  id_area INT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (id_producto, id_area),
  KEY idx_producto_area_area (id_area),
  CONSTRAINT fk_producto_area_producto FOREIGN KEY (id_producto) REFERENCES productos(id_producto) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_producto_area_area FOREIGN KEY (id_area) REFERENCES areas(id_area) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB;

-- ------------------------------------------------------------
-- Sample Data (optional, agreed to include)
INSERT IGNORE INTO oficinas (nombre, codigo) VALUES
  ('Oficina Administrativa', 'ADM'),
  ('Talento Humano', 'TH');

INSERT INTO areas (id_area, nombre, codigo, activa, created_at, updated_at)
SELECT id_oficina, nombre, codigo, activa, created_at, updated_at FROM oficinas
ON DUPLICATE KEY UPDATE nombre=VALUES(nombre), codigo=VALUES(codigo), activa=VALUES(activa);

INSERT INTO usuarios (id_oficina, id_area, username, password_hash, nombre_completo, email, rol, activo)
VALUES
  (NULL, NULL, 'admin_almacen', '$2y$10$5EBc3PPgmmy6bFCmqIj8ruyZLyMeWcCD1KT7M7UUTT16Fxz8o3WZ2', 'Administrador Almacén', 'admin@empresa.local', 'director', 1),
  (1, 1, 'oficina_adm', '$2y$10$5EBc3PPgmmy6bFCmqIj8ruyZLyMeWcCD1KT7M7UUTT16Fxz8o3WZ2', 'Usuario Oficina ADM', 'adm@empresa.local', 'operario', 1),
  (1, 1, 'secretario', '$2y$10$5EBc3PPgmmy6bFCmqIj8ruyZLyMeWcCD1KT7M7UUTT16Fxz8o3WZ2', 'Secretario Administrativo', 'secretario@empresa.local', 'secretario', 1),
  (NULL, NULL, 'director_general', '$2y$10$5EBc3PPgmmy6bFCmqIj8ruyZLyMeWcCD1KT7M7UUTT16Fxz8o3WZ2', 'Director General', 'director.general@empresa.local', 'director_general', 1),
  (NULL, NULL, 'almacenista', '$2y$10$5EBc3PPgmmy6bFCmqIj8ruyZLyMeWcCD1KT7M7UUTT16Fxz8o3WZ2', 'Responsable de Almacén', 'almacenista@empresa.local', 'almacenista', 1)
ON DUPLICATE KEY UPDATE
  id_oficina=VALUES(id_oficina), id_area=VALUES(id_area), password_hash=VALUES(password_hash),
  nombre_completo=VALUES(nombre_completo), email=VALUES(email), rol=VALUES(rol), activo=VALUES(activo);

INSERT IGNORE INTO productos (sku, nombre, descripcion, unidad_medida, stock_actual, stock_minimo) VALUES
  ('PAP-001', 'Resma Carta', 'Papel tamaño carta 75g', 'RESMA', 100, 20),
  ('PAP-002', 'Bolígrafo Azul', 'Bolígrafo tinta azul punta media', 'UND', 300, 50);

INSERT IGNORE INTO producto_area (id_producto, id_area)
SELECT p.id_producto, a.id_area
FROM productos p
JOIN areas a ON a.id_area IN (1,2)
WHERE p.id_producto = 1
UNION ALL
SELECT p.id_producto, a.id_area
FROM productos p
JOIN areas a ON a.id_area = 1
WHERE p.id_producto = 2;

-- ------------------------------------------------------------
-- End of script
