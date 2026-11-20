/**
 * validaciones.js — Validaciones del lado del cliente
 * TecnoStock · Sistema de Inventario
 *
 * Reglas implementadas:
 *  1. Precio, stock_actual y stock_minimo nunca negativos.
 *  2. Cantidad de movimiento > 0.
 *  3. Para SALIDA: cantidad ≤ stock actual del producto seleccionado.
 *  4. Campos requeridos no vacíos.
 */

'use strict';

// ══════════════════════════════════════════════════════════════════════════════
// 1.  FORMULARIO DE PRODUCTO  (#formProducto)
// ══════════════════════════════════════════════════════════════════════════════
(function () {
    const form = document.getElementById('formProducto');
    if (!form) return;

    form.addEventListener('submit', function (e) {
        limpiarErrores(form);
        let valido = true;

        // Código
        const codigo = form.querySelector('[name="codigo"]');
        if (codigo && codigo.value.trim() === '') {
            marcarError(codigo, 'El código del producto es obligatorio.');
            valido = false;
        }

        // Nombre
        const nombre = form.querySelector('[name="nombre"]');
        if (nombre && nombre.value.trim() === '') {
            marcarError(nombre, 'El nombre del producto es obligatorio.');
            valido = false;
        }

        // Precio ≥ 0
        const precio = form.querySelector('[name="precio"]');
        if (precio) {
            const val = parseFloat(precio.value);
            if (isNaN(val) || val < 0) {
                marcarError(precio, 'El precio no puede ser negativo.');
                valido = false;
            }
        }

        // Stock actual ≥ 0
        const stockActual = form.querySelector('[name="stock_actual"]');
        if (stockActual) {
            const val = parseInt(stockActual.value, 10);
            if (isNaN(val) || val < 0) {
                marcarError(stockActual, 'El stock actual no puede ser negativo.');
                valido = false;
            }
        }

        // Stock mínimo ≥ 0
        const stockMinimo = form.querySelector('[name="stock_minimo"]');
        if (stockMinimo) {
            const val = parseInt(stockMinimo.value, 10);
            if (isNaN(val) || val < 0) {
                marcarError(stockMinimo, 'El stock mínimo no puede ser negativo.');
                valido = false;
            }
        }

        // Categoría seleccionada
        const categoria = form.querySelector('[name="id_categoria"]');
        if (categoria && categoria.value === '') {
            marcarError(categoria, 'Seleccione una categoría.');
            valido = false;
        }

        if (!valido) e.preventDefault();
    });
})();

// ══════════════════════════════════════════════════════════════════════════════
// 2.  FORMULARIO DE MOVIMIENTO  (#formMovimiento)
// ══════════════════════════════════════════════════════════════════════════════
(function () {
    const form = document.getElementById('formMovimiento');
    if (!form) return;

    const selectProducto = form.querySelector('[name="id_producto"]');
    const inputCantidad  = form.querySelector('[name="cantidad"]');
    const selectTipo     = form.querySelector('[name="tipo"]');

    // ── Actualizar stock disponible al cambiar producto ──────────────────────
    if (selectProducto) {
        selectProducto.addEventListener('change', actualizarStockInfo);
        // Disparar al cargar por si hay valor preseleccionado
        actualizarStockInfo();
    }

    if (selectTipo) {
        selectTipo.addEventListener('change', actualizarStockInfo);
    }

    function actualizarStockInfo() {
        const opt = selectProducto
            ? selectProducto.options[selectProducto.selectedIndex]
            : null;

        const infoEl = document.getElementById('stockDisponibleInfo');
        if (!infoEl || !opt) return;

        const stock = parseInt(opt.dataset.stock ?? '-1', 10);
        const tipo  = selectTipo ? selectTipo.value : '';

        if (stock >= 0 && tipo === 'SALIDA') {
            infoEl.textContent = `Stock disponible: ${stock} unidades`;
            infoEl.className   = stock === 0
                ? 'form-text text-danger fw-bold'
                : 'form-text text-info';
            infoEl.hidden = false;
        } else if (stock >= 0) {
            infoEl.textContent = `Stock actual: ${stock} unidades`;
            infoEl.className   = 'form-text text-muted';
            infoEl.hidden = false;
        } else {
            infoEl.hidden = true;
        }
    }

    // ── Validar al enviar ────────────────────────────────────────────────────
    form.addEventListener('submit', function (e) {
        limpiarErrores(form);
        let valido = true;

        // Producto seleccionado
        if (selectProducto && selectProducto.value === '') {
            marcarError(selectProducto, 'Seleccione un producto.');
            valido = false;
        }

        // Tipo seleccionado
        if (selectTipo && selectTipo.value === '') {
            marcarError(selectTipo, 'Seleccione el tipo de movimiento.');
            valido = false;
        }

        // Cantidad > 0
        if (inputCantidad) {
            const cant = parseInt(inputCantidad.value, 10);
            if (isNaN(cant) || cant <= 0) {
                marcarError(inputCantidad, 'La cantidad debe ser un número entero mayor a cero.');
                valido = false;
            } else if (selectTipo && selectTipo.value === 'SALIDA' && selectProducto) {
                // Validar que la SALIDA no supere el stock disponible
                const opt   = selectProducto.options[selectProducto.selectedIndex];
                const stock = parseInt(opt.dataset.stock ?? '0', 10);

                if (cant > stock) {
                    marcarError(
                        inputCantidad,
                        `Cantidad (${cant}) supera el stock disponible (${stock}). Ajuste la cantidad.`
                    );
                    valido = false;
                }
            }
        }

        if (!valido) e.preventDefault();
    });
})();

// ══════════════════════════════════════════════════════════════════════════════
// 3.  UTILIDADES COMPARTIDAS
// ══════════════════════════════════════════════════════════════════════════════

/**
 * Marca un campo con error y muestra el mensaje bajo él.
 * @param {HTMLElement} campo
 * @param {string} mensaje
 */
function marcarError(campo, mensaje) {
    campo.classList.add('is-invalid');

    // Buscar o crear el div de feedback
    let feedback = campo.nextElementSibling;
    if (!feedback || !feedback.classList.contains('invalid-feedback')) {
        feedback = document.createElement('div');
        feedback.className = 'invalid-feedback';
        campo.parentNode.insertBefore(feedback, campo.nextSibling);
    }
    feedback.textContent = mensaje;
}

/**
 * Elimina todos los estados de error en un formulario.
 * @param {HTMLFormElement} form
 */
function limpiarErrores(form) {
    form.querySelectorAll('.is-invalid').forEach(el => el.classList.remove('is-invalid'));
    form.querySelectorAll('.invalid-feedback').forEach(el => el.remove());
}

// ══════════════════════════════════════════════════════════════════════════════
// 4.  CONFIRMACIÓN DE ACCIONES DESTRUCTIVAS (desactivar / activar)
// ══════════════════════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-confirm]').forEach(function (el) {
        el.addEventListener('click', function (e) {
            if (!confirm(el.dataset.confirm)) {
                e.preventDefault();
            }
        });
    });
});
