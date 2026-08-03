<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/PedidoModel.php';
require_once __DIR__ . '/AuthSession.php';

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
          'estado' => 'PENDIENTE_APROBACION',
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
      $idOficina = null;
      if (isset($_GET['id_oficina']) && $_GET['id_oficina'] !== '') {
        $idOficina = (int) $_GET['id_oficina'];
      }

      $pedidos = $this->pedidoModel->obtenerPedidosPendientesConDetalle($idOficina);

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

  public function aprobarYUnificarDesdeJson(): void
  {
    try {
      $input = json_decode(file_get_contents('php://input') ?: '', true, 512, JSON_THROW_ON_ERROR);
      $idsPedido = $this->normalizarIdsPedido($input);

      if ($idsPedido === []) {
        throw new InvalidArgumentException('Debes seleccionar al menos un pedido.');
      }

      $idPedidoUnificado = $this->pedidoModel->aprobarYUnificar($idsPedido);

      $this->responderJson(201, [
        'ok' => true,
        'mensaje' => 'Pedidos aprobados y unificados correctamente.',
        'data' => [
          'id_pedido_unificado' => $idPedidoUnificado,
          'ids_origen' => $idsPedido,
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
      error_log('Error BD al aprobar y unificar pedidos: ' . $e->getMessage());

      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => 'No fue posible aprobar y unificar los pedidos: ' . $e->getMessage(),
      ]);
    } catch (Throwable $e) {
      error_log('Error inesperado al aprobar y unificar pedidos: ' . $e->getMessage());

      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'Error interno del servidor.',
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

  public function historialDesdeSesion(): void
  {
    try {
      $usuarioSesion = AuthSession::requireRoles(['director', 'operario']);
      $idUsuario = (int) ($usuarioSesion['id_usuario'] ?? 0);
      $idOficina = (int) (($usuarioSesion['id_area'] ?? $usuarioSesion['id_oficina']) ?? 0);

      $historial = $this->pedidoModel->obtenerHistorialSolicitante(
        $idUsuario,
        $idOficina > 0 ? $idOficina : null
      );

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Historial de pedidos obtenido correctamente.',
        'data' => $historial,
      ]);
    } catch (Throwable $e) {
      error_log('Error al consultar historial de pedidos: ' . $e->getMessage());

      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible consultar el historial de pedidos.',
      ]);
    }
  }

  private function responderJson(int $statusCode, array $payload): void
  {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }

  private function normalizarIdsPedido(mixed $input): array
  {
    if (!is_array($input)) {
      return [];
    }

    $candidatos = [];
    foreach (['id_pedido', 'id_pedidos', 'ids'] as $clave) {
      if (!isset($input[$clave])) {
        continue;
      }

      $valor = $input[$clave];
      if (is_int($valor) || is_string($valor) && trim((string) $valor) !== '') {
        $candidatos[] = $valor;
      } elseif (is_array($valor)) {
        $candidatos = array_merge($candidatos, $valor);
      }
    }

    $ids = [];
    foreach ($candidatos as $valor) {
      $id = (int) $valor;
      if ($id > 0) {
        $ids[] = $id;
      }
    }

    return array_values(array_unique($ids));
  }
}
