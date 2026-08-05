<?php

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';

final class ProductoAdminModel
{
  private PDO $pdo;

  public function __construct()
  {
    $this->pdo = Conexion::getInstancia();
  }

  public function obtenerAreasActivas(): array
  {
    $stmt = $this->pdo->query("SELECT id_area, nombre, codigo FROM areas WHERE activa = 1 ORDER BY nombre ASC");
    return $stmt->fetchAll() ?: [];
  }

  public function listarProductos(): array
  {
    $sql = "
      SELECT
        p.id_producto,
        p.sku,
        p.nombre,
        p.descripcion,
        p.unidad_medida,
        p.stock_actual,
        p.stock_minimo,
        p.activo,
        GROUP_CONCAT(pa.id_area ORDER BY pa.id_area ASC) AS areas_ids
      FROM productos p
      LEFT JOIN producto_area pa ON pa.id_producto = p.id_producto
      GROUP BY p.id_producto
      ORDER BY p.nombre ASC
    ";

    $productos = $this->pdo->query($sql)->fetchAll() ?: [];

    foreach ($productos as &$producto) {
      $areasIds = trim((string) ($producto['areas_ids'] ?? ''));
      $producto['areas_ids'] = $areasIds === ''
        ? []
        : array_map('intval', explode(',', $areasIds));
    }
    unset($producto);

    return $productos;
  }

  public function crearProducto(array $data): int
  {
    $this->pdo->beginTransaction();

    try {
      $stmt = $this->pdo->prepare(
        "
          INSERT INTO productos (
            sku, nombre, descripcion, unidad_medida, stock_actual, stock_minimo, activo
          ) VALUES (
            :sku, :nombre, :descripcion, :unidad_medida, :stock_actual, :stock_minimo, :activo
          )
        "
      );

      $stmt->execute([
        ':sku' => (string) $data['sku'],
        ':nombre' => (string) $data['nombre'],
        ':descripcion' => $data['descripcion'] !== '' ? (string) $data['descripcion'] : null,
        ':unidad_medida' => (string) $data['unidad_medida'],
        ':stock_actual' => (int) $data['stock_actual'],
        ':stock_minimo' => (int) $data['stock_minimo'],
        ':activo' => (int) $data['activo'],
      ]);

      $idProducto = (int) $this->pdo->lastInsertId();
      $this->sincronizarAreasProducto($idProducto, $data['areas_ids']);

      $this->pdo->commit();
      return $idProducto;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  public function actualizarProducto(int $idProducto, array $data): void
  {
    $this->pdo->beginTransaction();

    try {
      $stmt = $this->pdo->prepare(
        "
          UPDATE productos
          SET sku = :sku,
              nombre = :nombre,
              descripcion = :descripcion,
              unidad_medida = :unidad_medida,
              stock_actual = :stock_actual,
              stock_minimo = :stock_minimo,
              activo = :activo
          WHERE id_producto = :id_producto
        "
      );

      $stmt->execute([
        ':sku' => (string) $data['sku'],
        ':nombre' => (string) $data['nombre'],
        ':descripcion' => $data['descripcion'] !== '' ? (string) $data['descripcion'] : null,
        ':unidad_medida' => (string) $data['unidad_medida'],
        ':stock_actual' => (int) $data['stock_actual'],
        ':stock_minimo' => (int) $data['stock_minimo'],
        ':activo' => (int) $data['activo'],
        ':id_producto' => $idProducto,
      ]);

      if ($stmt->rowCount() === 0) {
        $exists = $this->pdo->prepare('SELECT id_producto FROM productos WHERE id_producto = :id_producto LIMIT 1');
        $exists->execute([':id_producto' => $idProducto]);
        if ($exists->fetch() === false) {
          throw new RuntimeException('Producto no encontrado.');
        }
      }

      $this->sincronizarAreasProducto($idProducto, $data['areas_ids']);

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  public function eliminarProducto(int $idProducto): void
  {
    $this->pdo->beginTransaction();

    try {
      $stmtAreas = $this->pdo->prepare('DELETE FROM producto_area WHERE id_producto = :id_producto');
      $stmtAreas->execute([':id_producto' => $idProducto]);

      $stmt = $this->pdo->prepare('DELETE FROM productos WHERE id_producto = :id_producto');
      $stmt->execute([':id_producto' => $idProducto]);

      if ($stmt->rowCount() === 0) {
        throw new RuntimeException('Producto no encontrado.');
      }

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  private function sincronizarAreasProducto(int $idProducto, array $areasIds): void
  {
    $stmtDelete = $this->pdo->prepare('DELETE FROM producto_area WHERE id_producto = :id_producto');
    $stmtDelete->execute([':id_producto' => $idProducto]);

    if ($areasIds === []) {
      return;
    }

    $stmtInsert = $this->pdo->prepare(
      "
        INSERT INTO producto_area (id_producto, id_area)
        VALUES (:id_producto, :id_area)
      "
    );

    foreach ($areasIds as $idArea) {
      $stmtInsert->execute([
        ':id_producto' => $idProducto,
        ':id_area' => (int) $idArea,
      ]);
    }
  }
}
