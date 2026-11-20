<?php
/**
 * movimientos.php — Registro de entradas/salidas y Kardex
 * TecnoStock · Sistema de Inventario
 *
 * Transacción PDO: beginTransaction → INSERT movimiento + UPDATE stock → commit / rollBack
 */

declare(strict_types=1);
require_once 'db.php';
requireAuth();

$pdo      = getPDO();
$mensaje  = '';
$tipo_msg = '';

// ══════════════════════════════════════════════════════════════════════════════
// A.  PROCESAMIENTO DE NUEVO MOVIMIENTO (POST)
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_producto  = (int)   ($_POST['id_producto']  ?? 0);
    $tipo         = trim(    $_POST['tipo']          ?? '');
    $cantidad     = (int)   ($_POST['cantidad']      ?? 0);
    $observacion  = trim(    $_POST['observacion']   ?? '');
    $id_usuario   = (int)   ($_SESSION['id_usuario'] ?? 0);

    $errores = [];

    if ($id_producto <= 0)
        $errores[] = 'Seleccione un producto válido.';
    if (!in_array($tipo, ['ENTRADA', 'SALIDA'], true))
        $errores[] = 'El tipo de movimiento debe ser ENTRADA o SALIDA.';
    if ($cantidad <= 0)
        $errores[] = 'La cantidad debe ser un número entero mayor a cero.';

    if (empty($errores)) {
        $stProd = $pdo->prepare(
            'SELECT stock_actual FROM producto WHERE id_producto = :id AND estado = 1 LIMIT 1'
        );
        $stProd->execute([':id' => $id_producto]);
        $prodData = $stProd->fetch();

        if (!$prodData) {
            $errores[] = 'Producto no encontrado o inactivo.';
        } elseif ($tipo === 'SALIDA' && $cantidad > (int) $prodData['stock_actual']) {
            $errores[] = sprintf(
                'Stock insuficiente. Disponible: %d unidades. Solicitado: %d.',
                (int) $prodData['stock_actual'],
                $cantidad
            );
        }
    }

    if (empty($errores)) {
        try {
            $pdo->beginTransaction();

            // 1) Insertar movimiento
            $stIns = $pdo->prepare(
                'INSERT INTO movimiento
                    (tipo, cantidad, observacion, id_producto, id_usuario)
                 VALUES (:tipo, :cantidad, :observacion, :id_producto, :id_usuario)'
            );
            $stIns->bindValue(':tipo',        $tipo,        PDO::PARAM_STR);
            $stIns->bindValue(':cantidad',    $cantidad,    PDO::PARAM_INT);
            $stIns->bindValue(':observacion', $observacion, PDO::PARAM_STR);
            $stIns->bindValue(':id_producto', $id_producto, PDO::PARAM_INT);
            $stIns->bindValue(':id_usuario',  $id_usuario,  PDO::PARAM_INT);
            $stIns->execute();

            // 2) Actualizar stock_actual
            $sqlStock = $tipo === 'ENTRADA'
                ? 'UPDATE producto SET stock_actual = stock_actual + :cant WHERE id_producto = :id'
                : 'UPDATE producto SET stock_actual = stock_actual - :cant WHERE id_producto = :id AND stock_actual >= :cant';

            $stUpd = $pdo->prepare($sqlStock);
            $stUpd->bindValue(':cant', $cantidad,    PDO::PARAM_INT);
            $stUpd->bindValue(':id',   $id_producto, PDO::PARAM_INT);
            $stUpd->execute();

            if ($tipo === 'SALIDA' && $stUpd->rowCount() === 0) {
                throw new RuntimeException('El stock resultante sería negativo. Operación abortada.');
            }

            $pdo->commit();
            $mensaje  = sprintf(
                '%s de %d unidad(es) registrada exitosamente.',
                ucfirst(strtolower($tipo)),
                $cantidad
            );
            $tipo_msg = 'success';

        } catch (Throwable $ex) {
            $pdo->rollBack();
            $mensaje  = 'Error en la transacción: ' . $ex->getMessage();
            $tipo_msg = 'danger';
        }
    } else {
        $mensaje  = implode(' ', $errores);
        $tipo_msg = 'danger';
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// B.  DATOS PARA EL FORMULARIO Y EL KARDEX
// ══════════════════════════════════════════════════════════════════════════════
$productosActivos = $pdo->query(
    'SELECT id_producto, codigo, nombre, stock_actual
       FROM producto WHERE estado = 1 ORDER BY nombre ASC'
)->fetchAll();

$filtroTipo = $_GET['tipo'] ?? '';
$filtroProd = (int) ($_GET['producto'] ?? 0);

$sqlHist = 'SELECT m.id_movimiento, m.fecha_hora, m.tipo, m.cantidad, m.observacion,
                   p.nombre AS producto_nombre, p.codigo AS producto_codigo,
                   u.nombre AS usuario_nombre
              FROM movimiento m
              INNER JOIN producto p ON p.id_producto = m.id_producto
              INNER JOIN usuario  u ON u.id_usuario  = m.id_usuario
             WHERE 1=1';
$histParams = [];

if (in_array($filtroTipo, ['ENTRADA','SALIDA'], true)) {
    $sqlHist .= ' AND m.tipo = :tipo';
    $histParams[':tipo'] = $filtroTipo;
}
if ($filtroProd > 0) {
    $sqlHist .= ' AND m.id_producto = :prod';
    $histParams[':prod'] = $filtroProd;
}
$sqlHist .= ' ORDER BY m.fecha_hora DESC LIMIT 200';

$stHist = $pdo->prepare($sqlHist);
$stHist->execute($histParams);
$historial = $stHist->fetchAll();

$todosProductos = $pdo->query(
    'SELECT id_producto, codigo, nombre FROM producto ORDER BY nombre ASC'
)->fetchAll();

// Contadores rápidos para el Kardex
$totalEntradas = 0; $totalSalidas = 0;
foreach ($historial as $m) {
    if ($m['tipo'] === 'ENTRADA') $totalEntradas++;
    else $totalSalidas++;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoStock — Movimientos</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>

<!-- ══════════════════════════════════════════════════════════════════════════
     NAVBAR
═══════════════════════════════════════════════════════════════════════════ -->
<nav class="navbar navbar-expand-lg ts-navbar">
    <div class="container-fluid">
        <a class="navbar-brand" href="index.php">
            <span class="brand-icon"><i class="bi bi-box-seam-fill"></i></span>
            TecnoStock
        </a>
        <button class="navbar-toggler border-0" type="button"
                data-bs-toggle="collapse" data-bs-target="#navMenu"
                style="color:#94a3b8;">
            <i class="bi bi-list fs-5"></i>
        </button>
        <div class="collapse navbar-collapse" id="navMenu">
            <ul class="navbar-nav me-auto ms-3 gap-1">
                <li class="nav-item">
                    <a class="nav-link" href="index.php">
                        <i class="bi bi-grid-1x2"></i> Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active" href="movimientos.php">
                        <i class="bi bi-arrow-left-right"></i> Movimientos
                    </a>
                </li>
            </ul>
            <div class="d-flex align-items-center gap-3">
                <span class="ts-navbar nav-user">
                    <i class="bi bi-person-circle"></i>
                    <?= e($_SESSION['nombre']) ?>
                </span>
                <a href="logout.php" class="btn-logout">
                    <i class="bi bi-box-arrow-right"></i> Salir
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- ══════════════════════════════════════════════════════════════════════════
     CONTENIDO PRINCIPAL
═══════════════════════════════════════════════════════════════════════════ -->
<div class="container-fluid px-4 py-4" style="max-width:1400px;">

    <?php if ($mensaje !== ''): ?>
    <div class="ts-alert ts-alert-<?= e($tipo_msg) ?> mb-4" role="alert">
        <?php $icon = ($tipo_msg === 'success') ? 'check-circle-fill' : 'x-circle-fill'; ?>
        <i class="bi bi-<?= $icon ?>"></i>
        <span><?= e($mensaje) ?></span>
    </div>
    <?php endif; ?>

    <div class="row g-4">

        <!-- ══════════════════════════════════════════════════════════════════
             FORMULARIO DE MOVIMIENTO
        ═══════════════════════════════════════════════════════════════════ -->
        <div class="col-xl-3 col-lg-4">
            <div class="ts-card">
                <div class="ts-card-header">
                    <h5>
                        <i class="bi bi-plus-circle"></i>
                        Registrar Movimiento
                    </h5>
                </div>
                <div class="ts-card-body">
                    <form id="formMovimiento" method="POST" action="movimientos.php" novalidate>

                        <!-- Producto -->
                        <div class="mb-3">
                            <label for="id_producto" class="form-label">
                                Producto <span class="text-danger">*</span>
                            </label>
                            <select id="id_producto" name="id_producto" class="form-select" required>
                                <option value="">— Seleccione un producto —</option>
                                <?php foreach ($productosActivos as $p): ?>
                                    <option value="<?= (int)$p['id_producto'] ?>"
                                            data-stock="<?= (int)$p['stock_actual'] ?>">
                                        [<?= e($p['codigo']) ?>] <?= e($p['nombre']) ?>
                                        (Stock: <?= (int)$p['stock_actual'] ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <div id="stockDisponibleInfo" class="form-text" hidden></div>
                        </div>

                        <!-- Tipo -->
                        <div class="mb-3">
                            <label for="tipo" class="form-label">
                                Tipo <span class="text-danger">*</span>
                            </label>
                            <select id="tipo" name="tipo" class="form-select" required>
                                <option value="">— Seleccione —</option>
                                <option value="ENTRADA">↓ ENTRADA — ingreso de stock</option>
                                <option value="SALIDA"> ↑ SALIDA  — egreso de stock</option>
                            </select>
                        </div>

                        <!-- Cantidad -->
                        <div class="mb-3">
                            <label for="cantidad" class="form-label">
                                Cantidad <span class="text-danger">*</span>
                            </label>
                            <input type="number" id="cantidad" name="cantidad" class="form-control"
                                   min="1" step="1" placeholder="Ej: 5" required>
                        </div>

                        <!-- Observación -->
                        <div class="mb-4">
                            <label for="observacion" class="form-label">Observación</label>
                            <textarea id="observacion" name="observacion" class="form-control"
                                      rows="2" placeholder="OC número, motivo de salida, etc."></textarea>
                        </div>

                        <!-- Responsable (informativo) -->
                        <div class="ts-info-box mb-4">
                            <i class="bi bi-person-check-fill"></i>
                            Responsable: <strong><?= e($_SESSION['nombre']) ?></strong>
                        </div>

                        <button type="submit" class="btn-ts-primary w-100 justify-content-center py-2">
                            <i class="bi bi-save"></i> Registrar Movimiento
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════
             HISTORIAL (KARDEX)
        ═══════════════════════════════════════════════════════════════════ -->
        <div class="col-xl-9 col-lg-8">
            <div class="ts-card">
                <div class="ts-card-header">
                    <h5>
                        <i class="bi bi-clock-history"></i>
                        Historial de Movimientos
                        <span style="font-weight:400;color:#94a3b8;font-size:.8rem;">(Kardex)</span>
                    </h5>
                    <div class="d-flex align-items-center gap-2">
                        <span class="ts-badge-entrada">
                            <i class="bi bi-arrow-down-circle-fill"></i>
                            <?= $totalEntradas ?> entradas
                        </span>
                        <span class="ts-badge-salida">
                            <i class="bi bi-arrow-up-circle-fill"></i>
                            <?= $totalSalidas ?> salidas
                        </span>
                    </div>
                </div>

                <!-- Toolbar / Filtros -->
                <div class="ts-toolbar">
                    <form method="GET" action="movimientos.php"
                          class="d-flex flex-wrap gap-2 w-100 align-items-center">
                        <select name="tipo" class="form-select form-select-sm" style="max-width:160px;">
                            <option value="">Todos los tipos</option>
                            <option value="ENTRADA" <?= $filtroTipo === 'ENTRADA' ? 'selected' : '' ?>>ENTRADA</option>
                            <option value="SALIDA"  <?= $filtroTipo === 'SALIDA'  ? 'selected' : '' ?>>SALIDA</option>
                        </select>
                        <select name="producto" class="form-select form-select-sm flex-grow-1" style="max-width:280px;">
                            <option value="0">Todos los productos</option>
                            <?php foreach ($todosProductos as $p): ?>
                                <option value="<?= (int)$p['id_producto'] ?>"
                                    <?= $filtroProd == $p['id_producto'] ? 'selected' : '' ?>>
                                    [<?= e($p['codigo']) ?>] <?= e($p['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-ts-primary" style="padding:.35rem .85rem;font-size:.8rem;">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <?php if ($filtroTipo !== '' || $filtroProd > 0): ?>
                        <a href="movimientos.php" class="btn-ts-secondary" style="padding:.35rem .75rem;font-size:.8rem;">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Tabla Kardex -->
                <div style="overflow-x:auto;">
                    <table class="ts-table">
                        <thead>
                            <tr>
                                <th style="width:50px;">#</th>
                                <th>Fecha y Hora</th>
                                <th class="text-center">Tipo</th>
                                <th class="text-center">Cantidad</th>
                                <th>Producto</th>
                                <th class="ts-hide-mobile">Responsable</th>
                                <th class="ts-hide-mobile">Observación</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($historial)): ?>
                            <tr>
                                <td colspan="7">
                                    <div class="ts-empty">
                                        <i class="bi bi-clock-history"></i>
                                        <p>No hay movimientos registrados con los filtros aplicados.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($historial as $m): ?>
                            <tr>
                                <td style="color:#94a3b8;font-size:.75rem;"><?= (int)$m['id_movimiento'] ?></td>
                                <td style="white-space:nowrap;font-size:.8rem;">
                                    <?= e(date('d/m/Y', strtotime($m['fecha_hora']))) ?>
                                    <br>
                                    <span style="color:#94a3b8;"><?= e(date('H:i', strtotime($m['fecha_hora']))) ?></span>
                                </td>
                                <td class="text-center">
                                    <?php if ($m['tipo'] === 'ENTRADA'): ?>
                                        <span class="ts-badge-entrada">
                                            <i class="bi bi-arrow-down-circle-fill"></i> ENTRADA
                                        </span>
                                    <?php else: ?>
                                        <span class="ts-badge-salida">
                                            <i class="bi bi-arrow-up-circle-fill"></i> SALIDA
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center" style="font-weight:600;">
                                    <?= (int)$m['cantidad'] ?>
                                </td>
                                <td>
                                    <span class="fw-mono text-xs" style="color:#94a3b8;">
                                        <?= e($m['producto_codigo']) ?>
                                    </span>
                                    <br>
                                    <span style="font-size:.83rem;font-weight:500;">
                                        <?= e($m['producto_nombre']) ?>
                                    </span>
                                </td>
                                <td class="ts-hide-mobile" style="font-size:.82rem;">
                                    <?= e($m['usuario_nombre']) ?>
                                </td>
                                <td class="ts-hide-mobile" style="font-size:.8rem;color:#64748b;max-width:200px;">
                                    <?= $m['observacion'] !== '' ? e($m['observacion']) : '<span style="color:#cbd5e1;">—</span>' ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div><!-- /ts-card -->
        </div>

    </div><!-- /row -->
</div><!-- /container -->

<footer class="ts-footer">
    TecnoStock &copy; <?= date('Y') ?> &nbsp;·&nbsp; Sistema de Inventario &nbsp;·&nbsp; Ramo: Sistemas de Información
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="validaciones.js"></script>
</body>
</html>
