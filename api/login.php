<?php

declare(strict_types=1);

require_once __DIR__ . '/../controllers/AuthSession.php';
require_once __DIR__ . '/../models/AuthModel.php';

header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'OPTIONS') {
  http_response_code(200);
  exit;
}

AuthSession::start();

function responder(int $statusCode, array $payload): void
{
  http_response_code($statusCode);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

function vistaPorRol(string $rol): string
{
  $rolNormalizado = strtolower(trim($rol));

  return match ($rolNormalizado) {
    'inventarista' => 'inventarista.html',
    'director', 'directivo' => 'directivos.html',
    'operario' => 'operario.html',
    'paqueteria' => 'paqueteria.html',
    default => 'catalogo.html',
  };
}

try {
  if ($method === 'GET') {
    $user = AuthSession::getUser();
    if ($user === null) {
      responder(401, [
        'ok' => false,
        'mensaje' => 'No hay sesion activa.',
      ]);
      exit;
    }

    responder(200, [
      'ok' => true,
      'mensaje' => 'Sesion activa.',
      'data' => [
        'usuario' => $user,
        'vista_recomendada' => vistaPorRol((string) $user['rol']),
      ],
    ]);
    exit;
  }

  if ($method === 'DELETE') {
    AuthSession::clear();
    responder(200, [
      'ok' => true,
      'mensaje' => 'Sesion cerrada correctamente.',
    ]);
    exit;
  }

  if ($method !== 'POST') {
    responder(405, [
      'ok' => false,
      'mensaje' => 'Metodo no permitido.',
    ]);
    exit;
  }

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

  $authModel = new AuthModel();
  $user = $authModel->validarCredenciales($username, $password);

  if ($user === null) {
    responder(401, [
      'ok' => false,
      'mensaje' => 'Credenciales invalidas.',
    ]);
    exit;
  }

  session_regenerate_id(true);
  AuthSession::setUser($user);

  responder(200, [
    'ok' => true,
    'mensaje' => 'Inicio de sesion exitoso.',
    'data' => [
      'usuario' => $user,
      'vista_recomendada' => vistaPorRol((string) $user['rol']),
    ],
  ]);
} catch (Throwable $e) {
  error_log('Error API login: ' . $e->getMessage());
  responder(500, [
    'ok' => false,
    'mensaje' => 'Error interno del servidor.',
  ]);
}
