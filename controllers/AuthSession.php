<?php

declare(strict_types=1);

final class AuthSession
{
  public static function start(): void
  {
    if (session_status() !== PHP_SESSION_ACTIVE) {
      session_start();
    }
  }

  public static function setUser(array $user): void
  {
    self::start();
    $_SESSION['auth_user'] = [
      'id_usuario' => (int) ($user['id_usuario'] ?? 0),
      'rol' => (string) ($user['rol'] ?? ''),
      'id_area' => isset($user['id_area']) ? (int) $user['id_area'] : null,
      'id_oficina' => isset($user['id_oficina']) ? (int) $user['id_oficina'] : null,
      'nombre_completo' => (string) ($user['nombre_completo'] ?? ''),
      'username' => (string) ($user['username'] ?? ''),
    ];
  }

  public static function clear(): void
  {
    self::start();
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
      $params = session_get_cookie_params();
      setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
  }

  public static function getUser(): ?array
  {
    self::start();
    $user = $_SESSION['auth_user'] ?? null;
    if (!is_array($user)) {
      return null;
    }

    $idUsuario = (int) ($user['id_usuario'] ?? 0);
    $rol = (string) ($user['rol'] ?? '');
    if ($idUsuario <= 0 || $rol === '') {
      return null;
    }

    return $user;
  }

  public static function requireUser(): array
  {
    $user = self::getUser();
    if ($user === null) {
      self::abortJson(401, 'Sesion no iniciada.');
    }

    return $user;
  }

  public static function requireRoles(array $roles): array
  {
    $user = self::requireUser();
    if (!in_array((string) $user['rol'], $roles, true)) {
      self::abortJson(403, 'No tienes permisos para esta accion.');
    }

    return $user;
  }

  private static function abortJson(int $statusCode, string $mensaje): never
  {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok' => false,
      'mensaje' => $mensaje,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}
