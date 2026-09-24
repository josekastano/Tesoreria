--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE FESTIVOS
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_update_festivos (wid_festivo     tab_festivos.id_festivo%TYPE,
                                                wfecha          tab_festivos.fecha%TYPE,
                                                wnom_festivo    tab_festivos.nom_festivo%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DEL FESTIVO NO SEA NULO
    IF wid_festivo IS NULL THEN
        RAISE EXCEPTION 'El ID del festivo no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA FECHA NO SEA NULA
    IF wfecha IS NULL THEN
        RAISE EXCEPTION 'La fecha del festivo no puede ser nula.';
    END IF;

-- VALIDAR QUE EL NOMBRE DEL FESTIVO NO SEA NULO
    IF wnom_festivo IS NULL THEN
        RAISE EXCEPTION 'El nombre del festivo no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL FESTIVO EXISTA Y NO ESTÉ BORRADO
    IF NOT EXISTS (SELECT 1 FROM tab_festivos WHERE id_festivo = wid_festivo AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'El festivo % no existe o se encuentra inactivo.', wid_festivo;
    END IF;

-- VALIDAR QUE LA FECHA NO SEA MENOR A LA ACTUAL
    IF wfecha < CURRENT_DATE THEN
        RAISE EXCEPTION 'La fecha del festivo no puede ser anterior a la fecha actual.';
    END IF;

-- VALIDAR QUE EL NOMBRE NO SEA VACÍO
    IF wnom_festivo = '' THEN
        RAISE EXCEPTION 'El nombre del festivo no puede estar vacío.';
    END IF;

-- VALIDAR QUE EL NOMBRE ESTÉ ENTRE 3 Y 30 CARACTERES
    IF LENGTH(wnom_festivo) < 3 OR LENGTH(wnom_festivo) > 50 THEN
        RAISE EXCEPTION 'El nombre del festivo debe tener entre 3 y 50 caracteres.';
    END IF;

-- VALIDAR QUE NO EXISTA OTRO FESTIVO ACTIVO EN LA MISMA FECHA
    IF EXISTS (SELECT 1 FROM tab_festivos WHERE fecha = wfecha AND id_festivo <> wid_festivo AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'Ya existe otro festivo activo registrado en la fecha %.', wfecha;
    END IF;

-- SI TODO VA BIEN, SE ACTUALIZA EN tab_festivos
    UPDATE tab_festivos
    SET    fecha       = wfecha,
           nom_festivo = wnom_festivo
    WHERE  id_festivo  = wid_festivo;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE ENCABEZADO DE CAJA MENOR
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_update_enc_caja_menor (wid_caja_menor      tab_enc_caja_menor.id_caja_menor%TYPE,
                                                      wnom_caja_menor     tab_enc_caja_menor.nom_caja_menor%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DE LA CAJA MENOR NO SEA NULO
    IF wid_caja_menor IS NULL THEN
        RAISE EXCEPTION 'El ID de la caja menor no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL NOMBRE DE LA CAJA MENOR NO SEA NULO
    IF wnom_caja_menor IS NULL THEN
        RAISE EXCEPTION 'El nombre de la caja menor no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA CAJA MENOR EXISTA, ESTÉ ACTIVA Y NO ESTÉ CERRADA
    IF NOT EXISTS (SELECT 1 FROM tab_enc_caja_menor WHERE id_caja_menor = wid_caja_menor AND ind_estado_caja_m = TRUE) THEN
        RAISE EXCEPTION 'La caja menor % no existe, está inactiva o ya fue cerrada.', wid_caja_menor;
    END IF;

-- VALIDAR QUE EL NOMBRE NO SEA VACÍO
    IF wnom_caja_menor = '' THEN
        RAISE EXCEPTION 'El nombre de la caja menor no puede estar vacío.';
    END IF; 

-- VALIDAR QUE EL NOMBRE ESTÉ ENTRE 3 Y 50 CARACTERES
    IF LENGTH(wnom_caja_menor) < 3 OR LENGTH(wnom_caja_menor) > 50 THEN
        RAISE EXCEPTION 'El nombre de la caja menor debe tener entre 3 y 50 caracteres.';
    END IF;

-- SI TODO VA BIEN, SE ACTUALIZA EN tab_enc_caja_menor
    UPDATE tab_enc_caja_menor
    SET    nom_caja_menor = wnom_caja_menor
    WHERE  id_caja_menor  = wid_caja_menor;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE DETALLE DE CAJA MENOR
-- NOTA: Al marcar un movimiento como Reembolsado (3), se devuelve (suma) val_movimiento
--       al monto_disponible de la caja menor correspondiente.
--------------------------------------------------------------------------------------------------------------------------------------
--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE DETALLE DE CAJA MENOR
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_update_det_caja_menor (wid_caja_menor      tab_det_caja_menor.id_caja_menor%TYPE,
                                                      wid_movimiento      tab_det_caja_menor.id_movimiento%TYPE,
                                                      wind_estado         tab_det_caja_menor.ind_estado%TYPE) RETURNS BOOLEAN AS
$BODY$

DECLARE wind_estado_actual  tab_det_caja_menor.ind_estado%TYPE;

BEGIN

-- VALIDAR QUE EL ID DE LA CAJA MENOR NO SEA NULO
    IF wid_caja_menor IS NULL THEN
        RAISE EXCEPTION 'El ID de la caja menor no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ID DEL MOVIMIENTO NO SEA NULO
    IF wid_movimiento IS NULL THEN
        RAISE EXCEPTION 'El ID del movimiento no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL INDICADOR DE ESTADO NO SEA NULO
    IF wind_estado IS NULL THEN
        RAISE EXCEPTION 'El indicador de estado no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL MOVIMIENTO EXISTA Y OBTENER SU ESTADO ACTUAL
    SELECT ind_estado INTO wind_estado_actual
    FROM   tab_det_caja_menor
    WHERE  id_caja_menor = wid_caja_menor AND id_movimiento = wid_movimiento;

    IF wind_estado_actual IS NULL THEN
        RAISE EXCEPTION 'El movimiento % de la caja menor % no existe.', wid_movimiento, wid_caja_menor;
    END IF;

-- VALIDAR QUE EL MOVIMIENTO NO ESTÉ YA REEMBOLSADO (ESTADO FINAL)
    IF wind_estado_actual = 3 THEN
        RAISE EXCEPTION 'El movimiento % ya fue reembolsado y no puede modificarse.', wid_movimiento;
    END IF;

-- VALIDAR QUE EL INDICADOR DE ESTADO ESTÉ ENTRE 1 Y 3
    IF wind_estado < 1 OR wind_estado > 3 THEN
        RAISE EXCEPTION 'El indicador de estado debe ser 1 (Pendiente), 2 (Aprobado) o 3 (Reembolsado).';
    END IF;

-- VALIDAR QUE NO SE INTENTE RETROCEDER EL ESTADO (1 -> 2 -> 3, no al revés)
    IF wind_estado < wind_estado_actual THEN
        RAISE EXCEPTION 'No se puede regresar el movimiento del estado % al estado %.', wind_estado_actual, wind_estado;
    END IF;

-- SE ACTUALIZA EL ESTADO EN tab_det_caja_menor
-- (el ajuste de monto_disponible lo hace automáticamente el trigger
--  trg_actualizar_disponible_caja_menor al detectar el cambio de ind_estado,
--  ya no se hace manualmente aquí)
    UPDATE tab_det_caja_menor
    SET    ind_estado    = wind_estado
    WHERE  id_caja_menor = wid_caja_menor AND id_movimiento = wid_movimiento;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;


--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE PARÁMETROS DE TESORERÍA Y CXP
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_update_pmtros_tescxp (wid_empresa         tab_pmtros_tescxp.id_empresa%TYPE,
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

-- VALIDAR QUE LA EMPRESA EXISTA Y NO ESTÉ BORRADA
    IF NOT EXISTS (SELECT 1 FROM tab_pmtros_tescxp WHERE id_empresa = wid_empresa AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'La empresa % no existe en los parámetros o se encuentra inactiva.', wid_empresa;
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

-- SI TODO VA BIEN, SE ACTUALIZA EN tab_pmtros_tescxp
    UPDATE tab_pmtros_tescxp
    SET    fec_diapago1      = wfec_diapago1,
           fec_diapago2      = wfec_diapago2,
           fec_diapago3      = wfec_diapago3,
           val_min_reembolso = wval_min_reembolso
    WHERE  id_empresa        = wid_empresa;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE CUENTAS DE LA EMPRESA
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_update_ctas_empresa (wid_empresa         tab_ctas_empresa.id_empresa%TYPE,
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

-- VALIDAR QUE LA CUENTA DE LA EMPRESA EXISTA Y NO ESTÉ BORRADA
    IF NOT EXISTS (SELECT 1 FROM tab_ctas_empresa WHERE id_empresa = wid_empresa AND cta_empresa = wcta_empresa AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'La cuenta % de la empresa % no existe o se encuentra inactiva.', wcta_empresa, wid_empresa;
    END IF;

-- VALIDAR QUE EL BANCO EXISTA
    IF NOT EXISTS (SELECT 1 FROM tab_bancos WHERE id_banco = wid_banco) THEN
        RAISE EXCEPTION 'El banco % no existe.', wid_banco;
    END IF;

-- VALIDAR QUE EL ID DEL BANCO TENGA ENTRE 6 Y 10 CARACTERES
    IF LENGTH(wid_banco) < 6 OR LENGTH(wid_banco) > 10 THEN
        RAISE EXCEPTION 'El ID del banco debe tener entre 6 y 10 caracteres.';
    END IF;

-- SI TODO VA BIEN, SE ACTUALIZA EN tab_ctas_empresa
    UPDATE tab_ctas_empresa
    SET    id_banco       = wid_banco,
           ind_tipocuenta = wind_tipocuenta
    WHERE  id_empresa     = wid_empresa AND cta_empresa = wcta_empresa;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE BANCOS POR PROVEEDORES
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_update_bancoxprov (wid_proveedor       tab_bancoxprov.id_proveedor%TYPE,
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

-- VALIDAR QUE LA CUENTA DEL PROVEEDOR EXISTA Y NO ESTÉ BORRADA
    IF NOT EXISTS (SELECT 1 FROM tab_bancoxprov WHERE id_proveedor = wid_proveedor AND cta_proveedor = wcta_proveedor AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'La cuenta % del proveedor % no existe o se encuentra inactiva.', wcta_proveedor, wid_proveedor;
    END IF;

-- VALIDAR QUE EL BANCO EXISTA
    IF NOT EXISTS (SELECT 1 FROM tab_bancos WHERE id_banco = wid_banco) THEN
        RAISE EXCEPTION 'El banco % no existe.', wid_banco;
    END IF;

-- VALIDAR QUE EL ID DEL BANCO TENGA ENTRE 6 Y 10 CARACTERES
    IF LENGTH(wid_banco) < 6 OR LENGTH(wid_banco) > 10 THEN
        RAISE EXCEPTION 'El ID del banco debe tener entre 6 y 10 caracteres.';
    END IF;

-- SI TODO VA BIEN, SE ACTUALIZA EN tab_bancoxprov
    UPDATE tab_bancoxprov
    SET    id_banco       = wid_banco,
           ind_tipocuenta = wind_tipocuenta
    WHERE  id_proveedor   = wid_proveedor AND cta_proveedor = wcta_proveedor;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE ENCABEZADO DE CRONOGRAMA DE PAGOS
-- NOTA: ind_estado (pagado/pendiente) NO se actualiza aquí; se controla por trigger
--       al momento en que se pagan todas las cuotas asociadas al cronograma.
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_update_enc_cronopagos (wid_cronograma      tab_enc_cronopagos.id_cronograma%TYPE,
                                                      wnom_cronograma     tab_enc_cronopagos.nom_cronograma%TYPE,
                                                      wfec_programacion   tab_enc_cronopagos.fec_programacion%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DEL CRONOGRAMA NO SEA NULO
    IF wid_cronograma IS NULL THEN
        RAISE EXCEPTION 'El ID del cronograma no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL NOMBRE DEL CRONOGRAMA NO SEA NULO
    IF wnom_cronograma IS NULL THEN
        RAISE EXCEPTION 'El nombre del cronograma no puede ser nulo.';
    END IF;

-- VALIDAR QUE LA FECHA DE PROGRAMACIÓN NO SEA NULA
    IF wfec_programacion IS NULL THEN
        RAISE EXCEPTION 'La fecha de programación no puede ser nula.';
    END IF;

-- VALIDAR QUE EL CRONOGRAMA EXISTA, NO ESTÉ BORRADO Y ESTÉ PENDIENTE (NO PAGADO)
    IF NOT EXISTS (SELECT 1 FROM tab_enc_cronopagos WHERE id_cronograma = wid_cronograma AND ind_borrado = FALSE AND ind_estado = FALSE) THEN
        RAISE EXCEPTION 'El cronograma % no existe, se encuentra inactivo o ya fue pagado.', wid_cronograma;
    END IF;

-- VALIDAR QUE EL NOMBRE NO SEA VACÍO
    IF wnom_cronograma = '' THEN
        RAISE EXCEPTION 'El nombre del cronograma no puede estar vacío.';
    END IF;

-- VALIDAR QUE EL NOMBRE ESTÉ ENTRE 3 Y 30 CARACTERES
    IF LENGTH(wnom_cronograma) < 3 OR LENGTH(wnom_cronograma) > 30 THEN
        RAISE EXCEPTION 'El nombre del cronograma debe tener entre 3 y 30 caracteres.';
    END IF;

-- VALIDAR QUE LA FECHA DE PROGRAMACIÓN SEA MAYOR O IGUAL A LA FECHA ACTUAL
    IF wfec_programacion < CURRENT_DATE THEN
        RAISE EXCEPTION 'La fecha de programación no puede ser anterior a la fecha actual.';
    END IF;

-- SI TODO VA BIEN, SE ACTUALIZA EN tab_enc_cronopagos
    UPDATE tab_enc_cronopagos
    SET    nom_cronograma    = wnom_cronograma,
           fec_programacion  = wfec_programacion
    WHERE  id_cronograma     = wid_cronograma;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE ENCABEZADO DE ARCHIVO PLANO
-- NOTA: Solo se permite editar mientras el archivo plano NO haya sido generado (ind_generado = FALSE).
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_update_enc_archivo_plano (wid_archivo_plano   tab_enc_archivo_plano.id_archivo_plano%TYPE,
                                                         wid_banco           tab_enc_archivo_plano.id_banco%TYPE,
                                                         wnom_archivo        tab_enc_archivo_plano.nom_archivo%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DEL ARCHIVO PLANO NO SEA NULO
    IF wid_archivo_plano IS NULL THEN
        RAISE EXCEPTION 'El ID del archivo plano no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ID DEL BANCO NO SEA NULO
    IF wid_banco IS NULL THEN
        RAISE EXCEPTION 'El ID del banco no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL NOMBRE DEL ARCHIVO NO SEA NULO
    IF wnom_archivo IS NULL THEN
        RAISE EXCEPTION 'El nombre del archivo plano no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ARCHIVO PLANO EXISTA Y NO ESTÉ GENERADO AÚN
    IF NOT EXISTS (SELECT 1 FROM tab_enc_archivo_plano WHERE id_archivo_plano = wid_archivo_plano AND ind_generado = FALSE) THEN
        RAISE EXCEPTION 'El archivo plano % no existe o ya fue generado.', wid_archivo_plano;
    END IF;

-- VALIDAR QUE EL BANCO EXISTA
    IF NOT EXISTS (SELECT 1 FROM tab_bancos WHERE id_banco = wid_banco) THEN
        RAISE EXCEPTION 'El banco % no existe.', wid_banco;
    END IF;

-- VALIDAR QUE EL ID DEL BANCO TENGA ENTRE 6 Y 10 CARACTERES
    IF LENGTH(wid_banco) < 6 OR LENGTH(wid_banco) > 10 THEN
        RAISE EXCEPTION 'El ID del banco debe tener entre 6 y 10 caracteres.';
    END IF;

-- VALIDAR QUE EL NOMBRE DEL ARCHIVO NO SEA VACÍO
    IF wnom_archivo = '' THEN
        RAISE EXCEPTION 'El nombre del archivo plano no puede estar vacío.';
    END IF;

-- VALIDAR QUE EL NOMBRE DEL ARCHIVO TENGA ENTRE 3 Y 30 CARACTERES
    IF LENGTH(wnom_archivo) < 3 OR LENGTH(wnom_archivo) > 30 THEN
        RAISE EXCEPTION 'El nombre del archivo plano debe tener entre 3 y 30 caracteres.';
    END IF;

-- SI TODO VA BIEN, SE ACTUALIZA EN tab_enc_archivo_plano
    UPDATE tab_enc_archivo_plano
    SET    id_banco          = wid_banco,
           nom_archivo       = wnom_archivo
    WHERE  id_archivo_plano  = wid_archivo_plano;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE MOTIVOS DE RECHAZO DE CUENTAS POR PAGAR
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_update_motivos_rechazo (wid_motivo_rechazo tab_motivos_rechazo.id_motivo_rechazo%TYPE,
													   wdes_motivo tab_motivos_rechazo.des_motivo%TYPE, 
                                                       wcod_bancario tab_motivos_rechazo.cod_bancario%TYPE, 
                                                       wind_borrado tab_motivos_rechazo.ind_borrado%TYPE) RETURNS BOOLEAN AS $BODY$ 
BEGIN 
    -- 1. VALIDACIONES DE NULIDAD
    IF wid_motivo_rechazo IS NULL THEN 
		RAISE EXCEPTION 'El ID del motivo de rechazo no puede ser nulo.'; 
	END IF;
	
    IF wdes_motivo IS NULL THEN
		RAISE EXCEPTION 'La descripción del motivo no puede ser nula.';
	END IF;
	
    IF wcod_bancario IS NULL THEN
		RAISE EXCEPTION 'El código bancario no puede ser nulo.';
	END IF;
	
    IF wind_borrado IS NULL THEN
		RAISE EXCEPTION 'El indicador de borrado no puede ser nulo.';
	END IF;

    -- 2. VALIDAR QUE EL REGISTRO REALMENTE EXISTA PARA PODER ACTUALIZARLO
    IF NOT EXISTS (SELECT 1 FROM tab_motivos_rechazo WHERE id_motivo_rechazo = wid_motivo_rechazo) THEN 
        RAISE EXCEPTION 'El motivo de rechazo % no existe para ser actualizado.', wid_motivo_rechazo; 
    END IF;

    -- 3. VALIDAR QUE EL CÓDIGO BANCARIO NO ESTÉ ASIGNADO A *OTRO* REGISTRO DIFERENTE
    IF EXISTS (SELECT 1 FROM tab_motivos_rechazo WHERE cod_bancario = wcod_bancario AND id_motivo_rechazo != wid_motivo_rechazo) THEN 
        RAISE EXCEPTION 'El código bancario % ya está asignado a otro motivo de rechazo.', wcod_bancario; 
    END IF;

    -- 4. VALIDACIONES DE RANGOS Y LONGITUDES
    IF wid_motivo_rechazo < 1 OR wid_motivo_rechazo > 999 THEN 
        RAISE EXCEPTION 'El ID del motivo de rechazo debe estar entre 1 y 999.'; 
    END IF;
	
    IF LENGTH(wdes_motivo) < 3 OR LENGTH(wdes_motivo) > 200 THEN 
        RAISE EXCEPTION 'La descripción del motivo debe tener entre 3 y 200 caracteres.'; 
    END IF;
	
    IF LENGTH(wcod_bancario) < 1 OR LENGTH(wcod_bancario) > 10 THEN 
        RAISE EXCEPTION 'El código bancario debe tener entre 1 y 10 caracteres.'; 
    END IF;

    -- 5. EJECUCIÓN DEL UPDATE
    UPDATE tab_motivos_rechazo 
    SET    des_motivo        = wdes_motivo, 
           cod_bancario      = wcod_bancario, 
           ind_borrado       = wind_borrado 
    WHERE  id_motivo_rechazo = wid_motivo_rechazo;

    RETURN TRUE;

EXCEPTION 
    WHEN OTHERS THEN 
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM); 
END; 
$BODY$ LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE BANCOS
-- NOTA: Actualiza el nombre y el indicador de estado del banco. Si se envía wind_estado en
--       FALSE (inactivar), no se permite hacerlo si el banco está siendo usado en cuentas
--       activas de la empresa o de proveedores.
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_update_bancos (wid_banco     tab_bancos.id_banco%TYPE,
                                              wnom_banco     tab_bancos.nom_banco%TYPE,
                                              wind_estado    tab_bancos.ind_estado%TYPE) RETURNS BOOLEAN AS
$BODY$
BEGIN

-- VALIDAR QUE EL ID DEL BANCO NO SEA NULO
    IF wid_banco IS NULL THEN
        RAISE EXCEPTION 'El ID del banco no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL NOMBRE DEL BANCO NO SEA NULO
    IF wnom_banco IS NULL THEN
        RAISE EXCEPTION 'El nombre del banco no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL INDICADOR DE ESTADO NO SEA NULO
    IF wind_estado IS NULL THEN
        RAISE EXCEPTION 'El indicador de estado no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL BANCO EXISTA Y NO ESTÉ BORRADO
    IF NOT EXISTS (SELECT 1 FROM tab_bancos WHERE id_banco = wid_banco AND ind_borrado = FALSE) THEN
        RAISE EXCEPTION 'El banco % no existe o se encuentra borrado.', wid_banco;
    END IF;

-- VALIDAR QUE EL NOMBRE DEL BANCO NO SEA VACÍO
    IF wnom_banco = '' THEN
        RAISE EXCEPTION 'El nombre del banco no puede estar vacío.';
    END IF;

-- VALIDAR QUE EL NOMBRE DEL BANCO TENGA ENTRE 4 Y 50 CARACTERES
    IF LENGTH(wnom_banco) < 4 OR LENGTH(wnom_banco) > 50 THEN
        RAISE EXCEPTION 'El nombre del banco debe tener entre 4 y 50 caracteres.';
    END IF;

-- SI SE VA A INACTIVAR EL BANCO (wind_estado = FALSE), VALIDAR QUE NO ESTÉ SIENDO USADO
    IF wind_estado = FALSE THEN

        IF EXISTS (SELECT 1 FROM tab_ctas_empresa WHERE id_banco = wid_banco AND ind_borrado = FALSE) THEN
            RAISE EXCEPTION 'El banco % está asociado a cuentas activas de la empresa y no puede ser inactivado.', wid_banco;
        END IF;

        IF EXISTS (SELECT 1 FROM tab_bancoxprov WHERE id_banco = wid_banco AND ind_borrado = FALSE) THEN
            RAISE EXCEPTION 'El banco % está asociado a cuentas activas de proveedores y no puede ser inactivado.', wid_banco;
        END IF;

    END IF;

-- SI TODO VA BIEN, SE ACTUALIZA EN tab_bancos
    UPDATE tab_bancos
    SET    nom_banco  = wnom_banco,
           ind_estado = wind_estado
    WHERE  id_banco   = wid_banco;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;

--------------------------------------------------------------------------------------------------------------------------------------
-- FUNCIÓN DE UPDATE DE PAGOS DE CUENTAS POR PAGAR
-- NOTA: Fija el estado final que respondió el banco (APROBADO o RECHAZADO)
--       sobre un pago creado por fun_insert_pagos_cxp. Solo aplica sobre
--       pagos que sigan en PENDIENTE; no se puede volver a resolver uno
--       que ya quedó APROBADO o RECHAZADO.
--------------------------------------------------------------------------------------------------------------------------------------
CREATE OR REPLACE FUNCTION fun_update_pagos_cxp (wid_pago               tab_pagos_cxp.id_pago%TYPE,
                                                 westado_pago           tab_pagos_cxp.estado_pago%TYPE,
                                                 wreferencia_bancaria   tab_pagos_cxp.referencia_bancaria%TYPE,
                                                 wid_motivo_rechazo     tab_pagos_cxp.id_motivo_rechazo%TYPE) RETURNS BOOLEAN AS
$BODY$

DECLARE westado_actual tab_pagos_cxp.estado_pago%TYPE;

BEGIN

-- VALIDAR QUE EL ID DEL PAGO NO SEA NULO
    IF wid_pago IS NULL THEN
        RAISE EXCEPTION 'El ID del pago no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL ESTADO DEL PAGO NO SEA NULO
    IF westado_pago IS NULL THEN
        RAISE EXCEPTION 'El estado del pago no puede ser nulo.';
    END IF;

-- VALIDAR QUE EL PAGO EXISTA Y OBTENER SU ESTADO ACTUAL
    SELECT estado_pago INTO westado_actual
    FROM   tab_pagos_cxp
    WHERE  id_pago = wid_pago;

    IF westado_actual IS NULL THEN
        RAISE EXCEPTION 'El pago % no existe.', wid_pago;
    END IF;

-- VALIDAR QUE EL PAGO SIGA EN PENDIENTE (NO SE PUEDE VOLVER A RESOLVER UNO YA RESUELTO)
    IF westado_actual <> 'PENDIENTE' THEN
        RAISE EXCEPTION 'El pago % ya está en %.', wid_pago, westado_actual;
    END IF;

-- VALIDAR QUE EL ESTADO NUEVO SEA APROBADO O RECHAZADO
    IF westado_pago NOT IN ('APROBADO', 'RECHAZADO') THEN
        RAISE EXCEPTION 'El estado % no es válido: debe ser APROBADO o RECHAZADO.', westado_pago;
    END IF;

-- VALIDAR QUE EL MOTIVO DE RECHAZO VENGA SOLO CUANDO EL ESTADO ES RECHAZADO
    IF westado_pago = 'RECHAZADO' AND wid_motivo_rechazo IS NULL THEN
        RAISE EXCEPTION 'El motivo de rechazo es obligatorio cuando el estado es RECHAZADO.';
    END IF;

    IF westado_pago <> 'RECHAZADO' AND wid_motivo_rechazo IS NOT NULL THEN
        RAISE EXCEPTION 'El motivo de rechazo solo aplica cuando el estado es RECHAZADO.';
    END IF;

-- VALIDAR QUE EL MOTIVO DE RECHAZO EXISTA, SI SE ENVIÓ
    IF wid_motivo_rechazo IS NOT NULL THEN
        IF NOT EXISTS (SELECT 1 FROM tab_motivos_rechazo WHERE id_motivo_rechazo = wid_motivo_rechazo) THEN
            RAISE EXCEPTION 'El motivo de rechazo % no existe.', wid_motivo_rechazo;
        END IF;
    END IF;

-- VALIDAR QUE LA REFERENCIA BANCARIA NO SUPERE 30 CARACTERES
    IF wreferencia_bancaria IS NOT NULL AND LENGTH(wreferencia_bancaria) > 30 THEN
        RAISE EXCEPTION 'La referencia bancaria no puede superar 30 caracteres.';
    END IF;

-- SI TODO VA BIEN, SE ACTUALIZA EN tab_pagos_cxp
    UPDATE tab_pagos_cxp
    SET    estado_pago         = westado_pago,
           referencia_bancaria = wreferencia_bancaria,
           id_motivo_rechazo   = wid_motivo_rechazo
    WHERE  id_pago             = wid_pago;

    RETURN TRUE;

EXCEPTION
    WHEN OTHERS THEN
        RAISE EXCEPTION 'ERROR: %', public.fun_mensaje_error(SQLSTATE, SQLERRM);
END;
$BODY$
LANGUAGE PLPGSQL;
