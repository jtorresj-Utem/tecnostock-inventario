-- =============================================================
-- TecnoStock - Sistema de Inventario
-- Script SQL 100% reproducible
-- Motor: MySQL 8+ con InnoDB
-- =============================================================

-- 1. Crear y seleccionar base de datos
DROP DATABASE IF EXISTS tecnostock_db;
CREATE DATABASE tecnostock_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE tecnostock_db;

-- =============================================================
-- 2. TABLA: usuario
-- =============================================================
CREATE TABLE usuario (
    id_usuario  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    nombre      VARCHAR(100)    NOT NULL,
    correo      VARCHAR(150)    NOT NULL,
    clave       VARCHAR(255)    NOT NULL,          -- hash bcrypt
    estado      TINYINT(1)      NOT NULL DEFAULT 1,
    CONSTRAINT pk_usuario       PRIMARY KEY (id_usuario),
    CONSTRAINT uq_usuario_correo UNIQUE (correo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 3. TABLA: categoria
-- =============================================================
CREATE TABLE categoria (
    id_categoria INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    nombre       VARCHAR(100)   NOT NULL,
    descripcion  TEXT,
    estado       TINYINT(1)     NOT NULL DEFAULT 1,
    CONSTRAINT pk_categoria       PRIMARY KEY (id_categoria),
    CONSTRAINT uq_categoria_nombre UNIQUE (nombre)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 4. TABLA: producto
-- =============================================================
CREATE TABLE producto (
    id_producto  INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    codigo       VARCHAR(50)     NOT NULL,
    nombre       VARCHAR(150)    NOT NULL,
    descripcion  TEXT,
    precio       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
    stock_actual INT             NOT NULL DEFAULT 0,
    stock_minimo INT             NOT NULL DEFAULT 0,
    estado       TINYINT(1)      NOT NULL DEFAULT 1,
    id_categoria INT UNSIGNED    NOT NULL,
    CONSTRAINT pk_producto         PRIMARY KEY (id_producto),
    CONSTRAINT uq_producto_codigo  UNIQUE (codigo),
    CONSTRAINT chk_precio          CHECK (precio       >= 0),
    CONSTRAINT chk_stock_actual    CHECK (stock_actual >= 0),
    CONSTRAINT chk_stock_minimo    CHECK (stock_minimo >= 0),
    CONSTRAINT fk_producto_cat     FOREIGN KEY (id_categoria)
        REFERENCES categoria (id_categoria) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 5. TABLA: movimiento
-- =============================================================
CREATE TABLE movimiento (
    id_movimiento INT UNSIGNED   NOT NULL AUTO_INCREMENT,
    fecha_hora    DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tipo          ENUM('ENTRADA','SALIDA') NOT NULL,
    cantidad      INT             NOT NULL,
    observacion   TEXT,
    id_producto   INT UNSIGNED    NOT NULL,
    id_usuario    INT UNSIGNED    NOT NULL,
    CONSTRAINT pk_movimiento       PRIMARY KEY (id_movimiento),
    CONSTRAINT chk_cantidad        CHECK (cantidad > 0),
    CONSTRAINT fk_mov_producto     FOREIGN KEY (id_producto)
        REFERENCES producto (id_producto) ON DELETE RESTRICT ON UPDATE CASCADE,
    CONSTRAINT fk_mov_usuario      FOREIGN KEY (id_usuario)
        REFERENCES usuario (id_usuario) ON DELETE RESTRICT ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =============================================================
-- 6. DATOS DE SEMILLA
-- =============================================================

-- 6.1 Usuario administrador  (clave: admin123 — hash bcrypt cost=12 verificado)
INSERT INTO usuario (nombre, correo, clave, estado) VALUES
('Administrador TecnoStock',
 'admin@tecnostock.cl',
 '$2y$12$zUh1lQQ.M9I/ygzPishOuOUn7Aqv45Vkb7aP6apTAs/D4x7xFWj.G',   -- admin123
 1);

-- 6.2 Categorías tecnológicas
INSERT INTO categoria (nombre, descripcion, estado) VALUES
('Computación',   'Equipos de escritorio, laptops, accesorios de cómputo y almacenamiento', 1),
('Redes',         'Switches, routers, cables de red, access points y equipos de conectividad', 1),
('Periféricos',   'Teclados, mouse, monitores, impresoras y dispositivos de entrada/salida', 1);

-- 6.3 Productos (el 4.° tiene stock_actual < stock_minimo para evidenciar alerta)
INSERT INTO producto (codigo, nombre, descripcion, precio, stock_actual, stock_minimo, estado, id_categoria) VALUES
('COMP-001', 'Laptop HP ProBook 450',
 'Laptop empresarial Intel Core i5, 8 GB RAM, 256 GB SSD',
 749990.00, 15, 5, 1, 1),

('RED-001',  'Switch TP-Link 24 puertos',
 'Switch administrable 24 puertos Gigabit Ethernet, montaje en rack',
 89990.00, 8, 3, 1, 2),

('PERI-001', 'Monitor LG 24" FHD',
 'Monitor LED 24 pulgadas Full HD 1920x1080, panel IPS',
 189990.00, 20, 5, 1, 3),

('COMP-002', 'Memoria RAM DDR4 8 GB',
 'Módulo de memoria RAM DDR4 2666 MHz, compatible con la mayoría de equipos',
 29990.00, 2, 10, 1, 1);   -- stock_actual (2) < stock_minimo (10)  → ALERTA

-- 6.4 Movimientos iniciales
INSERT INTO movimiento (fecha_hora, tipo, cantidad, observacion, id_producto, id_usuario) VALUES
('2026-11-01 09:00:00', 'ENTRADA', 15, 'Compra inicial de laptops — OC-2026-001', 1, 1),
('2026-11-05 10:30:00', 'ENTRADA',  8, 'Recepción switches pedido trimestral',   2, 1),
('2026-11-07 14:00:00', 'SALIDA',   3, 'Entrega a sala de servidores — Bodega',  2, 1);
