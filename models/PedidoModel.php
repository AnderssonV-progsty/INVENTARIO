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

  public function obtenerPedidosPendientesConDetalle(): array
  {
    $sql = "
            SELECT
                p.id_pedido,
                p.id_oficina,
                o.nombre AS nombre_oficina,
                p.fecha_pedido,
                d.id_producto,
                pr.sku,
                pr.nombre AS nombre_producto,
                d.cantidad
            FROM pedidos p
            INNER JOIN oficinas o ON o.id_oficina = p.id_oficina
            INNER JOIN detalle_pedidos d ON d.id_pedido = p.id_pedido
            INNER JOIN productos pr ON pr.id_producto = d.id_producto
            WHERE p.estado = 'PENDIENTE'
            ORDER BY p.fecha_pedido ASC, p.id_pedido ASC, d.id_detalle ASC
        ";

    $stmt = $this->pdo->query($sql);
    $rows = $stmt->fetchAll();

    $pedidos = [];

    foreach ($rows as $row) {
      $idPedido = (int) $row['id_pedido'];

      if (!isset($pedidos[$idPedido])) {
        $pedidos[$idPedido] = [
          'id_pedido' => $idPedido,
          'id_oficina' => (int) $row['id_oficina'],
          'nombre_oficina' => (string) $row['nombre_oficina'],
          'fecha_pedido' => (string) $row['fecha_pedido'],
          'items' => [],
        ];
      }

      $pedidos[$idPedido]['items'][] = [
        'id_producto' => (int) $row['id_producto'],
        'sku' => (string) $row['sku'],
        'nombre_producto' => (string) $row['nombre_producto'],
        'cantidad' => (int) $row['cantidad'],
      ];
    }

    return array_values($pedidos);
  }

  public function marcarPedidoEntregado(int $idPedido): bool
  {
    $sql = "
            UPDATE pedidos
            SET estado = 'ENTREGADO'
            WHERE id_pedido = :id_pedido
              AND estado = 'PENDIENTE'
        ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_pedido' => $idPedido]);

    return $stmt->rowCount() > 0;
  }

  public function existePedido(int $idPedido): bool
  {
    $sql = "SELECT 1 FROM pedidos WHERE id_pedido = :id_pedido LIMIT 1";
    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':id_pedido' => $idPedido]);

    return (bool) $stmt->fetchColumn();
  }
}
