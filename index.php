<?php
/**
 * index.php — Panel principal: gestión de productos
 * TecnoStock · Sistema de Inventario
 */

declare(strict_types=1);
require_once 'db.php';
requireAuth();

$pdo      = getPDO();
$mensaje  = '';
$tipo_msg = '';

// ══════════════════════════════════════════════════════════════════════════════
// A.  PROCESAMIENTO DE ACCIONES POST
// ══════════════════════════════════════════════════════════════════════════════
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = $_POST['accion'] ?? '';

    // ── A1. Crear o editar producto ───────────────────────────────────────────
    if ($accion === 'guardar_producto') {
        $id_producto  = isset($_POST['id_producto']) ? (int) $_POST['id_producto'] : 0;
        $codigo       = trim($_POST['codigo']      ?? '');
        $nombre       = trim($_POST['nombre']      ?? '');
        $descripcion  = trim($_POST['descripcion'] ?? '');
        $precio       = $_POST['precio']       ?? '';
        $stock_actual = $_POST['stock_actual'] ?? '';
        $stock_minimo = $_POST['stock_minimo'] ?? '';
        $id_categoria = (int) ($_POST['id_categoria'] ?? 0);

        $errores = [];
        if ($codigo === '')                              $errores[] = 'El código es obligatorio.';
        if ($nombre === '')                              $errores[] = 'El nombre es obligatorio.';
        if (!is_numeric($precio) || (float)$precio < 0) $errores[] = 'El precio no puede ser negativo.';
        if (!is_numeric($stock_actual) || (int)$stock_actual < 0) $errores[] = 'El stock actual no puede ser negativo.';
        if (!is_numeric($stock_minimo) || (int)$stock_minimo < 0) $errores[] = 'El stock mínimo no puede ser negativo.';
        if ($id_categoria <= 0)                          $errores[] = 'Seleccione una categoría válida.';

        if (empty($errores)) {
            try {
                if ($id_producto === 0) {
                    $stmt = $pdo->prepare(
                        'INSERT INTO producto
                            (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, id_categoria)
                         VALUES (:codigo, :nombre, :descripcion, :precio, :stock_actual, :stock_minimo, :id_categoria)'
                    );
                } else {
                    $stmt = $pdo->prepare(
                        'UPDATE producto
                            SET codigo       = :codigo,
                                nombre       = :nombre,
                                descripcion  = :descripcion,
                                precio       = :precio,
                                stock_actual = :stock_actual,
                                stock_minimo = :stock_minimo,
                                id_categoria = :id_categoria
                          WHERE id_producto = :id_producto'
                    );
                    $stmt->bindValue(':id_producto', $id_producto, PDO::PARAM_INT);
                }
                $stmt->bindValue(':codigo',       $codigo,              PDO::PARAM_STR);
                $stmt->bindValue(':nombre',       $nombre,              PDO::PARAM_STR);
                $stmt->bindValue(':descripcion',  $descripcion,         PDO::PARAM_STR);
                $stmt->bindValue(':precio',       (float) $precio,      PDO::PARAM_STR);
                $stmt->bindValue(':stock_actual', (int)   $stock_actual,PDO::PARAM_INT);
                $stmt->bindValue(':stock_minimo', (int)   $stock_minimo,PDO::PARAM_INT);
                $stmt->bindValue(':id_categoria', $id_categoria,        PDO::PARAM_INT);
                $stmt->execute();

                $mensaje  = $id_producto === 0 ? 'Producto creado exitosamente.' : 'Producto actualizado exitosamente.';
                $tipo_msg = 'success';
            } catch (PDOException $e) {
                if ($e->getCode() === '23000') {
                    $mensaje = 'El código de producto ya existe. Use uno diferente.';
                } else {
                    $mensaje = 'Error al guardar el producto: ' . $e->getMessage();
                }
                $tipo_msg = 'danger';
            }
        } else {
            $mensaje  = implode(' ', $errores);
            $tipo_msg = 'danger';
        }
    }

    // ── A2. Cambiar estado (borrado lógico) ───────────────────────────────────
    if ($accion === 'cambiar_estado') {
        $id_producto = (int) ($_POST['id_producto'] ?? 0);
        if ($id_producto > 0) {
            $stmt = $pdo->prepare(
                'UPDATE producto
                    SET estado = CASE WHEN estado = 1 THEN 0 ELSE 1 END
                  WHERE id_producto = :id'
            );
            $stmt->execute([':id' => $id_producto]);
            $mensaje  = 'Estado del producto actualizado.';
            $tipo_msg = 'info';
        }
    }
}

// ══════════════════════════════════════════════════════════════════════════════
// B.  DATOS PARA EDITAR (GET)
// ══════════════════════════════════════════════════════════════════════════════
$productoEditar = null;
if (isset($_GET['editar'])) {
    $id = (int) $_GET['editar'];
    $st = $pdo->prepare('SELECT * FROM producto WHERE id_producto = :id LIMIT 1');
    $st->execute([':id' => $id]);
    $productoEditar = $st->fetch();
}

// ══════════════════════════════════════════════════════════════════════════════
// C.  LISTADO CON BUSCADOR Y FILTRO
// ══════════════════════════════════════════════════════════════════════════════
$busqueda   = trim($_GET['busqueda']   ?? '');
$filtro_cat = (int) ($_GET['categoria'] ?? 0);

$sql = 'SELECT p.*, c.nombre AS categoria_nombre, c.id_categoria AS cat_id
          FROM producto p
          INNER JOIN categoria c ON c.id_categoria = p.id_categoria
         WHERE 1=1';
$params = [];

if ($busqueda !== '') {
    $sql   .= ' AND (p.nombre LIKE :bus OR p.codigo LIKE :bus2)';
    $params[':bus']  = '%' . $busqueda . '%';
    $params[':bus2'] = '%' . $busqueda . '%';
}
if ($filtro_cat > 0) {
    $sql   .= ' AND p.id_categoria = :cat';
    $params[':cat'] = $filtro_cat;
}
$sql .= ' ORDER BY p.estado DESC, p.nombre ASC';

$stProductos = $pdo->prepare($sql);
$stProductos->execute($params);
$productos = $stProductos->fetchAll();

// KPIs
$kpiActivos    = (int) $pdo->query("SELECT COUNT(*) FROM producto WHERE estado = 1")->fetchColumn();
$kpiBajoStock  = (int) $pdo->query("SELECT COUNT(*) FROM producto WHERE estado = 1 AND stock_actual < stock_minimo")->fetchColumn();
$kpiValorTotal = (float) $pdo->query("SELECT COALESCE(SUM(precio * stock_actual),0) FROM producto WHERE estado = 1")->fetchColumn();

// Categorías para formulario
$categorias = $pdo->query('SELECT * FROM categoria WHERE estado = 1 ORDER BY nombre ASC')->fetchAll();

// Categorías para filtro
$categoriasFiltro = $pdo->query('SELECT * FROM categoria ORDER BY nombre ASC')->fetchAll();

// Mapa id_categoria → índice (para colores de badges ciclados)
$catColorMap = [];
foreach ($categoriasFiltro as $idx => $c) {
    $catColorMap[$c['id_categoria']] = $idx % 5;
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TecnoStock — Productos</title>
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
                    <a class="nav-link active" href="index.php">
                        <i class="bi bi-grid-1x2"></i> Productos
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="movimientos.php">
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

    <!-- Alerta de retroalimentación -->
    <?php if ($mensaje !== ''): ?>
    <div class="ts-alert ts-alert-<?= e($tipo_msg) ?> mb-4" role="alert">
        <?php
            $iconMap = ['success' => 'check-circle-fill', 'danger' => 'x-circle-fill', 'info' => 'info-circle-fill'];
            $icon = $iconMap[$tipo_msg] ?? 'info-circle-fill';
        ?>
        <i class="bi bi-<?= $icon ?>"></i>
        <span><?= e($mensaje) ?></span>
    </div>
    <?php endif; ?>

    <!-- ── KPI Cards ──────────────────────────────────────────────────────── -->
    <div class="row g-3 mb-4">
        <div class="col-sm-6 col-lg-4">
            <div class="ts-kpi">
                <div class="ts-kpi-icon blue"><i class="bi bi-box-seam"></i></div>
                <div>
                    <div class="ts-kpi-label">Productos Activos</div>
                    <div class="ts-kpi-value"><?= $kpiActivos ?></div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="ts-kpi">
                <div class="ts-kpi-icon <?= $kpiBajoStock > 0 ? 'red' : 'green' ?>">
                    <i class="bi bi-<?= $kpiBajoStock > 0 ? 'exclamation-triangle' : 'shield-check' ?>"></i>
                </div>
                <div>
                    <div class="ts-kpi-label">Stock Bajo Mínimo</div>
                    <div class="ts-kpi-value <?= $kpiBajoStock > 0 ? 'text-danger' : '' ?>">
                        <?= $kpiBajoStock ?>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-sm-6 col-lg-4">
            <div class="ts-kpi">
                <div class="ts-kpi-icon green"><i class="bi bi-currency-dollar"></i></div>
                <div>
                    <div class="ts-kpi-label">Valor Total Inventario</div>
                    <div class="ts-kpi-value" style="font-size:1.15rem;">
                        $<?= number_format($kpiValorTotal, 0, ',', '.') ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row g-4">

        <!-- ══════════════════════════════════════════════════════════════════
             FORMULARIO CREAR / EDITAR
        ═══════════════════════════════════════════════════════════════════ -->
        <div class="col-xl-3 col-lg-4">
            <div class="ts-card">
                <div class="ts-card-header">
                    <h5>
                        <i class="bi bi-<?= $productoEditar ? 'pencil-square' : 'plus-circle' ?>"></i>
                        <?= $productoEditar ? 'Editar Producto' : 'Nuevo Producto' ?>
                    </h5>
                </div>
                <div class="ts-card-body">
                    <form id="formProducto" method="POST" action="index.php" novalidate>
                        <input type="hidden" name="accion" value="guardar_producto">
                        <?php if ($productoEditar): ?>
                            <input type="hidden" name="id_producto" value="<?= (int)$productoEditar['id_producto'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label for="codigo" class="form-label">
                                Código <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="codigo" name="codigo" class="form-control"
                                   value="<?= e($productoEditar['codigo'] ?? '') ?>"
                                   placeholder="Ej: COMP-005" maxlength="50" required>
                        </div>

                        <div class="mb-3">
                            <label for="nombre" class="form-label">
                                Nombre <span class="text-danger">*</span>
                            </label>
                            <input type="text" id="nombre" name="nombre" class="form-control"
                                   value="<?= e($productoEditar['nombre'] ?? '') ?>"
                                   placeholder="Nombre del producto" maxlength="150" required>
                        </div>

                        <div class="mb-3">
                            <label for="descripcion" class="form-label">Descripción</label>
                            <textarea id="descripcion" name="descripcion" class="form-control"
                                      rows="2" placeholder="Descripción opcional"><?= e($productoEditar['descripcion'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label for="id_categoria" class="form-label">
                                Categoría <span class="text-danger">*</span>
                            </label>
                            <select id="id_categoria" name="id_categoria" class="form-select" required>
                                <option value="">— Seleccione —</option>
                                <?php foreach ($categorias as $cat): ?>
                                    <option value="<?= (int)$cat['id_categoria'] ?>"
                                        <?= (isset($productoEditar['id_categoria']) && $productoEditar['id_categoria'] == $cat['id_categoria']) ? 'selected' : '' ?>>
                                        <?= e($cat['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="precio" class="form-label">
                                Precio (CLP) <span class="text-danger">*</span>
                            </label>
                            <div class="input-group">
                                <span class="input-group-text">$</span>
                                <input type="number" id="precio" name="precio" class="form-control"
                                       value="<?= e($productoEditar['precio'] ?? '0') ?>"
                                       min="0" step="0.01" required>
                            </div>
                        </div>

                        <div class="row g-2 mb-4">
                            <div class="col-6">
                                <label for="stock_actual" class="form-label">
                                    Stock actual <span class="text-danger">*</span>
                                </label>
                                <input type="number" id="stock_actual" name="stock_actual"
                                       class="form-control"
                                       value="<?= e($productoEditar['stock_actual'] ?? '0') ?>"
                                       min="0" required>
                            </div>
                            <div class="col-6">
                                <label for="stock_minimo" class="form-label">
                                    Stock mínimo <span class="text-danger">*</span>
                                </label>
                                <input type="number" id="stock_minimo" name="stock_minimo"
                                       class="form-control"
                                       value="<?= e($productoEditar['stock_minimo'] ?? '0') ?>"
                                       min="0" required>
                            </div>
                        </div>

                        <div class="d-flex gap-2">
                            <button type="submit" class="btn-ts-primary flex-grow-1 justify-content-center">
                                <i class="bi bi-save"></i>
                                <?= $productoEditar ? 'Actualizar' : 'Guardar' ?>
                            </button>
                            <?php if ($productoEditar): ?>
                                <a href="index.php" class="btn-ts-secondary">
                                    <i class="bi bi-x"></i>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- ══════════════════════════════════════════════════════════════════
             TABLA DE PRODUCTOS
        ═══════════════════════════════════════════════════════════════════ -->
        <div class="col-xl-9 col-lg-8">
            <div class="ts-card">
                <!-- Header de la tarjeta -->
                <div class="ts-card-header">
                    <h5>
                        <i class="bi bi-table"></i>
                        Inventario de Productos
                    </h5>
                    <span class="ts-count-badge"><?= count($productos) ?> registros</span>
                </div>

                <!-- Toolbar / Buscador -->
                <div class="ts-toolbar">
                    <form method="GET" action="index.php"
                          class="d-flex flex-wrap gap-2 w-100 align-items-center">
                        <div class="input-group input-group-sm flex-grow-1" style="max-width:280px;">
                            <span class="input-group-text"><i class="bi bi-search"></i></span>
                            <input type="text" name="busqueda" class="form-control"
                                   placeholder="Buscar nombre o código…"
                                   value="<?= e($busqueda) ?>">
                        </div>
                        <select name="categoria" class="form-select form-select-sm" style="max-width:200px;">
                            <option value="0">Todas las categorías</option>
                            <?php foreach ($categoriasFiltro as $cat): ?>
                                <option value="<?= (int)$cat['id_categoria'] ?>"
                                    <?= $filtro_cat == $cat['id_categoria'] ? 'selected' : '' ?>>
                                    <?= e($cat['nombre']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit" class="btn-ts-primary" style="padding:.35rem .85rem;font-size:.8rem;">
                            <i class="bi bi-funnel"></i> Filtrar
                        </button>
                        <?php if ($busqueda !== '' || $filtro_cat > 0): ?>
                        <a href="index.php" class="btn-ts-secondary" style="padding:.35rem .75rem;font-size:.8rem;">
                            <i class="bi bi-x-circle"></i> Limpiar
                        </a>
                        <?php endif; ?>
                    </form>
                </div>

                <!-- Leyenda de alerta -->
                <?php if ($kpiBajoStock > 0): ?>
                <div class="px-4 pt-3">
                    <div class="ts-alert ts-alert-warning" style="font-size:.78rem;padding:.5rem .85rem;">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <span>
                            <strong><?= $kpiBajoStock ?> producto<?= $kpiBajoStock > 1 ? 's' : '' ?></strong>
                            con stock por debajo del mínimo requerido (filas destacadas en rojo).
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Tabla -->
                <div class="p-0" style="overflow-x:auto;">
                    <table class="ts-table">
                        <thead>
                            <tr>
                                <th>Código</th>
                                <th>Nombre</th>
                                <th class="ts-hide-mobile">Categoría</th>
                                <th class="text-end">Precio</th>
                                <th class="text-center">Stock Act.</th>
                                <th class="text-center ts-hide-mobile">Stock Mín.</th>
                                <th class="text-center">Estado</th>
                                <th class="text-center">Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                        <?php if (empty($productos)): ?>
                            <tr>
                                <td colspan="8">
                                    <div class="ts-empty">
                                        <i class="bi bi-inbox"></i>
                                        <p>No se encontraron productos con los filtros aplicados.</p>
                                    </div>
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($productos as $p):
                                $stockBajo  = ($p['stock_actual'] < $p['stock_minimo']);
                                $catIdx     = $catColorMap[$p['id_categoria']] ?? 0;
                            ?>
                            <tr class="<?= $stockBajo ? 'row-stock-bajo' : '' ?>">
                                <!-- Código -->
                                <td>
                                    <span class="fw-mono text-xs" style="color:#475569;letter-spacing:.02em;">
                                        <?= e($p['codigo']) ?>
                                    </span>
                                </td>

                                <!-- Nombre + badge de alerta -->
                                <td>
                                    <span class="fw-500" style="font-weight:500;">
                                        <?= e($p['nombre']) ?>
                                    </span>
                                    <?php if ($stockBajo): ?>
                                        <br>
                                        <span class="ts-badge ts-badge-danger ts-badge-stock-bajo mt-1">
                                            <i class="bi bi-exclamation-triangle-fill"></i>
                                            Stock bajo
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Categoría -->
                                <td class="ts-hide-mobile">
                                    <span class="ts-badge cat-badge-<?= $catIdx ?>">
                                        <?= e($p['categoria_nombre']) ?>
                                    </span>
                                </td>

                                <!-- Precio -->
                                <td class="text-end" style="font-variant-numeric:tabular-nums;white-space:nowrap;">
                                    $<?= number_format((float)$p['precio'], 0, ',', '.') ?>
                                </td>

                                <!-- Stock actual -->
                                <td class="text-center">
                                    <span class="<?= $stockBajo ? 'stock-bajo-val' : '' ?>">
                                        <?= (int)$p['stock_actual'] ?>
                                    </span>
                                </td>

                                <!-- Stock mínimo -->
                                <td class="text-center ts-hide-mobile" style="color:#94a3b8;">
                                    <?= (int)$p['stock_minimo'] ?>
                                </td>

                                <!-- Estado -->
                                <td class="text-center">
                                    <?php if ($p['estado'] == 1): ?>
                                        <span class="ts-badge ts-badge-success">
                                            <i class="bi bi-check-circle-fill"></i> Activo
                                        </span>
                                    <?php else: ?>
                                        <span class="ts-badge ts-badge-muted">
                                            <i class="bi bi-dash-circle"></i> Inactivo
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <!-- Acciones -->
                                <td class="text-center">
                                    <div class="d-flex justify-content-center gap-6px">
                                        <!-- Editar -->
                                        <a href="index.php?editar=<?= (int)$p['id_producto'] ?>"
                                           class="ts-btn-icon ts-btn-edit"
                                           title="Editar producto">
                                            <i class="bi bi-pencil"></i>
                                        </a>

                                        <!-- Activar / Desactivar -->
                                        <form method="POST" action="index.php" class="m-0">
                                            <input type="hidden" name="accion"      value="cambiar_estado">
                                            <input type="hidden" name="id_producto" value="<?= (int)$p['id_producto'] ?>">
                                            <?php if ($p['estado'] == 1): ?>
                                                <button type="submit"
                                                        class="ts-btn-icon ts-btn-off"
                                                        title="Desactivar producto"
                                                        data-confirm="¿Desactivar el producto '<?= e($p['nombre']) ?>'?">
                                                    <i class="bi bi-toggle-on"></i>
                                                </button>
                                            <?php else: ?>
                                                <button type="submit"
                                                        class="ts-btn-icon ts-btn-on"
                                                        title="Activar producto"
                                                        data-confirm="¿Activar el producto '<?= e($p['nombre']) ?>'?">
                                                    <i class="bi bi-toggle-off"></i>
                                                </button>
                                            <?php endif; ?>
                                        </form>
                                    </div>
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
