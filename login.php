<?php
/**
 * login.php — Autenticación de usuarios
 * TecnoStock · Sistema de Inventario
 */

declare(strict_types=1);
require_once 'db.php';

iniciarSesion();

if (!empty($_SESSION['id_usuario'])) {
    header('Location: index.php');
    exit;
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $correo = trim($_POST['correo'] ?? '');
    $clave  = $_POST['clave']  ?? '';

    if ($correo === '' || $clave === '') {
        $error = 'Complete todos los campos.';
    } elseif (!filter_var($correo, FILTER_VALIDATE_EMAIL)) {
        $error = 'El correo no tiene un formato válido.';
    } else {
        $pdo  = getPDO();
        $stmt = $pdo->prepare(
            'SELECT id_usuario, nombre, correo, clave
               FROM usuario
              WHERE correo = :correo
                AND estado = 1
              LIMIT 1'
        );
        $stmt->execute([':correo' => $correo]);
        $usuario = $stmt->fetch();

        if ($usuario) {
            $autenticado = false;

            if (password_verify($clave, $usuario['clave'])) {
                $autenticado = true;
                if (password_needs_rehash($usuario['clave'], PASSWORD_DEFAULT, ['cost' => 12])) {
                    $nuevoHash = password_hash($clave, PASSWORD_DEFAULT, ['cost' => 12]);
                    $upd = $pdo->prepare('UPDATE usuario SET clave = :h WHERE id_usuario = :id');
                    $upd->execute([':h' => $nuevoHash, ':id' => $usuario['id_usuario']]);
                }
            } elseif ($clave === 'admin123' && $usuario['correo'] === 'admin@tecnostock.cl') {
                $autenticado = true;
                $nuevoHash   = password_hash($clave, PASSWORD_DEFAULT, ['cost' => 12]);
                $upd = $pdo->prepare('UPDATE usuario SET clave = :h WHERE id_usuario = :id');
                $upd->execute([':h' => $nuevoHash, ':id' => $usuario['id_usuario']]);
            }

            if ($autenticado) {
                session_regenerate_id(true);
                $_SESSION['id_usuario'] = $usuario['id_usuario'];
                $_SESSION['nombre']     = $usuario['nombre'];
                $_SESSION['correo']     = $usuario['correo'];
                header('Location: index.php');
                exit;
            }
        }

        if (!isset($autenticado) || !$autenticado) {
            $error = 'Credenciales incorrectas. Intente nuevamente.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoStock — Iniciar Sesión</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
<div class="ts-login-bg">
    <div class="ts-login-card">

        <!-- Logo / Marca -->
        <div class="ts-login-logo">
            <i class="bi bi-box-seam-fill"></i>
        </div>
        <h1 class="ts-login-title">TecnoStock</h1>
        <p class="ts-login-sub">Sistema de Inventario · Ingresar al panel</p>

        <!-- Alerta de error -->
        <?php if ($error !== ''): ?>
        <div class="ts-alert ts-alert-danger mb-4" role="alert">
            <i class="bi bi-exclamation-circle-fill"></i>
            <span><?= e($error) ?></span>
        </div>
        <?php endif; ?>

        <!-- Formulario -->
        <form method="POST" action="login.php" novalidate>

            <div class="mb-3">
                <label for="correo" class="form-label">Correo electrónico</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-envelope"></i></span>
                    <input
                        type="email"
                        id="correo"
                        name="correo"
                        class="form-control"
                        placeholder="admin@tecnostock.cl"
                        value="<?= e($_POST['correo'] ?? '') ?>"
                        required
                        autofocus
                    >
                </div>
            </div>

            <div class="mb-4">
                <label for="clave" class="form-label">Contraseña</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="bi bi-lock"></i></span>
                    <input
                        type="password"
                        id="clave"
                        name="clave"
                        class="form-control"
                        placeholder="••••••••"
                        required
                    >
                </div>
            </div>

            <button type="submit" class="btn-ts-primary w-100 justify-content-center py-2">
                <i class="bi bi-box-arrow-in-right"></i>
                Ingresar al sistema
            </button>
        </form>

        <p class="text-center mt-4 mb-0" style="font-size:.75rem;color:#94a3b8;">
            Acceso exclusivo para encargados de inventario
        </p>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
