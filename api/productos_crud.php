<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/ProductoController.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
  http_response_code(200);
  exit;
}

try {
  $controller = new ProductoController();

  switch ($method) {
    case 'GET':
      $controller->inventario();
      break;
    case 'POST':
      $controller->crearDesdeJson();
      break;
    case 'PUT':
      $controller->actualizarDesdeJson();
      break;
    case 'PATCH':
      $controller->reactivarDesdeJson();
      break;
    case 'DELETE':
      $controller->eliminarDesdeJson();
      break;
    default:
      http_response_code(405);
      echo json_encode([
        'ok' => false,
        'mensaje' => 'Metodo no permitido. Use GET, POST, PUT, PATCH o DELETE.',
      ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
      break;
  }
} catch (Throwable $e) {
  error_log('Error API productos_crud: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'mensaje' => 'Error de conexion o inicializacion del servidor.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
