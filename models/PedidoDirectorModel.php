<?php

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';

final class PedidoDirectorModel
{
  private PDO $pdo;

  public function __construct()
  {
    $this->pdo = Conexion::getInstancia();
  }

  public function obtenerPendientesDirector(): array
  {
    $sqlPedidos = "
      SELECT
        p.id_pedido,
        COALESCE(p.id_area, p.id_oficina) AS id_area,
        a.nombre AS nombre_area,
        COALESCE(p.id_usuario_secretario, p.id_usuario_solicitante) AS id_usuario_secretario,
        u.nombre_completo AS nombre_secretario,
        p.observaciones,
        COALESCE(p.fecha_creacion, p.fecha_pedido) AS fecha_creacion,
        p.estado
      FROM pedidos p
      INNER JOIN areas a ON a.id_area = COALESCE(p.id_area, p.id_oficina)
      INNER JOIN usuarios u ON u.id_usuario = COALESCE(p.id_usuario_secretario, p.id_usuario_solicitante)
      WHERE p.estado = 'PENDIENTE_DIRECTOR'
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

  public function procesarPedido(int $idPedido, string $accion, array $cantidades, int $idDirector, ?string $motivoRechazo): void
  {
    $accion = strtolower(trim($accion));
    if (!in_array($accion, ['aprobar', 'rechazar'], true)) {
      throw new InvalidArgumentException('Accion invalida.');
    }

    $this->pdo->beginTransaction();

    try {
      $stmtPedido = $this->pdo->prepare(
        "
          SELECT id_pedido, estado
          FROM pedidos
          WHERE id_pedido = :id_pedido
          LIMIT 1
          FOR UPDATE
        "
      );
      $stmtPedido->execute([':id_pedido' => $idPedido]);
      $pedido = $stmtPedido->fetch();

      if ($pedido === false) {
        throw new RuntimeException('Pedido no encontrado.');
      }

      if ((string) $pedido['estado'] !== 'PENDIENTE_DIRECTOR') {
        throw new RuntimeException('El pedido ya no esta pendiente de director.');
      }

      if ($cantidades !== []) {
        $stmtUpdateDetalle = $this->pdo->prepare(
          "
            UPDATE detalle_pedidos
            SET cantidad = :cantidad
            WHERE id_pedido = :id_pedido AND id_producto = :id_producto
          "
        );

        foreach ($cantidades as $item) {
          $idProducto = (int) ($item['id_producto'] ?? 0);
          $cantidad = (int) ($item['cantidad'] ?? 0);

          if ($idProducto <= 0 || $cantidad <= 0) {
            throw new InvalidArgumentException('Cantidades editadas invalidas.');
          }

          $stmtUpdateDetalle->execute([
            ':cantidad' => $cantidad,
            ':id_pedido' => $idPedido,
            ':id_producto' => $idProducto,
          ]);

          if ($stmtUpdateDetalle->rowCount() === 0) {
            throw new RuntimeException('No se encontro detalle para actualizar en el pedido.');
          }
        }
      }

      if ($accion === 'aprobar') {
        $stmtUpdatePedido = $this->pdo->prepare(
          "
            UPDATE pedidos
            SET estado = 'LISTO_DESPACHO',
                id_usuario_director = :id_usuario_director,
                fecha_revision_director = NOW(),
                motivo_rechazo = NULL
            WHERE id_pedido = :id_pedido
          "
        );
      } else {
        $stmtUpdatePedido = $this->pdo->prepare(
          "
            UPDATE pedidos
            SET estado = 'RECHAZADO',
                id_usuario_director = :id_usuario_director,
                fecha_revision_director = NOW(),
                fecha_rechazo = NOW(),
                motivo_rechazo = :motivo_rechazo
            WHERE id_pedido = :id_pedido
          "
        );
      }

      $params = [
        ':id_usuario_director' => $idDirector,
        ':id_pedido' => $idPedido,
      ];

      if ($accion === 'rechazar') {
        $params[':motivo_rechazo'] = $motivoRechazo !== null && $motivoRechazo !== '' ? $motivoRechazo : 'Rechazado por Director General';
      }

      $stmtUpdatePedido->execute($params);

      $this->pdo->commit();
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }
}
