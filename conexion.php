<?php

declare(strict_types=1);

/**
 * Conexion PDO segura bajo patron Singleton.
 * Configura credenciales por variables de entorno:
 * DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS
 */
final class Conexion
{
  private static ?PDO $instancia = null;

  private function __construct() {}

  private function __clone() {}

  public function __wakeup(): void
  {
    throw new RuntimeException('No se permite deserializar la conexion Singleton.');
  }

  public static function getInstancia(): PDO
  {
    if (self::$instancia === null) {
      $host = getenv('DB_HOST') ?: '127.0.0.1';
      $port = getenv('DB_PORT') ?: '3306';
      $dbname = getenv('DB_NAME') ?: 'almacen_papeleria';
      $user = getenv('DB_USER') ?: 'root';
      $pass = getenv('DB_PASS') ?: '';

      $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";

      try {
        self::$instancia = new PDO($dsn, $user, $pass, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false,
        ]);
      } catch (PDOException $e) {
        error_log('Error de conexion PDO: ' . $e->getMessage());
        throw new RuntimeException('No fue posible conectar con la base de datos.');
      }
    }

    return self::$instancia;
  }
}

/*
Ejemplo de uso:

try {
    $pdo = Conexion::getInstancia();
    $stmt = $pdo->query('SELECT NOW() AS fecha_actual');
    $resultado = $stmt->fetch();
    print_r($resultado);
} catch (Throwable $e) {
    echo $e->getMessage();
}
*/
