<?php

declare(strict_types=1);

require_once 'auth.php';
require_once __DIR__ . '/../controllers/ProductoAdminController.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method === 'OPTIONS') {
  http_response_code(200);
  exit;
}

if ((string) ($_SESSION['rol'] ?? '') !== 'almacenista') {
  http_response_code(403);
  echo json_encode(['ok' => false, 'mensaje' => 'Acceso denegado.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

$controller = new ProductoAdminController();

try {
  if ($method === 'GET') {
    $data = $controller->listar();
    http_response_code(200);
    echo json_encode([
      'ok' => true,
      'mensaje' => 'Catalogos administrativos cargados correctamente.',
      'data' => $data,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $payload = json_decode(file_get_contents('php://input') ?: '', true);
  if (!is_array($payload)) {
    http_response_code(400);
    echo json_encode(['ok' => false, 'mensaje' => 'JSON invalido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($method === 'POST') {
    $idProducto = $controller->crear($payload);
    http_response_code(201);
    echo json_encode([
      'ok' => true,
      'mensaje' => 'Producto creado correctamente.',
      'data' => ['id_producto' => $idProducto],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($method === 'PUT') {
    $controller->actualizar($payload);
    http_response_code(200);
    echo json_encode([
      'ok' => true,
      'mensaje' => 'Producto actualizado correctamente.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  if ($method === 'DELETE') {
    $controller->eliminar($payload);
    http_response_code(200);
    echo json_encode([
      'ok' => true,
      'mensaje' => 'Producto eliminado correctamente.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  http_response_code(405);
  echo json_encode(['ok' => false, 'mensaje' => 'Metodo no permitido.'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
  http_response_code(400);
  echo json_encode([
    'ok' => false,
    'mensaje' => $e->getMessage(),
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
