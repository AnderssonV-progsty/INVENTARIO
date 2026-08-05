<?php

declare(strict_types=1);

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
  echo json_encode([
    'ok' => false,
    'mensaje' => 'Metodo no permitido.',
  ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();
  setcookie(
    session_name(),
    '',
    time() - 42000,
    $params['path'],
    $params['domain'] ?? '',
    (bool) $params['secure'],
    (bool) $params['httponly']
  );
}

session_destroy();

http_response_code(200);
echo json_encode([
  'ok' => true,
  'mensaje' => 'Sesion cerrada correctamente.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
