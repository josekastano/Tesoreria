-- --------------------------------------------------------|
-- SCRIPT ADSO ERP                                         |
-- Script de creacion de tablas del sistema automatizado   |
-- Autor: Carlos Eduardo Perez & equipo de ADSO 3171727    |
-- Versión: 1.0 - Julio de 2026                            |
-- --------------------------------------------------------|

CREATE EXTENSION IF NOT EXISTS pgcrypto;

-- -------------------------------------------------------------------
-- 1. CONFIGURACIÓN INICIAL
-- Establecer que si algo falla, se detenga el script inmediatamente.
-- -------------------------------------------------------------------
SET statement_timeout = 0;
SET client_encoding = 'UTF8';
SET standard_conforming_strings = on;
SET check_function_bodies = false;
SET client_min_messages = warning;
SET escape_string_warning = off;


--TABLAS EN LA LINEA 3830--


-- ----------------------------------------------
-- 3.3 BORRADO DE TABLAS PARA TESORERIA
-- ----------------------------------------------

DROP TABLE IF EXISTS tab_det_archivo_plano;
DROP TABLE IF EXISTS tab_enc_archivo_plano;
DROP TABLE IF EXISTS tab_det_cronopagos;
DROP TABLE IF EXISTS tab_enc_cronopagos;
DROP TABLE IF EXISTS tab_cuotasxfactura;
DROP TABLE IF EXISTS tab_cuentasxpagar;
DROP TABLE IF EXISTS tab_bancoxprov;
DROP TABLE IF EXISTS tab_ctas_empresa;
DROP TABLE IF EXISTS tab_pmtros_tescxp;
DROP TABLE IF EXISTS tab_det_caja_menor;
DROP TABLE IF EXISTS tab_enc_caja_menor;
DROP TABLE IF EXISTS tab_festivos;                              

-- ----------------------------------------------
-- 3.8 BORRADO DE TABLAS PARA COMPRAS
-- ----------------------------------------------

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

-- ----------------------------------------------
-- 3.10 BORRADO DE TABLAS GENERAL
-- ----------------------------------------------

DROP TABLE IF EXISTS tab_riesgos;
DROP TABLE IF EXISTS tab_areas;
DROP TABLE IF EXISTS tab_sesiones;
DROP TABLE IF EXISTS tab_menu_usuarios;
DROP TABLE IF EXISTS tab_menus;
DROP TABLE IF EXISTS tab_usuarios;
DROP TABLE IF EXISTS tab_bancos;
DROP TABLE IF EXISTS tab_terceros;
DROP TABLE IF EXISTS tab_tel_prefijo;
DROP TABLE IF EXISTS tab_tipo_identidad;
DROP TABLE IF EXISTS tab_cat_terceros;
DROP TABLE IF EXISTS tab_restricciones;
DROP TABLE IF EXISTS tab_ciudades;
DROP TABLE IF EXISTS tab_dptos;
DROP TABLE IF EXISTS tab_menu_palettes;
DROP TABLE IF EXISTS tab_pmtros_grales;
DROP TABLE IF EXISTS tab_audit_trail;
DROP TABLE IF EXISTS tab_cat_errores;
DROP TYPE  IF EXISTS DATOS_UBICACION;

-- ------------------------------------------------------------
-- 4. CREACIÓN DE TABLAS MAS USADAS
-- Estas tablas son esenciales y usadas por todos los módulos.
-- ------------------------------------------------------------

--ESTRUCTURA DE DATOS DE UBICACIÓN DE LOS TERCEROS DEL SISTEMA (Se usa en la tabla de terceros y en la tabla de parámetros generales para la empresa).

CREATE TYPE DATOS_UBICACION AS
(
	nom_corto           VARCHAR,             --nombre corto del lugar (ej: Barrio, Vereda, etc.)
    direccion           VARCHAR,             --direcion del tercero
    tel_fijo            DECIMAL,             --telefono fijo del tercero (7 a 10 dígitos dependiendo de la ciudad)
    id_prefijo_movil    DECIMAL,             --prefijo del celular del tercero
    tel_movil           DECIMAL,             --celular del tercero
    email               VARCHAR              --email del tercero
);

-- MANEJO DE ERRORES TRANSVERSAL (Tabla de códigos de error SQLSTATE y mensajes asociados).

CREATE TABLE tab_cat_errores (
    cod_sqlstate    VARCHAR     NOT NULL CHECK(LENGTH(cod_sqlstate) = 5),                        -- Codigo SQLSTATE del error
    mensaje         VARCHAR     NOT NULL CHECK(LENGTH(mensaje) >= 4 AND LENGTH(mensaje) <= 255), -- Mensaje descriptivo del error    
    PRIMARY KEY(cod_sqlstate)
);

-- 1. SEGURIDAD Y ACCESOS (Usuarios creados en Bases de Datos).

CREATE TABLE tab_usuarios
(
    id_usuario          VARCHAR         NOT NULL CHECK(LENGTH(id_usuario) >= 5),                                                -- Identificador único del usuario (ej: admin, jdoe, etc.)
    nom_usuario         VARCHAR         NOT NULL CHECK(LENGTH(nom_usuario) >= 8),                                               -- Nombre completo del usuario para mostrar en la interfaz 
    pass_usuario        VARCHAR         NOT NULL CHECK(LENGTH(pass_usuario) >= 12),                                             -- Se recomienda almacenar la contraseña en formato hash
    mail_usuario        VARCHAR         NOT NULL CHECK(mail_usuario ~* '^[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$'),     -- Email del usuario para notificaciones y recuperación de contraseña
    foto_usuario        VARCHAR         CHECK(LENGTH(foto_usuario) >= 3) DEFAULT 'sin_foto',                                    -- Foto del usuario si posee
    ind_usuario         BOOLEAN         NOT NULL DEFAULT FALSE,                                                                 -- TRUE= Si es administrador / FALSE= Es un usuario normal.
    ind_estado          BOOLEAN         NOT NULL DEFAULT TRUE,                                                                  -- Si está inhabilitado o modificado
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE,                                                                 --TRUE: Borrado lógico (Inactivo) / FALSE: Activo	           
    PRIMARY KEY(id_usuario)
);

-- 2. TABLA PARA CUMPLIMIENTO DE SESIÓN ÚNICA (Requerimientos).

CREATE TABLE tab_sesiones
(
    id_usuario          VARCHAR NOT NULL CHECK(LENGTH(id_usuario) >= 5),    -- ID del usuario que inició sesión (FK a tab_usuarios)
    token_sesion        VARCHAR NOT NULL check(LENGTH(token_sesion) >= 10),                   -- Token de sesión para validar sesión única
    fec_inicio          TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    ult_actividad       TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY(id_usuario),
    FOREIGN KEY(id_usuario) REFERENCES tab_usuarios(id_usuario) ON DELETE CASCADE
);

-- 3. TABLAS DE MENUS (Menus que se crean para asignar a los usuarios y evitar usar roles).

CREATE TABLE tab_menus
(
    id_menu             VARCHAR   NOT NULL,                                 -- Identificador único del menú (ej: 1, 11, 111, etc. para reflejar la jerarquía)
    nom_menu            VARCHAR   NOT NULL CHECK(LENGTH(nom_menu) <= 100),  -- Nombre del menú para mostrar en la interfaz (ej: Configuración, Parámetros, etc.)
    ind_id_padre        VARCHAR   NOT NULL,                                 -- Identificador del menú padre (ej: 0 para menús principales, 1 para submenús de Configuración, etc.)
    nom_programa        VARCHAR   NOT NULL DEFAULT 'no_aplica',             -- Nombre del programa o ruta que se ejecuta al hacer clic en el menú (ej: 'modules/compro/productos.php')
    PRIMARY KEY(id_menu)
);

-- 4. TABLAS DE MENUS POR USUARIO (para asignar menus sin necesidad de que todos los usuarios tengan los mismos).

CREATE TABLE tab_menu_usuarios
(
    id_usuario          VARCHAR NOT NULL CHECK(LENGTH(id_usuario) >= 5) REFERENCES tab_usuarios(id_usuario),    -- ID del usuario al que se le asigna el menú (FK a tab_usuarios)
    id_menu             VARCHAR NOT NULL REFERENCES tab_menus(id_menu),                                         -- ID del menú asignado al usuario (FK a tab_menus)
    PRIMARY KEY(id_usuario, id_menu)
);

-- 5. TABLA DE ÁREAS DE LA EMPRESA (Áreas creadas y asignadas a un responsable por su id de usuario del sistema).

CREATE TABLE IF NOT EXISTS tab_areas
(
    id_area             DECIMAL(5,0)    NOT NULL CHECK (id_area > 0),                                                       -- ID del área
    id_responsable      VARCHAR         NOT NULL CHECK(LENGTH(id_responsable) >= 5),                                        -- Usuario responsable del área
    nom_area            VARCHAR         NOT NULL CHECK (LENGTH(nom_area) >= 3),                                             -- Nombre del área (ej: Finanzas, Recursos Humanos, etc.)
    descrip_area        TEXT            NOT NULL DEFAULT 'Sin descripción de área',                                         -- Descripción detallada del área y sus funciones
    mail_area           VARCHAR         NOT NULL CHECK(mail_area ~* '^[A-Za-z0-9._%-]+@[A-Za-z0-9.-]+\.[A-Za-z]{2,4}$'),    -- Email corporativo del área
    tel_oficina         DECIMAL(10,0)   NOT NULL CHECK(tel_oficina >= 0 AND tel_oficina < 9999999999),                      -- Teléfono de la oficina del área
    ubi_oficina         VARCHAR         NOT NULL CHECK(LENGTH(ubi_oficina) >= 3),                                           -- Edificio, piso, oficina
    horario_atencion    VARCHAR         NOT NULL CHECK(LENGTH(horario_atencion) >= 3),                                      -- Lunes a Viernes 8am-5pm
    ind_estado          BOOLEAN         NOT NULL DEFAULT TRUE,                                                              -- Activo/Inactivo
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE, --TRUE: Borrado lógico (Inactivo) / FALSE: Activo	       
    PRIMARY KEY (id_area),
    FOREIGN KEY (id_responsable) REFERENCES tab_usuarios(id_usuario)
);

-- 6. TABLA PARAMETROS GENERALES

CREATE TABLE IF NOT EXISTS tab_pmtros_grales
(
    id_empresa	        VARCHAR(10)		NOT NULL,													                    --identificador de la empresa
    nom_empresa	        VARCHAR         NOT NULL CHECK(LENGTH(nom_empresa) >= 5 AND LENGTH(nom_empresa) <= 60),         --nombre de la empresa
    datos_residencia    DATOS_UBICACION,                                                                                --Estructura de datos de residencia de la empresa
    nom_replegal        VARCHAR	        NOT NULL CHECK(LENGTH(nom_replegal) >= 5 AND LENGTH(nom_replegal) <= 60),       --nombre del representante legal
    val_poriva	        DECIMAL(2,0)	NOT NULL CHECK(val_poriva   >= 0   AND val_poriva   < 100) DEFAULT 0,           --valor porcentaje iva
    val_pordesc	        DECIMAL(2,0)	NOT NULL CHECK(val_pordesc   >= 0   AND val_pordesc < 100) DEFAULT 0,           --valor porcentaje descuento
    val_porrete	        DECIMAL(2,0)	NOT NULL CHECK(val_porrete  >= 0   AND val_porrete < 100) DEFAULT 0,            --valor porcentaje retencion
    val_reteica	        DECIMAL(2,0)	NOT NULL CHECK(val_reteica  >= 0   AND val_reteica  < 100) DEFAULT 0,           --valor porcentaje reteica
    val_porutil	        DECIMAL(3,0)	NOT NULL CHECK(val_porutil  >= 0   AND val_porutil  <= 100) DEFAULT 0,          --valor porcentaje utilidad
    val_latitud	        DECIMAL(18,16)	NOT NULL CHECK(val_latitud  >= -4  AND val_latitud  <= 80),	                    --valor latitud
    val_longitud	    DECIMAL(18,16)	NOT NULL CHECK(val_longitud >= -80 AND val_longitud <= -50),                    --valor longitud
    anio_fiscal         DECIMAL(4,0)    NOT NULL,                                                                       --Año fiscal en el estamos actualmente
    mes_fiscal          DECIMAL(2,0)    NOT NULL,                                                                       --mes fiscal en el que estamos actualmente
    ind_autorete        BOOLEAN	        NOT NULL, --TRUE = autorete / FALSE = no autorete                               --indicador autoretenedor
    riesgo_arl          CHAR(1)         NOT NULL UNIQUE CHECK(LENGTH(riesgo_arl) >=1 AND LENGTH(riesgo_arl) <= 5),        -- 1 = 0.522%, 2 = 1.044%, 3 = 2.436%, 4 = 4.350%, 5 = 6.960% PORCENTAJE QUE DEBE PAGAR LA ARL POR EL RIESGO
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE, --TRUE: Borrado lógico (Inactivo) / FALSE: Activo	    --indicador de borrado lógico

    PRIMARY KEY(id_empresa),
    CONSTRAINT verificar_anio CHECK(anio_fiscal = EXTRACT (YEAR FROM CURRENT_DATE)), 
    CONSTRAINT verificar_mes  CHECK(mes_fiscal  = EXTRACT (MONTH FROM CURRENT_DATE)) 
);


CREATE TABLE IF NOT EXISTS tab_menu_palettes
(
    id_palette          VARCHAR(30)     NOT NULL,                                           -- Identificador único de la paleta (ej: 'blue_pro')
    nom_palette         VARCHAR(50)     NOT NULL,                                           -- Nombre descriptivo (ej: 'Azul Profesional')
    des_palette         TEXT            NOT NULL DEFAULT 'Sin descripción de la paleta',    -- Descripción de la paleta
    val_primary_color   VARCHAR(7)      NOT NULL DEFAULT '#1a1a2e',                       -- Color principal del menú (gradiente inicio)
    val_secondary_color VARCHAR(7)      NOT NULL DEFAULT '#16213e',                       -- Color secundario del menú (gradiente fin)
    val_accent_color    VARCHAR(7)      NOT NULL DEFAULT '#00d9ff',                       -- Color de acento (hover, active, iconos)
    val_text_color      VARCHAR(7)      NOT NULL DEFAULT '#ffffff',                       -- Color del texto en el menú superior
    val_hover_color     VARCHAR(7)      NOT NULL DEFAULT '#00d9ff',                       -- Color al pasar el mouse
    val_sidebar_bg      VARCHAR(7)      NOT NULL DEFAULT '#ffffff',                       -- Fondo del sidebar
    val_sidebar_text    VARCHAR(7)      NOT NULL DEFAULT '#555555',                       -- Color del texto en sidebar
    val_sidebar_hover   VARCHAR(7)      NOT NULL DEFAULT '#f4f7ff',                       -- Fondo al pasar el mouse
    val_active_bg       VARCHAR(7)      NOT NULL DEFAULT '#00aaff',                       -- Color del item activo
    num_orden           INTEGER         NOT NULL DEFAULT 0,                                 -- Orden de visualización
    ind_active          BOOLEAN         NOT NULL DEFAULT FALSE,                             -- Si es la paleta por defecto
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE,                             -- TRUE: Borrado lógico / FALSE: Activo
    PRIMARY KEY (id_palette)
);

-- 7. TABLA DEPARTAMENTOS DE COLOMBIA 

CREATE TABLE IF NOT EXISTS tab_dptos
(
    id_dpto	            VARCHAR         NOT NULL CHECK(LENGTH(id_dpto) = 2),                                            --identificador del departamento
    nom_dpto	        VARCHAR	        NOT NULL CHECK(LENGTH(nom_dpto) >= 4 AND LENGTH(nom_dpto) <= 20),               --nombre del departamento
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE, --TRUE: Borrado lógico (Inactivo) / FALSE: Activo       --indicador de borrado lógico
    PRIMARY KEY(id_dpto)
);


-- 8. TABLA CIUDADES DE COLOMBIA

CREATE TABLE IF NOT EXISTS tab_ciudades
(
    id_ciudad	        VARCHAR	        NOT NULL CHECK(LENGTH(id_ciudad) = 5),									    	--identificador de la ciudad									
    nom_ciudad	        VARCHAR	        NOT NULL CHECK(LENGTH(nom_ciudad) >= 3 AND LENGTH(nom_ciudad) <= 30), 		    --nombre de la ciudad
    id_dpto     	    VARCHAR	        NOT NULL CHECK(LENGTH(id_dpto) = 2),											--identidicador del departamento
    ind_capital	        BOOLEAN	        NOT NULL,   --True = capital / false = no capital							    --indicador de la capital
    cod_postal	        VARCHAR	        NOT NULL CHECK(LENGTH(cod_postal) = 6),										    --codigo postal
    val_latitud         DECIMAL(18,16)	NOT NULL CHECK(val_latitud >= -4    AND val_latitud <= 80), 					--valor latitud
    val_longitud        DECIMAL(18,16)  NOT NULL CHECK(val_longitud >= -80  AND val_longitud <= -50), 				    --valor longitud
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE, --TRUE: Borrado lógico (Inactivo) / FALSE: Activo	    --indicador de borrado lógico

    PRIMARY KEY(id_ciudad),
    FOREIGN KEY (id_dpto) REFERENCES tab_dptos(id_dpto)
);

-----------------------------------------------------------------------------------
-- TABLA DE CATEGORIAS DE TERCEROS                                      	     --
-----------------------------------------------------------------------------------		

CREATE TABLE IF NOT EXISTS tab_cat_terceros
(			
	id_cat_tercero 		DECIMAL(2,0)	NOT NULL CHECK(id_cat_tercero > 0 AND id_cat_tercero <= 99),					    --identificador de la categoria 
	nom_cat_tercero		VARCHAR			NOT NULL CHECK(LENGTH(nom_cat_tercero) >= 4 AND LENGTH(nom_cat_tercero) <= 50),	    --Nombre de la categoria tercero
	PRIMARY KEY(id_cat_tercero)			
);			
INSERT INTO tab_cat_terceros VALUES(1,'CLIENTE');			
INSERT INTO tab_cat_terceros VALUES(2,'VENDEDOR');			
INSERT INTO tab_cat_terceros VALUES(3,'EMPLEADO');			
INSERT INTO tab_cat_terceros VALUES(4,'LEAD');			
INSERT INTO tab_cat_terceros VALUES(5,'PROVEEDOR');			
			
-----------------------------------------------------------------------------------
-- TABLA DE RESTRICCIONES DE LOS TERCEROS, QUE IMPIDEN SU ACCESO AL SISTEMA	     --
-----------------------------------------------------------------------------------			
CREATE TABLE IF NOT EXISTS tab_restricciones			
(			
	id_restriccion		DECIMAL(2,0)	NOT NULL CHECK(id_restriccion > 0 AND id_restriccion <= 99),				        --identificador de la restrinción	
	nom_restriccion		VARCHAR			NOT NULL CHECK(LENGTH(nom_restriccion) >= 4 AND LENGTH(nom_restriccion) <= 50),	    --Nombre de la restrinción
	PRIMARY KEY(id_restriccion)			
);	
INSERT INTO tab_restricciones VALUES(99,'No aplica.');			
INSERT INTO tab_restricciones VALUES(1,'Finalización Contrato Mutuo Acuerdo');			
INSERT INTO tab_restricciones VALUES(2,'Vacaciones Colectivas');			
INSERT INTO tab_restricciones VALUES(3,'Inhabilidad Legal');			
INSERT INTO tab_restricciones VALUES(4,'Restricción Día Festivo');			

CREATE TABLE tab_tipo_identidad 
(
    id_tipo    	VARCHAR(5)   NOT NULL CHECK(id_tipo ~ '^[A-Z]{2,5}$'),                           --  tipo de documento
    nom_tipo    VARCHAR      NOT NULL check(LENGTH(nom_tipo) >= 5 AND LENGTH(nom_tipo) <= 50),    -- Nombre del tipo de documnento
    PRIMARY KEY(id_tipo)
);

CREATE TABLE tab_tel_prefijo
(
    id_prefijo  DECIMAL(4,0)    NOT NULL CHECK(id_prefijo > 0 AND id_prefijo <= 9999),
    nom_pais    VARCHAR(50)     NOT NULL CHECK(LENGTH(nom_pais) >= 4 AND LENGTH(nom_pais) <= 50),
    PRIMARY KEY(id_prefijo)
);

-------------------------
-- TABLA DE TERCEROS.  --
------------------------- 
--ES TRANSVERSAL. TODA PERSONA DEBE ESTAR REGISTRADA EN ESTA TABLA.			
-- TIENE EXTENSIONES COMO CLIENTES, VENDEDORES, EMPLEADO, ETC. Y CADA EXTENSIÓN TIENE LOS DATOS PARTICULARES			
CREATE TABLE IF NOT EXISTS tab_terceros			
(			
    id_tipo        		VARCHAR(5)      NOT NULL CHECK(id_tipo ~ '^[A-Z]{2,5}$'),                                   --  tipo de documento
    id_tercero          VARCHAR(10)     NOT NULL CHECK(id_tercero ~ '^[A-Z0-9]{7,10}$'),		                    --  identificador de terceros
	ind_tipo_tercero	BOOLEAN			NOT NULL, --TRUE:Jurídica / FALSE:Natural								    --  tipo de tercero si es persona natural o jurídica
	id_cat_tercero		DECIMAL(2,0)	NOT NULL CHECK(id_cat_tercero > 0 AND id_cat_tercero <= 99),			    --  identifiador de la categoria 
	nom_tercero			VARCHAR			NOT NULL CHECK(LENGTH(nom_tercero) >= 4 AND LENGTH(nom_tercero) <= 50),	    --  nombre del tercero
	dir_tercero			DATOS_UBICACION,																		    --  Estructura de   datos de ubicación de terceros
	id_ciudad			VARCHAR			NOT NULL ,																    --  identificador de ciudad
	id_restriccion		DECIMAL(2,0)	NOT NULL CHECK(id_restriccion > 0 AND id_restriccion <= 99),			    --  identificador de las restrincion
	ind_estado			BOOLEAN			NOT NULL, --TRUE:Activo ( FALSE:Inactivo)								    --  indicador de estado del tercero
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE, --TRUE: Borrado lógico (Inactivo) / FALSE: Activo   --  indicador de borrado lógico
	PRIMARY KEY(id_tercero),			
	FOREIGN KEY(id_ciudad)			REFERENCES tab_ciudades(id_ciudad),			
	FOREIGN KEY(id_cat_tercero)		REFERENCES tab_cat_terceros(id_cat_tercero),			
	FOREIGN KEY(id_restriccion)		REFERENCES tab_restricciones(id_restriccion),
    FOREIGN KEY(id_tipo)            REFERENCES tab_tipo_identidad(id_tipo)
);

-------------------------
-- TABLA DE BANCOS.    --
------------------------- 
CREATE TABLE IF NOT EXISTS tab_bancos
(
    id_banco			VARCHAR     	NOT NULL CHECK(LENGTH(id_banco) >= 6 AND (LENGTH(id_banco) <= 10)),														        --identificador del banco
    nom_banco			VARCHAR			NOT NULL CHECK(LENGTH(nom_banco) >= 4 AND LENGTH(nom_banco) <= 50),	        --nombre del banco
    ind_estado			BOOLEAN			NOT NULL, --TRUE:Activo ( FALSE:Inactivo)								    --indicador de estado del banco
    ind_borrado         BOOLEAN         NOT NULL DEFAULT FALSE, --TRUE: Borrado lógico (Inactivo) / FALSE: Activo   --indicador de borrado lógico
    PRIMARY KEY(id_banco)
);


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


----------------------------------------------
-- MÓDULO DE TESORERIA
-------------------------------------------
-----------------------
-- TABLA DE FESTIVOS --
-----------------------
-- TABLA 1: Días festivos
CREATE TABLE tab_festivos
(
    id_festivo         DECIMAL(4,0)         NOT NULL CHECK((id_festivo >= 1 AND id_festivo <= 9999)),                            -- Identificador del día festivo
    fecha              DATE                 NOT NULL DEFAULT CURRENT_DATE,                                                       -- Fecha de el día festivo
    nom_festivo        VARCHAR(40)          NOT NULL CHECK((LENGTH(nom_festivo) >= 3) AND (LENGTH(nom_festivo) <= 40)),          -- Nombre descriptivo
    ind_borrado        BOOLEAN              NOT NULL DEFAULT FALSE,                                                              -- TRUE: Borrado lógico (Inactivo) / FALSE: Activo
     
    PRIMARY KEY (id_festivo)
);

-------------------------
-- TABLA DE CAJA MENOR --
-------------------------
-- TABLA 2: Cajas Menores (fondos fijos asignados)
CREATE TABLE tab_enc_caja_menor
(
    id_caja_menor 		DECIMAL(10,0)		NOT NULL CHECK (id_caja_menor >= 0 AND id_caja_menor <= 9999999999),				 -- ID de caja (hasta 9,999,999,999)
    nom_caja_menor 		VARCHAR(30) 		NOT NULL CHECK (LENGTH(nom_caja_menor) >= 3 AND LENGTH(nom_caja_menor) <= 30),		 -- Nombre descriptivo
    monto_asignado 		DECIMAL(8,0) 		NOT NULL CHECK (monto_asignado >= 0 AND monto_asignado <= 99999999),				 -- Fondo fijo asignado
    monto_disponible 	DECIMAL(8,0) 		NOT NULL CHECK (monto_disponible >= 0 AND monto_disponible <= 99999999),			 -- Saldo disponible
    fecha_apertura 		DATE 				NOT NULL DEFAULT CURRENT_DATE,														 -- Fecha de creación
    fecha_cierre 		DATE,																									 -- Fecha de cierre (NULL si está activa)
    ind_estado_caja_m 	BOOLEAN 			NOT NULL DEFAULT TRUE,																 -- TRUE = Activa / FALSE = Cerrada
	
    PRIMARY KEY         (id_caja_menor),
	
    CONSTRAINT chk_caja_fechas              CHECK (fecha_cierre IS NULL OR fecha_cierre >= fecha_apertura),
    CONSTRAINT chk_caja_disponible          CHECK (monto_disponible <= monto_asignado)
);

----------------------------------------
-- TABLA DE MOVIMIENTOS DE CAJA MENOR --
----------------------------------------
-- TABLA 3: Movimientos de Caja Menor (ingresos y egresos)
CREATE TABLE tab_det_caja_menor
(
    id_caja_menor 		DECIMAL(10,0) 		NOT NULL CHECK (id_caja_menor >= 0 AND id_caja_menor <= 9999999999),                 -- ID de caja (hasta 9,999,999,999)
    id_movimiento 		DECIMAL(10,0) 		NOT NULL CHECK (id_movimiento > 0 AND id_movimiento <= 9999999999),                  -- ID del movimiento hecho en la caja
    concepto 			VARCHAR(200) 		NOT NULL CHECK (LENGTH(concepto) > 0 AND LENGTH(concepto) <= 200),                   -- Descripción del movimiento
    val_movimiento 		DECIMAL(8,0) 		NOT NULL CHECK (val_movimiento > 0 AND val_movimiento <= 99999999),                  -- Valor del movimiento
    fecha_movimiento 	DATE 				NOT NULL DEFAULT CURRENT_DATE,                                                       -- Fecha del movimiento
    ind_estado 			DECIMAL(1,0) 		NOT NULL DEFAULT 1 CHECK((ind_estado >= 1) AND (ind_estado <= 3)),                   -- 1 = Pendiente, 2 = Aprobado, 3 = Reembolsado

    PRIMARY KEY         (id_caja_menor, id_movimiento),
    FOREIGN KEY         (id_caja_menor)     REFERENCES tab_enc_caja_menor(id_caja_menor)
);
-- Consultas tipo: "fecha es la que se realizó un movimiento en la caja menor"
CREATE INDEX IF NOT EXISTS idx_mov_caja_fecha ON tab_det_caja_menor(fecha_movimiento);

-- Consultas tipo: "movimientos que se hayan realizado en una caja menor en específico"
CREATE INDEX IF NOT EXISTS idx_mov_caja_caja ON tab_det_caja_menor(id_caja_menor);

--------------------------------------------
-- TABLA DE PARÁMETROS DE TESORERÍA Y CXP --
--------------------------------------------
-- TABLA 4: Parámetros de Tesorería
CREATE TABLE tab_pmtros_tescxp
(
    id_empresa          VARCHAR             NOT NULL CHECK(LENGTH(id_empresa) >= 6 AND (LENGTH(id_empresa)) <= 10),              -- Identificador (NIT) de la empresa
    fec_diapago1        DECIMAL(1,0)        NOT NULL CHECK((fec_diapago1) >= 1 AND (fec_diapago1) <= 6),                         -- Día #1 en el que la empresa decide pagar
    fec_diapago2        DECIMAL(1,0)        NOT NULL CHECK((fec_diapago2) >= 1 AND (fec_diapago2) <= 6),                         -- Día #2 en el que la empresa decide pagar
    fec_diapago3        DECIMAL(1,0)        NOT NULL CHECK((fec_diapago3) >= 1 AND (fec_diapago3) <= 6),                         -- Día #3 en el que la empresa decide pagar
    val_min_reembolso   DECIMAL(8,0)        NOT NULL CHECK((val_min_reembolso >= 0) AND (val_min_reembolso <= 99999999)),        -- Valor mínimo de reembolso para crear otra caja menor
    ind_borrado         BOOLEAN             NOT NULL DEFAULT FALSE,                                                              -- TRUE: Borrado lógico (Inactivo) / FALSE: Activo

    PRIMARY KEY         (id_empresa),
    FOREIGN KEY         (id_empresa)        REFERENCES tab_pmtros_grales(id_empresa)                                                                                                   
);

------------------------------------
-- TABLA DE CUENTAS DE LA EMPRESA --
------------------------------------
-- TABLA 5: Cuentas de la empresa
CREATE TABLE tab_ctas_empresa
(
    id_empresa          VARCHAR             NOT NULL CHECK(LENGTH(id_empresa) >= 6 AND (LENGTH(id_empresa)) <= 10),              -- Identificador (NIT) de la empresa
    cta_empresa         VARCHAR             NOT NULL CHECK(LENGTH(cta_empresa) >= 10 AND LENGTH(cta_empresa) <= 16),             -- Número de cuenta bancaria de la empresa
    id_banco            VARCHAR             NOT NULL CHECK(LENGTH(id_banco) >= 6 AND (LENGTH(id_banco) <= 10)),                  -- Identificador (NIT) del banco
    ind_tipocuenta      BOOLEAN             NOT NULL DEFAULT FALSE,                                                              -- TRUE = Corriente / FALSE = Ahorros
    ind_borrado         BOOLEAN             NOT NULL DEFAULT FALSE,                                                              -- TRUE: Borrado lógico (Inactivo) / FALSE: Activo

    PRIMARY KEY         (id_empresa,cta_empresa),         
    FOREIGN KEY         (id_empresa)        REFERENCES tab_pmtros_tescxp(id_empresa),         
    FOREIGN KEY         (id_banco)          REFERENCES tab_bancos(id_banco)                                                                             
);

--------------------------------------------------
--        TABLA DE BANCOS POR PROVEEDORES       --
--------------------------------------------------
-- TABLA 6: Tabla de bancos por proveedores
CREATE TABLE IF NOT EXISTS tab_bancoxprov
(
    id_proveedor	    VARCHAR             NOT NULL CHECK(LENGTH(id_proveedor) >= 6  AND LENGTH(id_proveedor) <= 10), 	         -- Identificador (NIT) del proveedor
    cta_proveedor       VARCHAR             NOT NULL CHECK(LENGTH(cta_proveedor) >= 10  AND LENGTH(cta_proveedor) <= 16),        -- Número de cuenta bancaria del proveedor
    id_banco            VARCHAR             NOT NULL CHECK(LENGTH(id_banco) >= 6  AND LENGTH(id_banco) <= 10),                   -- Identificador (NIT) del banco  
    ind_tipocuenta      BOOLEAN             NOT NULL DEFAULT FALSE,                                                              -- TRUE = Corriente / FALSE = Ahorros
    ind_borrado         BOOLEAN             NOT NULL DEFAULT FALSE,                                                              -- TRUE: Borrado lógico (Inactivo) / FALSE: Activo

    PRIMARY KEY         (id_proveedor,cta_proveedor),                                                                                 
    FOREIGN KEY         (id_proveedor)      REFERENCES tab_proveedores(id_proveedor),
    FOREIGN KEY         (id_banco)          REFERENCES tab_bancos(id_banco)
);

--------------------------------
-- TABLA DE CUENTAS POR PAGAR --
--------------------------------
-- TABLA 7: Tabla de Cuentas por pagar
CREATE TABLE tab_cuentasxpagar
(
    id_factura          DECIMAL(8,0)        NOT NULL CHECK((id_factura) >= 1 AND (id_factura) <= 99999999),                     -- Identificador de la factura
    id_proveedor        VARCHAR   			NOT NULL CHECK(LENGTH(id_proveedor) >= 6 AND (LENGTH(id_proveedor) <= 10)),         -- Identificador (NIT) del proveedor
    fec_emision         DATE                NOT NULL DEFAULT CURRENT_DATE,                                                      -- Fecha de emisión de la factura
    fec_vencimiento     DATE                NOT NULL,                                                                           -- FECHA DE PAGO FACTURA (FECHA EMISIÓN + DIAS DE PAGO)
    val_factura         DECIMAL(10,0)       NOT NULL CHECK((val_factura) >= 0 AND (val_factura) <= 9999999999),                 -- Monto total de la factura
    val_saldo           DECIMAL(10,0)                CHECK((val_saldo >= 0) AND (val_saldo <= 9999999999)),                     -- Valor restante para terminar de pagar la factura
    num_cuotas          DECIMAL(2,0)        NOT NULL CHECK((num_cuotas) >= 1 AND (num_cuotas) <= 99),                           -- Número de cuotas totales en las que se acordó la factura
    ind_estado          BOOLEAN             NOT NULL DEFAULT FALSE,                                                             -- TRUE = Pagado O FALSE = En deuda

    PRIMARY KEY         (id_factura),                                                                                           
    FOREIGN KEY         (id_proveedor)      REFERENCES tab_proveedores(id_proveedor)    
);
-- Consultas tipo: "facturas pendientes de un proveedor"
CREATE INDEX idx_facturas_proveedor         ON tab_cuentasxpagar(id_proveedor);

-- Consultas tipo: "facturas vencidas hoy"
CREATE INDEX idx_facturas_vencimiento       ON tab_cuentasxpagar(fec_vencimiento);

-- Consultas tipo: "todas las facturas pendientes"
CREATE INDEX idx_facturas_estado            ON tab_cuentasxpagar(ind_estado);

------------------------------
-- TABLA DE CUOTAS FACTURAS --
------------------------------
-- TABLA 8: Tabla de cuotas por facturas
CREATE TABLE tab_cuotasxfactura
(
    id_factura          DECIMAL(8,0)        NOT NULL CHECK((id_factura) >= 1 AND (id_factura) <= 99999999),                     -- Identificador de la factura
    id_cuota            DECIMAL(2,0)        NOT NULL CHECK((id_cuota) >= 1 AND (id_cuota) <= 99),                               -- Número de cuota de la factura
    fec_vencimiento     DATE 				NOT NULL,                                                                           -- Fecha en la que vence cada cuota
    val_cuota           DECIMAL(10,0)       NOT NULL CHECK((val_cuota) >= 0 AND (val_cuota) <= 9999999999),                     -- Monto total de la factura
    ind_pagada          BOOLEAN             NOT NULL DEFAULT FALSE,                                                             -- TRUE = Pagado / FALSE = Pendiente

    PRIMARY KEY(id_factura,id_cuota),
    FOREIGN KEY(id_factura)                 REFERENCES tab_cuentasxpagar(id_factura)  
);
-- Consultas tipo: "cuotas pendientes de esta factura"
CREATE INDEX idx_cuotas_pagada ON tab_cuotasxfactura(ind_pagada);

-- Consultas tipo: "cuotas que vencen esta semana"
CREATE INDEX idx_cuotas_vencimiento ON tab_cuotasxfactura(fec_vencimiento);

------------------------------------------------
-- TABLA DE ENCABEZADO DE CRONOGRAMA DE PAGOS --
------------------------------------------------
-- TABLA 9: Tabla de encabezado cronograma de pagos
CREATE TABLE tab_enc_cronopagos
(
    id_cronograma       DECIMAL(10,0) 		NOT NULL CHECK((id_cronograma) >= 0 AND (id_cronograma) <= 9999999999),             -- Identificador del cronograma
    fec_programacion    DATE                NOT NULL,                                                                           -- Fecha para la cual se planificó pagar dicho cronograma
    total_a_pagar       DECIMAL(10,0)       NOT NULL CHECK((total_a_pagar) >= 0 AND (total_a_pagar) <= 9999999999),             -- Monto total a pagar por ese cronograma
    ind_estado          BOOLEAN             NOT NULL DEFAULT FALSE,                                                             -- TRUE = Pagado / FALSE = Pendiente

    PRIMARY KEY(id_cronograma)
);
-- Consultas tipo: "fecha en las que se va a realizar un cronograma"
CREATE INDEX idx_crono_fec_prog ON tab_enc_cronopagos(fec_programacion);

-- Consultas tipo: "cronogramas pendientes o pagados"
CREATE INDEX idx_crono_estado   ON tab_enc_cronopagos(ind_estado);

---------------------------------------------
-- TABLA DE DETALLE DE CRONOGRAMA DE PAGOS --
---------------------------------------------
-- TABLA 10: Tabla de detalle de cronograma de pagos
CREATE TABLE tab_det_cronopagos
(
    id_cronograma       DECIMAL(10,0) 		NOT NULL CHECK((id_cronograma) >= 0 AND (id_cronograma) <= 9999999999),             -- Identificador del cronograma
    id_factura          DECIMAL(8,0)        NOT NULL CHECK((id_factura) >= 1 AND (id_factura) <= 99999999),                     -- Identificador de la factura
    id_cuota            DECIMAL(2,0)        NOT NULL CHECK((id_cuota) >= 1 AND (id_cuota) <= 99),                               -- Número de cuota que se va a pagar en el cronograma
    val_a_pagar         DECIMAL(10,0)       NOT NULL CHECK((val_a_pagar) >= 0 AND (val_a_pagar) <= 9999999999),                 -- Monto a pagar por cada cuota factura

    PRIMARY KEY(id_cronograma,id_factura,id_cuota),

    FOREIGN KEY(id_cronograma)              REFERENCES tab_enc_cronopagos(id_cronograma),
    FOREIGN KEY(id_factura,id_cuota)        REFERENCES tab_cuotasxfactura(id_factura,id_cuota)
);

------------------------------------------
-- TABLA DE ENCABEZADO DE ARCHIVO PLANO --
------------------------------------------
-- TABLA 11: Tabla de encabezado de archivo plano
CREATE TABLE tab_enc_archivo_plano
(
    id_archivo_plano    DECIMAL(10,0) 		NOT NULL CHECK((id_archivo_plano) >= 0 AND (id_archivo_plano) <= 9999999999),       -- Identificador del archivo plano
    id_cronograma       DECIMAL(10,0) 		NOT NULL CHECK((id_cronograma) >= 0 AND (id_cronograma) <= 9999999999),             -- Identificador del cronograma
    id_banco            VARCHAR             NOT NULL CHECK(LENGTH(id_banco) >= 6 AND (LENGTH(id_banco) <= 10)),                 -- NIT del banco al cuál se va a generar el archivo plano, esto sirve para generar en distinto formato dependiendo el banco
    nom_archivo         VARCHAR(30)         NOT NULL CHECK(LENGTH(nom_archivo) >= 3 AND (LENGTH(nom_archivo) <= 30)),           -- Nombre del archivo plano
    fec_generacion      DATE,                                                                                                   -- Fecha de generación del archivo(NULL Si no se ha creado)
    ind_generado        BOOLEAN             NOT NULL DEFAULT FALSE,                                                             -- Indicador de generado del archivo

    PRIMARY KEY(id_archivo_plano),
    FOREIGN KEY(id_banco)                   REFERENCES tab_bancos(id_banco),
    FOREIGN KEY(id_cronograma)              REFERENCES tab_enc_cronopagos(id_cronograma)
);
-- Consultas tipo: "archivos planos que se hayan generado o no"
CREATE INDEX idx_archplano_generado ON tab_enc_archivo_plano(ind_generado);

------------------------------------
-- TABLA DE DETALLE ARCHIVO PLANO --
------------------------------------
-- TABLA 12: Tabla de detalle de archivo plano
CREATE TABLE tab_det_archivo_plano
(
    id_archivo_plano    DECIMAL(10,0) 		NOT NULL CHECK((id_archivo_plano) >= 0 AND (id_archivo_plano) <= 9999999999),       -- Identificador del archivo plano
    id_empresa          VARCHAR             NOT NULL CHECK(LENGTH(id_empresa) >= 6 AND (LENGTH(id_empresa)) <= 10),             -- Identificador (NIT) de la empresa
    cta_empresa         VARCHAR             NOT NULL CHECK(LENGTH(cta_empresa) >= 10 AND LENGTH(cta_empresa) <= 16),            -- Número de cuenta bancaria de la empresa de la cuál va a salir el dinero
    id_proveedor        VARCHAR   			NOT NULL CHECK(LENGTH(id_proveedor) >= 6 AND (LENGTH(id_proveedor) <= 10)),         -- Identificador (NIT) del proveedor al que se le va a pagar
    cta_proveedor       VARCHAR             NOT NULL CHECK(LENGTH(cta_proveedor) >= 10  AND LENGTH(cta_proveedor) <= 16),       -- Número de cuenta de destino para pagar, no se referencia de tab_bancoxprov para tener una trazabilidad y la cuenta no cambie en el archivo plano cuando el proveedor cambie su cuenta
    ind_tipocuenta      BOOLEAN             NOT NULL DEFAULT FALSE,                                                             -- TRUE = Corriente / FALSE = Ahorros
    id_factura          DECIMAL(8,0)        NOT NULL CHECK((id_factura) >= 1 AND (id_factura) <= 99999999),                     -- Identificador de la factura 
    id_cuota            DECIMAL(2,0)        NOT NULL CHECK((id_cuota) >= 1 AND (id_cuota) <= 99),                               -- Número de cuota que se va a pagar en el cronograma
    
    val_a_pagar         DECIMAL(10,0)       NOT NULL CHECK((val_a_pagar) >= 0 AND (val_a_pagar) <= 9999999999),                 -- Monto a pagar por cada cuota factura

    PRIMARY KEY(id_archivo_plano,id_factura,id_cuota),

    FOREIGN KEY(id_archivo_plano)           REFERENCES tab_enc_archivo_plano(id_archivo_plano),
    FOREIGN KEY(id_factura,id_cuota)        REFERENCES tab_cuotasxfactura(id_factura,id_cuota),
    FOREIGN KEY(id_proveedor,cta_proveedor) REFERENCES tab_bancoxprov(id_proveedor,cta_proveedor),
    FOREIGN KEY(id_empresa,cta_empresa)     REFERENCES tab_ctas_empresa(id_empresa,cta_empresa)
);

---------
-- 7. CONFIGURACIÓN DEL SEARCH_PATH (Ruta de Búsqueda)
-- Esto permite que los módulos accedan a 'public' sin prefijo.
-- Se establece el path por defecto para que las consultas busquen 
-- primero en 'public' y luego en el esquema actual de la sesión.
-- (Aunque las aplicaciones siempre deberían usar el prefijo por seguridad, 
-- esta es una configuración común).
-- ---------------------------------------------------

-- Ejemplo de configuración para un usuario específico (opcional, pero recomendado 
-- si se crean roles específicos para cada módulo).
-- ALTER USER app_user SET search_path TO "$user", public;

-- Configuración general de la base de datos (para quien no tenga un path definido)
--ALTER DATABASE db_erpadso SET search_path TO public, "$user";

-- ---------------------------------------------------------------------
-- 8. CREACIÓN DE TRIGGER DE AUDITORÍA PARA LAS TABLAS. ESTRUCTURA TYPE
------------------------------------------------------------------------

-- ---------------------------------------------------
-- 02_create_audit_trail.sql
-- Creación de la tabla de registros de auditoría 
-- y la función de trigger.
-- ---------------------------------------------------
-- DATA INICIAL DE PRUEBA
ALTER TABLE tab_usuarios ALTER COLUMN pass_usuario TYPE VARCHAR(255);
INSERT INTO tab_usuarios VALUES('admin','Administrador del Sistema',CRYPT('Administrador12345678!', GEN_SALT('bf')),
                                'admin@correo.edu',TRUE,TRUE)
ON CONFLICT (id_usuario) DO NOTHING;

-- CREACIÓN DE EXTENSIÓN PARA ENCRIPTACIÓN
CREATE EXTENSION IF NOT EXISTS pgcrypto;
-- ENCRIPTAR CLAVE DE ADMIN
UPDATE tab_usuarios SET pass_usuario = crypt('Administrador12345678!', gen_salt('bf')) 
WHERE id_usuario = 'admin';
-- ---------------------------------------------------
-- 9. CREAR LA TABLA CENTRAL DE REGISTROS DE AUDITORÍA
-- Ubicada en 'public' para un acceso sencillo desde todos los esquemas.
-- ---------------------------------------------------
CREATE TABLE tab_audit_trail
(
    id_auditoria            BIGSERIAL                   NOT NULL,
    id_usuario              VARCHAR,                                              -- Usuario ERP autenticado (tab_usuarios.id_usuario); NULL si no hubo sesión activa
    ip_conexion             VARCHAR,                                              -- IP de la conexión que ejecutó el cambio; se captura siempre que sea posible
    nom_operacion           CHAR(2)                     NOT NULL,                  -- Tipo de operación auditada
    fec_operacion           TIMESTAMP WITHOUT TIME ZONE NOT NULL DEFAULT CURRENT_TIMESTAMP,
    dato_viejo              JSONB                       NOT NULL,                 -- En UPDATE: solo columnas que cambiaron (OLD). En borrados: fila completa (OLD)
    dato_nuevo              JSONB                       NOT NULL,                 -- En UPDATE: solo columnas que cambiaron (NEW). En BORRADO_FISICO: se repite OLD
    nom_tabla               VARCHAR                     NOT NULL,                 -- Tabla origen del cambio (TG_TABLE_NAME)
    PRIMARY KEY (id_auditoria),
    CONSTRAINT chk_audit_usuario CHECK (id_usuario IS NOT NULL OR ip_conexion IS NOT NULL)
);

-- Índices de apoyo para las consultas más frecuentes de auditoría: por tabla+fecha, por usuario y por IP.
CREATE INDEX IF NOT EXISTS idx_audit_tabla_fecha ON tab_audit_trail (nom_tabla, fec_operacion DESC);

-- ----------------------------------
-- FUNCIÓN DE TRIGGER DE AUDITORÍA --
-- ----------------------------------
--select * from tab_audit_trail


CREATE OR REPLACE FUNCTION fun_audit_trail() RETURNS TRIGGER AS $$
DECLARE
    wid_usuario         tab_usuarios.id_usuario%TYPE;  -- Esto es TEXT
    wip                 VARCHAR;
    woperacion          varchar;
    wind_borrado        BOOLEAN;
    wold_borrado        BOOLEAN;
    wnew_borrado        BOOLEAN;
    old_diff            JSONB;
    new_diff            JSONB;
BEGIN
    -- 1. Recuperar el ID de usuario desde las variables de sesión (TEXTO)
    wid_usuario := COALESCE(current_setting('myapp.user_id', TRUE), '0');

    -- 2. Si no se recibió o es '0', asignar un usuario por defecto que EXISTA en tab_usuarios
    IF wid_usuario = '0' OR wid_usuario IS NULL OR wid_usuario = '' THEN
        wid_usuario := 'admin';   -- Cambia por un ID real que exista en tu tabla
    END IF;

    -- 3. IP
    wip := COALESCE(current_setting('myapp.user_ip', TRUE), '0.0.0.0');
	 IF wip = '::1' OR wip = '127.0.0.1' THEN
        wip := 'localhost';
    END IF;

    -- 4. Validar que el usuario exista en tab_usuarios (comparación TEXT vs TEXT)
    IF NOT EXISTS (SELECT 1 FROM tab_usuarios WHERE id_usuario = wid_usuario) THEN
        -- Si no existe, asignar un fallback (por ejemplo, 'admin')
        wid_usuario := 'admin';
        RAISE NOTICE 'Usuario % no encontrado, usando admin', wid_usuario;
    END IF;

    -- 5. Lógica para INSERT
    IF (TG_OP = 'INSERT') THEN 
        INSERT INTO tab_audit_trail (id_usuario, ip_conexion, nom_operacion, dato_viejo, dato_nuevo, nom_tabla)
        VALUES (wid_usuario, wip, 'INSERT', '{}'::jsonb, to_jsonb(NEW), TG_TABLE_NAME);
        RETURN NEW;
    END IF;

    -- 6. Lógica para DELETE
    IF (TG_OP = 'DELETE') THEN
        INSERT INTO tab_audit_trail (id_usuario, ip_conexion, nom_operacion, dato_viejo, dato_nuevo, nom_tabla)
        VALUES (wid_usuario, wip, 'DELETE FISICO', to_jsonb(OLD), '{}'::jsonb, TG_TABLE_NAME); 
        RETURN OLD;
    END IF;

    -- 7. Lógica para UPDATE (detectando borrado lógico)
    wind_borrado := (to_jsonb(NEW) ? 'ind_borrado');
    IF wind_borrado THEN
        wold_borrado := (to_jsonb(OLD)->>'ind_borrado')::BOOLEAN;
        wnew_borrado := (to_jsonb(NEW)->>'ind_borrado')::BOOLEAN;
        
        IF (wold_borrado = FALSE AND wnew_borrado = TRUE) THEN
            woperacion := 'BORRADO LOGICO';
        ELSIF (wold_borrado = TRUE AND wnew_borrado = FALSE) THEN
            woperacion := 'RESTAURAR';
        ELSE
            woperacion := 'UPDATE';
        END IF;
    ELSE
        woperacion := 'UPDATE'; 
    END IF;

    -- 8. Calcular diferencias (JSONB)
     IF woperacion IN ('U', 'BL', 'R') THEN
        IF to_jsonb(OLD) IS DISTINCT FROM to_jsonb(NEW) THEN
            SELECT jsonb_object_agg(o.key, o.value) INTO old_diff
            FROM jsonb_each(to_jsonb(OLD)) o
            WHERE to_jsonb(OLD) -> o.key IS DISTINCT FROM to_jsonb(NEW) -> o.key;
            
            SELECT jsonb_object_agg(n.key, n.value) INTO new_diff
            FROM jsonb_each(to_jsonb(NEW)) n
            WHERE to_jsonb(OLD) -> n.key IS DISTINCT FROM to_jsonb(NEW) -> n.key;
        ELSE
            old_diff := to_jsonb(OLD);
            new_diff := to_jsonb(NEW);
        END IF;
    ELSE
        old_diff := to_jsonb(OLD);
        new_diff := to_jsonb(NEW);
    END IF;

    -- 9. Enmascarar datos sensibles
    IF TG_TABLE_NAME = 'tab_usuarios' THEN
        old_diff := old_diff - 'pass_usuario';
        new_diff := new_diff - 'pass_usuario';
    END IF;

    -- 10. Insertar en la tabla de auditoría
    INSERT INTO tab_audit_trail (id_usuario, ip_conexion, nom_operacion, dato_viejo, dato_nuevo, nom_tabla)
    VALUES (wid_usuario, wip, woperacion, old_diff, new_diff, TG_TABLE_NAME);
    
    RETURN NEW;
END;
$$ LANGUAGE plpgsql SECURITY DEFINER;

-- SECURITY DEFINER: el trigger se ejecuta con los permisos de quien creó la función,
-- garantizando que cualquier usuario de la app pueda insertar en tab_audit_trail
-- sin necesidad de otorgarle permisos directos sobre esa tabla.

----------------------------------------------------------------
-- 10.1 CREACION DE TRIGGERS PARA AUDITORIAS TABLAS GENERALES --
---------------------------------------------------------------- 

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_dptos
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_ciudades
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_cat_terceros
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_restricciones
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_terceros
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_areas
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_menus
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_usuarios
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_bancos
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

--------------------------------------------------------------
-- 10.3 CREACION DE TRIGGERS PARA AUDITORIAS TABLAS COMPRAS --
--------------------------------------------------------------
CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_pmtros_compras
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_proveedores
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_eval_prov
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_productos
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_prodxprov
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_enc_solcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_det_solcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_enc_ordcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_det_ordcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_seg_ordcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_det_seg_ordcomp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

--------------------------------------------------------------------
-- 10.8 CREACION DE TRIGGERS PARA AUDITORIAS TABLAS TESORERÍA Y CXP --
--------------------------------------------------------------------
CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_det_archivo_plano
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_enc_archivo_plano
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_det_cronopagos
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_enc_cronopagos
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_cuotasxfactura
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_cuentasxpagar
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_bancoxprov
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_ctas_empresa
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_pmtros_tescxp
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_det_caja_menor
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_enc_caja_menor
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

CREATE OR REPLACE TRIGGER tri_audit_trail AFTER INSERT OR UPDATE OR DELETE ON tab_festivos
FOR EACH ROW EXECUTE FUNCTION fun_audit_trail();

