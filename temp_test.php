<?php
require 'conexion.php';
require 'models/ProductoModel.php';
try {
  $model = new ProductoModel();
  $id = $model->crearUsuario('test_user', 'Usuario Prueba', 'prueba@local', 'inventarista', 1);
  echo "ID=$id\n";
} catch (Throwable $e) {
  echo get_class($e) . PHP_EOL;
  echo $e->getMessage() . PHP_EOL;
  if ($e instanceof PDOException) {
    print_r($e->errorInfo);
  }
}
