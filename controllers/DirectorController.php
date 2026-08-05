<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/PedidoDirectorModel.php';

final class DirectorController
{
  private PedidoDirectorModel $model;

  public function __construct()
  {
    $this->model = new PedidoDirectorModel();
  }

  public function obtenerPendientes(): array
  {
    return $this->model->obtenerPendientesDirector();
  }

  public function procesarPedido(int $idDirector, array $payload): void
  {
    $idPedido = (int) ($payload['id_pedido'] ?? 0);
    $accion = (string) ($payload['accion'] ?? '');
    $cantidades = $payload['cantidades'] ?? [];
    $motivoRechazo = trim((string) ($payload['motivo_rechazo'] ?? ''));

    if ($idPedido <= 0) {
      throw new InvalidArgumentException('id_pedido es obligatorio.');
    }

    if (!is_array($cantidades)) {
      throw new InvalidArgumentException('cantidades debe ser un arreglo.');
    }

    $this->model->procesarPedido(
      $idPedido,
      $accion,
      $cantidades,
      $idDirector,
      $motivoRechazo !== '' ? $motivoRechazo : null
    );
  }
}
