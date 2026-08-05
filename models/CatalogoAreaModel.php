<?php

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';

final class CatalogoAreaModel
{
  private PDO $pdo;

  public function __construct()
  {
    $this->pdo = Conexion::getInstancia();
  }

  public function obtenerCatalogoPorArea(int $idArea): array
  {
    $sql = "
      SELECT
        p.id_producto,
        p.sku,
        p.nombre,
        p.descripcion,
        p.unidad_medida,
        p.stock_actual
      FROM producto_area pa
      INNER JOIN productos p ON p.id_producto = pa.id_producto
      WHERE pa.id_area = :id_area
        AND pa.activo = 1
        AND p.activo = 1
        AND p.stock_actual > 0
      ORDER BY p.nombre ASC
    ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_area' => $idArea]);

    return $stmt->fetchAll() ?: [];
  }
}
