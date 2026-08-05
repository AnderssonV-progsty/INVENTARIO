<?php

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';

final class PedidoSecretarioModel
{
  private PDO $pdo;

  public function __construct()
  {
    $this->pdo = Conexion::getInstancia();
  }

  public function crearPedido(int $idArea, int $idUsuarioSecretario, array $carrito, string $observaciones = ''): int
  {
    if ($idArea <= 0 || $idUsuarioSecretario <= 0) {
      throw new InvalidArgumentException('Sesion invalida para crear pedido.');
    }

    if ($carrito === []) {
      throw new InvalidArgumentException('El carrito no puede estar vacio.');
    }

    $this->pdo->beginTransaction();

    try {
      $stmtPedido = $this->pdo->prepare(
        "
          INSERT INTO pedidos (id_area, id_usuario_secretario, estado, observaciones)
          VALUES (:id_area, :id_usuario_secretario, 'PENDIENTE_DIRECTOR', :observaciones)
        "
      );

      $stmtPedido->execute([
        ':id_area' => $idArea,
        ':id_usuario_secretario' => $idUsuarioSecretario,
        ':observaciones' => $observaciones !== '' ? $observaciones : null,
      ]);

      $idPedido = (int) $this->pdo->lastInsertId();

      $stmtProducto = $this->pdo->prepare(
        "
          SELECT p.id_producto, p.stock_actual
          FROM producto_area pa
          INNER JOIN productos p ON p.id_producto = pa.id_producto
          WHERE pa.id_area = :id_area
            AND pa.id_producto = :id_producto
            AND pa.activo = 1
            AND p.activo = 1
          LIMIT 1
          FOR UPDATE
        "
      );

      $stmtDetalle = $this->pdo->prepare(
        "
          INSERT INTO detalle_pedidos (id_pedido, id_producto, cantidad)
          VALUES (:id_pedido, :id_producto, :cantidad)
        "
      );

      foreach ($carrito as $item) {
        $idProducto = (int) ($item['id_producto'] ?? 0);
        $cantidad = (int) ($item['cantidad'] ?? 0);

        if ($idProducto <= 0 || $cantidad <= 0) {
          throw new InvalidArgumentException('El carrito contiene productos o cantidades invalidas.');
        }

        $stmtProducto->execute([
          ':id_area' => $idArea,
          ':id_producto' => $idProducto,
        ]);
        $producto = $stmtProducto->fetch();

        if ($producto === false) {
          throw new RuntimeException('Producto no permitido para el area del secretario.');
        }

        if ($cantidad > (int) ($producto['stock_actual'] ?? 0)) {
          throw new RuntimeException('Cantidad solicitada supera el stock disponible.');
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
