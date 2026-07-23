<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/PedidoController.php';

// CORS: permitir llamadas desde el frontend en Vercel
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder preflight
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
  http_response_code(200);
  exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
  http_response_code(405);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => false,
    'mensaje' => 'Metodo no permitido. Use POST.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $controller = new PedidoController();
  $controller->guardarDesdeCarrito();
} catch (Throwable $e) {
  error_log('Error API guardar_pedido: ' . $e->getMessage());
  http_response_code(500);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => false,
    'mensaje' => 'Error de conexion o inicializacion del servidor.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
