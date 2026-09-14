--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE PARÁMETROS DE TESORERÍA Y CXP
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_pmtros_tescxp (wid_empresa         tab_pmtros_tescxp.id_empresa%TYPE,
                                                     wfec_diapago1       tab_pmtros_tescxp.fec_diapago1%TYPE,
                                                     wfec_diapago2       tab_pmtros_tescxp.fec_diapago2%TYPE,
                                                     wfec_diapago3       tab_pmtros_tescxp.fec_diapago3%TYPE,
                                                     wval_min_reembolso  tab_pmtros_tescxp.val_min_reembolso%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DE LA EMPRESA NO SEA NULO
    IF wid_empresa IS NULL THEN
        RAISE EXCEPTION 'El ID de la empresa no puede ser nulo.';
    END IF;

-- VALIDAR QUE LOS DÍAS DE PAGO NO SEAN NULOS
    IF wfec_diapago1 IS NULL THEN
        RAISE EXCEPTION 'El día de pago 1 no puede ser nulo.';
    END IF;

    IF wfec_diapago2 IS NULL THEN
        RAISE EXCEPTION 'El día de pago 2 no puede ser nulo.';
    END IF;

    IF wfec_diapago3 IS NULL THEN
        RAISE EXCEPTION 'El día de pago 3 no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL VALOR MÍNIMO DE REEMBOLSO NO SEA NULO
    IF wval_min_reembolso IS NULL THEN
        RAISE EXCEPTION 'El valor mínimo de reembolso no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ID DE LA EMPRESA EXISTA EN PARÁMETROS GENERALES
    IF NOT EXISTS (SELECT 1 FROM tab_pmtros_grales WHERE id_empresa = wid_empresa) THEN
        RAISE EXCEPTION 'La empresa % no existe en los parámetros.', wid_empresa;
    END IF;

-- VALIDAR QUE EL ID DE LA EMPRESA ESTÉ ENTRE 6 Y 10 CARACTERES
    IF LENGTH(wid_empresa) < 6 OR LENGTH(wid_empresa) > 10 THEN
        RAISE EXCEPTION 'El ID de la empresa debe tener entre 6 y 10 caracteres.';
    END IF;

-- VALIDAR QUE LOS DÍAS DE PAGO NO SEAN IGUALES
    IF wfec_diapago1 = wfec_diapago2 OR wfec_diapago1 = wfec_diapago3 OR wfec_diapago2 = wfec_diapago3 THEN
        RAISE EXCEPTION 'Los días de pago no pueden ser iguales entre sí.';
    END IF;

-- VALIDAR QUE LOS DÍAS DE PAGO ESTÉN ENTRE 1 Y 6 (lunes a sábado)
    IF wfec_diapago1 < 1 OR wfec_diapago1 > 6 THEN
        RAISE EXCEPTION 'El día de pago 1 debe estar entre 1 y 6.';
    END IF;

    IF wfec_diapago2 < 1 OR wfec_diapago2 > 6 THEN
        RAISE EXCEPTION 'El día de pago 2 debe estar entre 1 y 6.';
    END IF;

    IF wfec_diapago3 < 1 OR wfec_diapago3 > 6 THEN
        RAISE EXCEPTION 'El día de pago 3 debe estar entre 1 y 6.';
    END IF;

-- VALIDAR QUE EL VALOR MÍNIMO DE REEMBOLSO ESTÉ ENTRE 0 Y 99999999
    IF wval_min_reembolso < 0 OR wval_min_reembolso > 99999999 THEN
        RAISE EXCEPTION 'El valor mínimo de reembolso debe estar entre 0 y 99.999.999.';
    END IF;

-- SI TODO VA BIEN, SE INSERTA EN tab_pmtros_tescxp
    INSERT INTO tab_pmtros_tescxp (id_empresa, fec_diapago1, fec_diapago2, fec_diapago3, val_min_reembolso)
    VALUES (wid_empresa, wfec_diapago1, wfec_diapago2, wfec_diapago3, wval_min_reembolso);

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE FESTIVOS
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_festivos (wfecha          tab_festivos.fecha%TYPE,
                                                wnom_festivo    tab_festivos.nom_festivo%TYPE) RETURNS BOOLEAN AS
$BODY$

DECLARE wid_festivo tab_festivos.id_festivo%TYPE;

BEGIN

-- VALIDAR QUE LA FECHA NO SEA NULA
    IF wfecha IS NULL THEN
        RAISE EXCEPTION 'La fecha del festivo no puede ser nula.';
    END IF;

-- VALIDAR QUE EL NOMBRE DEL FESTIVO NO SEA NULO
    IF wnom_festivo IS NULL THEN
        RAISE EXCEPTION 'El nombre del festivo no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA FECHA NO SEA MENOR A LA ACTUAL
    IF wfecha < CURRENT_DATE THEN
        RAISE EXCEPTION 'La fecha del festivo no puede ser anterior a la fecha actual.';
    END IF;

-- VALIDAR QUE EL NOMBRE NO SEA VACÍO
    IF wnom_festivo = '' THEN
        RAISE EXCEPTION 'El nombre del festivo no puede estar vacío.';
    END IF;

-- VALIDAR QUE EL NOMBRE ESTÉ ENTRE 3 Y 50 CARACTERES
    IF LENGTH(wnom_festivo) < 3 OR LENGTH(wnom_festivo) > 50 THEN
        RAISE EXCEPTION 'El nombre del festivo debe tener entre 3 y 50 caracteres.';
    END IF;

-- SI TODO VA BIEN, SE INSERTA EN tab_festivos

    SELECT COALESCE(MAX(id_festivo), 0) + 1 INTO wid_festivo
    FROM tab_festivos;

    INSERT INTO tab_festivos (id_festivo, fecha, nom_festivo)
    VALUES                   (wid_festivo,wfecha,wnom_festivo);

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE CAJA MENOR
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_enc_caja_menor (wnom_caja_menor         tab_enc_caja_menor.nom_caja_menor%TYPE,
                                                      wmonto_asignado         tab_enc_caja_menor.monto_asignado%TYPE) RETURNS BOOLEAN AS
$BODY$

DECLARE wid_caja_menor tab_enc_caja_menor.id_caja_menor%TYPE;

BEGIN

-- VALIDAR QUE EL NOMBRE DE LA CAJA MENOR NO SEA NULO
    IF wnom_caja_menor IS NULL THEN
        RAISE EXCEPTION 'El nombre de la caja menor no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL MONTO ASIGNADO NO SEA NULO
    IF wmonto_asignado IS NULL THEN
        RAISE EXCEPTION 'El monto asignado no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL NOMBRE NO SEA VACÍO
    IF wnom_caja_menor = '' THEN
        RAISE EXCEPTION 'El nombre de la caja menor no puede estar vacío.';
    END IF;

-- VALIDAR QUE EL NOMBRE ESTÉ ENTRE 3 Y 30 CARACTERES
    IF LENGTH(wnom_caja_menor) < 3 OR LENGTH(wnom_caja_menor) > 30 THEN
        RAISE EXCEPTION 'El nombre de la caja menor debe tener entre 3 y 30 caracteres.';
    END IF;

-- VALIDAR QUE EL MONTO ASIGNADO ESTÉ ENTRE 0 Y 99999999
    IF wmonto_asignado < 0 OR wmonto_asignado > 99999999 THEN
        RAISE EXCEPTION 'El monto asignado debe estar entre 0 y 99.999.999.';
    END IF;

-- SI TODO VA BIEN, SE INSERTA EN tab_enc_caja_menor

    SELECT COALESCE(MAX(id_caja_menor), 0) + 1 INTO wid_caja_menor
    FROM tab_enc_caja_menor;

    INSERT INTO tab_enc_caja_menor (id_caja_menor, nom_caja_menor, monto_asignado)
    VALUES                         (wid_caja_menor, wnom_caja_menor, wmonto_asignado);

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE DETALLE DE CAJA MENOR
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_det_caja_menor (wid_caja_menor        tab_det_caja_menor.id_caja_menor%TYPE,
                                                      wconcepto             tab_det_caja_menor.concepto%TYPE,
                                                      wval_movimiento       tab_det_caja_menor.val_movimiento%TYPE,
                                                      wfecha_movimiento     tab_det_caja_menor.fecha_movimiento%TYPE) RETURNS BOOLEAN AS
$BODY$

DECLARE wdisponible     tab_enc_caja_menor.monto_disponible%TYPE;
DECLARE wid_movimiento  tab_det_caja_menor.id_movimiento%TYPE;

BEGIN

-- VALIDAR QUE EL ID DE LA CAJA MENOR NO SEA NULO
    IF wid_caja_menor IS NULL THEN
        RAISE EXCEPTION 'El ID de la caja menor no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL CONCEPTO NO SEA NULO
    IF wconcepto IS NULL THEN
        RAISE EXCEPTION 'El concepto no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL VALOR DEL MOVIMIENTO NO SEA NULO
    IF wval_movimiento IS NULL THEN
        RAISE EXCEPTION 'El valor del movimiento no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA FECHA DEL MOVIMIENTO NO SEA NULA
    IF wfecha_movimiento IS NULL THEN
        RAISE EXCEPTION 'La fecha del movimiento no puede ser nula.';
    END IF;    

-- VALIDAR QUE EL CONCEPTO NO SEA VACÍO
    IF wconcepto = '' THEN
        RAISE EXCEPTION 'El concepto no puede estar vacío.';
    END IF;

-- VALIDAR QUE EL CONCEPTO TENGA ENTRE 1 Y 200 CARACTERES
    IF LENGTH(wconcepto) < 3 OR LENGTH(wconcepto) > 200 THEN
        RAISE EXCEPTION 'El concepto debe tener entre 1 y 200 caracteres.';
    END IF;

-- VALIDAR QUE EL VALOR DEL MOVIMIENTO SEA MAYOR A 0 Y MENOR O IGUAL A 99999999
    IF wval_movimiento <= 0 OR wval_movimiento > 99999999 THEN
        RAISE EXCEPTION 'El valor del movimiento debe estar entre 1 y 99.999.999.';
    END IF;

-- VALIDAR QUE HAY SALDO SUFICIENTE EN LA CAJA MENOR
    SELECT monto_disponible INTO wdisponible FROM tab_enc_caja_menor WHERE id_caja_menor = wid_caja_menor;
    IF wval_movimiento > wdisponible THEN
        RAISE EXCEPTION 'El valor del movimiento (%) supera el monto disponible en la caja menor (%).', wval_movimiento, wdisponible;
    END IF;

-- SI TODO VA BIEN, SE INSERTA EN tab_det_caja_menor
    SELECT COALESCE(MAX(id_movimiento), 0) + 1 INTO wid_movimiento
    FROM tab_det_caja_menor;

    INSERT INTO tab_det_caja_menor (id_caja_menor, id_movimiento, concepto, val_movimiento, fecha_movimiento)
    VALUES                         (wid_caja_menor, wid_movimiento, wconcepto, wval_movimiento, wfecha_movimiento);

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE CUENTAS DE LA EMPRESA
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_ctas_empresa (wid_empresa         tab_ctas_empresa.id_empresa%TYPE,
                                                    wcta_empresa        tab_ctas_empresa.cta_empresa%TYPE,
                                                    wid_banco           tab_ctas_empresa.id_banco%TYPE,
                                                    wind_tipocuenta     tab_ctas_empresa.ind_tipocuenta%TYPE) RETURNS BOOLEAN AS
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

-- VALIDAR QUE EL ID DEL BANCO NO SEA NULO
    IF wid_banco IS NULL THEN
        RAISE EXCEPTION 'El ID del banco no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL INDICADOR DE TIPO DE CUENTA NO SEA NULO
    IF wind_tipocuenta IS NULL THEN
        RAISE EXCEPTION 'El indicador de tipo de cuenta no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA EMPRESA EXISTA EN tab_pmtros_tescxp (FK)
    IF NOT EXISTS (SELECT 1 FROM tab_pmtros_tescxp WHERE id_empresa = wid_empresa) THEN
        RAISE EXCEPTION 'La empresa % no existe en los parámetros de tesorería.', wid_empresa;
    END IF;

-- VALIDAR QUE NO EXISTA YA ESA COMBINACIÓN EMPRESA + CUENTA (PK COMPUESTA)
    IF EXISTS (SELECT 1 FROM tab_ctas_empresa WHERE id_empresa = wid_empresa AND cta_empresa = wcta_empresa) THEN
        RAISE EXCEPTION 'La cuenta % ya está registrada para la empresa %.', wcta_empresa, wid_empresa;
    END IF;

-- VALIDAR QUE EL BANCO EXISTA
    IF NOT EXISTS (SELECT 1 FROM tab_bancos WHERE id_banco = wid_banco) THEN
        RAISE EXCEPTION 'El banco % no existe.', wid_banco;
    END IF;

-- VALIDAR QUE EL ID DE LA EMPRESA TENGA ENTRE 6 Y 10 CARACTERES
    IF LENGTH(wid_empresa) < 6 OR LENGTH(wid_empresa) > 10 THEN
        RAISE EXCEPTION 'El ID de la empresa debe tener entre 6 y 10 caracteres.';
    END IF;

-- VALIDAR QUE EL NÚMERO DE CUENTA TENGA ENTRE 10 Y 16 CARACTERES
    IF LENGTH(wcta_empresa) < 10 OR LENGTH(wcta_empresa) > 16 THEN
        RAISE EXCEPTION 'El número de cuenta de la empresa debe tener entre 10 y 16 caracteres.';
    END IF;

-- VALIDAR QUE EL ID DEL BANCO TENGA ENTRE 6 Y 10 CARACTERES
    IF LENGTH(wid_banco) < 6 OR LENGTH(wid_banco) > 10 THEN
        RAISE EXCEPTION 'El ID del banco debe tener entre 6 y 10 caracteres.';
    END IF;

-- SI TODO VA BIEN, SE INSERTA EN tab_ctas_empresa
    INSERT INTO tab_ctas_empresa (id_empresa, cta_empresa, id_banco, ind_tipocuenta)
    VALUES (wid_empresa, wcta_empresa, wid_banco, wind_tipocuenta);

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE BANCOS POR PROVEEDORES
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_bancoxprov (wid_proveedor       tab_bancoxprov.id_proveedor%TYPE,
                                                  wcta_proveedor      tab_bancoxprov.cta_proveedor%TYPE,
                                                  wid_banco           tab_bancoxprov.id_banco%TYPE,
                                                  wind_tipocuenta     tab_bancoxprov.ind_tipocuenta%TYPE) RETURNS BOOLEAN AS
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

-- VALIDAR QUE EL ID DEL BANCO NO SEA NULO
    IF wid_banco IS NULL THEN
        RAISE EXCEPTION 'El ID del banco no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL INDICADOR DE TIPO DE CUENTA NO SEA NULO
    IF wind_tipocuenta IS NULL THEN
        RAISE EXCEPTION 'El indicador de tipo de cuenta no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL PROVEEDOR EXISTA
    IF NOT EXISTS (SELECT 1 FROM tab_proveedores WHERE id_proveedor = wid_proveedor) THEN
        RAISE EXCEPTION 'El proveedor % no existe.', wid_proveedor;
    END IF;

-- VALIDAR QUE EL BANCO EXISTA
    IF NOT EXISTS (SELECT 1 FROM tab_bancos WHERE id_banco = wid_banco) THEN
        RAISE EXCEPTION 'El banco % no existe.', wid_banco;
    END IF;

-- VALIDAR QUE NO EXISTA YA ESA COMBINACIÓN PROVEEDOR + CUENTA (PK COMPUESTA)
    IF EXISTS (SELECT 1 FROM tab_bancoxprov WHERE id_proveedor = wid_proveedor AND cta_proveedor = wcta_proveedor) THEN
        RAISE EXCEPTION 'La cuenta % ya está registrada para el proveedor %.', wcta_proveedor, wid_proveedor;
    END IF;

-- VALIDAR QUE EL ID DEL PROVEEDOR TENGA ENTRE 6 Y 10 CARACTERES
    IF LENGTH(wid_proveedor) < 6 OR LENGTH(wid_proveedor) > 10 THEN
        RAISE EXCEPTION 'El ID del proveedor debe tener entre 6 y 10 caracteres.';
    END IF;

-- VALIDAR QUE EL NÚMERO DE CUENTA TENGA ENTRE 10 Y 16 CARACTERES
    IF LENGTH(wcta_proveedor) < 10 OR LENGTH(wcta_proveedor) > 16 THEN
        RAISE EXCEPTION 'El número de cuenta del proveedor debe tener entre 10 y 16 caracteres.';
    END IF;

-- VALIDAR QUE EL ID DEL BANCO TENGA ENTRE 6 Y 10 CARACTERES
    IF LENGTH(wid_banco) < 6 OR LENGTH(wid_banco) > 10 THEN
        RAISE EXCEPTION 'El ID del banco debe tener entre 6 y 10 caracteres.';
    END IF;

-- SI TODO VA BIEN, SE INSERTA EN tab_bancoxprov
    INSERT INTO tab_bancoxprov (id_proveedor, cta_proveedor, id_banco, ind_tipocuenta)
    VALUES (wid_proveedor, wcta_proveedor, wid_banco, wind_tipocuenta);

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

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

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE ENCABEZADO DE CRONOGRAMA DE PAGOS
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_enc_cronopagos (wnom_cronograma     tab_enc_cronopagos.nom_cronograma%TYPE,
                                                      wfec_programacion   tab_enc_cronopagos.fec_programacion%TYPE) RETURNS BOOLEAN AS
$BODY$

DECLARE wid_cronograma      tab_enc_cronopagos.id_cronograma%TYPE;
        wtotal_a_pagar      tab_enc_cronopagos.total_a_pagar%TYPE := 0;

BEGIN

-- VALIDAR QUE LA FECHA DE PROGRAMACIÓN NO SEA NULA
    IF wfec_programacion IS NULL THEN
        RAISE EXCEPTION 'La fecha de programación no puede ser nula.';
    END IF;

-- VALIDAR QUE LA FECHA DE PROGRAMACIÓN SEA MAYOR O IGUAL A LA FECHA ACTUAL
    IF wfec_programacion < CURRENT_DATE THEN
        RAISE EXCEPTION 'La fecha de programación no puede ser anterior a la fecha actual.';
    END IF;

-- VALIDAR QUE LA FECHA DE PROGRAMACIÓN NO SEA NULA
    IF wnom_cronograma IS NULL THEN
        RAISE EXCEPTION 'El nombre del cronograma no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA FECHA DE PROGRAMACIÓN SEA MAYOR O IGUAL A LA FECHA ACTUAL
    IF wnom_cronograma = '' THEN
        RAISE EXCEPTION 'El nombre del cronograma no puede estar vacío.';
    END IF;

-- SI TODO VA BIEN, SE INSERTA EN tab_enc_cronopagos

    SELECT COALESCE(MAX(id_cronograma), 0) + 1 INTO wid_cronograma
    FROM tab_enc_cronopagos;

    INSERT INTO tab_enc_cronopagos (id_cronograma, nom_cronograma , fec_programacion, total_a_pagar)
    VALUES (wid_cronograma, wnom_cronograma, wfec_programacion, wtotal_a_pagar);

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE DETALLE DE CRONOGRAMA DE PAGOS
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_det_cronopagos (wid_cronograma      tab_det_cronopagos.id_cronograma%TYPE,
                                                      wid_factura         tab_det_cronopagos.id_factura%TYPE,
                                                      wid_cuota           tab_det_cronopagos.id_cuota%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DEL CRONOGRAMA NO SEA NULO
    IF wid_cronograma IS NULL THEN
        RAISE EXCEPTION 'El ID del cronograma no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ID DE LA FACTURA NO SEA NULO
    IF wid_factura IS NULL THEN
        RAISE EXCEPTION 'El ID de la factura no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ID DE LA CUOTA NO SEA NULO
    IF wid_cuota IS NULL THEN
        RAISE EXCEPTION 'El ID de la cuota no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL CRONOGRAMA EXISTA Y ESTÉ PENDIENTE
    IF NOT EXISTS (SELECT 1 FROM tab_enc_cronopagos WHERE id_cronograma = wid_cronograma AND ind_estado = FALSE) THEN
        RAISE EXCEPTION 'El cronograma % no existe o ya fue pagado.', wid_cronograma;
    END IF;

-- VALIDAR QUE LA CUOTA DE LA FACTURA EXISTA EN tab_cuotasxfactura (FK)
    IF NOT EXISTS (SELECT 1 FROM tab_cuotasxfactura WHERE id_factura = wid_factura AND id_cuota = wid_cuota) THEN
        RAISE EXCEPTION 'La cuota % de la factura % no existe.', wid_cuota, wid_factura;
    END IF;

-- VALIDAR QUE LA CUOTA NO ESTÉ YA PAGADA
    IF EXISTS (SELECT 1 FROM tab_cuotasxfactura WHERE id_factura = wid_factura AND id_cuota = wid_cuota AND ind_pagada = TRUE) THEN
        RAISE EXCEPTION 'La cuota % de la factura % ya fue pagada.', wid_cuota, wid_factura;
    END IF;

-- VALIDAR QUE NO EXISTA YA ESA COMBINACIÓN EN EL DETALLE DEL CRONOGRAMA (PK COMPUESTA)
    IF EXISTS (SELECT 1 FROM tab_det_cronopagos WHERE id_cronograma = wid_cronograma AND id_factura = wid_factura AND id_cuota = wid_cuota) THEN
        RAISE EXCEPTION 'La cuota % de la factura % ya está en el cronograma %.', wid_cuota, wid_factura, wid_cronograma;
    END IF;

-- SI TODO VA BIEN, SE INSERTA EN tab_det_cronopagos
    INSERT INTO tab_det_cronopagos (id_cronograma, id_factura, id_cuota)
    VALUES (wid_cronograma, wid_factura, wid_cuota);

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE ENCABEZADO DE ARCHIVO PLANO
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_enc_archivo_plano (wid_cronograma      tab_enc_archivo_plano.id_cronograma%TYPE,
                                                         wid_banco           tab_enc_archivo_plano.id_banco%TYPE,
                                                         wnom_archivo        tab_enc_archivo_plano.nom_archivo%TYPE) RETURNS BOOLEAN AS
$BODY$

DECLARE wid_archivo_plano	tab_enc_archivo_plano.id_archivo_plano%TYPE;

BEGIN

-- VALIDAR QUE EL ID DEL CRONOGRAMA NO SEA NULO
    IF wid_cronograma IS NULL THEN
        RAISE EXCEPTION 'El ID del cronograma no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ID DEL BANCO NO SEA NULO
    IF wid_banco IS NULL THEN
        RAISE EXCEPTION 'El ID del banco no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL NOMBRE DEL ARCHIVO NO SEA NULO
    IF wnom_archivo IS NULL THEN
        RAISE EXCEPTION 'El nombre del archivo plano no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL CRONOGRAMA EXISTA
    IF NOT EXISTS (SELECT 1 FROM tab_enc_cronopagos WHERE id_cronograma = wid_cronograma) THEN
        RAISE EXCEPTION 'El cronograma % no existe.', wid_cronograma;
    END IF;

-- VALIDAR QUE EL BANCO EXISTA
    IF NOT EXISTS (SELECT 1 FROM tab_bancos WHERE id_banco = wid_banco) THEN
        RAISE EXCEPTION 'El banco % no existe.', wid_banco;
    END IF;

-- VALIDAR QUE EL NOMBRE DEL ARCHIVO NO SEA VACÍO
    IF wnom_archivo = '' THEN
        RAISE EXCEPTION 'El nombre del archivo plano no puede estar vacío.';
    END IF;

-- VALIDAR QUE EL NOMBRE DEL ARCHIVO TENGA ENTRE 3 Y 30 CARACTERES
    IF LENGTH(wnom_archivo) < 3 OR LENGTH(wnom_archivo) > 30 THEN
        RAISE EXCEPTION 'El nombre del archivo plano debe tener entre 3 y 30 caracteres.';
    END IF;

-- SI TODO VA BIEN, SE INSERTA EN tab_enc_archivo_plano

    SELECT COALESCE(MAX(id_archivo_plano), 0) + 1 INTO wid_archivo_plano
    FROM tab_enc_archivo_plano;

    INSERT INTO tab_enc_archivo_plano (id_archivo_plano, id_cronograma, id_banco, nom_archivo)
    VALUES (wid_archivo_plano, wid_cronograma, wid_banco, wnom_archivo);

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE DETALLE DE ARCHIVO PLANO
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_det_archivo_plano (wid_archivo_plano   tab_det_archivo_plano.id_archivo_plano%TYPE,
                                                         wid_empresa         tab_det_archivo_plano.id_empresa%TYPE,
                                                         wcta_empresa        tab_det_archivo_plano.cta_empresa%TYPE,
                                                         wid_proveedor       tab_det_archivo_plano.id_proveedor%TYPE,
                                                         wcta_proveedor      tab_det_archivo_plano.cta_proveedor%TYPE,
                                                         wind_tipocuenta     tab_det_archivo_plano.ind_tipocuenta%TYPE,
                                                         wid_factura         tab_det_archivo_plano.id_factura%TYPE,
                                                         wid_cuota           tab_det_archivo_plano.id_cuota%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DEL ARCHIVO PLANO NO SEA NULO
    IF wid_archivo_plano IS NULL THEN
        RAISE EXCEPTION 'El ID del archivo plano no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ID DE LA EMPRESA NO SEA NULO
    IF wid_empresa IS NULL THEN
        RAISE EXCEPTION 'El ID de la empresa no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA CUENTA DE LA EMPRESA NO SEA NULA
    IF wcta_empresa IS NULL THEN
        RAISE EXCEPTION 'La cuenta de la empresa no puede ser nula.';
    END IF;

-- VALIDAR QUE EL ID DEL PROVEEDOR NO SEA NULO
    IF wid_proveedor IS NULL THEN
        RAISE EXCEPTION 'El ID del proveedor no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA CUENTA DEL PROVEEDOR NO SEA NULA
    IF wcta_proveedor IS NULL THEN
        RAISE EXCEPTION 'La cuenta del proveedor no puede ser nula.';
    END IF;

-- VALIDAR QUE EL ID DE LA FACTURA NO SEA NULO
    IF wid_factura IS NULL THEN
        RAISE EXCEPTION 'El ID de la factura no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ID DE LA CUOTA NO SEA NULO
    IF wid_cuota IS NULL THEN
        RAISE EXCEPTION 'El ID de la cuota no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ARCHIVO PLANO EXISTA Y NO ESTÉ GENERADO AÚN
    IF NOT EXISTS (SELECT 1 FROM tab_enc_archivo_plano WHERE id_archivo_plano = wid_archivo_plano AND ind_generado = FALSE) THEN
        RAISE EXCEPTION 'El archivo plano % no existe o ya fue generado.', wid_archivo_plano;
    END IF;

-- VALIDAR QUE NO EXISTA YA ESA COMBINACIÓN EN EL DETALLE (PK COMPUESTA)
    IF EXISTS (SELECT 1 FROM tab_det_archivo_plano WHERE id_archivo_plano = wid_archivo_plano AND id_factura = wid_factura AND id_cuota = wid_cuota) THEN
        RAISE EXCEPTION 'La cuota % de la factura % ya está en el archivo plano %.', wid_cuota, wid_factura, wid_archivo_plano;
    END IF;

-- VALIDAR QUE LA COMBINACIÓN EMPRESA + CUENTA EXISTA EN tab_ctas_empresa (FK)
    IF NOT EXISTS (SELECT 1 FROM tab_ctas_empresa WHERE id_empresa = wid_empresa AND cta_empresa = wcta_empresa) THEN
        RAISE EXCEPTION 'La cuenta % no está registrada para la empresa %.', wcta_empresa, wid_empresa;
    END IF;

-- VALIDAR QUE LA COMBINACIÓN PROVEEDOR + CUENTA EXISTA EN tab_bancoxprov (FK)
    IF NOT EXISTS (SELECT 1 FROM tab_bancoxprov WHERE id_proveedor = wid_proveedor AND cta_proveedor = wcta_proveedor) THEN
        RAISE EXCEPTION 'La cuenta % no está registrada para el proveedor %.', wcta_proveedor, wid_proveedor;
    END IF;

-- VALIDAR QUE LA CUOTA DE LA FACTURA EXISTA EN tab_cuotasxfactura (FK)
    IF NOT EXISTS (SELECT 1 FROM tab_cuotasxfactura WHERE id_factura = wid_factura AND id_cuota = wid_cuota) THEN
        RAISE EXCEPTION 'La cuota % de la factura % no existe.', wid_cuota, wid_factura;
    END IF;

-- VALIDAR QUE LA CUOTA NO ESTÉ YA PAGADA
    IF EXISTS (SELECT 1 FROM tab_cuotasxfactura WHERE id_factura = wid_factura AND id_cuota = wid_cuota AND ind_pagada = TRUE) THEN
        RAISE EXCEPTION 'La cuota % de la factura % ya fue pagada.', wid_cuota, wid_factura;
    END IF;

-- VALIDAR QUE LAS LONGITUDES DE LOS CAMPOS VARCHAR SEAN CORRECTAS
    IF LENGTH(wid_empresa) < 6 OR LENGTH(wid_empresa) > 10 THEN
        RAISE EXCEPTION 'El ID de la empresa debe tener entre 6 y 10 caracteres.';
    END IF;

    IF LENGTH(wcta_empresa) < 10 OR LENGTH(wcta_empresa) > 16 THEN
        RAISE EXCEPTION 'La cuenta de la empresa debe tener entre 10 y 16 caracteres.';
    END IF;

    IF LENGTH(wid_proveedor) < 6 OR LENGTH(wid_proveedor) > 10 THEN
        RAISE EXCEPTION 'El ID del proveedor debe tener entre 6 y 10 caracteres.';
    END IF;

    IF LENGTH(wcta_proveedor) < 10 OR LENGTH(wcta_proveedor) > 16 THEN
        RAISE EXCEPTION 'La cuenta del proveedor debe tener entre 10 y 16 caracteres.';
    END IF;


-- SI TODO VA BIEN, SE INSERTA EN tab_det_archivo_plano
    INSERT INTO tab_det_archivo_plano (id_archivo_plano, id_empresa, cta_empresa, id_proveedor, cta_proveedor, id_factura, id_cuota)
    VALUES (wid_archivo_plano, wid_empresa, wcta_empresa, wid_proveedor, wcta_proveedor, wid_factura, wid_cuota);
    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE INSERT DE MOTIVOS DE RECHAZO DE CUENTAS POR PAGAR
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_insert_motivos_rechazo (wid_motivo_rechazo   tab_motivos_rechazo.id_motivo_rechazo%TYPE,
                                                       wdes_motivo          tab_motivos_rechazo.des_motivo%TYPE,
                                                       wcod_bancario        tab_motivos_rechazo.cod_bancario%TYPE,
                                                       wind_borrado         tab_motivos_rechazo.ind_borrado%TYPE) RETURNS BOOLEAN AS

$BODY$
BEGIN

-- VALIDAR QUE EL ID DEL MOTIVO DE RECHAZO NO SEA NULO
    IF wid_motivo_rechazo IS NULL THEN
        RAISE EXCEPTION 'El ID del motivo de rechazo no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA DESCRIPCIÓN DEL MOTIVO NO SEA NULA
    IF wdes_motivo IS NULL THEN
        RAISE EXCEPTION 'La descripción del motivo no puede ser nula.';
    END IF;

-- VALIDAR QUE EL CÓDIGO BANCARIO NO SEA NULO
    IF wcod_bancario IS NULL THEN
        RAISE EXCEPTION 'El código bancario no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL INDICADOR DE BORRADO NO SEA NULO
    IF wind_borrado IS NULL THEN
        RAISE EXCEPTION 'El indicador de borrado no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL MOTIVO DE RECHAZO NO EXISTA YA (PK)
    IF EXISTS (SELECT 1 FROM tab_motivos_rechazo WHERE id_motivo_rechazo = wid_motivo_rechazo) THEN
        RAISE EXCEPTION 'El motivo de rechazo % ya existe.', wid_motivo_rechazo;
    END IF;

-- VALIDAR QUE EL CÓDIGO BANCARIO NO ESTÉ YA ASIGNADO A OTRO MOTIVO
    IF EXISTS (SELECT 1 FROM tab_motivos_rechazo WHERE cod_bancario = wcod_bancario) THEN
        RAISE EXCEPTION 'El código bancario % ya está asignado a otro motivo de rechazo.', wcod_bancario;
    END IF;

-- VALIDAR QUE LAS LONGITUDES DE LOS CAMPOS DECIMAL SEAN CORRECTAS
    IF wid_motivo_rechazo < 1 OR wid_motivo_rechazo > 999 THEN
        RAISE EXCEPTION 'El ID del motivo de rechazo debe tener entre 1 y 999 caracteres.';
    END IF;
    
-- VALIDAR QUE LAS LONGITUDES DE LOS CAMPOS VARCHAR SEAN CORRECTAS
    IF LENGTH(wdes_motivo) < 3 OR LENGTH(wdes_motivo) > 200 THEN
        RAISE EXCEPTION 'La descripción del motivo debe tener entre 3 y 200 caracteres.';
    END IF;

    IF LENGTH(wcod_bancario) < 1 OR LENGTH(wcod_bancario) > 10 THEN
        RAISE EXCEPTION 'El código bancario debe tener entre 1 y 10 caracteres.';
    END IF;

-- SI TODO VA BIEN, SE INSERTA EN tab_motivos_rechazo
    INSERT INTO tab_motivos_rechazo (id_motivo_rechazo, des_motivo, cod_bancario, ind_borrado)
    VALUES (wid_motivo_rechazo, wdes_motivo, wcod_bancario, wind_borrado);
    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;