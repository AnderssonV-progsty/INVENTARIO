<?php
session_start();
require_once __DIR__ . '/db.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    if ($username && $password) {
        $stmt = $pdo->prepare('SELECT id_usuario, username, password_hash, rol, activo FROM usuarios WHERE username = :u');
        $stmt->execute(['u' => $username]);
        $user = $stmt->fetch();
        if ($user && $user['activo'] && password_verify($password, $user['password_hash'])) {
            // Authenticated
            $_SESSION['user_id'] = $user['id_usuario'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['rol'] = $user['rol'];
            header('Location: list_users.php');
            exit;
        } else {
            $error = 'Usuario o contraseña incorrectos.';
        }
    } else {
        $error = 'Ingrese usuario y contraseña.';
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>🔐 Iniciar Sesión - Inventario Papelería</title>
    <meta name="description" content="Página de login para acceder al sistema de inventario de papelería.">
    <style>
        body {font-family: 'Inter', sans-serif; background: linear-gradient(135deg, #e0f7fa, #e0e0f7); display:flex; justify-content:center; align-items:center; height:100vh; margin:0;}
        .login-box {background:#fff; padding:2rem; border-radius:8px; box-shadow:0 4px 12px rgba(0,0,0,0.1); width:320px;}
        h2 {margin-top:0; text-align:center; color:#333;}
        input {width:100%; padding:0.5rem; margin:0.5rem 0; border:1px solid #ccc; border-radius:4px; box-sizing: border-box;}
        button {width:100%; padding:0.6rem; background:#0066cc; color:#fff; border:none; border-radius:4px; cursor:pointer;}
        button:hover {background:#005bb5;}
        .error {color:#c00; margin-bottom:0.5rem; text-align:center;}
    </style>
</head>
<body>
    <div class="login-box">
        <h2>Iniciar Sesión</h2>
        <?php if ($error): ?><p class="error"><?= htmlspecialchars($error) ?></p><?php endif; ?>
        <form method="POST" action="">
            <input type="text" name="username" placeholder="Usuario" required>
            <input type="password" name="password" placeholder="Contraseña" required>
            <button type="submit">Entrar</button>
        </form>
    </div>
</body>
</html>
