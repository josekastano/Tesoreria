--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE FACTURAS (actualizada: id_ordencompra opcional + validaciones cruzadas)
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_cuentasxpagar (wid_factura         tab_cuentasxpagar.id_factura%TYPE,
                                                     wid_proveedor       tab_cuentasxpagar.id_proveedor%TYPE,
                                                     wid_ordencompra     tab_cuentasxpagar.id_ordencompra%TYPE,
                                                     wfec_emision        tab_cuentasxpagar.fec_emision%TYPE,
                                                     wfec_vencimiento    tab_cuentasxpagar.fec_vencimiento%TYPE,
                                                     wval_factura        tab_cuentasxpagar.val_factura%TYPE,
                                                     wnum_cuotas         tab_cuentasxpagar.num_cuotas%TYPE) RETURNS BOOLEAN AS
$BODY$

DECLARE wordencompra_id_proveedor   tab_enc_ordcomp.id_proveedor%TYPE;
        wordencompra_ind_estado     tab_enc_ordcomp.ind_estado%TYPE;
        wordencompra_val_total      tab_enc_ordcomp.val_total%TYPE;
        wtotal_facturado            tab_cuentasxpagar.val_factura%TYPE;

BEGIN

-- VALIDAR QUE EL ID DE LA FACTURA NO SEA NULO
    IF wid_factura IS NULL THEN
        RAISE EXCEPTION 'El ID de la factura no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ID DEL PROVEEDOR NO SEA NULO
    IF wid_proveedor IS NULL THEN
        RAISE EXCEPTION 'El ID del proveedor no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA FECHA DE EMISIÓN NO SEA NULA
    IF wfec_emision IS NULL THEN
        RAISE EXCEPTION 'La fecha de emisión no puede ser nula.';
    END IF;

-- VALIDAR QUE LA FECHA DE VENCIMIENTO NO SEA NULA
    IF wfec_vencimiento IS NULL THEN
        RAISE EXCEPTION 'La fecha de vencimiento no puede ser nula.';
    END IF;

-- VALIDAR QUE EL VALOR DE LA FACTURA NO SEA NULO
    IF wval_factura IS NULL THEN
        RAISE EXCEPTION 'El valor de la factura no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL NÚMERO DE CUOTAS NO SEA NULO
    IF wnum_cuotas IS NULL THEN
        RAISE EXCEPTION 'El número de cuotas no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ID DE LA FACTURA NO EXISTA
    IF EXISTS (SELECT 1 FROM tab_cuentasxpagar WHERE id_factura = wid_factura) THEN
        RAISE EXCEPTION 'El ID de la factura % ya existe.', wid_factura;
    END IF;

-- VALIDAR QUE EL ID DE LA FACTURA ESTÉ ENTRE 1 Y 99999999
    IF wid_factura < 1 OR wid_factura > 99999999 THEN
        RAISE EXCEPTION 'El ID de la factura debe estar entre 1 y 99.999.999.';
    END IF;

-- VALIDAR QUE EL PROVEEDOR EXISTA
    IF NOT EXISTS (SELECT 1 FROM tab_proveedores WHERE id_proveedor = wid_proveedor) THEN
        RAISE EXCEPTION 'El proveedor % no existe.', wid_proveedor;
    END IF;

-- VALIDAR LA ORDEN DE COMPRA (CAMPO OPCIONAL: puede llegar NULL desde el front)
    IF wid_ordencompra IS NOT NULL THEN

        -- VALIDAR QUE EL ID DE LA ORDEN DE COMPRA SEA MAYOR A 0
        IF wid_ordencompra <= 0 THEN
            RAISE EXCEPTION 'El ID de la orden de compra debe ser mayor a 0.';
        END IF;

        -- VALIDAR QUE LA ORDEN EXISTA Y TRAER SUS DATOS PARA LAS VALIDACIONES CRUZADAS
        SELECT id_proveedor, ind_estado, val_total
        INTO   wordencompra_id_proveedor, wordencompra_ind_estado, wordencompra_val_total
        FROM   tab_enc_ordcomp
        WHERE  id_ordencompra = wid_ordencompra;

        IF NOT FOUND THEN
            RAISE EXCEPTION 'La orden de compra % no existe.', wid_ordencompra;
        END IF;

        -- VALIDAR QUE LA ORDEN PERTENEZCA AL MISMO PROVEEDOR DE LA FACTURA
        IF wordencompra_id_proveedor <> wid_proveedor THEN
            RAISE EXCEPTION 'La orden de compra % no pertenece al proveedor % (pertenece a %).', wid_ordencompra, wid_proveedor, wordencompra_id_proveedor;
        END IF;

        -- VALIDAR QUE LA ORDEN ESTÉ APROBADA (1=Aprobado, 2=Pendiente, 3=Anulado)
        IF wordencompra_ind_estado = 2 THEN
            RAISE EXCEPTION 'La orden de compra % está pendiente de aprobación y no puede facturarse.', wid_ordencompra;
        ELSIF wordencompra_ind_estado = 3 THEN
            RAISE EXCEPTION 'La orden de compra % está anulada y no puede facturarse.', wid_ordencompra;
        END IF;

        -- VALIDAR QUE LO YA FACTURADO CONTRA ESTA ORDEN + LA FACTURA NUEVA NO SUPERE EL VALOR TOTAL DE LA ORDEN
        SELECT COALESCE(SUM(val_factura), 0)
        INTO   wtotal_facturado
        FROM   tab_cuentasxpagar
        WHERE  id_ordencompra = wid_ordencompra;

        IF (wtotal_facturado + wval_factura) > wordencompra_val_total THEN
            RAISE EXCEPTION 'El valor de la factura supera el saldo disponible de la orden de compra % (disponible: %, valor total de la orden: %).',
                wid_ordencompra, (wordencompra_val_total - wtotal_facturado), wordencompra_val_total;
        END IF;

    END IF;

-- VALIDAR QUE LA FECHA DE EMISIÓN NO SEA FUTURA
    IF wfec_emision > CURRENT_DATE THEN
        RAISE EXCEPTION 'La fecha de emisión no puede ser mayor a la fecha actual.';
    END IF;

-- VALIDAR QUE LA FECHA DE VENCIMIENTO SEA MAYOR A LA FECHA DE EMISIÓN
    IF wfec_vencimiento < wfec_emision THEN
        RAISE EXCEPTION 'La fecha de vencimiento debe ser mayor a la fecha de emisión.';
    END IF;

-- VALIDAR QUE EL VALOR DE LA FACTURA ESTÉ ENTRE 0 Y 9999999999
    IF wval_factura < 0 OR wval_factura > 9999999999 THEN
        RAISE EXCEPTION 'El valor de la factura debe estar entre 0 y 9.999.999.999.';
    END IF;

-- VALIDAR QUE EL NÚMERO DE CUOTAS ESTÉ ENTRE 1 Y 99
    IF wnum_cuotas < 1 OR wnum_cuotas > 99 THEN
        RAISE EXCEPTION 'El número de cuotas debe estar entre 1 y 99.';
    END IF;

-- SI TODO VA BIEN, SE INSERTA EN tab_cuentasxpagar
-- (val_saldo lo asigna el trigger aparte, por eso no se incluye acá)
    INSERT INTO tab_cuentasxpagar (id_factura, id_proveedor, id_ordencompra, fec_emision, fec_vencimiento, val_factura, num_cuotas)
    VALUES                        (wid_factura, wid_proveedor, wid_ordencompra, wfec_emision, wfec_vencimiento, wval_factura, wnum_cuotas);

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

