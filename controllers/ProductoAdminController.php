<?php

declare(strict_types=1);

require_once __DIR__ . '/../models/ProductoAdminModel.php';

final class ProductoAdminController
{
  private ProductoAdminModel $model;

  public function __construct()
  {
    $this->model = new ProductoAdminModel();
  }

  public function listar(): array
  {
    return [
      'productos' => $this->model->listarProductos(),
      'areas' => $this->model->obtenerAreasActivas(),
    ];
  }

  public function crear(array $payload): int
  {
    $data = $this->normalizarPayload($payload, false);
    return $this->model->crearProducto($data);
  }

  public function actualizar(array $payload): void
  {
    $idProducto = (int) ($payload['id_producto'] ?? 0);
    if ($idProducto <= 0) {
      throw new InvalidArgumentException('id_producto es obligatorio para actualizar.');
    }

    $data = $this->normalizarPayload($payload, true);
    $this->model->actualizarProducto($idProducto, $data);
  }

  public function eliminar(array $payload): void
  {
    $idProducto = (int) ($payload['id_producto'] ?? 0);
    if ($idProducto <= 0) {
      throw new InvalidArgumentException('id_producto es obligatorio para eliminar.');
    }

    $this->model->eliminarProducto($idProducto);
  }

  private function normalizarPayload(array $payload, bool $forUpdate): array
  {
    $sku = trim((string) ($payload['sku'] ?? ''));
    $nombre = trim((string) ($payload['nombre'] ?? ''));
    $descripcion = trim((string) ($payload['descripcion'] ?? ''));
    $unidadMedida = trim((string) ($payload['unidad_medida'] ?? 'UND'));
    $stockActual = (int) ($payload['stock_actual'] ?? 0);
    $stockMinimo = (int) ($payload['stock_minimo'] ?? 0);
    $activo = isset($payload['activo']) ? (int) ((bool) $payload['activo']) : 1;
    $areasIdsRaw = $payload['areas_ids'] ?? [];

    if ($sku === '' || $nombre === '') {
      throw new InvalidArgumentException('SKU y nombre son obligatorios.');
    }

    if ($stockActual < 0 || $stockMinimo < 0) {
      throw new InvalidArgumentException('Los valores de stock no pueden ser negativos.');
    }

    if (!is_array($areasIdsRaw)) {
      throw new InvalidArgumentException('areas_ids debe ser un arreglo.');
    }

    $areasIds = [];
    foreach ($areasIdsRaw as $idArea) {
      $value = (int) $idArea;
      if ($value > 0) {
        $areasIds[$value] = $value;
      }
    }

    if (!$forUpdate && $areasIds === []) {
      throw new InvalidArgumentException('Debe asignar al menos un area al producto.');
    }

    return [
      'sku' => $sku,
      'nombre' => $nombre,
      'descripcion' => $descripcion,
      'unidad_medida' => $unidadMedida !== '' ? $unidadMedida : 'UND',
      'stock_actual' => $stockActual,
      'stock_minimo' => $stockMinimo,
      'activo' => $activo === 1 ? 1 : 0,
      'areas_ids' => array_values($areasIds),
    ];
  }
}
