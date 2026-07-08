--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE DELETE LÓGICO DE FESTIVOS
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_delete_festivos (wid_festivo     tab_festivos.id_festivo%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DEL FESTIVO NO SEA NULO
    IF wid_festivo IS NULL THEN
        RAISE EXCEPTION 'El ID del festivo no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL FESTIVO EXISTA Y NO ESTÉ YA BORRADO
    IF NOT EXISTS (SELECT 1 FROM tab_festivos WHERE id_festivo = wid_festivo AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'El festivo % no existe o ya se encuentra inactivo.', wid_festivo;
    END IF;

-- SI TODO VA BIEN, SE BORRA LÓGICAMENTE EN tab_festivos
    UPDATE tab_festivos
    SET    ind_borrado = TRUE
    WHERE  id_festivo  = wid_festivo;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE DELETE LÓGICO DE PARÁMETROS DE TESORERÍA Y CXP
-- NOTA: No se permite borrar si la empresa todavía tiene cuentas activas registradas
--       en tab_ctas_empresa (FK directa), ya que estas dependen de los parámetros de la empresa.
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_delete_pmtros_tescxp (wid_empresa     tab_pmtros_tescxp.id_empresa%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DE LA EMPRESA NO SEA NULO
    IF wid_empresa IS NULL THEN
        RAISE EXCEPTION 'El ID de la empresa no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA EMPRESA EXISTA Y NO ESTÉ YA BORRADA
    IF NOT EXISTS (SELECT 1 FROM tab_pmtros_tescxp WHERE id_empresa = wid_empresa AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'La empresa % no existe en los parámetros o ya se encuentra inactiva.', wid_empresa;
    END IF;

-- VALIDAR QUE LA EMPRESA NO TENGA CUENTAS ACTIVAS REGISTRADAS EN tab_ctas_empresa
    IF EXISTS (SELECT 1 FROM tab_ctas_empresa WHERE id_empresa = wid_empresa AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'La empresa % tiene cuentas activas registradas y no puede ser borrada.', wid_empresa;
    END IF;

-- SI TODO VA BIEN, SE BORRA LÓGICAMENTE EN tab_pmtros_tescxp
    UPDATE tab_pmtros_tescxp
    SET    ind_borrado = TRUE
    WHERE  id_empresa  = wid_empresa;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE DELETE LÓGICO DE CUENTAS DE LA EMPRESA
-- NOTA: No se permite borrar si la cuenta está siendo usada en un archivo plano
--       que aún no ha sido generado (ind_generado = FALSE).
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_delete_ctas_empresa (wid_empresa     tab_ctas_empresa.id_empresa%TYPE,
                                                    wcta_empresa    tab_ctas_empresa.cta_empresa%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DE LA EMPRESA NO SEA NULO
    IF wid_empresa IS NULL THEN
        RAISE EXCEPTION 'El ID de la empresa no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL NÚMERO DE CUENTA NO SEA NULO
    IF wcta_empresa IS NULL THEN
        RAISE EXCEPTION 'El número de cuenta de la empresa no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA CUENTA EXISTA Y NO ESTÉ YA BORRADA
    IF NOT EXISTS (SELECT 1 FROM tab_ctas_empresa WHERE id_empresa = wid_empresa AND cta_empresa = wcta_empresa AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'La cuenta % de la empresa % no existe o ya se encuentra inactiva.', wcta_empresa, wid_empresa;
    END IF;

-- VALIDAR QUE LA CUENTA NO ESTÉ SIENDO USADA EN UN ARCHIVO PLANO PENDIENTE (NO GENERADO)
    IF EXISTS (SELECT 1 FROM tab_det_archivo_plano AS a, tab_enc_archivo_plano AS b 
               WHERE  a.id_archivo_plano = b.id_archivo_plano
               AND    a.id_empresa  = wid_empresa
               AND    a.cta_empresa = wcta_empresa
               AND    b.ind_generado = FALSE) THEN
        RAISE EXCEPTION 'La cuenta % de la empresa % está siendo usada en un archivo plano pendiente y no puede ser borrada.', wcta_empresa, wid_empresa;
    END IF;

-- SI TODO VA BIEN, SE BORRA LÓGICAMENTE EN tab_ctas_empresa
    UPDATE tab_ctas_empresa
    SET    ind_borrado  = TRUE
    WHERE  id_empresa   = wid_empresa AND cta_empresa = wcta_empresa;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE DELETE LÓGICO DE BANCOS POR PROVEEDORES
-- NOTA: No se permite borrar si la cuenta está siendo usada en un archivo plano
--       que aún no ha sido generado (ind_generado = FALSE).
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_delete_bancoxprov (wid_proveedor     tab_bancoxprov.id_proveedor%TYPE,
                                                   wcta_proveedor    tab_bancoxprov.cta_proveedor%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DEL PROVEEDOR NO SEA NULO
    IF wid_proveedor IS NULL THEN
        RAISE EXCEPTION 'El ID del proveedor no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL NÚMERO DE CUENTA NO SEA NULO
    IF wcta_proveedor IS NULL THEN
        RAISE EXCEPTION 'El número de cuenta del proveedor no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA CUENTA EXISTA Y NO ESTÉ YA BORRADA
    IF NOT EXISTS (SELECT 1 FROM tab_bancoxprov WHERE id_proveedor = wid_proveedor AND cta_proveedor = wcta_proveedor AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'La cuenta % del proveedor % no existe o ya se encuentra inactiva.', wcta_proveedor, wid_proveedor;
    END IF;

-- VALIDAR QUE LA CUENTA NO ESTÉ SIENDO USADA EN UN ARCHIVO PLANO PENDIENTE (NO GENERADO)
    IF EXISTS (SELECT 1 FROM   tab_det_archivo_plano AS a, tab_enc_archivo_plano AS b 
               WHERE  a.id_archivo_plano = b.id_archivo_plano
               AND    a.id_proveedor  = wid_proveedor
               AND    a.cta_proveedor = wcta_proveedor
               AND    b.ind_generado = FALSE) THEN
        RAISE EXCEPTION 'La cuenta % del proveedor % está siendo usada en un archivo plano pendiente y no puede ser borrada.', wcta_proveedor, wid_proveedor;
    END IF;

-- SI TODO VA BIEN, SE BORRA LÓGICAMENTE EN tab_bancoxprov
    UPDATE tab_bancoxprov
    SET    ind_borrado   = TRUE
    WHERE  id_proveedor  = wid_proveedor AND cta_proveedor = wcta_proveedor;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE DELETE LÓGICO DE ENCABEZADO DE CRONOGRAMA DE PAGOS
-- NOTA: Solo se permite borrar un cronograma "vacío", es decir, que no tenga
--       detalle (tab_det_cronopagos) ni archivos planos (tab_enc_archivo_plano) asociados.
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_delete_enc_cronopagos (wid_cronograma     tab_enc_cronopagos.id_cronograma%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DEL CRONOGRAMA NO SEA NULO
    IF wid_cronograma IS NULL THEN
        RAISE EXCEPTION 'El ID del cronograma no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL CRONOGRAMA EXISTA Y NO ESTÉ YA BORRADO
    IF NOT EXISTS (SELECT 1 FROM tab_enc_cronopagos WHERE id_cronograma = wid_cronograma AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'El cronograma % no existe o ya se encuentra inactivo.', wid_cronograma;
    END IF;

-- VALIDAR QUE EL CRONOGRAMA NO TENGA DETALLE ASOCIADO EN tab_det_cronopagos
    IF EXISTS (SELECT 1 FROM tab_det_cronopagos WHERE id_cronograma = wid_cronograma) THEN
        RAISE EXCEPTION 'El cronograma % tiene detalle asociado y no puede ser borrado.', wid_cronograma;
    END IF;

-- VALIDAR QUE EL CRONOGRAMA NO TENGA ARCHIVOS PLANOS ASOCIADOS EN tab_enc_archivo_plano
    IF EXISTS (SELECT 1 FROM tab_enc_archivo_plano WHERE id_cronograma = wid_cronograma) THEN
        RAISE EXCEPTION 'El cronograma % tiene archivos planos asociados y no puede ser borrado.', wid_cronograma;
    END IF;

-- SI TODO VA BIEN, SE BORRA LÓGICAMENTE EN tab_enc_cronopagos
    UPDATE tab_enc_cronopagos
    SET    ind_borrado    = TRUE
    WHERE  id_cronograma  = wid_cronograma;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;