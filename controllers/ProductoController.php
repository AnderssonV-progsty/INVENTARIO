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
      $productos = $this->productoModel->obtenerCatalogo();

      $this->responderJson(200, [
        'ok' => true,
        'mensaje' => 'Catalogo obtenido correctamente.',
        'data' => $productos,
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

      if ($sku === '' || $nombre === '') {
        $this->responderJson(400, [
          'ok' => false,
          'mensaje' => 'SKU y nombre son obligatorios.',
        ]);
        return;
      }

      $idProducto = $this->productoModel->crearProducto($sku, $nombre, $stockActual);
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

      $this->productoModel->actualizarProducto($idProducto, $sku, $nombre, $stockActual);
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

  private function manejarErrorPdo(PDOException $e, string $mensajeGeneral): void
  {
    error_log($mensajeGeneral . ' ' . $e->getMessage());

    if (($e->errorInfo[0] ?? '') === '23000') {
      $this->responderJson(409, [
        'ok' => false,
        'mensaje' => 'Ya existe un producto con ese SKU.',
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
