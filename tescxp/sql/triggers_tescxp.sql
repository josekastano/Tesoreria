-- =========================================================================
-- TRIGGERS - MÓDULO DE TESORERÍA Y CUENTAS POR PAGAR
-- =========================================================================

-- =========================================================================
-- 1. tab_enc_caja_menor
-- =========================================================================

-- 1.1 Al insertar una caja menor, monto_disponible = monto_asignado
CREATE OR REPLACE FUNCTION fun_monto_disponible_caja_menor() RETURNS TRIGGER AS 
$BODY$
BEGIN
    NEW.monto_disponible := NEW.monto_asignado;
    RETURN NEW;
END;
$BODY$ 
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_monto_disponible_caja_menor BEFORE INSERT ON tab_enc_caja_menor
FOR EACH ROW EXECUTE FUNCTION fun_monto_disponible_caja_menor();


-- 1.2 Al cerrar la caja (ind_estado_caja_m: TRUE -> FALSE), asignar fecha_cierre
--     y al reabrirla (FALSE -> TRUE), limpiar fecha_cierre.
CREATE OR REPLACE FUNCTION fun_fecha_cierre_caja_menor() RETURNS TRIGGER AS 
$BODY$
BEGIN
    IF OLD.ind_estado_caja_m = TRUE AND NEW.ind_estado_caja_m = FALSE THEN
        NEW.fecha_cierre := CURRENT_DATE;
    ELSIF OLD.ind_estado_caja_m = FALSE AND NEW.ind_estado_caja_m = TRUE THEN
        NEW.fecha_cierre := NULL;
    END IF;
    RETURN NEW;
END;
$BODY$
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_fecha_cierre_caja_menor BEFORE UPDATE ON tab_enc_caja_menor
FOR EACH ROW WHEN (OLD.ind_estado_caja_m IS DISTINCT FROM NEW.ind_estado_caja_m)
EXECUTE FUNCTION fun_fecha_cierre_caja_menor();

-- =========================================================================
-- 2. tab_det_caja_menor
-- =========================================================================

-- 2.1 Forzar fecha_movimiento = CURRENT_DATE siempre (ignora lo que envíe el usuario)
CREATE OR REPLACE FUNCTION fun_fecha_movimiento_caja_menor() RETURNS TRIGGER AS 
$BODY$
BEGIN
    NEW.fecha_movimiento := CURRENT_DATE;
    RETURN NEW;
END;
$BODY$
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_fecha_movimiento_caja_menor BEFORE INSERT ON tab_det_caja_menor
FOR EACH ROW EXECUTE FUNCTION fun_fecha_movimiento_caja_menor();


-- 2.2 Descontar monto_disponible SOLO cuando el movimiento pasa a Aprobado (1 -> 2)
--     y reponerlo si pasa a Reembolsado (2 -> 3) o si se revierte (2 -> 1).
CREATE OR REPLACE FUNCTION fun_actualizar_disponible_caja_menor() RETURNS TRIGGER AS
$BODY$
BEGIN
    -- Pasa de Pendiente(1) a Aprobado(2): se descuenta del disponible
    IF OLD.ind_estado = 1 AND NEW.ind_estado = 2 THEN
        UPDATE tab_enc_caja_menor
        SET monto_disponible = monto_disponible - NEW.val_movimiento
        WHERE id_caja_menor = NEW.id_caja_menor;

    -- Pasa de Aprobado(2) a Reembolsado(3): el dinero vuelve a la caja
    ELSIF OLD.ind_estado = 2 AND NEW.ind_estado = 3 THEN
        UPDATE tab_enc_caja_menor
        SET monto_disponible = monto_disponible + NEW.val_movimiento
        WHERE id_caja_menor = NEW.id_caja_menor;

    -- Se revierte una aprobación de vuelta a Pendiente: se repone también
    ELSIF OLD.ind_estado = 2 AND NEW.ind_estado = 1 THEN
        UPDATE tab_enc_caja_menor
        SET monto_disponible = monto_disponible + NEW.val_movimiento
        WHERE id_caja_menor = NEW.id_caja_menor;
    END IF;

    RETURN NEW;
END;
$BODY$
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_actualizar_disponible_caja_menor AFTER UPDATE ON tab_det_caja_menor
FOR EACH ROW WHEN (OLD.ind_estado IS DISTINCT FROM NEW.ind_estado)
EXECUTE FUNCTION fun_actualizar_disponible_caja_menor();

-- NOTA: esta función no valida que monto_disponible no quede negativo.
-- Si quieres bloquear la aprobación cuando no hay fondos suficientes,
-- se puede agregar una validación adicional antes del UPDATE (lanzando
-- una excepción con RAISE EXCEPTION). Lo dejo fuera por ahora porque no
-- lo mencionaste como requisito, pero es algo a considerar.

-- =========================================================================
-- 3. tab_cuentasxpagar
-- =========================================================================

-- 3.1 Al insertar una factura, val_saldo = val_factura
CREATE OR REPLACE FUNCTION fun_saldo_factura() RETURNS TRIGGER AS
$BODY$
BEGIN
    NEW.val_saldo := NEW.val_factura;
    RETURN NEW;
END;
$BODY$
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_saldo_factura BEFORE INSERT ON tab_cuentasxpagar FOR EACH ROW
EXECUTE FUNCTION fun_saldo_factura();

-- =========================================================================
-- 4. tab_cuotasxfactura
-- =========================================================================

-- 4.1 Al insertar una factura, generar automáticamente sus N cuotas
--     (1 mes de separación entre cada cuota, a partir de fec_vencimiento)
--     Reparte val_factura entre num_cuotas; ajusta la última cuota para
--     que la suma cuadre exacto si la división no es entera.
CREATE OR REPLACE FUNCTION fun_generar_cuotas_factura() RETURNS TRIGGER AS
$BODY$
DECLARE
    v_valor_base   DECIMAL(10,0);
    v_suma_parcial DECIMAL(10,0) := 0;
    i              INT;
BEGIN
    v_valor_base := TRUNC(NEW.val_factura / NEW.num_cuotas);

    FOR i IN 1..NEW.num_cuotas LOOP
        IF i < NEW.num_cuotas THEN
            INSERT INTO tab_cuotasxfactura(id_factura, id_cuota, fec_vencimiento, val_cuota)
            VALUES (
                NEW.id_factura,
                i,
                NEW.fec_vencimiento + ((i - 1) * INTERVAL '1 month'),
                v_valor_base
            );
            v_suma_parcial := v_suma_parcial + v_valor_base;
        ELSE
            -- última cuota: se ajusta para que la suma total sea exacta
            INSERT INTO tab_cuotasxfactura(id_factura, id_cuota, fec_vencimiento, val_cuota)
            VALUES (
                NEW.id_factura,
                i,
                NEW.fec_vencimiento + ((i - 1) * INTERVAL '1 month'),
                NEW.val_factura - v_suma_parcial
            );
        END IF;
    END LOOP;

    RETURN NEW;
END;
$BODY$
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_generar_cuotas_factura AFTER INSERT ON tab_cuentasxpagar FOR EACH ROW
EXECUTE FUNCTION fun_generar_cuotas_factura();

-- NOTA IMPORTANTE: esta función usa NEW.fec_vencimiento como ancla para la
-- primera cuota. Revisa si esto es lo que quieres, porque fec_vencimiento
-- en tab_cuentasxpagar tiene el CHECK (fec_vencimiento > fec_emision), es
-- decir, representa el vencimiento de TODA la factura, no necesariamente
-- el de la primera cuota. Si prefieres anclar desde fec_emision en su lugar,
-- solo cambia esa referencia dentro del INSERT.

-- =========================================================================
-- 5. tab_enc_cronopagos
-- =========================================================================

-- 5.1 Recalcular total_a_pagar sumando el detalle (tab_det_cronopagos)
--     Se dispara desde el DETALLE, no desde el encabezado, porque el valor
--     depende de filas que viven en otra tabla.
CREATE OR REPLACE FUNCTION fun_calcular_total_cronograma() RETURNS TRIGGER AS
$BODY$
DECLARE
    v_id_cronograma DECIMAL(10,0);
BEGIN
    v_id_cronograma := COALESCE(NEW.id_cronograma, OLD.id_cronograma);

    UPDATE tab_enc_cronopagos
    SET total_a_pagar = (
        SELECT COALESCE(SUM(val_a_pagar), 0)
        FROM tab_det_cronopagos
        WHERE id_cronograma = v_id_cronograma
    )
    WHERE id_cronograma = v_id_cronograma;

    RETURN NULL; -- trigger AFTER, el valor de retorno se ignora
END;
$BODY$
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_calcular_total_cronograma AFTER INSERT OR UPDATE OR DELETE ON tab_det_cronopagos
FOR EACH ROW EXECUTE FUNCTION fun_calcular_total_cronograma();


-- =========================================================================
-- 6. tab_det_cronopagos
-- =========================================================================

-- 6.1 Al insertar una fila de detalle, traer val_a_pagar desde la cuota
--     real en tab_cuotasxfactura (nunca se digita a mano)
CREATE OR REPLACE FUNCTION fun_val_a_pagar_cronograma() RETURNS TRIGGER AS 
$BODY$
BEGIN
    SELECT val_cuota
    INTO NEW.val_a_pagar
    FROM tab_cuotasxfactura
    WHERE id_factura = NEW.id_factura
      AND id_cuota   = NEW.id_cuota;

    IF NEW.val_a_pagar IS NULL THEN
        RAISE EXCEPTION 'No existe la cuota % de la factura % en tab_cuotasxfactura', NEW.id_cuota, NEW.id_factura;
    END IF;

    RETURN NEW;
END;
$BODY$
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_val_a_pagar_cronograma BEFORE INSERT ON tab_det_cronopagos FOR EACH ROW
EXECUTE FUNCTION fun_val_a_pagar_cronograma();

-- =========================================================================
-- 7. tab_enc_archivo_plano
-- =========================================================================

-- 7.1 Al generar el archivo (ind_generado: FALSE -> TRUE), asignar fec_generacion
CREATE OR REPLACE FUNCTION fun_fecha_generacion_archivo() RETURNS TRIGGER AS
$BODY$
BEGIN
    IF OLD.ind_generado = FALSE AND NEW.ind_generado = TRUE THEN
        NEW.fec_generacion := CURRENT_DATE;
    END IF;
    RETURN NEW;
END;
$BODY$
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_fecha_generacion_archivo BEFORE UPDATE ON tab_enc_archivo_plano
FOR EACH ROW WHEN (OLD.ind_generado IS DISTINCT FROM NEW.ind_generado)
EXECUTE FUNCTION fun_fecha_generacion_archivo();


-- =========================================================================
-- 8. tab_det_archivo_plano 
-- =========================================================================

-- 8.1 Al insertar una fila de detalle, traer val_a_pagar desde el cronograma
--     ya programado (tab_det_cronopagos), no desde la cuota original, para
--     reflejar exactamente lo que se programó pagar.
CREATE OR REPLACE FUNCTION fun_val_a_pagar_archivo_plano() RETURNS TRIGGER AS
$BODY$
DECLARE
    v_id_cronograma DECIMAL(10,0);
BEGIN
    SELECT id_cronograma
    INTO v_id_cronograma
    FROM tab_enc_archivo_plano
    WHERE id_archivo_plano = NEW.id_archivo_plano;

    SELECT val_a_pagar
    INTO NEW.val_a_pagar
    FROM tab_det_cronopagos
    WHERE id_cronograma = v_id_cronograma
      AND id_factura     = NEW.id_factura
      AND id_cuota        = NEW.id_cuota;

    IF NEW.val_a_pagar IS NULL THEN
        RAISE EXCEPTION 'La cuota % de la factura % no está programada en el cronograma del archivo plano %',
            NEW.id_cuota, NEW.id_factura, NEW.id_archivo_plano;
    END IF;

    RETURN NEW;
END;
$BODY$
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_val_a_pagar_archivo_plano BEFORE INSERT ON tab_det_archivo_plano
FOR EACH ROW EXECUTE FUNCTION fun_val_a_pagar_archivo_plano();


-- =========================================================================
-- 9. CADENA DE PAGO: cronograma pagado -> cuotas pagadas -> saldo factura
-- =========================================================================

-- 9.1 Cuando el cronograma pasa a Pagado (ind_estado: FALSE -> TRUE),
--     marcar como pagadas todas las cuotas que estaban en su detalle.
CREATE OR REPLACE FUNCTION fun_marcar_cuotas_pagadas() RETURNS TRIGGER AS
$BODY$
BEGIN
    IF OLD.ind_estado = FALSE AND NEW.ind_estado = TRUE THEN
        UPDATE tab_cuotasxfactura cf
        SET ind_pagada = TRUE
        WHERE ind_pagada = FALSE
          AND EXISTS (
              SELECT 1
              FROM tab_det_cronopagos dc
              WHERE dc.id_cronograma = NEW.id_cronograma
                AND dc.id_factura     = cf.id_factura
                AND dc.id_cuota        = cf.id_cuota
          );
    END IF;
    RETURN NEW;
END;
$BODY$
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_marcar_cuotas_pagadas AFTER UPDATE ON tab_enc_cronopagos
FOR EACH ROW WHEN (OLD.ind_estado IS DISTINCT FROM NEW.ind_estado)
EXECUTE FUNCTION fun_marcar_cuotas_pagadas();


-- 9.2 Cuando una cuota pasa a pagada (ind_pagada: FALSE -> TRUE), descontar
--     su valor del saldo de la factura, y si el saldo llega a 0, marcar la
--     factura como pagada (ind_estado = TRUE).
CREATE OR REPLACE FUNCTION fun_actualizar_saldo_factura() RETURNS TRIGGER AS
$BODY$
BEGIN
    IF OLD.ind_pagada = FALSE AND NEW.ind_pagada = TRUE THEN
        UPDATE tab_cuentasxpagar
        SET val_saldo  = val_saldo - NEW.val_cuota,
            ind_estado = CASE WHEN (val_saldo - NEW.val_cuota) <= 0 THEN TRUE ELSE ind_estado END
        WHERE id_factura = NEW.id_factura;
    END IF;
    RETURN NEW;
END;
$BODY$
LANGUAGE PLPGSQL;

CREATE TRIGGER trg_actualizar_saldo_factura AFTER UPDATE ON tab_cuotasxfactura
FOR EACH ROW WHEN (OLD.ind_pagada IS DISTINCT FROM NEW.ind_pagada)
EXECUTE FUNCTION fun_actualizar_saldo_factura();

-- NOTA: esta cadena cubre el flujo "pagar TODO el cronograma de una vez".
-- Si en algún momento necesitas marcar una sola cuota como pagada de forma
-- manual e independiente (sin pasar por un cronograma), el trigger 9.2 ya
-- lo soporta automáticamente, porque escucha cambios directos sobre
-- tab_cuotasxfactura.ind_pagada, sin importar quién los origine.