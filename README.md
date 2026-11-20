# TecnoStock — Sistema de Inventario

> **Proyecto académico — Caso 1: Sistema de Inventario para TecnoStock**  
> Ramo: Sistemas de Información · Ingeniería en Informática  
> **Institución:** Universidad Tecnológica Metropolitana (UTEM)  
> **Estudiante:** Jacob Eliaser Torres Jorquera

---

## Tabla de contenidos

1. [Análisis del problema](#1-análisis-del-problema)  
2. [Alcance](#2-alcance)  
3. [Actor principal](#3-actor-principal)  
4. [Requerimientos funcionales](#4-requerimientos-funcionales)  
5. [Reglas de negocio](#5-reglas-de-negocio)  
6. [Stack tecnológico](#6-stack-tecnológico)  
7. [Estructura del proyecto](#7-estructura-del-proyecto)  
8. [Instalación en XAMPP](#8-instalación-en-xampp)  
9. [Credenciales de acceso](#9-credenciales-de-acceso)  
10. [Casos de prueba](#10-casos-de-prueba)  
11. [Notas de seguridad](#11-notas-de-seguridad)

---

## 1. Análisis del problema

TecnoStock es una empresa distribuidora de equipos tecnológicos que gestionaba su inventario de forma **manual en hojas de cálculo**. Esta práctica generaba los siguientes problemas:

| Problema | Impacto |
|---|---|
| Conteos de stock incorrectos | Ventas de productos sin existencias reales |
| Sin trazabilidad de movimientos | Imposible identificar responsables de errores |
| Sin alertas de stock mínimo | Quiebres de stock no detectados a tiempo |
| Sin control de acceso | Cualquier persona podía modificar datos |

**Solución propuesta:** Sistema web de inventario con autenticación, CRUD de productos, registro de movimientos con transacciones atómicas y alertas visuales automáticas de stock bajo.

---

## 2. Alcance

### Dentro del alcance

- Autenticación de encargado de inventario con sesión segura.
- Gestión de productos: crear, editar, activar y desactivar (borrado lógico).
- Indicador visual automático cuando `stock_actual < stock_minimo`.
- Búsqueda y filtrado de productos por nombre, código y categoría.
- Registro de movimientos de **ENTRADA** y **SALIDA** con actualización atómica del stock (transacciones PDO).
- Historial de movimientos (Kardex) con filtros por tipo y producto.
- KPIs rápidos en el panel principal: productos activos, bajo stock y valor total del inventario.

### Fuera del alcance

- Múltiples sucursales o bodegas.
- Facturación o módulo de ventas.
- Gestión de proveedores.
- Módulo de reportes avanzados o exportación a PDF/Excel.
- Múltiples roles de usuario.

---

## 3. Actor principal

| Actor | Descripción | Acciones principales |
|---|---|---|
| **Encargado de inventario** | Usuario autenticado responsable de la gestión operativa del inventario. Es el único rol del sistema en este alcance. | Iniciar sesión, gestionar productos, registrar entradas/salidas, consultar historial. |

---

## 4. Requerimientos funcionales

| ID | Requerimiento |
|---|---|
| RF-01 | El sistema debe permitir iniciar sesión con correo y contraseña, y cerrar sesión de forma segura. |
| RF-02 | El sistema debe listar todos los productos con su código, nombre, categoría, precio, stock actual, stock mínimo y estado. |
| RF-03 | El sistema debe destacar visualmente (badge rojo animado + fila destacada) los productos cuyo `stock_actual < stock_minimo`. |
| RF-04 | El sistema debe mostrar KPIs: total de productos activos, cantidad con bajo stock y valor total del inventario. |
| RF-05 | El sistema debe permitir buscar productos por nombre o código, y filtrar por categoría. |
| RF-06 | El sistema debe permitir crear y editar productos con validaciones en cliente (JS) y servidor (PHP). |
| RF-07 | El sistema debe permitir desactivar/activar productos (borrado lógico). Nunca debe eliminar registros físicamente. |
| RF-08 | El sistema debe registrar movimientos de tipo ENTRADA y SALIDA asociando el producto y el usuario de sesión. |
| RF-09 | El sistema debe actualizar automáticamente el `stock_actual` del producto al registrar un movimiento, dentro de una transacción PDO. |
| RF-10 | El sistema debe rechazar una SALIDA que deje el stock en valores negativos (validación en JS, PHP y en la transacción). |
| RF-11 | El sistema debe mostrar el historial de movimientos (Kardex) con fecha, tipo, cantidad, producto y usuario responsable. |
| RF-12 | El historial de movimientos debe permitir filtrar por tipo (ENTRADA/SALIDA) y por producto. |

---

## 5. Reglas de negocio

| ID | Regla |
|---|---|
| RN-01 | El precio de un producto nunca puede ser negativo (`precio >= 0`). |
| RN-02 | El stock actual y el stock mínimo nunca pueden ser negativos (`stock >= 0`). |
| RN-03 | La cantidad de un movimiento debe ser un número entero estrictamente mayor a cero (`cantidad > 0`). |
| RN-04 | Una SALIDA está prohibida si `cantidad > stock_actual`. Validación en JS (cliente), PHP (servidor) y dentro de la transacción de BD. |
| RN-05 | Los productos no se eliminan físicamente. Se desactivan cambiando `estado = 0` (borrado lógico). |
| RN-06 | Solo los productos con `estado = 1` (activos) aparecen en el selector de movimientos. |
| RN-07 | El `INSERT` del movimiento y el `UPDATE` del stock se ejecutan en la misma transacción PDO. Si cualquier paso falla, se ejecuta `rollBack()` y no se modifica ningún dato. |
| RN-08 | Las contraseñas se almacenan exclusivamente como hash **bcrypt** (`password_hash` / `password_verify`). Nunca en texto plano. |
| RN-09 | Al detectar un hash con parámetros desactualizados, el sistema lo regenera automáticamente en el próximo login exitoso (`password_needs_rehash`). |

---

## 6. Stack tecnológico

| Capa | Tecnología | Restricción |
|---|---|---|
| Frontend | HTML5, Bootstrap 5.3 (CDN), JavaScript ES6 Vanilla | Sin frameworks de frontend |
| Estilos | CSS personalizado (`style.css`), Google Fonts Inter, Bootstrap Icons (CDN) | Sin preprocesadores |
| Backend | PHP 8 nativo, PDO | Sin frameworks de backend |
| Base de datos | MySQL 8.x (InnoDB, utf8mb4) | Motor transaccional |
| Servidor local | XAMPP (Apache + MySQL) | Entorno académico |

---

## 7. Estructura del proyecto

```
tecnostock/
├── database.sql        ← DDL + DML de semilla (100% reproducible)
├── db.php              ← Singleton PDO, opciones de seguridad, gestión de sesión
├── login.php           ← Formulario e inicio de sesión con bcrypt
├── logout.php          ← Cierre de sesión seguro
├── index.php           ← Panel principal: KPIs, CRUD de productos, búsqueda
├── movimientos.php     ← Registro de movimientos + Kardex (transacciones PDO)
├── validaciones.js     ← Validaciones del lado del cliente (ES6 strict)
├── style.css           ← Estética SaaS moderna sobre Bootstrap 5
├── reset_admin.php     ← Utilidad de migración de hash (eliminar tras usar)
└── README.md           ← Este archivo
```

---

## 8. Instalación en XAMPP

### Prerrequisitos

- **XAMPP ≥ 8.1** instalado en Windows con **Apache** y **MySQL** activos.
- Navegador web moderno (Chrome, Firefox, Edge).

### Pasos de instalación

#### Opción A — phpMyAdmin (recomendada)

1. Inicia Apache y MySQL desde el **Panel de Control de XAMPP**.
2. Abre tu navegador en **http://localhost/phpmyadmin**.
3. Haz clic en la pestaña **SQL** (asegúrate de que no esté seleccionada ninguna BD).
4. Pega el contenido completo de `database.sql` y haz clic en **Continuar / Go**.
5. El script crea `tecnostock_db`, las 4 tablas y los datos de semilla automáticamente.

#### Opción B — Consola MySQL

```bash
# Desde la consola de XAMPP (Shell) o desde cmd:
mysql -u root -p < C:\xampp\htdocs\tecnostock\database.sql
```

#### Acceder al sistema

```
URL: http://localhost/tecnostock/login.php
```

> [!NOTE]
> Si cambiaste la contraseña de `root` en MySQL, edita las constantes `DB_USER` y `DB_PASS` en `db.php` antes de acceder.

---

## 9. Credenciales de acceso

| Campo | Valor |
|---|---|
| **URL de login** | http://localhost/tecnostock/login.php |
| **Correo** | `admin@tecnostock.cl` |
| **Contraseña** | `admin123` |

> [!IMPORTANT]
> El hash bcrypt incluido en `database.sql` fue generado y verificado con la instalación de PHP de XAMPP. Si al ingresar ves "Credenciales incorrectas", visita primero **http://localhost/tecnostock/reset_admin.php** para regenerar el hash con tu versión local de PHP y, a continuación, elimina ese archivo.

---

## 10. Casos de prueba

| # | Nombre del caso | Tipo | Pasos / Entrada | Resultado esperado | Resultado obtenido |
|---|---|---|---|---|---|
| **CP-01** | Login exitoso | Normal | Ingresar `admin@tecnostock.cl` y `admin123` en `login.php` | Redirige a `index.php`. Muestra el nombre del usuario en la barra de navegación. | ✅ Pasa |
| **CP-02** | Login con contraseña incorrecta | Error | Ingresar correo correcto y clave `wrongpass` | Permanece en `login.php`. Muestra: *"Credenciales incorrectas. Intente nuevamente."* Sin revelar si el correo existe. | ✅ Pasa |
| **CP-03** | Alerta visual de bajo stock | Normal | Cargar `index.php` con el producto *"Memoria RAM DDR4 8 GB"* (stock actual 2 < mínimo 10) | La fila aparece destacada, el badge rojo *"Stock bajo"* aparece con animación de pulso. KPI de bajo stock muestra ≥ 1. | ✅ Pasa |
| **CP-04** | Salida que supera el stock (frontera) | Límite / Error | En `movimientos.php`, seleccionar "Switch TP-Link" (stock 5), tipo SALIDA, cantidad **6** | JS bloquea el envío con: *"Cantidad (6) supera el stock disponible (5)"*. Si se bypasea JS, PHP también rechaza en servidor y hace `rollBack`. El stock no se modifica. | ✅ Pasa |
| **CP-05** | Entrada exitosa con actualización de stock | Normal | En `movimientos.php`, seleccionar "Memoria RAM DDR4 8 GB", tipo ENTRADA, cantidad **15** | Mensaje: *"Entrada de 15 unidad(es) registrada exitosamente."* Stock pasa de 2 a 17. La alerta de stock bajo desaparece en `index.php`. Movimiento aparece en el Kardex. | ✅ Pasa |
| **CP-06** | Crear producto con código duplicado | Error | Intentar guardar un producto con código `COMP-001` (ya existente) | PHP captura la excepción PDO (código `23000`) y muestra: *"El código de producto ya existe. Use uno diferente."* | ✅ Pasa |
| **CP-07** | Desactivar producto (borrado lógico) | Normal | En `index.php`, hacer clic en el botón de toggle de cualquier producto activo y confirmar | El producto cambia a estado `Inactivo` (badge gris). No aparece en el selector de movimientos. No se elimina de la BD. | ✅ Pasa |
| **CP-08** | Precio negativo bloqueado | Error | En el formulario de producto, ingresar precio `-500` y enviar | JS impide el envío mostrando: *"El precio no puede ser negativo."* Si se bypasea, PHP también valida en servidor. | ✅ Pasa |

---

## 11. Notas de seguridad

| Medida | Implementación |
|---|---|
| Consultas SQL seguras | Sentencias preparadas reales (`ATTR_EMULATE_PREPARES => false`), elimina inyección SQL. |
| Contraseñas | Hash **bcrypt** con cost=12 (`password_hash`). Nunca en texto plano. |
| Protección de sesión | `session_regenerate_id(true)` al iniciar sesión. Cookie con `httponly: true` y `samesite: Strict`. |
| Prevención de XSS | Toda salida al HTML pasa por `htmlspecialchars()` (función `e()` en `db.php`). |
| Autorización | Función `requireAuth()` al inicio de cada página protegida. Redirige a `login.php` si no hay sesión. |
| Integridad de datos | Restricciones `CHECK`, `FOREIGN KEY ... ON DELETE RESTRICT` y `UNIQUE` en la BD. |

---

*TecnoStock — Sistema de Inventario · &copy; 2026 · Jacob Eliaser Torres Jorquera · UTEM*
