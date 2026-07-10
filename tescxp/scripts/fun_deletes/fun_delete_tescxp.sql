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
-- FUNCIÓN DE CIERRE DE ENCABEZADO DE CAJA MENOR 
--------------------------------------------------------------------------------------------------------------------------------------
--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE CIERRE (DELETE LÓGICO) DE ENCABEZADO DE CAJA MENOR
-- NOTA: No se elimina físicamente el registro. Se marca la caja como cerrada
--       (fecha_cierre = CURRENT_DATE, ind_estado_caja_m = FALSE). Solo se
--       permite cerrar una caja que no tenga movimientos sin resolver, es
--       decir, movimientos en estado Pendiente (1) o Aprobado (2) que aún
--       no hayan sido Reembolsados (3) a la caja.
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_delete_enc_caja_menor (wid_caja_menor      tab_enc_caja_menor.id_caja_menor%TYPE) RETURNS BOOLEAN AS
$BODY$

DECLARE wmovs_sin_resolver  INTEGER;

BEGIN

-- VALIDAR QUE EL ID DE LA CAJA MENOR NO SEA NULO
    IF wid_caja_menor IS NULL THEN
        RAISE EXCEPTION 'El ID de la caja menor no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA CAJA MENOR EXISTA, ESTÉ ACTIVA Y NO ESTÉ CERRADA
    IF NOT EXISTS (SELECT 1 FROM tab_enc_caja_menor WHERE id_caja_menor = wid_caja_menor AND ind_estado_caja_m = TRUE) THEN
        RAISE EXCEPTION 'La caja menor % no existe, está inactiva o ya fue cerrada.', wid_caja_menor;
    END IF;

-- VALIDAR QUE NO EXISTAN MOVIMIENTOS SIN RESOLVER (PENDIENTES O APROBADOS, AÚN NO REEMBOLSADOS)
    SELECT COUNT(id_movimiento) INTO wmovs_sin_resolver
    FROM   tab_det_caja_menor
    WHERE  id_caja_menor = wid_caja_menor AND ind_estado IN (1, 2);

    IF wmovs_sin_resolver > 0 THEN
        RAISE EXCEPTION 'La caja menor % tiene % movimiento(s) sin reembolsar. Debe reembolsarlos antes de cerrar la caja.', wid_caja_menor, wmovs_sin_resolver;
    END IF;

-- SI TODO VA BIEN, SE CIERRA LA CAJA EN tab_enc_caja_menor
    UPDATE tab_enc_caja_menor
    SET    fecha_cierre      = CURRENT_DATE,
           ind_estado_caja_m = FALSE
    WHERE  id_caja_menor     = wid_caja_menor;

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

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE DELETE FÍSICO DE UNA LÍNEA DE DETALLE DE CRONOGRAMA DE PAGOS
-- NOTA: tab_det_cronopagos NO tiene ind_borrado, por lo que el borrado es FÍSICO (DELETE).
--       Solo se permite borrar detalle de un cronograma ACTIVO, que esté PENDIENTE (no pagado)
--       y que no tenga archivos planos GENERADOS. Tras borrar la línea se RECALCULA el
--       total_a_pagar del encabezado con el detalle restante.
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_delete_det_cronopagos (wid_cronograma  tab_det_cronopagos.id_cronograma%TYPE,
                                                      wid_factura     tab_det_cronopagos.id_factura%TYPE,
                                                      wid_cuota       tab_det_cronopagos.id_cuota%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE LOS PARÁMETROS NO SEAN NULOS
    IF wid_cronograma IS NULL OR wid_factura IS NULL OR wid_cuota IS NULL THEN
        RAISE EXCEPTION 'El ID del cronograma, la factura y la cuota no pueden ser nulos.';
    END IF;

-- VALIDAR QUE EL CRONOGRAMA EXISTA Y ESTÉ ACTIVO
    IF NOT EXISTS (SELECT 1 FROM tab_enc_cronopagos WHERE id_cronograma = wid_cronograma AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'El cronograma % no existe o se encuentra inactivo.', wid_cronograma;
    END IF;

-- VALIDAR QUE EL CRONOGRAMA NO ESTÉ YA PAGADO
    IF EXISTS (SELECT 1 FROM tab_enc_cronopagos WHERE id_cronograma = wid_cronograma AND ind_estado = TRUE) THEN
        RAISE EXCEPTION 'El cronograma % ya se encuentra pagado y su detalle no puede ser modificado.', wid_cronograma;
    END IF;

-- VALIDAR QUE EL CRONOGRAMA NO TENGA ARCHIVOS PLANOS GENERADOS
    IF EXISTS (SELECT 1 FROM tab_enc_archivo_plano WHERE id_cronograma = wid_cronograma AND ind_generado = TRUE) THEN
        RAISE EXCEPTION 'El cronograma % tiene archivos planos generados y su detalle no puede ser modificado.', wid_cronograma;
    END IF;

-- VALIDAR QUE LA LÍNEA DE DETALLE EXISTA
    IF NOT EXISTS (SELECT 1 FROM tab_det_cronopagos WHERE id_cronograma = wid_cronograma AND id_factura = wid_factura AND id_cuota = wid_cuota) THEN
        RAISE EXCEPTION 'La cuota % de la factura % no existe en el detalle del cronograma %.', wid_cuota, wid_factura, wid_cronograma;
    END IF;

-- SI TODO VA BIEN, SE BORRA FÍSICAMENTE LA LÍNEA DE DETALLE
    DELETE FROM tab_det_cronopagos
    WHERE  id_cronograma = wid_cronograma
    AND    id_factura    = wid_factura
    AND    id_cuota      = wid_cuota;

-- SE RECALCULA EL TOTAL A PAGAR DEL ENCABEZADO CON EL DETALLE RESTANTE
    UPDATE tab_enc_cronopagos e
    SET    total_a_pagar = COALESCE((SELECT SUM(d.val_a_pagar)
                                     FROM   tab_det_cronopagos d
                                     WHERE  d.id_cronograma = e.id_cronograma), 0)
    WHERE  e.id_cronograma = wid_cronograma;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;