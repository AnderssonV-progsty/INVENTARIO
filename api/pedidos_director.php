<?php

declare(strict_types=1);

require_once 'auth.php';
require_once __DIR__ . '/../controllers/DirectorController.php';

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
  echo json_encode(['ok' => false, 'mensaje' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ((string) ($_SESSION['rol'] ?? '') !== 'director_general') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $controller = new DirectorController();
  $pedidos = $controller->obtenerPendientes();

  http_response_code(200);
  echo json_encode([
    'ok' => true,
    'mensaje' => 'Pedidos pendientes de director obtenidos correctamente.',
    'data' => $pedidos,
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'mensaje' => 'No fue posible cargar los pedidos.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
