<?php
/**
 * db.php — Conexión PDO segura y utilidades de sesión
 * TecnoStock · Sistema de Inventario
 */

declare(strict_types=1);

// ── Configuración de base de datos ─────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'tecnostock_db');
define('DB_USER', 'root');       // Cambiar en producción
define('DB_PASS', '');           // Cambiar en producción
define('DB_PORT', '3306');
define('DB_CHARSET', 'utf8mb4');

/**
 * Retorna la instancia PDO (singleton).
 *
 * @throws PDOException si la conexión falla.
 */
function getPDO(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            DB_HOST, DB_PORT, DB_NAME, DB_CHARSET
        );

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,   // Consultas realmente preparadas
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // No exponer detalles en producción
            error_log('[TecnoStock] Error de conexión: ' . $e->getMessage());
            die(json_encode(['error' => 'Error interno del servidor. Contacte al administrador.']));
        }
    }

    return $pdo;
}

// ── Gestión de sesión ───────────────────────────────────────────────────────

/**
 * Inicia sesión de forma segura (si no está iniciada).
 */
function iniciarSesion(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        session_name('TECNOSTOCK_SID');
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => false,   // true en HTTPS/producción
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();
    }
}

/**
 * Verifica que el usuario esté autenticado.
 * Si no lo está, redirige a login.php y detiene la ejecución.
 */
function requireAuth(): void
{
    iniciarSesion();

    if (empty($_SESSION['id_usuario'])) {
        header('Location: login.php');
        exit;
    }
}

/**
 * Escapa una cadena para salida HTML segura.
 */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}
