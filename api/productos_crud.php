<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/ProductoController.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$resource = strtolower((string) ($_GET['resource'] ?? 'productos'));

if ($method === 'OPTIONS') {
  http_response_code(200);
  exit;
}

try {
  $controller = new ProductoController();
  $action = null;

  switch ($resource) {
    case 'areas':
      $action = match ($method) {
        'GET' => 'listarAreas',
        'POST' => 'crearArea',
        'PUT' => 'actualizarArea',
        'DELETE' => 'eliminarArea',
        default => null,
      };
      break;

    case 'usuarios':
      $action = match ($method) {
        'GET' => 'listarUsuarios',
        'POST' => 'crearUsuario',
        'PUT' => 'actualizarUsuario',
        'DELETE' => 'eliminarUsuario',
        default => null,
      };
      break;

    case 'productos':
    default:
      $action = match ($method) {
        'GET' => 'inventario',
        'POST' => 'crearDesdeJson',
        'PUT' => 'actualizarDesdeJson',
        'PATCH' => 'reactivarDesdeJson',
        'DELETE' => 'eliminarDesdeJson',
        default => null,
      };
      break;
  }

  if ($action === null) {
    http_response_code(405);
    echo json_encode([
      'ok' => false,
      'mensaje' => 'Metodo no permitido para la ruta solicitada.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  if (!method_exists($controller, $action)) {
    http_response_code(500);
    echo json_encode([
      'ok' => false,
      'mensaje' => 'El metodo solicitado no existe en el controlador.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }

  $controller->$action();
} catch (Throwable $e) {
  error_log('Error API productos_crud: ' . $e->getMessage());
  http_response_code(500);
  echo json_encode([
    'ok' => false,
    'mensaje' => 'Error de conexion o inicializacion del servidor.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
