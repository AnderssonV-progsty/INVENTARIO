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
}
