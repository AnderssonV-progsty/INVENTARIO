<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/ProductoModel.php';

final class ProductoController
{
  private ProductoModel $productoModel;

  public function __construct()
  {
    $this->productoModel = new ProductoModel();
  }

  public function catalogo(): void
  {
    try {
      $productos = $this->productoModel->obtenerCatalogo();

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Catalogo obtenido correctamente.',
        'data' => $productos,
      ]);
    } catch (Throwable $e) {
      error_log('Error catalogo: ' . $e->getMessage());

      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible obtener el catalogo.',
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
