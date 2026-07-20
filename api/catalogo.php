<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/ProductoController.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET') {
  http_response_code(405);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => false,
    'mensaje' => 'Metodo no permitido. Use GET.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $controller = new ProductoController();
  $controller->catalogo();
} catch (Throwable $e) {
  error_log('Error API catalogo: ' . $e->getMessage());
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => false,
    'mensaje' => 'Error de conexion o inicializacion del servidor.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
