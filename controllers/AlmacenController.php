<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/PedidoAlmacenModel.php';

final class AlmacenController
{
  private $model;

  public function __construct()
  {
    $this->model = new PedidoAlmacenModel();
  }

  public function listarPendientesDespacho(): array
  {
    return $this->model->obtenerPedidosListosDespacho();
  }

  public function despacharDesdeSesion(int $idUsuario, array $payload): void
  {
    $idPedido = (int) ($payload['id_pedido'] ?? 0);
    if ($idPedido <= 0) {
      throw new InvalidArgumentException('id_pedido es obligatorio.');
    }

    $this->model->despacharPedido($idPedido, $idUsuario);
  }
}
