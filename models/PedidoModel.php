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
                VALUES (:id_usuario_solicitante, :id_oficina, :observaciones, 'PENDIENTE_APROBACION')
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

  public function obtenerPedidosPendientesConDetalle(?int $idOficina = null): array
  {
    $sql = "
            SELECT
                p.id_pedido,
                p.id_oficina,
                p.estado,
                p.observaciones,
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
            WHERE p.estado IN ('PENDIENTE_APROBACION', 'PENDIENTE')
        ";

    $params = [];
    if ($idOficina !== null && $idOficina > 0) {
      $sql .= ' AND p.id_oficina = :id_oficina';
      $params[':id_oficina'] = $idOficina;
    }

    $sql .= ' ORDER BY p.fecha_pedido ASC, p.id_pedido ASC, d.id_detalle ASC';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $pedidos = [];

    foreach ($rows as $row) {
      $idPedido = (int) $row['id_pedido'];

      if (!isset($pedidos[$idPedido])) {
        $pedidos[$idPedido] = [
          'id_pedido' => $idPedido,
          'id_oficina' => (int) $row['id_oficina'],
          'estado' => (string) $row['estado'],
          'observaciones' => (string) ($row['observaciones'] ?? ''),
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

  public function aprobarYUnificar(array $idsPedido): int
  {
    if ($idsPedido === []) {
      throw new InvalidArgumentException('Debes seleccionar al menos un pedido.');
    }

    $this->pdo->beginTransaction();

    try {
      $placeholders = implode(', ', array_fill(0, count($idsPedido), '?'));
      $sqlPedidos = "
                SELECT id_pedido, id_usuario_solicitante, id_oficina, estado
                FROM pedidos
                WHERE id_pedido IN ($placeholders)
                FOR UPDATE
            ";
      $stmtPedidos = $this->pdo->prepare($sqlPedidos);
      $stmtPedidos->execute($idsPedido);
      $pedidos = $stmtPedidos->fetchAll();

      if (count($pedidos) !== count($idsPedido)) {
        throw new InvalidArgumentException('Uno o mas pedidos no existen.');
      }

      foreach ($pedidos as $pedido) {
        if ((string) ($pedido['estado'] ?? '') !== 'PENDIENTE_APROBACION') {
          throw new InvalidArgumentException('Solo se pueden unificar pedidos en estado PENDIENTE_APROBACION.');
        }
      }

      $sqlDetalle = "
                SELECT id_pedido, id_producto, cantidad
                FROM detalle_pedidos
                WHERE id_pedido IN ($placeholders)
                ORDER BY id_pedido, id_producto
            ";
      $stmtDetalle = $this->pdo->prepare($sqlDetalle);
      $stmtDetalle->execute($idsPedido);
      $detalles = $stmtDetalle->fetchAll();

      $productosAgrupados = [];
      foreach ($detalles as $detalle) {
        $idProducto = (int) $detalle['id_producto'];
        $cantidad = (int) $detalle['cantidad'];
        if (!isset($productosAgrupados[$idProducto])) {
          $productosAgrupados[$idProducto] = 0;
        }
        $productosAgrupados[$idProducto] += $cantidad;
      }

      $primerPedido = $pedidos[0];
      $observaciones = 'Unificación de pedidos: ' . implode(', ', array_map(static fn(int $id): string => (string) $id, $idsPedido));

      $sqlInsertPedido = "
                INSERT INTO pedidos (id_usuario_solicitante, id_oficina, observaciones, estado)
                VALUES (:id_usuario_solicitante, :id_oficina, :observaciones, 'PENDIENTE')
            ";
      $stmtInsertPedido = $this->pdo->prepare($sqlInsertPedido);
      $stmtInsertPedido->execute([
        ':id_usuario_solicitante' => (int) $primerPedido['id_usuario_solicitante'],
        ':id_oficina' => (int) $primerPedido['id_oficina'],
        ':observaciones' => $observaciones,
      ]);

      $idPedidoUnificado = (int) $this->pdo->lastInsertId();

      $sqlInsertDetalle = "
                INSERT INTO detalle_pedidos (id_pedido, id_producto, cantidad)
                VALUES (:id_pedido, :id_producto, :cantidad)
            ";
      $stmtInsertDetalle = $this->pdo->prepare($sqlInsertDetalle);

      foreach ($productosAgrupados as $idProducto => $cantidad) {
        $stmtInsertDetalle->execute([
          ':id_pedido' => $idPedidoUnificado,
          ':id_producto' => $idProducto,
          ':cantidad' => $cantidad,
        ]);
      }

      $sqlActualizarOriginales = "
                UPDATE pedidos
                SET estado = 'FUSIONADO'
                WHERE id_pedido IN ($placeholders)
            ";
      $stmtActualizarOriginales = $this->pdo->prepare($sqlActualizarOriginales);
      $stmtActualizarOriginales->execute($idsPedido);

      $this->pdo->commit();
      return $idPedidoUnificado;
    } catch (Throwable $e) {
      if ($this->pdo->inTransaction()) {
        $this->pdo->rollBack();
      }
      throw $e;
    }
  }

  public function marcarPedidoEntregado(int $idPedido): bool
  {
    $sql = "
            UPDATE pedidos
            SET estado = 'ENTREGADO'
            WHERE id_pedido = :id_pedido
              AND estado IN ('PENDIENTE_APROBACION', 'PENDIENTE')
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

  public function obtenerHistorialSolicitante(int $idUsuario, ?int $idOficina = null): array
  {
    $sql = "
      SELECT
        p.id_pedido,
        p.id_oficina,
        p.estado,
        p.observaciones,
        p.fecha_pedido,
        p.fecha_entrega,
        o.nombre AS nombre_oficina,
        d.id_producto,
        pr.sku,
        pr.nombre AS nombre_producto,
        d.cantidad
      FROM pedidos p
      INNER JOIN oficinas o ON o.id_oficina = p.id_oficina
      INNER JOIN detalle_pedidos d ON d.id_pedido = p.id_pedido
      INNER JOIN productos pr ON pr.id_producto = d.id_producto
      WHERE p.id_usuario_solicitante = :id_usuario
    ";

    $params = [':id_usuario' => $idUsuario];

    if ($idOficina !== null && $idOficina > 0) {
      $sql .= ' AND p.id_oficina = :id_oficina';
      $params[':id_oficina'] = $idOficina;
    }

    $sql .= ' ORDER BY p.fecha_pedido DESC, p.id_pedido DESC, d.id_detalle ASC';

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    $pedidos = [];

    foreach ($rows as $row) {
      $idPedido = (int) $row['id_pedido'];

      if (!isset($pedidos[$idPedido])) {
        $pedidos[$idPedido] = [
          'id_pedido' => $idPedido,
          'id_oficina' => (int) $row['id_oficina'],
          'nombre_oficina' => (string) $row['nombre_oficina'],
          'estado' => (string) $row['estado'],
          'observaciones' => (string) ($row['observaciones'] ?? ''),
          'fecha_pedido' => (string) $row['fecha_pedido'],
          'fecha_entrega' => $row['fecha_entrega'] !== null ? (string) $row['fecha_entrega'] : null,
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
}
