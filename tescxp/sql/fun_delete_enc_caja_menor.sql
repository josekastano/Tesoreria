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
    SELECT COUNT(*) INTO wmovs_sin_resolver
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
