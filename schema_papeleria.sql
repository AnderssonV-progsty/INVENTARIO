-- Script MySQL 8.x - Sistema de almacén y pedidos de papelería interna
-- Crea base de datos, tablas, constraints e integridad de stock al entregar pedidos.

CREATE DATABASE IF NOT EXISTS almacen_papeleria
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE almacen_papeleria;

-- Elimina triggers primero si ya existían (para re-ejecutar script sin errores)
DROP TRIGGER IF EXISTS trg_pedido_before_update;
DROP TRIGGER IF EXISTS trg_detalle_pedido_before_insert;
DROP TRIGGER IF EXISTS trg_detalle_pedido_before_update;

-- Elimina tablas en orden de dependencias
DROP TABLE IF EXISTS detalle_pedidos;
DROP TABLE IF EXISTS pedidos;
DROP TABLE IF EXISTS productos;
DROP TABLE IF EXISTS usuarios;
DROP TABLE IF EXISTS oficinas;

-- Tabla de oficinas/departamentos internos
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

-- Tabla de usuarios (oficinas y administrador)
CREATE TABLE usuarios (
  id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_oficina INT UNSIGNED NULL,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nombre_completo VARCHAR(120) NOT NULL,
  email VARCHAR(120) NULL,
  rol ENUM('OFICINA','ADMIN_ALMACEN') NOT NULL DEFAULT 'OFICINA',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_username (username),
  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_id_oficina (id_oficina),
  CONSTRAINT fk_usuarios_oficinas
    FOREIGN KEY (id_oficina)
    REFERENCES oficinas(id_oficina)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT ck_usuarios_oficina_rol
    CHECK (
      (rol = 'ADMIN_ALMACEN' AND id_oficina IS NULL)
      OR (rol = 'OFICINA' AND id_oficina IS NOT NULL)
    )
) ENGINE=InnoDB;

-- Catálogo de productos con stock disponible
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
  UNIQUE KEY uq_productos_sku (sku),
  KEY idx_productos_nombre (nombre)
) ENGINE=InnoDB;

-- Pedidos: estado de flujo (pendiente -> entregado)
CREATE TABLE pedidos (
  id_pedido BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_usuario_solicitante INT UNSIGNED NOT NULL,
  id_oficina INT UNSIGNED NOT NULL,
  fecha_pedido DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  estado ENUM('PENDIENTE','ENTREGADO','CANCELADO') NOT NULL DEFAULT 'PENDIENTE',
  observaciones VARCHAR(255) NULL,
  fecha_entrega DATETIME NULL,
  id_usuario_entrega INT UNSIGNED NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_pedidos_estado (estado),
  KEY idx_pedidos_oficina (id_oficina),
  KEY idx_pedidos_solicitante (id_usuario_solicitante),
  CONSTRAINT fk_pedidos_usuario_solicitante
    FOREIGN KEY (id_usuario_solicitante)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_pedidos_oficina
    FOREIGN KEY (id_oficina)
    REFERENCES oficinas(id_oficina)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT fk_pedidos_usuario_entrega
    FOREIGN KEY (id_usuario_entrega)
    REFERENCES usuarios(id_usuario)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- Detalle de productos por pedido
CREATE TABLE detalle_pedidos (
  id_detalle BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pedido BIGINT UNSIGNED NOT NULL,
  id_producto INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_detalle_pedido_producto (id_pedido, id_producto),
  KEY idx_detalle_id_producto (id_producto),
  CONSTRAINT fk_detalle_pedido
    FOREIGN KEY (id_pedido)
    REFERENCES pedidos(id_pedido)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto
    FOREIGN KEY (id_producto)
    REFERENCES productos(id_producto)
    ON UPDATE CASCADE
    ON DELETE RESTRICT,
  CONSTRAINT ck_detalle_cantidad_mayor_0 CHECK (cantidad > 0)
) ENGINE=InnoDB;

DELIMITER $$

-- Evita insertar detalle en pedidos ya cerrados y valida stock
CREATE TRIGGER trg_detalle_pedido_before_insert
BEFORE INSERT ON detalle_pedidos
FOR EACH ROW
BEGIN
  DECLARE v_estado VARCHAR(20);
  DECLARE v_stock INT UNSIGNED;

  SELECT estado INTO v_estado
  FROM pedidos
  WHERE id_pedido = NEW.id_pedido;

  IF v_estado IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Pedido no existe.';
  END IF;

  IF v_estado <> 'PENDIENTE' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se puede agregar detalle a pedidos pendientes.';
  END IF;

  SELECT stock_actual INTO v_stock
  FROM productos
  WHERE id_producto = NEW.id_producto;

  IF v_stock IS NULL THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Producto no existe.';
  END IF;

  IF NEW.cantidad > v_stock THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cantidad solicitada supera stock disponible.';
  END IF;
END$$

-- Evita modificar detalle en pedidos cerrados y revalida stock
CREATE TRIGGER trg_detalle_pedido_before_update
BEFORE UPDATE ON detalle_pedidos
FOR EACH ROW
BEGIN
  DECLARE v_estado VARCHAR(20);
  DECLARE v_stock INT UNSIGNED;

  SELECT estado INTO v_estado
  FROM pedidos
  WHERE id_pedido = NEW.id_pedido;

  IF v_estado <> 'PENDIENTE' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Solo se puede modificar detalle de pedidos pendientes.';
  END IF;

  SELECT stock_actual INTO v_stock
  FROM productos
  WHERE id_producto = NEW.id_producto;

  IF NEW.cantidad > v_stock THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Cantidad solicitada supera stock disponible.';
  END IF;
END$$

-- Al pasar un pedido a ENTREGADO, descuenta stock automáticamente
CREATE TRIGGER trg_pedido_before_update
BEFORE UPDATE ON pedidos
FOR EACH ROW
BEGIN
  DECLARE v_faltantes INT DEFAULT 0;

  IF OLD.estado = 'ENTREGADO' AND NEW.estado <> 'ENTREGADO' THEN
    SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'No se permite revertir pedidos entregados.';
  END IF;

  IF OLD.estado <> 'ENTREGADO' AND NEW.estado = 'ENTREGADO' THEN
    -- Verifica stock suficiente antes de descontar
    SELECT COUNT(*) INTO v_faltantes
    FROM detalle_pedidos d
    JOIN productos p ON p.id_producto = d.id_producto
    WHERE d.id_pedido = OLD.id_pedido
      AND d.cantidad > p.stock_actual;

    IF v_faltantes > 0 THEN
      SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'Stock insuficiente para completar la entrega del pedido.';
    END IF;

    -- Descuenta stock de cada producto del pedido
    UPDATE productos p
    JOIN detalle_pedidos d ON d.id_producto = p.id_producto
    SET p.stock_actual = p.stock_actual - d.cantidad
    WHERE d.id_pedido = OLD.id_pedido;

    SET NEW.fecha_entrega = IFNULL(NEW.fecha_entrega, NOW());
  END IF;
END$$

DELIMITER ;

-- Migración segura para soportar áreas, roles y nuevos estados de pedido
-- (No borra datos; solo agrega compatibilidad)
CREATE TABLE IF NOT EXISTS areas (
  id_area INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  codigo VARCHAR(20) NOT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_areas_nombre (nombre),
  UNIQUE KEY uq_areas_codigo (codigo)
) ENGINE=InnoDB;

INSERT INTO areas (id_area, nombre, codigo, activa, created_at, updated_at)
SELECT id_oficina, nombre, codigo, activa, created_at, updated_at
FROM oficinas
WHERE NOT EXISTS (
  SELECT 1 FROM areas a WHERE a.id_area = oficinas.id_oficina
);

ALTER TABLE usuarios
  ADD COLUMN id_area INT UNSIGNED NULL AFTER id_oficina;

UPDATE usuarios u
JOIN areas a ON a.id_area = u.id_oficina
SET u.id_area = u.id_oficina
WHERE u.id_area IS NULL AND u.id_oficina IS NOT NULL;

ALTER TABLE usuarios
  ADD CONSTRAINT fk_usuarios_area
    FOREIGN KEY (id_area)
    REFERENCES areas(id_area)
    ON UPDATE CASCADE
    ON DELETE SET NULL;

ALTER TABLE usuarios
  MODIFY COLUMN rol ENUM('inventarista','director','operario','paqueteria','OFICINA','ADMIN_ALMACEN') NOT NULL DEFAULT 'operario';

UPDATE usuarios
SET rol = CASE
  WHEN rol = 'ADMIN_ALMACEN' THEN 'director'
  WHEN rol = 'OFICINA' THEN 'operario'
  ELSE rol
END
WHERE rol IN ('ADMIN_ALMACEN','OFICINA');

ALTER TABLE usuarios
  MODIFY COLUMN rol ENUM('inventarista','director','operario','paqueteria') NOT NULL DEFAULT 'operario';

CREATE TABLE IF NOT EXISTS producto_area (
  id_producto INT UNSIGNED NOT NULL,
  id_area INT UNSIGNED NOT NULL,
  PRIMARY KEY (id_producto, id_area),
  KEY idx_producto_area_area (id_area),
  CONSTRAINT fk_producto_area_producto
    FOREIGN KEY (id_producto)
    REFERENCES productos(id_producto)
    ON UPDATE CASCADE
    ON DELETE CASCADE,
  CONSTRAINT fk_producto_area_area
    FOREIGN KEY (id_area)
    REFERENCES areas(id_area)
    ON UPDATE CASCADE
    ON DELETE CASCADE
) ENGINE=InnoDB;

UPDATE pedidos
SET estado = 'PENDIENTE_APROBACION'
WHERE estado = 'CANCELADO';

ALTER TABLE pedidos
  MODIFY COLUMN estado ENUM('PENDIENTE_APROBACION','PENDIENTE','ENTREGADO') NOT NULL DEFAULT 'PENDIENTE_APROBACION';

-- Datos mínimos de ejemplo (opcional)
INSERT INTO oficinas (nombre, codigo) VALUES
('Oficina Administrativa', 'ADM'),
('Talento Humano', 'TH');

INSERT INTO usuarios (id_oficina, username, password_hash, nombre_completo, email, rol)
VALUES
(NULL, 'admin_almacen', 'hash_seguro_admin', 'Administrador Almacen', 'admin@empresa.local', 'ADMIN_ALMACEN'),
(1, 'oficina_adm', 'hash_seguro_oficina', 'Usuario Oficina ADM', 'adm@empresa.local', 'OFICINA');

INSERT INTO productos (sku, nombre, descripcion, unidad_medida, stock_actual, stock_minimo)
VALUES
('PAP-001', 'Resma Carta', 'Papel tamano carta 75g', 'RESMA', 100, 20),
('PAP-002', 'Boligrafo Azul', 'Boligrafo tinta azul punta media', 'UND', 300, 50);
