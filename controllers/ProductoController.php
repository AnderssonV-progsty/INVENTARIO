<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/ProductoModel.php';

final class ProductoController
{
  private ProductoModel $productoModel;

  public function __construct()
  {
    $this->productoModel = new ProductoModel();
  }

  public function catalogo(): void
  {
    try {
      $idUsuario = $this->normalizarIdOpcional($_GET['id_usuario'] ?? null);
      $idArea = $this->normalizarIdOpcional($_GET['id_area'] ?? null);

      if ($idArea === null && $idUsuario !== null) {
        $usuario = $this->productoModel->obtenerUsuarioPorId($idUsuario);
        if ($usuario !== null) {
          $idArea = $this->normalizarIdOpcional($usuario['id_area'] ?? null);
        }
      }

      $productos = $idArea !== null
        ? $this->productoModel->obtenerCatalogoPorArea($idArea)
        : $this->productoModel->obtenerCatalogo();

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Catalogo obtenido correctamente.',
        'data' => $productos,
        'meta' => [
          'id_area' => $idArea,
          'id_usuario' => $idUsuario,
        ],
      ]);
    } catch (Throwable $e) {
      error_log('Error catalogo: ' . $e->getMessage());

      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible obtener el catalogo.',
      ]);
    }
  }

  public function inventario(): void
  {
    try {
      $productos = $this->productoModel->obtenerInventario();

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Inventario obtenido correctamente.',
        'data' => $productos,
      ]);
    } catch (Throwable $e) {
      error_log('Error inventario: ' . $e->getMessage());

      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible obtener el inventario.',
      ]);
    }
  }

  public function crearDesdeJson(): void
  {
    try {
      $payload = $this->leerJsonRequest();
      $sku = strtoupper(trim((string) ($payload['sku'] ?? '')));
      $nombre = trim((string) ($payload['nombre'] ?? ''));
      $stockActual = $this->validarStock($payload['stock_actual'] ?? null);
      $areas = $this->normalizarIds($payload['areas'] ?? []);

      if ($sku === '' || $nombre === '') {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'SKU y nombre son obligatorios.',
        ]);
        return;
      }

      $idProducto = $this->productoModel->crearProducto($sku, $nombre, $stockActual, $areas);
      $producto = $this->productoModel->obtenerPorId($idProducto);

      $this->responderJson(201, [
        'ok' => true,
        'mensaje' => 'Producto creado correctamente.',
        'data' => $producto,
      ]);
    } catch (InvalidArgumentException $e) {
      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => $e->getMessage(),
      ]);
    } catch (PDOException $e) {
      $this->manejarErrorPdo($e, 'Error al crear producto.');
    } catch (Throwable $e) {
      error_log('Error crear producto: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible crear el producto.',
      ]);
    }
  }

  public function actualizarDesdeJson(): void
  {
    try {
      $payload = $this->leerJsonRequest();
      $idProducto = (int) ($payload['id_producto'] ?? 0);
      $sku = strtoupper(trim((string) ($payload['sku'] ?? '')));
      $nombre = trim((string) ($payload['nombre'] ?? ''));
      $stockActual = $this->validarStock($payload['stock_actual'] ?? null);
      $areas = $this->normalizarIds($payload['areas'] ?? []);

      if ($idProducto <= 0) {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'id_producto es obligatorio y debe ser mayor a 0.',
        ]);
        return;
      }

      if ($sku === '' || $nombre === '') {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'SKU y nombre son obligatorios.',
        ]);
        return;
      }

      $productoActual = $this->productoModel->obtenerPorId($idProducto);
      if ($productoActual === null) {
        $this->responderJson(404, [
          'ok' => false,
          'mensaje' => 'Producto no encontrado.',
        ]);
        return;
      }

      $this->productoModel->actualizarProducto($idProducto, $sku, $nombre, $stockActual, $areas);
      $producto = $this->productoModel->obtenerPorId($idProducto);

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Producto actualizado correctamente.',
        'data' => $producto,
      ]);
    } catch (InvalidArgumentException $e) {
      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => $e->getMessage(),
      ]);
    } catch (PDOException $e) {
      $this->manejarErrorPdo($e, 'Error al actualizar producto.');
    } catch (Throwable $e) {
      error_log('Error actualizar producto: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible actualizar el producto.',
      ]);
    }
  }

  public function eliminarDesdeJson(): void
  {
    try {
      $payload = $this->leerJsonRequest();
      $idProducto = (int) ($payload['id_producto'] ?? 0);

      if ($idProducto <= 0) {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'id_producto es obligatorio y debe ser mayor a 0.',
        ]);
        return;
      }

      $productoActual = $this->productoModel->obtenerPorId($idProducto);
      if ($productoActual === null) {
        $this->responderJson(404, [
          'ok' => false,
          'mensaje' => 'Producto no encontrado.',
        ]);
        return;
      }

      if ((int) ($productoActual['activo'] ?? 0) !== 1) {
        $this->responderJson(409, [
          'ok' => false,
          'mensaje' => 'El producto ya se encuentra inactivo.',
        ]);
        return;
      }

      $this->productoModel->desactivarProducto($idProducto);

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Producto eliminado correctamente (baja logica).',
      ]);
    } catch (InvalidArgumentException $e) {
      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => $e->getMessage(),
      ]);
    } catch (Throwable $e) {
      error_log('Error eliminar producto: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible eliminar el producto.',
      ]);
    }
  }

  public function reactivarDesdeJson(): void
  {
    try {
      $payload = $this->leerJsonRequest();
      $idProducto = (int) ($payload['id_producto'] ?? 0);

      if ($idProducto <= 0) {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'id_producto es obligatorio y debe ser mayor a 0.',
        ]);
        return;
      }

      $productoActual = $this->productoModel->obtenerPorId($idProducto);
      if ($productoActual === null) {
        $this->responderJson(404, [
          'ok' => false,
          'mensaje' => 'Producto no encontrado.',
        ]);
        return;
      }

      if ((int) ($productoActual['activo'] ?? 0) !== 0) {
        $this->responderJson(409, [
          'ok' => false,
          'mensaje' => 'El producto ya se encuentra activo.',
        ]);
        return;
      }

      $this->productoModel->reactivarProducto($idProducto);

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Producto reactivado correctamente.',
      ]);
    } catch (InvalidArgumentException $e) {
      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => $e->getMessage(),
      ]);
    } catch (Throwable $e) {
      error_log('Error reactivar producto: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible reactivar el producto.',
      ]);
    }
  }

  public function listarAreas(): void
  {
    try {
      $areas = $this->productoModel->obtenerAreas();
      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Áreas obtenidas correctamente.',
        'data' => $areas,
      ]);
    } catch (Throwable $e) {
      error_log('Error listar áreas: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible obtener las áreas.',
      ]);
    }
  }

  public function crearArea(): void
  {
    try {
      $payload = $this->leerJsonRequest();
      $nombre = trim((string) ($payload['nombre'] ?? ''));
      $codigo = strtoupper(trim((string) ($payload['codigo'] ?? '')));
      $activa = (bool) ($payload['activa'] ?? true);

      if ($nombre === '' || $codigo === '') {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'Nombre y código son obligatorios.',
        ]);
        return;
      }

      $idArea = $this->productoModel->crearArea($nombre, $codigo, $activa);
      $area = $this->productoModel->obtenerAreaPorId($idArea);

      $this->responderJson(201, [
        'ok' => true,
        'mensaje' => 'Área creada correctamente.',
        'data' => $area,
      ]);
    } catch (InvalidArgumentException $e) {
      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => $e->getMessage(),
      ]);
    } catch (Throwable $e) {
      error_log('Error crear área: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible crear el área.',
      ]);
    }
  }

  public function actualizarArea(): void
  {
    try {
      $payload = $this->leerJsonRequest();
      $idArea = (int) ($payload['id_area'] ?? 0);
      $nombre = trim((string) ($payload['nombre'] ?? ''));
      $codigo = strtoupper(trim((string) ($payload['codigo'] ?? '')));
      $activa = (bool) ($payload['activa'] ?? true);

      if ($idArea <= 0) {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'id_area es obligatorio y debe ser mayor a 0.',
        ]);
        return;
      }

      if ($nombre === '' || $codigo === '') {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'Nombre y código son obligatorios.',
        ]);
        return;
      }

      $areaActual = $this->productoModel->obtenerAreaPorId($idArea);
      if ($areaActual === null) {
        $this->responderJson(404, [
          'ok' => false,
          'mensaje' => 'Área no encontrada.',
        ]);
        return;
      }

      $this->productoModel->actualizarArea($idArea, $nombre, $codigo, $activa);
      $area = $this->productoModel->obtenerAreaPorId($idArea);

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Área actualizada correctamente.',
        'data' => $area,
      ]);
    } catch (InvalidArgumentException $e) {
      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => $e->getMessage(),
      ]);
    } catch (Throwable $e) {
      error_log('Error actualizar área: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible actualizar el área.',
      ]);
    }
  }

  public function eliminarArea(): void
  {
    try {
      $payload = $this->leerJsonRequest();
      $idArea = (int) ($payload['id_area'] ?? 0);

      if ($idArea <= 0) {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'id_area es obligatorio y debe ser mayor a 0.',
        ]);
        return;
      }

      $areaActual = $this->productoModel->obtenerAreaPorId($idArea);
      if ($areaActual === null) {
        $this->responderJson(404, [
          'ok' => false,
          'mensaje' => 'Área no encontrada.',
        ]);
        return;
      }

      $this->productoModel->eliminarArea($idArea);

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Área eliminada correctamente.',
      ]);
    } catch (Throwable $e) {
      error_log('Error eliminar área: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible eliminar el área.',
      ]);
    }
  }

  public function listarUsuarios(): void
  {
    try {
      $usuarios = $this->productoModel->obtenerUsuarios();
      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Usuarios obtenidos correctamente.',
        'data' => $usuarios,
      ]);
    } catch (Throwable $e) {
      error_log('Error listar usuarios: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible obtener los usuarios.',
      ]);
    }
  }

  public function crearUsuario(): void
  {
    try {
      $payload = $this->leerJsonRequest();
      $username = trim((string) ($payload['username'] ?? ''));
      $nombreCompleto = trim((string) ($payload['nombre_completo'] ?? ''));
      $email = trim((string) ($payload['email'] ?? ''));
      $rol = strtolower(trim((string) ($payload['rol'] ?? '')));
      $idArea = $this->normalizarIdOpcional($payload['id_area'] ?? null);

      if ($username === '' || $nombreCompleto === '') {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'Usuario y nombre completo son obligatorios.',
        ]);
        return;
      }

      $rolesPermitidos = ['inventarista', 'director', 'operario', 'paqueteria'];
      if (!in_array($rol, $rolesPermitidos, true)) {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'Rol invalido. Use inventarista, director, operario o paqueteria.',
        ]);
        return;
      }

      $idUsuario = $this->productoModel->crearUsuario($username, $nombreCompleto, $email, $rol, $idArea);
      $usuario = $this->productoModel->obtenerUsuarioPorId($idUsuario);

      $this->responderJson(201, [
        'ok' => true,
        'mensaje' => 'Usuario creado correctamente.',
        'data' => $usuario,
      ]);
    } catch (InvalidArgumentException $e) {
      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => $e->getMessage(),
      ]);
    } catch (Throwable $e) {
      error_log('Error crear usuario: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible crear el usuario.',
      ]);
    }
  }

  public function actualizarUsuario(): void
  {
    try {
      $payload = $this->leerJsonRequest();
      $idUsuario = (int) ($payload['id_usuario'] ?? 0);
      $username = trim((string) ($payload['username'] ?? ''));
      $nombreCompleto = trim((string) ($payload['nombre_completo'] ?? ''));
      $email = trim((string) ($payload['email'] ?? ''));
      $rol = strtolower(trim((string) ($payload['rol'] ?? '')));
      $idArea = $this->normalizarIdOpcional($payload['id_area'] ?? null);

      if ($idUsuario <= 0) {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'id_usuario es obligatorio y debe ser mayor a 0.',
        ]);
        return;
      }

      if ($username === '' || $nombreCompleto === '') {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'Usuario y nombre completo son obligatorios.',
        ]);
        return;
      }

      $rolesPermitidos = ['inventarista', 'director', 'operario', 'paqueteria'];
      if (!in_array($rol, $rolesPermitidos, true)) {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'Rol invalido. Use inventarista, director, operario o paqueteria.',
        ]);
        return;
      }

      $usuarioActual = $this->productoModel->obtenerUsuarioPorId($idUsuario);
      if ($usuarioActual === null) {
        $this->responderJson(404, [
          'ok' => false,
          'mensaje' => 'Usuario no encontrado.',
        ]);
        return;
      }

      $this->productoModel->actualizarUsuario($idUsuario, $username, $nombreCompleto, $email, $rol, $idArea);
      $usuario = $this->productoModel->obtenerUsuarioPorId($idUsuario);

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Usuario actualizado correctamente.',
        'data' => $usuario,
      ]);
    } catch (InvalidArgumentException $e) {
      $this->responderJson(400, [
        'ok' => false,
        'mensaje' => $e->getMessage(),
      ]);
    } catch (Throwable $e) {
      error_log('Error actualizar usuario: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible actualizar el usuario.',
      ]);
    }
  }

  public function eliminarUsuario(): void
  {
    try {
      $payload = $this->leerJsonRequest();
      $idUsuario = (int) ($payload['id_usuario'] ?? 0);

      if ($idUsuario <= 0) {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'id_usuario es obligatorio y debe ser mayor a 0.',
        ]);
        return;
      }

      $usuarioActual = $this->productoModel->obtenerUsuarioPorId($idUsuario);
      if ($usuarioActual === null) {
        $this->responderJson(404, [
          'ok' => false,
          'mensaje' => 'Usuario no encontrado.',
        ]);
        return;
      }

      $this->productoModel->eliminarUsuario($idUsuario);

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Usuario eliminado correctamente.',
      ]);
    } catch (Throwable $e) {
      error_log('Error eliminar usuario: ' . $e->getMessage());
      $this->responderJson(500, [
        'ok' => false,
        'mensaje' => 'No fue posible eliminar el usuario.',
      ]);
    }
  }

  private function leerJsonRequest(): array
  {
    $rawBody = file_get_contents('php://input');
    if ($rawBody === false || trim($rawBody) === '') {
      throw new InvalidArgumentException('El cuerpo JSON es obligatorio.');
    }

    $payload = json_decode($rawBody, true);
    if (!is_array($payload)) {
      throw new InvalidArgumentException('JSON invalido.');
    }

    return $payload;
  }

  private function validarStock(mixed $stockActual): int
  {
    if (!is_numeric($stockActual)) {
      throw new InvalidArgumentException('stock_actual debe ser numerico.');
    }

    $stock = (int) $stockActual;
    if ($stock < 0) {
      throw new InvalidArgumentException('stock_actual no puede ser negativo.');
    }

    return $stock;
  }

  private function normalizarIds(mixed $valor): array
  {
    if (!is_array($valor)) {
      return [];
    }

    $ids = [];
    foreach ($valor as $id) {
      $idEntero = (int) $id;
      if ($idEntero > 0) {
        $ids[] = $idEntero;
      }
    }

    return array_values(array_unique($ids));
  }

  private function normalizarIdOpcional(mixed $valor): ?int
  {
    if ($valor === null || $valor === '') {
      return null;
    }

    $id = (int) $valor;
    return $id > 0 ? $id : null;
  }

  private function manejarErrorPdo(PDOException $e, string $mensajeGeneral): void
  {
    error_log($mensajeGeneral . ' ' . $e->getMessage());

    if (($e->errorInfo[0] ?? '') === '23000') {
      $this->responderJson(409, [
        'ok' => false,
        'mensaje' => 'Ya existe un registro con esos datos.',
      ]);
      return;
    }

    $this->responderJson(500, [
      'ok' => false,
      'mensaje' => $mensajeGeneral,
    ]);
  }

  private function responderJson(int $statusCode, array $payload): void
  {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  }
}
