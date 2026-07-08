CREATE TABLE tab_pmtros_grales
(
    id_empresa          VARCHAR             NOT NULL CHECK(LENGTH(id_empresa) >= 6 AND (LENGTH(id_empresa)) <= 10) DEFAULT '222222222222',    -- Identificador (NIT) de la empresa
	nom_empresa			VARCHAR				NOT NULL,
    PRIMARY KEY         (id_empresa)                                                                                             
);

CREATE TABLE tab_bancos
(
    id_banco          	VARCHAR             NOT NULL CHECK(LENGTH(id_banco) >= 6 AND (LENGTH(id_banco)) <= 10),                         -- Identificador (NIT) de la empresa
	nom_banco		 	VARCHAR				NOT NULL,
	
    PRIMARY KEY         (id_banco)                                                                       
);

CREATE TABLE IF NOT EXISTS tab_proveedores
(
    id_proveedor	    VARCHAR             NOT NULL CHECK(LENGTH(id_proveedor) >= 6  AND LENGTH(id_proveedor) <= 10) DEFAULT '222222222222',   -- Identificador (NIT) del proveedor
	nom_proveedor		VARCHAR				NOT NULL,
	ind_dias_pago		DECIMAL				NOT NULL,
	ind_borrado			BOOLEAN				NOT NULL DEFAULT FALSE,
	
    PRIMARY KEY         (id_proveedor)   
);

CREATE TABLE IF NOT EXISTS tab_terceros
(
    id_tercero	    	VARCHAR             NOT NULL CHECK(LENGTH(id_tercero) >= 6  AND LENGTH(id_tercero) <= 10) DEFAULT '222222222222',   -- Identificador (NIT) del proveedor
	nom_tercero			VARCHAR				NOT NULL,
	ind_borrado			BOOLEAN				NOT NULL DEFAULT FALSE,
	
    PRIMARY KEY         (id_tercero)   
);
