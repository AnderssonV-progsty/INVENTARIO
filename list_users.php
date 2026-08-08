<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/db.php';

$stmt = $pdo->query('SELECT username, password_hash, rol, activo FROM usuarios');
$users = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>📋 Lista de Usuarios</title>
    <style>
        body {font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #f0f4ff, #e0eaff); margin:0; padding:0; display:flex; flex-direction:column; min-height:100vh;}
        header {background: rgba(255,255,255,0.6); backdrop-filter:blur(8px); padding:1rem; text-align:center; position:relative;}
        .logout-btn {position:absolute; right:20px; top:20px; text-decoration:none; color:white; background:#d32f2f; padding:8px 16px; border-radius:4px; font-size:0.9rem;}
        h1 {margin:0; font-size:2rem; color:#333;}
        main {flex:1; padding:2rem;}
        .styled-table {border-collapse:collapse; margin:0 auto; font-size:0.9rem; min-width:600px; box-shadow:0 0 10px rgba(0,0,0,0.1); background:#fff;}
        .styled-table thead tr {background:#009879; color:#ffffff; text-align:left;}
        .styled-table th, .styled-table td {padding:12px 15px;}
        .styled-table tbody tr {border-bottom:1px solid #dddddd;}
        .styled-table tbody tr:nth-of-type(even) {background:#f3f3f3;}
        .styled-table tbody tr:hover {background:#f1f1f1;}
        footer {text-align:center; padding:1rem; background:rgba(255,255,255,0.6); backdrop-filter:blur(8px);}
    </style>
</head>
<body>
    <header>
        <h1>📋 Lista de Usuarios</h1>
        <a href="logout.php" class="logout-btn">Cerrar Sesión (<?= htmlspecialchars($_SESSION['username']) ?>)</a>
    </header>
    <main>
        <?php if (empty($users)): ?>
            <p>No hay usuarios registrados.</p>
        <?php else: ?>
            <table class="styled-table">
                <thead>
                    <tr>
                        <th>Usuario</th>
                        <th>Hash de Contraseña</th>
                        <th>Rol</th>
                        <th>Activo</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td><?= htmlspecialchars($u['username']) ?></td>
                            <td><code style="font-size:0.8em; color:#555;"><?= htmlspecialchars($u['password_hash']) ?></code></td>
                            <td><?= htmlspecialchars($u['rol']) ?></td>
                            <td><?= $u['activo'] ? 'Sí' : 'No' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
    <footer>© <?= date('Y') ?> Inventario Papelería</footer>
</body>
</html>
