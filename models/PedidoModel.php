<?php

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';

final class PedidoModel
{
  private PDO $pdo;

  public function __construct()
  {
    $this->pdo = Conexion::getInstancia();
  }

  /**
   * Guarda pedido y detalle en una transaccion.
   *
   * @param array<int, array{id_producto:int, cantidad:int}> $items
   */
  public function crearPedidoDesdeCarrito(
    int $idUsuarioSolicitante,
    int $idOficina,
    array $items,
    ?string $observaciones = null
  ): int {
    if (empty($items)) {
      throw new InvalidArgumentException('El carrito no puede estar vacio.');
    }

    $this->pdo->beginTransaction();

    try {
      $sqlPedido = "
                INSERT INTO pedidos (id_usuario_solicitante, id_oficina, observaciones, estado)
                VALUES (:id_usuario_solicitante, :id_oficina, :observaciones, 'PENDIENTE')
            ";
      $stmtPedido = $this->pdo->prepare($sqlPedido);
      $stmtPedido->execute([
        ':id_usuario_solicitante' => $idUsuarioSolicitante,
        ':id_oficina' => $idOficina,
        ':observaciones' => $observaciones,
      ]);

      $idPedido = (int) $this->pdo->lastInsertId();

      $sqlDetalle = "
                INSERT INTO detalle_pedidos (id_pedido, id_producto, cantidad)
                VALUES (:id_pedido, :id_producto, :cantidad)
            ";
      $stmtDetalle = $this->pdo->prepare($sqlDetalle);

      foreach ($items as $item) {
        $idProducto = isset($item['id_producto']) ? (int) $item['id_producto'] : 0;
        $cantidad = isset($item['cantidad']) ? (int) $item['cantidad'] : 0;

        if ($idProducto <= 0 || $cantidad <= 0) {
          throw new InvalidArgumentException('Cada item debe tener id_producto y cantidad validos.');
        }

        $stmtDetalle->execute([
          ':id_pedido' => $idPedido,
          ':id_producto' => $idProducto,
          ':cantidad' => $cantidad,
        ]);
      }

      $this->pdo->commit();
      return $idPedido;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }
}
