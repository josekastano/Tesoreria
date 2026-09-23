-- =========================================================================
-- VALIDAR LA FECHA DE PROGRAMACIÓN DE UN CRONOGRAMA DE PAGOS
-- =========================================================================
-- Reglas:
--   1) fec_programacion debe caer en uno de los tres días de pago definidos
--      en tab_pmtros_tescxp (fec_diapago1..3: 1 = lunes ... 6 = sábado).
--   2) fec_programacion no puede ser un festivo activo de tab_festivos.
--
-- Se implementa como trigger BEFORE INSERT OR UPDATE para que aplique tanto
-- a fun_insert_enc_cronopagos como a fun_update_enc_cronopagos sin tener que
-- tocar esas funciones. El RAISE del trigger lo captura el EXCEPTION de la
-- función que hizo el INSERT/UPDATE y llega a PHP como mensaje normal.
--
-- EXTRACT(ISODOW ...) devuelve 1 = lunes ... 7 = domingo, que coincide con
-- la numeración usada en tab_pmtros_tescxp.
-- =========================================================================

CREATE OR REPLACE FUNCTION fun_validar_fecha_cronograma() RETURNS TRIGGER AS
$BODY$
DECLARE
    wdia            INTEGER;
    wdia1           tab_pmtros_tescxp.fec_diapago1%TYPE;
    wdia2           tab_pmtros_tescxp.fec_diapago2%TYPE;
    wdia3           tab_pmtros_tescxp.fec_diapago3%TYPE;
    wnom_festivo    tab_festivos.nom_festivo%TYPE;
    wnom_dia        VARCHAR(10);
BEGIN
    -- En un UPDATE que no cambia la fecha (ej. solo se renombra el cronograma)
    -- no se revalida: si después se creó un festivo en esa fecha o cambiaron
    -- los parámetros, el cronograma viejo se puede seguir editando.
    IF TG_OP = 'UPDATE' AND NEW.fec_programacion = OLD.fec_programacion THEN
        RETURN NEW;
    END IF;

    IF NEW.fec_programacion IS NULL THEN
        RAISE EXCEPTION 'La fecha de programación no puede ser nula.';
    END IF;

-- TRAER LOS DÍAS DE PAGO CONFIGURADOS
    SELECT fec_diapago1, fec_diapago2, fec_diapago3
      INTO wdia1, wdia2, wdia3
      FROM tab_pmtros_tescxp
     WHERE ind_borrado = FALSE
     LIMIT 1;

    IF NOT FOUND THEN
        RAISE EXCEPTION 'No hay días de pago configurados en Parámetros de Tesorería.';
    END IF;

-- VALIDAR QUE LA FECHA CAIGA EN UN DÍA DE PAGO
    wdia := EXTRACT(ISODOW FROM NEW.fec_programacion)::INTEGER;

    IF  wdia IS DISTINCT FROM wdia1
    AND wdia IS DISTINCT FROM wdia2
    AND wdia IS DISTINCT FROM wdia3 THEN
        wnom_dia := CASE wdia
                        WHEN 1 THEN 'lunes'
                        WHEN 2 THEN 'martes'
                        WHEN 3 THEN 'miércoles'
                        WHEN 4 THEN 'jueves'
                        WHEN 5 THEN 'viernes'
                        WHEN 6 THEN 'sábado'
                        ELSE        'domingo'
                    END;
        RAISE EXCEPTION 'La fecha % cae en %, que no es un día de pago configurado en Parámetros de Tesorería.',
            TO_CHAR(NEW.fec_programacion, 'DD/MM/YYYY'), wnom_dia;
    END IF;

-- VALIDAR QUE LA FECHA NO SEA FESTIVO
    SELECT nom_festivo
      INTO wnom_festivo
      FROM tab_festivos
     WHERE fecha       = NEW.fec_programacion
       AND ind_borrado = FALSE
     LIMIT 1;

    IF FOUND THEN
        RAISE EXCEPTION 'La fecha % es festivo (%). Elija otro día de pago.',
            TO_CHAR(NEW.fec_programacion, 'DD/MM/YYYY'), wnom_festivo;
    END IF;

    RETURN NEW;
END;
$BODY$
LANGUAGE PLPGSQL;

DROP TRIGGER IF EXISTS trg_validar_fecha_cronograma ON tab_enc_cronopagos;

CREATE TRIGGER trg_validar_fecha_cronograma
BEFORE INSERT OR UPDATE OF fec_programacion ON tab_enc_cronopagos
FOR EACH ROW EXECUTE FUNCTION fun_validar_fecha_cronograma();
