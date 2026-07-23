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
    return $stmt->fetchAll();
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

    return $producto === false ? null : $producto;
  }

  public function crearProducto(string $sku, string $nombre, int $stockActual): int
  {
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

    return (int) $this->pdo->lastInsertId();
  }

  public function actualizarProducto(int $idProducto, string $sku, string $nombre, int $stockActual): bool
  {
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

    return $stmt->rowCount() > 0;
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
}
