<?php

declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

header('Content-Type: application/json; charset=utf-8');

if (!isset($_SESSION['id_usuario'], $_SESSION['rol'])) {
  http_response_code(401);
  echo json_encode([
    'ok' => false,
    'mensaje' => 'No autenticado.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

$authUser = [
  'id_usuario' => (int) $_SESSION['id_usuario'],
  'rol' => (string) $_SESSION['rol'],
  'id_area' => isset($_SESSION['id_area']) ? (int) $_SESSION['id_area'] : null,
];
