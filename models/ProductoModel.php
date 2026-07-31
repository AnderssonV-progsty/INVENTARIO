<?php

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';

final class ProductoModel
{
  private PDO $pdo;

  public function __construct()
  {
    $this->pdo = Conexion::getInstancia();
  }

  /**
   * Retorna el catalogo activo con stock para mostrar en frontend.
   */
  public function obtenerCatalogo(): array
  {
    $sql = "
            SELECT
                id_producto,
                sku,
                nombre,
                descripcion,
                unidad_medida,
                stock_actual,
                stock_minimo
            FROM productos
            WHERE activo = 1
            ORDER BY nombre ASC
        ";

    $stmt = $this->pdo->query($sql);
    return $stmt->fetchAll();
  }

  public function obtenerCatalogoPorArea(?int $idArea): array
  {
    if ($idArea === null || $idArea <= 0) {
      return $this->obtenerCatalogo();
    }

    $sql = "
            SELECT
                p.id_producto,
                p.sku,
                p.nombre,
                p.descripcion,
                p.unidad_medida,
                p.stock_actual,
                p.stock_minimo
            FROM productos p
            INNER JOIN producto_area pa ON pa.id_producto = p.id_producto
            WHERE p.activo = 1
              AND pa.id_area = :id_area
            ORDER BY p.nombre ASC
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_area' => $idArea]);
    return $stmt->fetchAll();
  }

  public function obtenerInventario(): array
  {
    $sql = "
            SELECT
                id_producto,
                sku,
                nombre,
                descripcion,
                unidad_medida,
                stock_actual,
                stock_minimo,
                activo
            FROM productos
            ORDER BY nombre ASC
        ";

    $stmt = $this->pdo->query($sql);
    $productos = $stmt->fetchAll();

    foreach ($productos as &$producto) {
      $producto['areas_asignadas'] = $this->obtenerAreasProducto((int) $producto['id_producto']);
    }

    return $productos;
  }

  public function obtenerPorId(int $idProducto): ?array
  {
    $sql = "
            SELECT
                id_producto,
                sku,
                nombre,
                descripcion,
                unidad_medida,
                stock_actual,
                stock_minimo,
                activo
            FROM productos
            WHERE id_producto = :id_producto
            LIMIT 1
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_producto' => $idProducto]);
    $producto = $stmt->fetch();

    if ($producto === false) {
      return null;
    }

    $producto['areas_asignadas'] = $this->obtenerAreasProducto($idProducto);
    return $producto;
  }

  public function crearProducto(string $sku, string $nombre, int $stockActual, array $areas = []): int
  {
    $this->pdo->beginTransaction();

    try {
      $sql = "
              INSERT INTO productos (
                  sku,
                  nombre,
                  descripcion,
                  unidad_medida,
                  stock_actual,
                  stock_minimo,
                  activo
              ) VALUES (
                  :sku,
                  :nombre,
                  NULL,
                  'UND',
                  :stock_actual,
                  0,
                  1
              )
          ";

      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([
        ':sku' => $sku,
        ':nombre' => $nombre,
        ':stock_actual' => $stockActual,
      ]);

      $idProducto = (int) $this->pdo->lastInsertId();
      $this->sincronizarAreasProducto($idProducto, $areas);
      $this->pdo->commit();

      return $idProducto;
    } catch (Throwable $e) {
      $this->pdo->rollBack();
      throw $e;
    }
  }

  public function actualizarProducto(int $idProducto, string $sku, string $nombre, int $stockActual, array $areas = []): bool
  {
    $this->pdo->beginTransaction();

    try {
      $sql = "
              UPDATE productos
              SET
                  sku = :sku,
                  nombre = :nombre,
                  stock_actual = :stock_actual
              WHERE id_producto = :id_producto
          ";

      $stmt = $this->pdo->prepare($sql);
      $stmt->execute([
        ':sku' => $sku,
        ':nombre' => $nombre,
        ':stock_actual' => $stockActual,
        ':id_producto' => $idProducto,
      ]);

      $this->sincronizarAreasProducto($idProducto, $areas);
      $this->pdo->commit();

      return $stmt->rowCount() > 0;
    } catch (Throwable $e) {
      $this->pdo->rollBack();
      throw $e;
    }
  }

  public function desactivarProducto(int $idProducto): bool
  {
    $sql = "
            UPDATE productos
            SET activo = 0
            WHERE id_producto = :id_producto
              AND activo = 1
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_producto' => $idProducto]);

    return $stmt->rowCount() > 0;
  }

  public function reactivarProducto(int $idProducto): bool
  {
    $sql = "
            UPDATE productos
            SET activo = 1
            WHERE id_producto = :id_producto
              AND activo = 0
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_producto' => $idProducto]);

    return $stmt->rowCount() > 0;
  }

  public function obtenerAreas(): array
  {
    $sql = "
            SELECT
                id_area,
                nombre,
                codigo,
                activa,
                created_at,
                updated_at
            FROM areas
            ORDER BY nombre ASC
        ";

    $stmt = $this->pdo->query($sql);
    return $stmt->fetchAll();
  }

  public function obtenerAreaPorId(int $idArea): ?array
  {
    $sql = "
            SELECT
                id_area,
                nombre,
                codigo,
                activa,
                created_at,
                updated_at
            FROM areas
            WHERE id_area = :id_area
            LIMIT 1
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_area' => $idArea]);
    $area = $stmt->fetch();

    return $area === false ? null : $area;
  }

  public function crearArea(string $nombre, string $codigo, bool $activa): int
  {
    $sql = "
            INSERT INTO areas (nombre, codigo, activa)
            VALUES (:nombre, :codigo, :activa)
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':nombre' => $nombre,
      ':codigo' => $codigo,
      ':activa' => $activa ? 1 : 0,
    ]);

    return (int) $this->pdo->lastInsertId();
  }

  public function actualizarArea(int $idArea, string $nombre, string $codigo, bool $activa): bool
  {
    $sql = "
            UPDATE areas
            SET nombre = :nombre,
                codigo = :codigo,
                activa = :activa
            WHERE id_area = :id_area
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':nombre' => $nombre,
      ':codigo' => $codigo,
      ':activa' => $activa ? 1 : 0,
      ':id_area' => $idArea,
    ]);

    return $stmt->rowCount() > 0;
  }

  public function eliminarArea(int $idArea): bool
  {
    $sql = "DELETE FROM areas WHERE id_area = :id_area";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_area' => $idArea]);

    return $stmt->rowCount() > 0;
  }

  public function obtenerUsuarios(): array
  {
    $sql = "
            SELECT
                u.id_usuario,
                u.username,
                u.nombre_completo,
                u.email,
                u.rol,
                u.id_area,
                a.nombre AS area_nombre
            FROM usuarios u
            LEFT JOIN areas a ON a.id_area = u.id_area
            ORDER BY u.nombre_completo ASC
        ";

    $stmt = $this->pdo->query($sql);
    return $stmt->fetchAll();
  }

  public function obtenerUsuarioPorId(int $idUsuario): ?array
  {
    $sql = "
            SELECT
                u.id_usuario,
                u.username,
                u.nombre_completo,
                u.email,
                u.rol,
                u.id_area,
                a.nombre AS area_nombre
            FROM usuarios u
            LEFT JOIN areas a ON a.id_area = u.id_area
            WHERE u.id_usuario = :id_usuario
            LIMIT 1
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_usuario' => $idUsuario]);
    $usuario = $stmt->fetch();

    return $usuario === false ? null : $usuario;
  }

  public function crearUsuario(string $username, string $nombreCompleto, string $email, string $rol, ?int $idArea): int
  {
    $sql = "
            INSERT INTO usuarios (username, password_hash, nombre_completo, email, rol, id_area)
            VALUES (:username, :password_hash, :nombre_completo, :email, :rol, :id_area)
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':username' => $username,
      ':password_hash' => 'hash_' . $username,
      ':nombre_completo' => $nombreCompleto,
      ':email' => $email !== '' ? $email : null,
      ':rol' => $rol,
      ':id_area' => $idArea,
    ]);

    return (int) $this->pdo->lastInsertId();
  }

  public function actualizarUsuario(int $idUsuario, string $username, string $nombreCompleto, string $email, string $rol, ?int $idArea): bool
  {
    $sql = "
            UPDATE usuarios
            SET username = :username,
                nombre_completo = :nombre_completo,
                email = :email,
                rol = :rol,
                id_area = :id_area
            WHERE id_usuario = :id_usuario
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([
      ':username' => $username,
      ':nombre_completo' => $nombreCompleto,
      ':email' => $email !== '' ? $email : null,
      ':rol' => $rol,
      ':id_area' => $idArea,
      ':id_usuario' => $idUsuario,
    ]);

    return $stmt->rowCount() > 0;
  }

  public function eliminarUsuario(int $idUsuario): bool
  {
    $sql = "DELETE FROM usuarios WHERE id_usuario = :id_usuario";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_usuario' => $idUsuario]);

    return $stmt->rowCount() > 0;
  }

  public function obtenerAreasProducto(int $idProducto): array
  {
    $sql = "
            SELECT id_area
            FROM producto_area
            WHERE id_producto = :id_producto
            ORDER BY id_area ASC
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_producto' => $idProducto]);

    return array_map(static fn($row): int => (int) $row['id_area'], $stmt->fetchAll());
  }

  public function sincronizarAreasProducto(int $idProducto, array $areas): void
  {
    $sqlDelete = 'DELETE FROM producto_area WHERE id_producto = :id_producto';
    $stmtDelete = $this->pdo->prepare($sqlDelete);
    $stmtDelete->execute([':id_producto' => $idProducto]);

    $areasUnicas = [];
    foreach ($areas as $idArea) {
      $idAreaEntero = (int) $idArea;
      if ($idAreaEntero > 0) {
        $areasUnicas[] = $idAreaEntero;
      }
    }

    $areasUnicas = array_values(array_unique($areasUnicas));

    if ($areasUnicas === []) {
      return;
    }

    $sqlInsert = 'INSERT INTO producto_area (id_producto, id_area) VALUES (:id_producto, :id_area)';
    $stmtInsert = $this->pdo->prepare($sqlInsert);

    foreach ($areasUnicas as $idArea) {
      $stmtInsert->execute([
        ':id_producto' => $idProducto,
        ':id_area' => $idArea,
      ]);
    }
  }
}
