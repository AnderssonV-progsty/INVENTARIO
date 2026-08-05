<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/CatalogoAreaModel.php';
require_once __DIR__ . '/../models/PedidoSecretarioModel.php';

final class SecretarioController
{
  private $catalogoModel;
  private $pedidoModel;

  public function __construct()
  {
    $this->catalogoModel = new CatalogoAreaModel();
    $this->pedidoModel = new PedidoSecretarioModel();
  }

  public function obtenerCatalogoArea(int $idArea): array
  {
    if ($idArea <= 0) {
      throw new InvalidArgumentException('El usuario no tiene area asignada.');
    }

    return $this->catalogoModel->obtenerCatalogoPorArea($idArea);
  }

  public function crearPedidoDesdeSesion(int $idArea, int $idUsuario, array $payload): int
  {
    $carrito = $payload['carrito'] ?? [];
    if (!is_array($carrito)) {
      throw new InvalidArgumentException('El carrito es invalido.');
    }

    $observaciones = trim((string) ($payload['observaciones'] ?? ''));

    return $this->pedidoModel->crearPedido($idArea, $idUsuario, $carrito, $observaciones);
  }
}
