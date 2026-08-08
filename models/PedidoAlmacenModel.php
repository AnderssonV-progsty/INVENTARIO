<?php

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';

final class PedidoAlmacenModel
{
  private PDO $pdo;

  public function __construct()
  {
    $this->pdo = Conexion::getInstancia();
  }

  public function obtenerPedidosListosDespacho(): array
  {
    $sqlPedidos = "
      SELECT
        p.id_pedido,
        COALESCE(p.id_area, p.id_oficina) AS id_area,
        a.nombre AS nombre_area,
        u.nombre_completo AS nombre_secretario,
        p.observaciones,
        COALESCE(p.fecha_creacion, p.fecha_pedido) AS fecha_creacion,
        p.estado
      FROM pedidos p
      INNER JOIN areas a ON a.id_area = COALESCE(p.id_area, p.id_oficina)
      INNER JOIN usuarios u ON u.id_usuario = COALESCE(p.id_usuario_secretario, p.id_usuario_solicitante)
      WHERE p.estado = 'LISTO_DESPACHO'
      ORDER BY p.fecha_pedido ASC
    ";

    $pedidos = $this->pdo->query($sqlPedidos)->fetchAll() ?: [];
    if ($pedidos === []) {
      return [];
    }

    $ids = array_map(static fn(array $p): int => (int) $p['id_pedido'], $pedidos);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sqlDetalles = "
      SELECT
        d.id_pedido,
        d.id_producto,
        p.sku,
        p.nombre,
        p.unidad_medida,
        d.cantidad
      FROM detalle_pedidos d
      INNER JOIN productos p ON p.id_producto = d.id_producto
      WHERE d.id_pedido IN ($placeholders)
      ORDER BY p.nombre ASC
    ";

    $stmtDetalles = $this->pdo->prepare($sqlDetalles);
    foreach ($ids as $index => $idPedido) {
      $stmtDetalles->bindValue($index + 1, $idPedido, PDO::PARAM_INT);
    }
    $stmtDetalles->execute();
    $detalles = $stmtDetalles->fetchAll() ?: [];

    $detallesPorPedido = [];
    foreach ($detalles as $item) {
      $idPedido = (int) $item['id_pedido'];
      if (!isset($detallesPorPedido[$idPedido])) {
        $detallesPorPedido[$idPedido] = [];
      }
      $detallesPorPedido[$idPedido][] = [
        'id_producto' => (int) $item['id_producto'],
        'sku' => (string) $item['sku'],
        'nombre' => (string) $item['nombre'],
        'unidad_medida' => (string) $item['unidad_medida'],
        'cantidad' => (int) $item['cantidad'],
      ];
    }

    foreach ($pedidos as &$pedido) {
      $idPedido = (int) $pedido['id_pedido'];
      $pedido['items'] = $detallesPorPedido[$idPedido] ?? [];
    }
    unset($pedido);

    return $pedidos;
  }

  public function despacharPedido(int $idPedido, int $idAlmacenista): void
  {
    $stmt = $this->pdo->prepare(
      "
        UPDATE pedidos
        SET estado = 'ENTREGADO',
            id_usuario_almacenista = :id_usuario_almacenista,
            id_usuario_entrega = :id_usuario_almacenista,
            fecha_despacho = NOW(),
            fecha_entrega = NOW()
        WHERE id_pedido = :id_pedido
          AND estado = 'LISTO_DESPACHO'
      "
    );

    $stmt->execute([
      ':id_usuario_almacenista' => $idAlmacenista,
      ':id_pedido' => $idPedido,
    ]);

    if ($stmt->rowCount() === 0) {
      throw new RuntimeException('Pedido no encontrado o no esta listo para despacho.');
    }
  }
}
