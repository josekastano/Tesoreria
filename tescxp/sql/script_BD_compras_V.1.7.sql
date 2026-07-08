-- ============================================================
-- MÓDULO DE COMPRAS Y PROVEEDORES - ESQUEMA COMPRO
-- ============================================================

-- -------------------------
-- DROP EN ORDEN CORRECTO --
-- -------------------------
DROP VIEW  IF EXISTS vw_semaforo_ordcomp;
DROP TABLE IF EXISTS tab_det_seg_ordcomp;
DROP TABLE IF EXISTS tab_seg_ordcomp;
DROP TABLE IF EXISTS tab_det_ordcomp;
DROP TABLE IF EXISTS tab_enc_ordcomp;
DROP TABLE IF EXISTS tab_det_solcomp;
DROP TABLE IF EXISTS tab_enc_solcomp;
DROP TABLE IF EXISTS tab_prodxprov;
DROP TABLE IF EXISTS tab_productos;
DROP TABLE IF EXISTS tab_eval_prov;
DROP TABLE IF EXISTS tab_proveedores;
DROP TABLE IF EXISTS tab_pmtros_compras;

-------------------------------------
-- MODULO DE COMPRAS Y PROVEEDORES --
-------------------------------------

-- TABLA DE PAREÁMETROS DE COMPRAS
CREATE TABLE tab_pmtros_compras 
(
    id_empresa           VARCHAR(10)     NOT NULL CHECK(id_empresa ~ '^[1-9][0-9]{7,9}$'),                                         -- Identificador de la empresa (ID empresa)
    val_puntaje_sel_prov SMALLINT        NOT NULL CHECK(val_puntaje_sel_prov >= 0 AND val_puntaje_sel_prov <= 100) DEFAULT 80,     -- Puntaje de selección de proveedor (0-100)
    PRIMARY KEY(id_empresa),
    FOREIGN KEY(id_empresa) REFERENCES tab_pmtros_grales(id_empresa)
);

-----------------------
-- TABLA PROVEEDORES --
-----------------------
CREATE TABLE IF NOT EXISTS tab_proveedores
(
    id_proveedor        VARCHAR(10)     NOT NULL CHECK(id_proveedor ~ '^[A-Z0-9]{7,10}$'),                                                                               -- Identificador del proveedor (ID empresa)
    val_sigla           VARCHAR         NOT NULL CHECK(LENGTH(val_sigla) >= 2 AND LENGTH(val_sigla) <= 10),                                                              -- Sigla del proveedor (ej. "PROV1")
    val_latitud         DECIMAL(18,16)  NOT NULL CHECK(val_latitud >= -4 AND val_latitud <= 80) DEFAULT  4.0000000000000000,                                             -- Latitud geográfica del proveedor
    val_longitud        DECIMAL(18,16)  NOT NULL CHECK(val_longitud >= -80 AND val_longitud <= -50) DEFAULT -72.0000000000000000,                                        -- Latitud geográfica del proveedor
    nom_contacto        VARCHAR         NOT NULL CHECK(LENGTH(nom_contacto) >= 5 AND LENGTH(nom_contacto) <= 60) DEFAULT 'Sin nombre del contacto',                      -- Nombre del contacto
    tel_contacto        DECIMAL(10,0)   NOT NULL CHECK(tel_contacto >= 0) DEFAULT 0,                                                                                     -- Teléfono del contacto
    nom_cont_contab     VARCHAR         NOT NULL CHECK(LENGTH(nom_cont_contab) >= 5 AND LENGTH(nom_cont_contab) <= 60) DEFAULT 'Sin nombre del contacto contabilidad',   -- Nombre del contacto contable
    tel_cont_contab     DECIMAL(10,0)   NOT NULL CHECK(tel_cont_contab >= 0) DEFAULT 0,                                                                                  -- Teléfono del contacto contable
    ind_dias_pago       DECIMAL(3,0)    NOT NULL CHECK(ind_dias_pago <= 90) DEFAULT 0,                                                                                   -- Días de pago acordados con el proveedor
    val_saldo_deuda     DECIMAL(11,0)   NOT NULL CHECK(val_saldo_deuda >= 0 AND val_saldo_deuda <= 99999999999) DEFAULT 0,                                               -- Saldo de deuda actual con el proveedor
    val_tiempo_entrega  DECIMAL(3,0)    NOT NULL CHECK(val_tiempo_entrega >= 0 AND val_tiempo_entrega <= 365) DEFAULT 0,                                                 -- Días de entrega del proveedor
    fec_registro        DATE            NOT NULL DEFAULT CURRENT_DATE,                                                                                                   -- Fecha de registro del proveedor
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE,                                                                                                          -- TRUE = Borrado / FALSE = Activo 
    PRIMARY KEY(id_proveedor),
    FOREIGN KEY(id_proveedor) REFERENCES tab_terceros(id_tercero)
);

-------------------------------------
-- TABLA EVALUACIÓN DE PROVEEDORES --
-------------------------------------
CREATE TABLE IF NOT EXISTS tab_eval_prov
(
    id_eva_prov         DECIMAL(3,0)    NOT NULL CHECK(id_eva_prov > 0 AND id_eva_prov <= 999),                            -- Identificador de la evaluación
    id_proveedor        VARCHAR(10)     NOT NULL CHECK(id_proveedor ~ '^[A-Z0-9]{7,10}$'),                                 -- Identificador del proveedor
    fec_evaluacion      DATE            NOT NULL DEFAULT CURRENT_DATE,                                                     -- Fecha de la evaluación
    val_punt_calidad    DECIMAL(3,0)    NOT NULL CHECK(val_punt_calidad >= 0 AND val_punt_calidad <= 100) DEFAULT 0,       -- Puntaje de calidad (0-100)
    val_punt_puntual    DECIMAL(3,0)    NOT NULL CHECK(val_punt_puntual >= 0 AND val_punt_puntual <= 100) DEFAULT 0,       -- Puntaje de puntualidad (0-100)
    val_puntaje_total   DECIMAL(4,2)    NOT NULL CHECK(val_puntaje_total >= 0 AND val_puntaje_total <= 100) DEFAULT 0,     -- Puntaje total (calculado por trigger)
    val_coments         TEXT            NOT NULL CHECK(LENGTH(TRIM(val_coments)) > 0)DEFAULT 'Sin comentarios',            -- Comentarios de la evaluación
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE,                                                            -- TRUE = Borrado / FALSE = Activo
    PRIMARY KEY(id_eva_prov),
    FOREIGN KEY(id_proveedor) REFERENCES tab_proveedores(id_proveedor)
);

---------------------
-- TABLA PRODUCTOS --
---------------------
CREATE TABLE IF NOT EXISTS tab_productos
(
    id_producto         DECIMAL(3,0)    NOT NULL CHECK(id_producto > 0 AND id_producto <= 999),                            -- Identificador del producto
    ind_tip_producto    BOOLEAN         NOT NULL,                                                                          -- TRUE = comercial / FALSE = interno
    ind_tipo_bien       BOOLEAN         NOT NULL,                                                                          -- TRUE = Bien  / FALSE = Servicio
    id_area             DECIMAL(5,0)    NOT NULL CHECK(id_area > 0) DEFAULT 1,                                             -- Área a la que pertenece el producto interno (1 = "Compras comerciales)"
    nom_producto        VARCHAR(60)     NOT NULL CHECK(LENGTH(nom_producto) >= 4 AND LENGTH(nom_producto) <= 60),          -- Nombre del producto
    val_poriva          DECIMAL(2,0)    NOT NULL CHECK(val_poriva >= 0 AND val_poriva < 100) DEFAULT 0,                    -- % IVA del producto
    val_exist           DECIMAL(3,0)    NOT NULL CHECK(val_exist >= 0 AND val_exist <= 999) DEFAULT 0,                     -- Existencias del producto
    val_venta           DECIMAL(11,0)   NOT NULL CHECK(val_venta >= 0 AND val_venta <= 99999999999) DEFAULT 0,             -- Valor de venta
    ind_disponible      BOOLEAN         NOT NULL DEFAULT FALSE,                                                            -- TRUE = Disponible para venta / FALSE = No disponible para venta
    ind_estado          BOOLEAN         NOT NULL DEFAULT TRUE,                                                             -- TRUE = Activo / FALSE = Inactivo
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE,                                                            -- TRUE = Borrado / FALSE = Activo
    PRIMARY KEY(id_producto)
);

-----------------------------------
-- TABLA PRODUCTOS POR PROVEEDOR --
-----------------------------------
CREATE TABLE IF NOT EXISTS tab_prodxprov
(
    id_proveedor        VARCHAR(10)     NOT NULL CHECK(id_proveedor ~ '^[A-Z0-9]{7,10}$'),                                 -- Identificador del proveedor
    id_producto         DECIMAL(3,0)    NOT NULL CHECK(id_producto > 0 AND id_producto <= 999),                            -- Identificador del producto
    val_costo           DECIMAL(11,0)   NOT NULL CHECK(val_costo >= 0 AND val_costo <= 99999999999) DEFAULT 0,             -- Costo del producto
    val_pordesc         DECIMAL(2,0)    NOT NULL CHECK(val_pordesc >= 0 AND val_pordesc < 100) DEFAULT 0,                  -- % Descuento del proveedor
    ind_disponible      BOOLEAN         NOT NULL DEFAULT TRUE,                                                             -- TRUE = Disponible para compra / FALSE = No disponible para compra
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE,                                                            -- TRUE = Borrado / FALSE = Activo
    PRIMARY KEY(id_proveedor, id_producto),
    FOREIGN KEY(id_proveedor) REFERENCES tab_proveedores(id_proveedor),
    FOREIGN KEY(id_producto)  REFERENCES tab_productos(id_producto)
);

-----------------------------------------------------------------
-- TABLA ENCABEZADO SOLICITUD DE COMPRA POR ÁREA (REQUISICIÓN) --
-----------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tab_enc_solcomp
(
    id_solcompra        DECIMAL(6,0)    NOT NULL CHECK(id_solcompra > 0),                                                  -- Identificador de la solicitud
    id_area             DECIMAL(5,0)    NOT NULL CHECK(id_area > 0),                                                       -- Área que solicita la compra
    fec_solicitud       DATE            NOT NULL DEFAULT CURRENT_DATE,                                                     -- Fecha de solicitud
    fec_requerida       DATE            NOT NULL,                                                                          -- Fecha requerida (>= fec_solicitud)
    val_justificacion   TEXT            NOT NULL CHECK(LENGTH(TRIM(val_justificacion)) > 0) DEFAULT 'Sin junstificación',  -- Justificación de la solicitud
    ind_estado          DECIMAL(1,0)    NOT NULL CHECK(ind_estado >= 1 AND ind_estado <= 3) DEFAULT 2,                     -- 1=Aprobado, 2=Pendiente, 3=Anulado
    PRIMARY KEY(id_solcompra),
    FOREIGN KEY(id_area) REFERENCES tab_areas(id_area),
    CONSTRAINT fec_requerida_valida CHECK(fec_requerida >= fec_solicitud)
);

------------------------------------------------
-- TABLA DETALLE SOLICITUD DE COMPRA POR ÁREA --
------------------------------------------------
CREATE TABLE IF NOT EXISTS tab_det_solcomp
(
    id_solcompra        DECIMAL(6,0)    NOT NULL CHECK(id_solcompra > 0),                                                  -- Identificador de la solicitud
    id_producto         DECIMAL(3,0)    NOT NULL CHECK(id_producto > 0 AND id_producto <= 999),                            -- Identificador del producto
    val_cantidad        DECIMAL(3,0)    NOT NULL CHECK(val_cantidad > 0 AND val_cantidad <= 999) DEFAULT 1,                -- Cantidad solicitada
    PRIMARY KEY(id_solcompra, id_producto),
    FOREIGN KEY(id_solcompra) REFERENCES tab_enc_solcomp(id_solcompra),
    FOREIGN KEY(id_producto)  REFERENCES tab_productos(id_producto)
);

-------------------------------------------
-- TABLA ENCABEZADO DE ÓRDENES DE COMPRA --
-------------------------------------------
CREATE TABLE IF NOT EXISTS tab_enc_ordcomp
(
    id_ordencompra      DECIMAL(6,0)    NOT NULL CHECK(id_ordencompra > 0),                                                -- Identificador de la orden de compra
    id_proveedor        VARCHAR(10)     NOT NULL CHECK(id_proveedor ~ '^[A-Z0-9]{7,10}$'),                                 -- Identificador del proveedor
    id_solcompra        DECIMAL(6,0)    NOT NULL DEFAULT 0,                                                                -- ID de solicitud de compra asociada (0 si no viene de una solicitud) 
    fec_emision         DATE            NOT NULL DEFAULT CURRENT_DATE,                                                     -- Fecha de emisión
    id_ciudad           VARCHAR         NOT NULL CHECK(LENGTH(id_ciudad) = 5),                                             -- Identificador de la ciudad
    ind_estado          DECIMAL(1,0)    NOT NULL CHECK(ind_estado >= 1 AND ind_estado <= 3) DEFAULT 2,                     -- 1=Aprobado, 2=Pendiente, 3=Anulado
    val_total           DECIMAL(11,0)   NOT NULL CHECK(val_total >= 0 AND val_total <= 99999999999) DEFAULT 0,             -- Valor total de la orden
    met_pago            BOOLEAN         NOT NULL DEFAULT FALSE,                                                            -- true = crédito, false = contado
    PRIMARY KEY(id_ordencompra),
    FOREIGN KEY(id_proveedor) REFERENCES tab_proveedores(id_proveedor),
    FOREIGN KEY(id_ciudad)    REFERENCES tab_ciudades(id_ciudad),
    FOREIGN KEY(id_solcompra) REFERENCES tab_enc_solcomp(id_solcompra)
);

----------------------------------------
-- TABLA DETALLE DE ÓRDENES DE COMPRA --
----------------------------------------
CREATE TABLE IF NOT EXISTS tab_det_ordcomp
(
    id_ordencompra      DECIMAL(6,0)    NOT NULL CHECK(id_ordencompra > 0),                                                -- Identificador de la orden de compra
    id_producto         DECIMAL(3,0)    NOT NULL CHECK(id_producto > 0 AND id_producto <= 999),                            -- Identificador del producto
    val_cantidad        DECIMAL(3,0)    NOT NULL CHECK(val_cantidad > 0 AND val_cantidad <= 999) DEFAULT 1,                -- Cantidad pedida
    val_descuento       DECIMAL(11,0)   NOT NULL CHECK(val_descuento >= 0 AND val_descuento <= 99999999999) DEFAULT 0,     -- Valor descuento (costo * %desc)
    val_iva             DECIMAL(11,0)   NOT NULL CHECK(val_iva >= 0 AND val_iva <= 99999999999) DEFAULT 0,                 -- Valor IVA
    val_neto            DECIMAL(11,0)   NOT NULL CHECK(val_neto >= 0 AND val_neto <= 99999999999) DEFAULT 0,               -- Valor neto
    PRIMARY KEY(id_ordencompra, id_producto),
    FOREIGN KEY(id_ordencompra) REFERENCES tab_enc_ordcomp(id_ordencompra),
    FOREIGN KEY(id_producto)    REFERENCES tab_productos(id_producto)
);

--------------------------------------------------
-- TABLA SEGUIMIENTO DE ÓRDENES DE COMPRA (ENC) --
--------------------------------------------------
CREATE TABLE IF NOT EXISTS tab_seg_ordcomp
(
    id_ordencompra      DECIMAL(6,0)    NOT NULL CHECK(id_ordencompra > 0),                                                -- Identificador de la orden de compra
    fec_aprobacion      DATE            NOT NULL DEFAULT CURRENT_DATE,                                                     -- Se llena via trigger cuando ind_estado cambia a 1
    fec_limite          DATE            NOT NULL,                                                                          -- fec_aprobacion + val_tiempo_entrega de tab_proveedores (se llena via trigger)
    ind_estado          DECIMAL(1,0)    NOT NULL CHECK(ind_estado >= 1 AND ind_estado <= 3) DEFAULT 1,                     -- 1=En espera, 2=Parcial, 3=Completo
    val_observacion     TEXT            NOT NULL  CHECK(LENGTH(TRIM(val_observacion)) > 0)DEFAULT 'Sin observaciones',                   -- Detalles si hubo problema
    PRIMARY KEY(id_ordencompra),
    FOREIGN KEY(id_ordencompra) REFERENCES tab_enc_ordcomp(id_ordencompra)
);

----------------------------------------------------
-- TABLA DETALLE SEGUIMIENTO DE ÓRDENES DE COMPRA --
----------------------------------------------------
CREATE TABLE IF NOT EXISTS tab_det_seg_ordcomp
(
    id_ordencompra      DECIMAL(6,0)    NOT NULL CHECK(id_ordencompra > 0),                                                -- Identificador de la orden de compra
    id_producto         DECIMAL(3,0)    NOT NULL CHECK(id_producto > 0 AND id_producto <= 999),                            -- Identificador del producto
    val_cant_esperada   DECIMAL(3,0)    NOT NULL CHECK(val_cant_esperada > 0 AND val_cant_esperada <= 999),                -- Cantidad pedida original (no cambia)
    val_cant_recibida   DECIMAL(3,0)    NOT NULL CHECK(val_cant_recibida >= 0 AND val_cant_recibida <= 999) DEFAULT 0,     -- Cantidad recibida acumulada
    fec_recepcion       DATE            NULL,                                                                              -- Fecha de última recepción (NULL si aún no llega)
    PRIMARY KEY(id_ordencompra, id_producto),
    FOREIGN KEY(id_ordencompra) REFERENCES tab_seg_ordcomp(id_ordencompra),
    FOREIGN KEY(id_producto)    REFERENCES tab_productos(id_producto),
    CONSTRAINT cant_valida CHECK(val_cant_recibida <= val_cant_esperada)                                                   -- No puede recibirse más de lo pedido
);

--------------------------------------------
-- VISTA SEMAFORIZACIÓN ÓRDENES DE COMPRA --
--------------------------------------------
CREATE OR REPLACE VIEW vw_semaforo_ordcomp AS
SELECT
    s.id_ordencompra,
    o.id_proveedor,
    s.fec_aprobacion,
    s.fec_limite,
    s.ind_estado,
    s.fec_limite - CURRENT_DATE AS dias_restantes,
    CASE
        WHEN s.ind_estado = 3                           THEN 'COMPLETO'  -- ⚪
        WHEN s.fec_limite - CURRENT_DATE < 0            THEN 'VENCIDO'   -- 🔴
        WHEN s.fec_limite - CURRENT_DATE <= 3           THEN 'CRITICO'   -- 🔴
        WHEN s.fec_limite - CURRENT_DATE <= 7           THEN 'PROXIMO'   -- 🟡
        ELSE                                                 'A TIEMPO'  -- 🟢
    END AS semaforo
FROM tab_seg_ordcomp s
JOIN tab_enc_ordcomp o ON s.id_ordencompra = o.id_ordencompra;

--------------------------------------------
-- VISTA SEMAFORIZACIÓN ÓRDENES DE COMPRA --
--------------------------------------------
CREATE OR REPLACE VIEW vw_semaforo_ordcomp AS
SELECT
    s.id_ordencompra,
    o.id_proveedor,
    s.fec_aprobacion,
    s.fec_limite,
    s.ind_estado,
    s.fec_limite - CURRENT_DATE AS dias_restantes,
    CASE
        WHEN s.ind_estado = 3                           THEN 'COMPLETO'  -- ⚪
        WHEN s.fec_limite - CURRENT_DATE < 0            THEN 'VENCIDO'   -- 🔴
        WHEN s.fec_limite - CURRENT_DATE <= 3           THEN 'CRITICO'   -- 🔴
        WHEN s.fec_limite - CURRENT_DATE <= 7           THEN 'PROXIMO'   -- 🟡
        ELSE                                                 'A TIEMPO'  -- 🟢
    END AS semaforo
FROM tab_seg_ordcomp s
JOIN tab_enc_ordcomp o ON s.id_ordencompra = o.id_ordencompra;

-------------------------------------------
-- LLAMADOS AL TRIGGER PARA LA AUDITORÍA --
-------------------------------------------
CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE ON tab_pmtros_compras
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE ON tab_proveedores
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE ON tab_eval_prov
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE ON tab_productos
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE ON tab_prodxprov
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE ON tab_enc_solcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE ON tab_det_solcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE ON tab_enc_ordcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE ON tab_det_ordcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE ON tab_seg_ordcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE ON tab_det_seg_ordcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();