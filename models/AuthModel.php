<?php

declare(strict_types=1);

require_once __DIR__ . '/../conexion.php';

final class AuthModel
{
  private PDO $pdo;

  public function __construct()
  {
    $this->pdo = Conexion::getInstancia();
  }

  public function buscarUsuarioPorUsername(string $username): ?array
  {
    $sql = "
      SELECT
        id_usuario,
        id_oficina,
        id_area,
        username,
        password_hash,
        nombre_completo,
        rol,
        activo
      FROM usuarios
      WHERE username = :username
      LIMIT 1
    ";

    $stmt = $this->pdo->prepare($sql);
    $stmt->execute([':username' => $username]);
    $usuario = $stmt->fetch();

    return $usuario === false ? null : $usuario;
  }

  public function validarCredenciales(string $username, string $password): ?array
  {
    $usuario = $this->buscarUsuarioPorUsername($username);
    if ($usuario === null) {
      return null;
    }

    if ((int) ($usuario['activo'] ?? 0) !== 1) {
      return null;
    }

    $hash = (string) ($usuario['password_hash'] ?? '');
    $esHashModerno = preg_match('/^\$2y\$|^\$argon2id\$|^\$argon2i\$/', $hash) === 1;
    $passwordValido = $esHashModerno
      ? password_verify($password, $hash)
      : hash_equals($hash, $password);

    if (!$passwordValido) {
      return null;
    }

    return [
      'id_usuario' => (int) $usuario['id_usuario'],
      'id_oficina' => isset($usuario['id_oficina']) ? (int) $usuario['id_oficina'] : null,
      'id_area' => isset($usuario['id_area']) ? (int) $usuario['id_area'] : null,
      'username' => (string) $usuario['username'],
      'nombre_completo' => (string) $usuario['nombre_completo'],
      'rol' => (string) $usuario['rol'],
    ];
  }
}
