<?php

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';

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

function responder(int $statusCode, array $payload): void
{
  http_response_code($statusCode);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function urlPorRol(string $rol): string
{
  return match (strtolower(trim($rol))) {
    'secretario' => 'secretario.html',
    'director_general' => 'director.html',
    'almacenista' => 'almacenista.html',
    default => 'login.html',
  };
}

try {
  $rawBody = file_get_contents('php://input') ?: '';
  $payload = json_decode($rawBody, true);

  if (!is_array($payload)) {
    responder(400, [
      'ok' => false,
      'mensaje' => 'JSON invalido.',
    ]);
    exit;
  }

  $username = trim((string) ($payload['username'] ?? ''));
  $password = (string) ($payload['password'] ?? '');

  if ($username === '' || $password === '') {
    responder(422, [
      'ok' => false,
      'mensaje' => 'Usuario y clave son obligatorios.',
    ]);
    exit;
  }

  $pdo = Conexion::getInstancia();
  $stmt = $pdo->prepare(
    "
      SELECT id_usuario, id_area, username, password_hash, rol, activo
      FROM usuarios
      WHERE username = :username
      LIMIT 1
    "
  );
  $stmt->execute([':username' => $username]);
  $usuario = $stmt->fetch();

  if ($usuario === false || (int) ($usuario['activo'] ?? 0) !== 1) {
    responder(401, [
      'ok' => false,
      'mensaje' => 'Credenciales invalidas.',
    ]);
    exit;
  }

  $hash = (string) ($usuario['password_hash'] ?? '');
  if (!password_verify($password, $hash)) {
    responder(401, [
      'ok' => false,
      'mensaje' => 'Credenciales invalidas.',
    ]);
    exit;
  }

  $rol = strtolower(trim((string) ($usuario['rol'] ?? '')));
  if (!in_array($rol, ['secretario', 'director_general', 'almacenista'], true)) {
    responder(403, [
      'ok' => false,
      'mensaje' => 'Rol no autorizado.',
    ]);
    exit;
  }

  session_regenerate_id(true);
  $_SESSION['id_usuario'] = (int) $usuario['id_usuario'];
  $_SESSION['rol'] = $rol;
  $_SESSION['id_area'] = isset($usuario['id_area']) ? (int) $usuario['id_area'] : null;

  $redirectUrl = urlPorRol($rol);

  responder(200, [
    'ok' => true,
    'mensaje' => 'Inicio de sesion exitoso.',
    'data' => [
      'usuario' => [
        'id_usuario' => (int) $usuario['id_usuario'],
        'rol' => $rol,
        'id_area' => isset($usuario['id_area']) ? (int) $usuario['id_area'] : null,
        'username' => (string) $usuario['username'],
      ],
      'redirect_url' => $redirectUrl,
    ],
  ]);
} catch (Throwable $e) {
  error_log('Error API login: ' . $e->getMessage());
  responder(500, [
    'ok' => false,
    'mensaje' => 'Error interno del servidor.',
  ]);
}
