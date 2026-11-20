<?php
/**
 * logout.php — Cierre de sesión seguro
 * TecnoStock · Sistema de Inventario
 */

declare(strict_types=1);
require_once 'db.php';

iniciarSesion();

// Destruir datos de sesión
$_SESSION = [];

// Invalidar la cookie de sesión
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: login.php');
exit;
