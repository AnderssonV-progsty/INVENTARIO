<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/PedidoController.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
  http_response_code(200);
  exit;
}

if ($method !== 'GET') {
  http_response_code(405);
  echo json_encode([
    'ok' => false,
    'mensaje' => 'Metodo no permitido. Use GET.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $controller = new PedidoController();
  $controller->pendientes();
} catch (Throwable $e) {
  error_log('Error API pedidos_pendientes: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'mensaje' => 'Error de conexion o inicializacion del servidor.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
