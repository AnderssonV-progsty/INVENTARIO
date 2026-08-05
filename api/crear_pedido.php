<?php

declare(strict_types=1);

require_once 'auth.php';
require_once __DIR__ . '/../controllers/SecretarioController.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
  http_response_code(200);
  exit;
}

if ($method !== 'POST') {
  http_response_code(405);
  echo json_encode(['ok' => false, 'mensaje' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if ((string) ($_SESSION['rol'] ?? '') !== 'secretario') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

$idUsuario = (int) ($_SESSION['id_usuario'] ?? 0);
$idArea = (int) ($_SESSION['id_area'] ?? 0);

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
  http_response_code(400);
  echo json_encode(['ok' => false, 'mensaje' => 'JSON invalido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

try {
  $controller = new SecretarioController();
  $idPedido = $controller->crearPedidoDesdeSesion($idArea, $idUsuario, $payload);

  http_response_code(201);
  echo json_encode([
    'ok' => true,
    'mensaje' => 'Pedido creado correctamente en estado PENDIENTE_DIRECTOR.',
    'data' => [
      'id_pedido' => $idPedido,
      'estado' => 'PENDIENTE_DIRECTOR',
    ],
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'mensaje' => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
