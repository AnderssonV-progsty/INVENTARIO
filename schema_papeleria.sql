-- Script MySQL 8.x - Sistema de almacén y pedidos de papelería interna
-- Crea base de datos, tablas, constraints e integridad de stock al entregar pedidos.

CREATE DATABASE IF NOT EXISTS almacen_papeleria
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE almacen_papeleria;

-- Limpieza para re-ejecuciones sobre bases ya creadas
SET @old_check = (
  SELECT CONSTRAINT_NAME
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND CONSTRAINT_TYPE = 'CHECK'
    AND CONSTRAINT_NAME = 'ck_usuarios_oficina_rol'
  LIMIT 1
);

SET @sql = IF(@old_check IS NOT NULL, CONCAT('ALTER TABLE usuarios DROP CONSTRAINT ', @old_check), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Elimina triggers primero si ya existían (para re-ejecutar script sin errores)
DROP TRIGGER IF EXISTS trg_pedido_before_update;
DROP TRIGGER IF EXISTS trg_detalle_pedido_before_insert;
DROP TRIGGER IF EXISTS trg_detalle_pedido_before_update;

-- Tabla de oficinas/departamentos internos
CREATE TABLE IF NOT EXISTS oficinas (
  id_oficina INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  codigo VARCHAR(20) NOT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_oficinas_nombre (nombre),
  UNIQUE KEY uq_oficinas_codigo (codigo)
) ENGINE=InnoDB;

-- Catálogo de áreas del negocio
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

-- Tabla de usuarios (oficinas y administrador)
CREATE TABLE IF NOT EXISTS usuarios (
  id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_oficina INT UNSIGNED NULL,
  id_area INT UNSIGNED NULL,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nombre_completo VARCHAR(120) NOT NULL,
  email VARCHAR(120) NULL,
  rol ENUM('inventarista','director','operario','paqueteria') NOT NULL DEFAULT 'operario',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_username (username),
  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_id_oficina (id_oficina),
  KEY idx_usuarios_id_area (id_area),
  CONSTRAINT fk_usuarios_oficinas
    FOREIGN KEY (id_oficina)
    REFERENCES oficinas(id_oficina)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_usuarios_area
-- Script MySQL 8.x - Sistema de almacén y pedidos de papelería interna
-- Crea base de datos, tablas, constraints e integridad de stock al entregar pedidos.

CREATE DATABASE IF NOT EXISTS almacen_papeleria
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE almacen_papeleria;

-- Limpieza para re-ejecuciones sobre bases ya creadas
SET @old_check = (
  SELECT CONSTRAINT_NAME
  FROM information_schema.TABLE_CONSTRAINTS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'usuarios'
    AND CONSTRAINT_TYPE = 'CHECK'
    AND CONSTRAINT_NAME = 'ck_usuarios_oficina_rol'
  LIMIT 1
);

SET @sql = IF(@old_check IS NOT NULL, CONCAT('ALTER TABLE usuarios DROP CONSTRAINT ', @old_check), 'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Elimina triggers primero si ya existían (para re-ejecutar script sin errores)
DROP TRIGGER IF EXISTS trg_pedido_before_update;
DROP TRIGGER IF EXISTS trg_detalle_pedido_before_insert;
DROP TRIGGER IF EXISTS trg_detalle_pedido_before_update;

-- Tabla de oficinas/departamentos internos
CREATE TABLE IF NOT EXISTS oficinas (
  id_oficina INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(120) NOT NULL,
  codigo VARCHAR(20) NOT NULL,
  activa TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_oficinas_nombre (nombre),
  UNIQUE KEY uq_oficinas_codigo (codigo)
) ENGINE=InnoDB;

-- Catálogo de áreas del negocio
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

-- Tabla de usuarios (oficinas y administrador)
CREATE TABLE IF NOT EXISTS usuarios (
  id_usuario INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_oficina INT UNSIGNED NULL,
  id_area INT UNSIGNED NULL,
  username VARCHAR(60) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  nombre_completo VARCHAR(120) NOT NULL,
  email VARCHAR(120) NULL,
  rol ENUM('inventarista','director','operario','paqueteria') NOT NULL DEFAULT 'operario',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_usuarios_username (username),
  UNIQUE KEY uq_usuarios_email (email),
  KEY idx_usuarios_id_oficina (id_oficina),
  KEY idx_usuarios_id_area (id_area),
  CONSTRAINT fk_usuarios_oficinas
    FOREIGN KEY (id_oficina)
    REFERENCES oficinas(id_oficina)
    ON UPDATE CASCADE
    ON DELETE SET NULL,
  CONSTRAINT fk_usuarios_area
    FOREIGN KEY (id_area)
    REFERENCES areas(id_area)
    ON UPDATE CASCADE
    ON DELETE SET NULL
) ENGINE=InnoDB;

-- Catálogo de productos con stock disponible
CREATE TABLE IF NOT EXISTS productos (
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

CREATE TABLE IF NOT EXISTS detalle_pedidos (
  id_detalle INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  id_pedido BIGINT UNSIGNED NOT NULL,
  id_producto INT UNSIGNED NOT NULL,
  cantidad INT UNSIGNED NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_detalle_pedido_producto (id_pedido, id_producto),
  KEY idx_detalle_id_producto (id_producto),
  CONSTRAINT fk_detalle_pedido
    FOREIGN KEY (id_pedido) REFERENCES pedidos(id_pedido)
    ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT fk_detalle_producto
    FOREIGN KEY (id_producto) REFERENCES productos(id_producto)
    ON UPDATE CASCADE ON DELETE RESTRICT,
  CONSTRAINT ck_detalle_cantidad_mayor_0 CHECK (cantidad > 0)
) ENGINE=InnoDB;

DELIMITER $$
CREATE TRIGGER trg_detalle_pedido_before_insert
BEFORE INSERT ON detalle_pedidos
FOR EACH ROW
BEGIN
  DECLARE v_estado VARCHAR(20);
  DECLARE v_stock INT UNSIGNED;

  INSERT INTO areas (id_area, nombre, codigo, activa, created_at, updated_at)
  SELECT id_oficina, nombre, codigo, activa, created_at, updated_at
  FROM oficinas
  WHERE NOT EXISTS (
    SELECT 1 FROM areas a WHERE a.id_area = oficinas.id_oficina
  )
  ON DUPLICATE KEY UPDATE
    nombre = VALUES(nombre),
    codigo = VALUES(codigo),
    activa = VALUES(activa);

  SET @has_id_area = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'usuarios'
      AND COLUMN_NAME = 'id_area'
  );

  SET @sql_area = IF(@has_id_area = 0, 'ALTER TABLE usuarios ADD COLUMN id_area INT UNSIGNED NULL AFTER id_oficina', 'SELECT 1');
  PREPARE stmt_area FROM @sql_area;
  EXECUTE stmt_area;
  DEALLOCATE PREPARE stmt_area;

  UPDATE usuarios u
  LEFT JOIN areas a ON a.id_area = u.id_oficina
  SET u.id_area = u.id_oficina
  WHERE u.id_area IS NULL AND u.id_oficina IS NOT NULL AND u.id_oficina IN (SELECT id_oficina FROM oficinas);

  UPDATE usuarios
  SET rol = CASE
    WHEN rol = 'ADMIN_ALMACEN' THEN 'director'
    WHEN rol = 'OFICINA' THEN 'operario'
    ELSE rol
  END
  WHERE rol IN ('ADMIN_ALMACEN','OFICINA');

  ALTER TABLE usuarios
    MODIFY COLUMN rol ENUM(
      'inventarista',
      'director',
      'operario',
      'paqueteria',
      'secretario',
      'director_general',
      'almacenista'
    ) NOT NULL DEFAULT 'operario';
END $$
DELIMITER ;

CREATE TABLE IF NOT EXISTS producto_area (
  id_producto INT UNSIGNED NOT NULL,
  id_area INT UNSIGNED NOT NULL,
  activo TINYINT(1) NOT NULL DEFAULT 1,
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
  MODIFY COLUMN estado ENUM(
    'PENDIENTE_APROBACION',
    'PENDIENTE_DIRECTOR',
    'LISTO_DESPACHO',
    'PENDIENTE',
    'ENTREGADO',
    'RECHAZADO',
    'CANCELADO',
    'FUSIONADO'
  ) NOT NULL DEFAULT 'PENDIENTE_APROBACION';

-- Datos mínimos de ejemplo (opcional)
INSERT IGNORE INTO oficinas (nombre, codigo) VALUES
('Oficina Administrativa', 'ADM'),
('Talento Humano', 'TH');

-- Sincroniza las áreas después de crear las oficinas de ejemplo.
INSERT INTO areas (id_area, nombre, codigo, activa, created_at, updated_at)
SELECT id_oficina, nombre, codigo, activa, created_at, updated_at
FROM oficinas
ON DUPLICATE KEY UPDATE
  nombre = VALUES(nombre),
  codigo = VALUES(codigo),
  activa = VALUES(activa);

-- Único bloque de usuarios iniciales. Puede ejecutarse varias veces sin duplicarlos.
-- Todos usan la clave inicial 123456; cámbiala antes de usar el sistema en producción.
INSERT INTO usuarios
  (id_oficina, id_area, username, password_hash, nombre_completo, email, rol, activo)
VALUES
(NULL, NULL, 'admin_almacen', '$2y$10$l6wv/1USYkxuHd5dG10uGuVDmkNIEaeveK.gar/N67cQIZuvfzzvy', 'Administrador Almacén', 'admin@empresa.local', 'director', 1),
(1, 1, 'oficina_adm', '$2y$10$l6wv/1USYkxuHd5dG10uGuVDmkNIEaeveK.gar/N67cQIZuvfzzvy', 'Usuario Oficina ADM', 'adm@empresa.local', 'operario', 1),
(1, 1, 'secretario', '$2y$10$l6wv/1USYkxuHd5dG10uGuVDmkNIEaeveK.gar/N67cQIZuvfzzvy', 'Secretario Administrativo', 'secretario@empresa.local', 'secretario', 1),
(NULL, NULL, 'director_general', '$2y$10$l6wv/1USYkxuHd5dG10uGuVDmkNIEaeveK.gar/N67cQIZuvfzzvy', 'Director General', 'director.general@empresa.local', 'director_general', 1),
(NULL, NULL, 'almacenista', '$2y$10$l6wv/1USYkxuHd5dG10uGuVDmkNIEaeveK.gar/N67cQIZuvfzzvy', 'Responsable de Almacén', 'almacenista@empresa.local', 'almacenista', 1)
ON DUPLICATE KEY UPDATE
  id_oficina = VALUES(id_oficina),
  id_area = VALUES(id_area),
  password_hash = VALUES(password_hash),
  nombre_completo = VALUES(nombre_completo),
  email = VALUES(email),
  rol = VALUES(rol),
  activo = VALUES(activo);

INSERT IGNORE INTO productos (sku, nombre, descripcion, unidad_medida, stock_actual, stock_minimo)
VALUES
('PAP-001', 'Resma Carta', 'Papel tamano carta 75g', 'RESMA', 100, 20),
('PAP-002', 'Boligrafo Azul', 'Boligrafo tinta azul punta media', 'UND', 300, 50);

INSERT IGNORE INTO producto_area (id_producto, id_area)
SELECT p.id_producto, a.id_area
FROM productos p
JOIN areas a ON a.id_area IN (1,2)
WHERE p.id_producto = 1
  AND a.id_area IN (1,2)
UNION ALL
SELECT p.id_producto, a.id_area
FROM productos p
JOIN areas a ON a.id_area = 1
WHERE p.id_producto = 2;
