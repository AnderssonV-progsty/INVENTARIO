<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/PedidoModel.php';

final class PedidoController
{
  private PedidoModel $pedidoModel;

  public function __construct()
  {
    $this->pedidoModel = new PedidoModel();
  }

  public function guardarDesdeCarrito(): void
  {
    try {
      $input = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);

      $idUsuario = isset($input['id_usuario']) ? (int) $input['id_usuario'] : 0;
      $idOficina = isset($input['id_oficina']) ? (int) $input['id_oficina'] : 0;
      $items = $input['items'] ?? [];
      $observaciones = isset($input['observaciones']) ? trim((string) $input['observaciones']) : null;

      if ($idUsuario <= 0 || $idOficina <= 0) {
        throw new InvalidArgumentException('id_usuario e id_oficina son obligatorios.');
      }

      if (!is_array($items) || $items === []) {
        throw new InvalidArgumentException('El campo items es obligatorio y debe tener elementos.');
      }

      $idPedido = $this->pedidoModel->crearPedidoDesdeCarrito(
        $idUsuario,
        $idOficina,
        $items,
        $observaciones
      );

      $this->responderJson(201, [
        'ok' => true,
        'mensaje' => 'Pedido guardado correctamente.',
        'data' => [
          'id_pedido' => $idPedido,
          'estado' => 'PENDIENTE',
        ],
      ]);
    } catch (JsonException $e) {
      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => 'JSON invalido en la solicitud.',
      ]);
    } catch (InvalidArgumentException $e) {
      $this->responderJson(422, [
        'ok' => false,
        'mensaje' => $e->getMessage(),
      ]);
    } catch (PDOException $e) {
      error_log('Error BD al guardar pedido: ' . $e->getMessage());

      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => 'No fue posible guardar el pedido: ' . $e->getMessage(),
      ]);
    } catch (Throwable $e) {
      error_log('Error inesperado al guardar pedido: ' . $e->getMessage());

      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'Error interno del servidor.',
      ]);
    }
  }

  public function pendientes(): void
  {
    try {
      $pedidos = $this->pedidoModel->obtenerPedidosPendientesConDetalle();

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Pedidos pendientes obtenidos correctamente.',
        'data' => $pedidos,
      ]);
    } catch (Throwable $e) {
      error_log('Error al consultar pedidos pendientes: ' . $e->getMessage());

      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible consultar los pedidos pendientes.',
      ]);
    }
  }

  public function entregarDesdeJson(): void
  {
    try {
      $input = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);
      $idPedido = isset($input['id_pedido']) ? (int) $input['id_pedido'] : 0;

      if ($idPedido <= 0) {
        throw new InvalidArgumentException('id_pedido es obligatorio.');
      }

      $actualizado = $this->pedidoModel->marcarPedidoEntregado($idPedido);

      if (!$actualizado) {
        if (!$this->pedidoModel->existePedido($idPedido)) {
          $this->responderJson(404, [
            'ok' => false,
            'mensaje' => 'El pedido no existe.',
          ]);
          return;
        }

        $this->responderJson(409, [
          'ok' => false,
          'mensaje' => 'El pedido no esta en estado PENDIENTE.',
        ]);
        return;
      }

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Pedido despachado correctamente.',
        'data' => [
          'id_pedido' => $idPedido,
          'estado' => 'ENTREGADO',
        ],
      ]);
    } catch (JsonException $e) {
      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => 'JSON invalido en la solicitud.',
      ]);
    } catch (InvalidArgumentException $e) {
      $this->responderJson(422, [
        'ok' => false,
        'mensaje' => $e->getMessage(),
      ]);
    } catch (PDOException $e) {
      error_log('Error BD al despachar pedido: ' . $e->getMessage());

      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => 'No fue posible despachar el pedido: ' . $e->getMessage(),
      ]);
    } catch (Throwable $e) {
      error_log('Error inesperado al despachar pedido: ' . $e->getMessage());

      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'Error interno del servidor.',
      ]);
    }
  }

  private function responderJson(int $statusCode, array $payload): void
  {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
}
