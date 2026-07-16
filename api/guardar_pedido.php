<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/PedidoController.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'POST') !== 'POST') {
  http_response_code(405);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => false,
    'mensaje' => 'Metodo no permitido. Use POST.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

$controller = new PedidoController();
$controller->guardarDesdeCarrito();
