<?php
/**
 * CENTRAL DE INSTRUCCIONES PREPARADAS - Módulo Tesorería y CxP
 * Versión: 2.1 — Alineada al modelo script_BD_tesore_cxp.sql (14 tablas)
 *
 * Orden de los bloques: igual al orden de creación de tablas en el script SQL.
 *
 *   1.  tab_festivos                                        (Tabla 1)
 *   2.  tab_enc_caja_menor / tab_det_caja_menor             (Tablas 2 y 3)
 *   3.  tab_pmtros_tescxp (+ tab_pmtros_grales, solo lectura)   (Tabla 4)
 *   4.  tab_ctas_empresa                                    (Tabla 5)
 *   5.  tab_bancoxprov                                      (Tabla 6)
 *   6.  tab_cuentasxpagar / tab_cuotasxfactura              (Tablas 7 y 8)
 *   7.  tab_enc_cronopagos / tab_det_cronopagos             (Tablas 9 y 10)
 *   8.  tab_enc_archivo_plano / tab_det_archivo_plano       (Tablas 11 y 12)
 *   9.  tab_motivos_rechazo                                 (Tabla 13)
 *  10.  tab_pagos_cxp                                       (Tabla 14)
 *  11.  tab_bancos  (catálogo compartido con otros módulos)
 *
 * ---------------------------------------------------------------------------
 * MARCAS QUE VAS A ENCONTRAR EN LOS COMENTARIOS
 * ---------------------------------------------------------------------------
 *  >>> FIRMA INFERIDA   La función no está confirmada contra el script de
 *                       funciones. Verificar nombre, orden y cantidad de
 *                       parámetros antes de publicar.
 *
 *  >>> CORREGIDO        Diferencia respecto a la versión anterior del prepare.
 *                       Se explica el motivo en el comentario.
 *
 *  >>> NUEVO            Consulta que no existía y que el módulo necesita.
 *
 *  >>> SIN TRIGGER      La lógica la ejecuta la aplicación, no la base de
 *                       datos. Debe ir dentro de una transacción.
 *
 * ---------------------------------------------------------------------------
 * OJO — DOS ERRORES QUE ESTÁN EN EL SCRIPT SQL, NO EN ESTE ARCHIVO
 * ---------------------------------------------------------------------------
 *  1) tab_det_cronopagos NO COMPILA. Falta la coma después del segundo
 *     FOREIGN KEY y sobra la coma final antes del paréntesis de cierre:
 *
 *         FOREIGN KEY(id_factura,id_cuota) REFERENCES tab_cuotasxfactura(...)   <-- falta ","
 *         CONSTRAINT uq_cuota_programada   UNIQUE (id_factura,id_cuota),        <-- sobra ","
 *
 *     Debe quedar:
 *
 *         FOREIGN KEY(id_factura,id_cuota) REFERENCES tab_cuotasxfactura(id_factura,id_cuota),
 *         CONSTRAINT uq_cuota_programada   UNIQUE (id_factura,id_cuota)
 *
 *  2) El comentario de tab_pagos_cxp menciona una tabla "tab_reembolsos_caja"
 *     que no existe en el script. O se crea, o se quita la referencia.
 *
 *     (Menor: id_banco está declarado como VARCHAR sin longitud en
 *      tab_ctas_empresa, tab_bancoxprov y tab_enc_archivo_plano. Funciona,
 *      pero conviene igualarlo al tipo real de tab_bancos.id_banco.)
 * ---------------------------------------------------------------------------
 */

require_once('config.php');

try {
    $pdo = getDBConnection();

    // =========================================================================
    // 1. TAB_FESTIVOS
    // =========================================================================

    // ---- LISTADO PRINCIPAL DE FESTIVOS ----
    $list_festivos = $pdo->prepare(
        "SELECT  id_festivo,
                 fecha,
                 nom_festivo
           FROM  tab_festivos
          WHERE  ind_borrado = FALSE
          ORDER BY fecha"
    );


    // ---- UN FESTIVO (para el formulario de edición) ----
    // >>> NUEVO: no existía y el formulario de edición no tenía de dónde
    //     precargar los datos de la fila que se va a modificar.
    $get_festivo = $pdo->prepare(
        "SELECT  id_festivo,
                 fecha,
                 nom_festivo
           FROM  tab_festivos
          WHERE  id_festivo  = :wid_festivo
            AND  ind_borrado = FALSE"
    );

    // ---- INSERT — fun_insert_festivos (2 params) ----
    // fecha, nom_festivo
    // (el id_festivo se autogenera dentro de la función)
    $ins_festivo = $pdo->prepare(
        "SELECT fun_insert_festivos(
            :wfecha,
            :wnom_festivo
        )"
    );

    // ---- UPDATE — fun_update_festivos (3 params) ----
    // id_festivo, fecha, nom_festivo
    $upd_festivo = $pdo->prepare(
        "SELECT fun_update_festivos(
            :wid_festivo,
            :wfecha,
            :wnom_festivo
        )"
    );

    // ---- DELETE — fun_delete_festivos (1 param) ----
    // Borrado lógico (ind_borrado = TRUE)
    $del_festivo = $pdo->prepare(
        "SELECT fun_delete_festivos(:wid_festivo)"
    );

    // =========================================================================
    // 2. TAB_ENC_CAJA_MENOR / TAB_DET_CAJA_MENOR
    // =========================================================================

    // ---- LISTADO DE CAJAS MENORES (ENCABEZADO) ----
    // >>> CORREGIDO: el modelo agregó ind_borrado a tab_enc_caja_menor y la
    //     versión anterior de esta consulta no lo filtraba, así que las cajas
    //     borradas seguían apareciendo en pantalla.
    $list_enc_caja_menor = $pdo->prepare(
        "SELECT  id_caja_menor,
                 nom_caja_menor,
                 monto_asignado,
                 monto_disponible,
                 fecha_apertura,
                 fecha_cierre,
                 ind_estado_caja_m
           FROM  tab_enc_caja_menor
          WHERE  ind_borrado = FALSE
          ORDER BY fecha_apertura DESC, id_caja_menor DESC"
    );

    // ---- CAJAS MENORES ACTIVAS (para selects: solo abiertas) ----
    $list_cajas_activas = $pdo->prepare(
        "SELECT  id_caja_menor,
                 nom_caja_menor,
                 monto_disponible
           FROM  tab_enc_caja_menor
          WHERE  ind_borrado       = FALSE
            AND  ind_estado_caja_m = TRUE
          ORDER BY nom_caja_menor"
    );

    // ---- UNA CAJA MENOR (para el formulario de edición) ----
    $get_enc_caja_menor = $pdo->prepare(
        "SELECT  id_caja_menor,
                 nom_caja_menor,
                 monto_asignado,
                 monto_disponible,
                 fecha_apertura,
                 fecha_cierre,
                 ind_estado_caja_m
           FROM  tab_enc_caja_menor
          WHERE  id_caja_menor = :wid_caja_menor
            AND  ind_borrado   = FALSE"
    );

    // ---- MOVIMIENTOS DE UNA CAJA MENOR (DETALLE) ----
    $list_det_caja_menor = $pdo->prepare(
        "SELECT  id_caja_menor,
                 id_movimiento,
                 concepto,
                 val_movimiento,
                 fecha_movimiento,
                 ind_estado
           FROM  tab_det_caja_menor
          WHERE  id_caja_menor = :wid_caja_menor
          ORDER BY id_movimiento DESC"
    );

    // ---- UN MOVIMIENTO (para el formulario de cambio de estado) ----
    // >>> NUEVO: la PK es compuesta (id_caja_menor, id_movimiento) y no había
    //     forma de traer una sola fila.
    $get_det_caja_menor = $pdo->prepare(
        "SELECT  id_caja_menor,
                 id_movimiento,
                 concepto,
                 val_movimiento,
                 fecha_movimiento,
                 ind_estado
           FROM  tab_det_caja_menor
          WHERE  id_caja_menor = :wid_caja_menor
            AND  id_movimiento = :wid_movimiento"
    );

    // ---- MOVIMIENTOS FILTRADOS POR ESTADO ----
    // >>> NUEVO. Para las pestañas Pendiente / Aprobado / Reembolsado.
    //     (1 = Pendiente, 2 = Aprobado, 3 = Reembolsado)
    $list_mov_caja_por_estado = $pdo->prepare(
        "SELECT  d.id_caja_menor,
                 e.nom_caja_menor,
                 d.id_movimiento,
                 d.concepto,
                 d.val_movimiento,
                 d.fecha_movimiento,
                 d.ind_estado
           FROM  tab_det_caja_menor  d
           JOIN  tab_enc_caja_menor  e ON e.id_caja_menor = d.id_caja_menor
          WHERE  e.ind_borrado = FALSE
            AND  d.ind_estado  = :wind_estado
          ORDER BY d.fecha_movimiento DESC, d.id_movimiento DESC"
    );

    // ---- TOTALES POR ESTADO DE UNA CAJA (1=Pend, 2=Aprob, 3=Reemb) ----
    // Sirve para el tablero de la caja y para saber si se puede cerrar:
    // fun_delete_enc_caja_menor falla si quedan movimientos en estado 1 o 2.
    $sum_det_caja_menor = $pdo->prepare(
        "SELECT  ind_estado,
                 COUNT(*)              AS cant_movimientos,
                 SUM(val_movimiento)   AS total_movimientos
           FROM  tab_det_caja_menor
          WHERE  id_caja_menor = :wid_caja_menor
          GROUP BY ind_estado
          ORDER BY ind_estado"
    );

    // ---- ¿LA CAJA YA ALCANZÓ EL MÍNIMO PARA PEDIR REEMBOLSO? ----
    // >>> NUEVO. tab_pmtros_tescxp.val_min_reembolso existe en el modelo pero
    //     no se estaba usando en ninguna consulta. Compara lo aprobado y
    //     todavía no reembolsado (estado 2) contra ese mínimo.
    $check_min_reembolso = $pdo->prepare(
        "SELECT  COALESCE(SUM(d.val_movimiento), 0)      AS total_aprobado,
                 p.val_min_reembolso,
                 COALESCE(SUM(d.val_movimiento), 0) >= p.val_min_reembolso
                                                         AS alcanza_minimo
           FROM  tab_pmtros_tescxp p
           LEFT JOIN tab_det_caja_menor d
                  ON d.id_caja_menor = :wid_caja_menor
                 AND d.ind_estado    = 2
          WHERE  p.ind_borrado = FALSE
          GROUP BY p.val_min_reembolso"
    );

    // ---- INSERT — fun_insert_enc_caja_menor (2 params) ----
    // nom_caja_menor, monto_asignado
    // (id_caja_menor se autogenera; monto_disponible = monto_asignado al crear)
    $ins_enc_caja_menor = $pdo->prepare(
        "SELECT fun_insert_enc_caja_menor(
            :wnom_caja_menor,
            :wmonto_asignado
        )"
    );

    // ---- UPDATE — fun_update_enc_caja_menor (2 params) ----
    // id_caja_menor, nom_caja_menor
    // (Solo permite renombrar la caja; el monto asignado no se puede editar)
    $upd_enc_caja_menor = $pdo->prepare(
        "SELECT fun_update_enc_caja_menor(
            :wid_caja_menor,
            :wnom_caja_menor
        )"
    );

    // ---- INSERT — fun_insert_det_caja_menor (4 params) ----
    // id_caja_menor, concepto, val_movimiento, fecha_movimiento
    // (id_movimiento se autogenera; la función descuenta monto_disponible
    //  de la caja al insertar el movimiento, que queda en estado Pendiente)
    $ins_det_caja_menor = $pdo->prepare(
        "SELECT fun_insert_det_caja_menor(
            :wid_caja_menor,
            :wconcepto,
            :wval_movimiento,
            :wfecha_movimiento
        )"
    );

    // ---- UPDATE — fun_update_det_caja_menor (3 params) ----
    // id_caja_menor, id_movimiento, ind_estado
    // (1=Pendiente, 2=Aprobado, 3=Reembolsado — un trigger repone
    //  monto_disponible de la caja cuando el movimiento pasa a Reembolsado)
    $upd_det_caja_menor = $pdo->prepare(
        "SELECT fun_update_det_caja_menor(
            :wid_caja_menor,
            :wid_movimiento,
            :wind_estado
        )"
    );

    // ---- CIERRE DE CAJA — fun_delete_enc_caja_menor (1 param) ----
    // id_caja_menor
    // (Fija fecha_cierre = CURRENT_DATE e ind_estado_caja_m = FALSE. Falla si
    //  quedan movimientos en estado Pendiente o Aprobado sin reembolsar.)
    $del_enc_caja_menor = $pdo->prepare(
        "SELECT fun_delete_enc_caja_menor(
            :wid_caja_menor
        )"
    );

    // ---- BORRADO LÓGICO DE UNA CAJA YA CERRADA ----
    // >>> NUEVO — resuelve el "PENDIENTE DE DEFINIR" de la versión anterior.
    //     El modelo agregó ind_borrado a tab_enc_caja_menor, pero
    //     fun_delete_enc_caja_menor solo CIERRA la caja, no la borra.
    //     Mientras no exista fun_delete_logico_enc_caja_menor, este UPDATE
    //     cubre el caso, y el WHERE impide borrar una caja que siga abierta o
    //     que tenga movimientos sin reembolsar (estado 1 o 2).
    $borrar_enc_caja_menor = $pdo->prepare(
        "UPDATE tab_enc_caja_menor e
            SET ind_borrado = TRUE
          WHERE e.id_caja_menor     = :wid_caja_menor
            AND e.ind_borrado       = FALSE
            AND e.ind_estado_caja_m = FALSE
            AND NOT EXISTS (
                    SELECT 1
                      FROM tab_det_caja_menor d
                     WHERE d.id_caja_menor = e.id_caja_menor
                       AND d.ind_estado   IN (1, 2)
                 )"
    );

    // =========================================================================
    // 3. TAB_PMTROS_TESCXP (+ TAB_PMTROS_GRALES, solo lectura)
    // =========================================================================

    // ---- PARÁMETROS GENERALES DE LA EMPRESA (solo lectura) ----
    $list_pmtros_grales = $pdo->prepare(
        "SELECT  id_empresa
           FROM  tab_pmtros_grales
          LIMIT  1"
    );

    // ---- PARÁMETROS DE TESORERÍA Y CXP (registro único de configuración) ----
    // fec_diapago1..3 son días de la semana (1 = lunes ... 6 = sábado).
    $list_pmtros_tescxp = $pdo->prepare(
        "SELECT  id_empresa,
                 fec_diapago1,
                 fec_diapago2,
                 fec_diapago3,
                 val_min_reembolso
           FROM  tab_pmtros_tescxp
          WHERE  ind_borrado = FALSE
          LIMIT  1"
    );

    // ---- PARÁMETROS DE UNA EMPRESA ESPECÍFICA ----
    // >>> NUEVO. La PK de tab_pmtros_tescxp es id_empresa, así que el modelo
    //     admite más de una empresa. La consulta con LIMIT 1 de arriba sirve
    //     para el caso mono-empresa; esta es la correcta si algún día hay dos.
    $get_pmtros_tescxp = $pdo->prepare(
        "SELECT  id_empresa,
                 fec_diapago1,
                 fec_diapago2,
                 fec_diapago3,
                 val_min_reembolso
           FROM  tab_pmtros_tescxp
          WHERE  id_empresa  = :wid_empresa
            AND  ind_borrado = FALSE"
    );

    // ---- INSERT — fun_insert_pmtros_tescxp (5 params) ----
    // id_empresa, fec_diapago1, fec_diapago2, fec_diapago3, val_min_reembolso
    $ins_pmtros_tescxp = $pdo->prepare(
        "SELECT fun_insert_pmtros_tescxp(
            :wid_empresa,
            :wfec_diapago1,
            :wfec_diapago2,
            :wfec_diapago3,
            :wval_min_reembolso
        )"
    );

    // ---- UPDATE — fun_update_pmtros_tescxp (5 params) ----
    // id_empresa, fec_diapago1, fec_diapago2, fec_diapago3, val_min_reembolso
    $upd_pmtros_tescxp = $pdo->prepare(
        "SELECT fun_update_pmtros_tescxp(
            :wid_empresa,
            :wfec_diapago1,
            :wfec_diapago2,
            :wfec_diapago3,
            :wval_min_reembolso
        )"
    );

    // ---- DELETE — fun_delete_pmtros_tescxp (1 param) ----
    // >>> NUEVO / FIRMA INFERIDA. La tabla tiene ind_borrado y no había
    //     ninguna instrucción para el borrado lógico de la configuración.
    $del_pmtros_tescxp = $pdo->prepare(
        "SELECT fun_delete_pmtros_tescxp(:wid_empresa)"
    );

    // =========================================================================
    // 4. TAB_CTAS_EMPRESA
    // =========================================================================

    // ---- SELECT MAESTRO — BANCOS (catálogo para selects) ----
    // >>> CORREGIDO: antes no filtraba nada, así que los bancos borrados o
    //     inactivos aparecían en los combos de cuentas y de archivo plano.
    $list_bancos = $pdo->prepare(
        "SELECT  id_banco, nom_banco
           FROM  tab_bancos
          WHERE  ind_borrado = FALSE
            AND  ind_estado  = TRUE
          ORDER BY nom_banco"
    );

    // ---- LISTADO DE CUENTAS DE LA EMPRESA ----
    $list_ctas_empresa = $pdo->prepare(
        "SELECT  c.id_empresa,
                 c.cta_empresa,
                 c.id_banco,
                 b.nom_banco,
                 c.ind_tipocuenta
           FROM  tab_ctas_empresa  c
           JOIN  tab_bancos        b ON b.id_banco = c.id_banco
          WHERE  c.ind_borrado = FALSE
          ORDER BY b.nom_banco, c.cta_empresa"
    );

    // ---- UNA CUENTA DE LA EMPRESA (para el formulario de edición) ----
    // >>> NUEVO. La PK es compuesta (id_empresa, cta_empresa) y no había
    //     consulta para traer una sola fila.
    $get_cta_empresa = $pdo->prepare(
        "SELECT  c.id_empresa,
                 c.cta_empresa,
                 c.id_banco,
                 b.nom_banco,
                 c.ind_tipocuenta
           FROM  tab_ctas_empresa  c
           JOIN  tab_bancos        b ON b.id_banco = c.id_banco
          WHERE  c.id_empresa  = :wid_empresa
            AND  c.cta_empresa = :wcta_empresa
            AND  c.ind_borrado = FALSE"
    );

    // ---- CUENTAS DE LA EMPRESA EN UN BANCO ESPECÍFICO ----
    // (al generar un archivo plano, la cuenta de origen debe ser del mismo
    //  banco al que se le entrega el archivo)
    $list_ctas_empresa_banco = $pdo->prepare(
        "SELECT  c.id_empresa,
                 c.cta_empresa,
                 c.ind_tipocuenta
           FROM  tab_ctas_empresa  c
          WHERE  c.ind_borrado = FALSE
            AND  c.id_banco    = :wid_banco
          ORDER BY c.cta_empresa"
    );

    // ---- INSERT — fun_insert_ctas_empresa (4 params) ----
    // id_empresa, cta_empresa, id_banco, ind_tipocuenta
    $ins_cta_empresa = $pdo->prepare(
        "SELECT fun_insert_ctas_empresa(
            :wid_empresa,
            :wcta_empresa,
            :wid_banco,
            :wind_tipocuenta
        )"
    );

    // ---- UPDATE — fun_update_ctas_empresa (4 params) ----
    // id_empresa, cta_empresa, id_banco, ind_tipocuenta
    // (id_empresa + cta_empresa actúan como llave de búsqueda / PK compuesta)
    $upd_cta_empresa = $pdo->prepare(
        "SELECT fun_update_ctas_empresa(
            :wid_empresa,
            :wcta_empresa,
            :wid_banco,
            :wind_tipocuenta
        )"
    );

    // ---- DELETE — fun_delete_ctas_empresa (2 params) ----
    // id_empresa, cta_empresa — Borrado lógico (ind_borrado = TRUE)
    $del_cta_empresa = $pdo->prepare(
        "SELECT fun_delete_ctas_empresa(
            :wid_empresa,
            :wcta_empresa
        )"
    );

    // =========================================================================
    // 5. TAB_BANCOXPROV
    // =========================================================================

    // ---- SELECT MAESTRO — PROVEEDORES (catálogo para selects) ----
    $list_proveedores_select = $pdo->prepare(
        "SELECT  p.id_proveedor,
                 t.nom_tercero
           FROM  tab_proveedores p
           JOIN  tab_terceros    t ON t.id_tercero = p.id_proveedor
          WHERE  p.ind_borrado = FALSE
            AND  t.ind_borrado = FALSE
          ORDER BY t.nom_tercero"
    );

    // ---- LISTADO DE BANCOS POR PROVEEDOR ----
    $list_bancoxprov = $pdo->prepare(
        "SELECT  bp.id_proveedor,
                 t.nom_tercero,
                 bp.cta_proveedor,
                 bp.id_banco,
                 b.nom_banco,
                 bp.ind_tipocuenta
           FROM  tab_bancoxprov  bp
           JOIN  tab_terceros    t ON t.id_tercero = bp.id_proveedor
           JOIN  tab_bancos      b ON b.id_banco   = bp.id_banco
          WHERE  bp.ind_borrado = FALSE
          ORDER BY t.nom_tercero, bp.cta_proveedor"
    );

    // ---- UNA CUENTA DE PROVEEDOR (para el formulario de edición) ----
    // >>> NUEVO. Igual que en tab_ctas_empresa, la PK es compuesta.
    $get_bancoxprov = $pdo->prepare(
        "SELECT  bp.id_proveedor,
                 t.nom_tercero,
                 bp.cta_proveedor,
                 bp.id_banco,
                 b.nom_banco,
                 bp.ind_tipocuenta
           FROM  tab_bancoxprov  bp
           JOIN  tab_terceros    t ON t.id_tercero = bp.id_proveedor
           JOIN  tab_bancos      b ON b.id_banco   = bp.id_banco
          WHERE  bp.id_proveedor  = :wid_proveedor
            AND  bp.cta_proveedor = :wcta_proveedor
            AND  bp.ind_borrado   = FALSE"
    );

    // ---- CUENTAS ACTIVAS DE UN PROVEEDOR (para el select del archivo plano) ----
    // >>> CORREGIDO: faltaba filtrar el banco. Se estaban ofreciendo cuentas
    //     de bancos borrados o inactivos como destino de un pago.
    $list_ctas_de_proveedor = $pdo->prepare(
        "SELECT  bp.id_proveedor,
                 bp.cta_proveedor,
                 bp.id_banco,
                 b.nom_banco,
                 bp.ind_tipocuenta
           FROM  tab_bancoxprov  bp
           JOIN  tab_bancos      b ON b.id_banco = bp.id_banco
          WHERE  bp.ind_borrado   = FALSE
            AND  b.ind_borrado    = FALSE
            AND  b.ind_estado     = TRUE
            AND  bp.id_proveedor  = :wid_proveedor
          ORDER BY b.nom_banco, bp.cta_proveedor"
    );

    // ---- INSERT — fun_insert_bancoxprov (4 params) ----
    // id_proveedor, cta_proveedor, id_banco, ind_tipocuenta
    $ins_bancoxprov = $pdo->prepare(
        "SELECT fun_insert_bancoxprov(
            :wid_proveedor,
            :wcta_proveedor,
            :wid_banco,
            :wind_tipocuenta
        )"
    );

    // ---- UPDATE — fun_update_bancoxprov (4 params) ----
    // id_proveedor, cta_proveedor, id_banco, ind_tipocuenta
    // (id_proveedor + cta_proveedor actúan como llave de búsqueda / PK compuesta)
    $upd_bancoxprov = $pdo->prepare(
        "SELECT fun_update_bancoxprov(
            :wid_proveedor,
            :wcta_proveedor,
            :wid_banco,
            :wind_tipocuenta
        )"
    );

    // ---- DELETE — fun_delete_bancoxprov (2 params) ----
    // id_proveedor, cta_proveedor — Borrado lógico (ind_borrado = TRUE)
    //
    // OJO: tab_det_archivo_plano tiene FK hacia (id_proveedor, cta_proveedor),
    // por eso el borrado tiene que ser lógico y nunca físico: si se borra la
    // fila desaparecería la trazabilidad de archivos planos ya generados.
    $del_bancoxprov = $pdo->prepare(
        "SELECT fun_delete_bancoxprov(
            :wid_proveedor,
            :wcta_proveedor
        )"
    );

    // =========================================================================
    // 6. TAB_CUENTASXPAGAR / TAB_CUOTASXFACTURA
    // =========================================================================

    // ---- SELECT MAESTRO — PROVEEDORES CON DÍAS DE PAGO ----
    // (para calcular fec_vencimiento = fec_emision + ind_dias_pago)
    $list_proveedores_dias_pago = $pdo->prepare(
        "SELECT  p.id_proveedor,
                 t.nom_tercero,
                 p.ind_dias_pago
           FROM  tab_proveedores p
           JOIN  tab_terceros    t ON t.id_tercero = p.id_proveedor
          WHERE  p.ind_borrado = FALSE
            AND  t.ind_borrado = FALSE
          ORDER BY t.nom_tercero"
    );

    // ---- LISTADO DE CUENTAS POR PAGAR (FACTURAS) ----
    $list_cuentasxpagar = $pdo->prepare(
        "SELECT  f.id_factura,
                 f.id_proveedor,
                 f.id_ordencompra,
                 t.nom_tercero,
                 f.fec_emision,
                 f.fec_vencimiento,
                 f.val_factura,
                 f.val_saldo,
                 f.num_cuotas,
                 f.ind_estado
           FROM  tab_cuentasxpagar f
           JOIN  tab_terceros      t ON t.id_tercero = f.id_proveedor
          ORDER BY f.fec_vencimiento"
    );

    // ---- UNA FACTURA (encabezado, para la vista de detalle) ----
    // >>> NUEVO. La pantalla de cuotas necesita mostrar los datos de la
    //     factura y no había forma de traerlos.
    $get_cuentasxpagar = $pdo->prepare(
        "SELECT  f.id_factura,
                 f.id_proveedor,
                 f.id_ordencompra,
                 t.nom_tercero,
                 f.fec_emision,
                 f.fec_vencimiento,
                 f.val_factura,
                 f.val_saldo,
                 f.num_cuotas,
                 f.ind_estado
           FROM  tab_cuentasxpagar f
           JOIN  tab_terceros      t ON t.id_tercero = f.id_proveedor
          WHERE  f.id_factura = :wid_factura"
    );

    // ---- VALIDACIÓN — ¿YA EXISTE ESA FACTURA? ----
    // >>> NUEVO. id_factura lo digita el usuario (no se autogenera), así que
    //     conviene avisarle antes de que el INSERT falle por PK duplicada.
    $check_factura_existe = $pdo->prepare(
        "SELECT  EXISTS (
                    SELECT 1
                      FROM tab_cuentasxpagar
                     WHERE id_factura = :wid_factura
                 ) AS existe"
    );

    // ---- FACTURAS DE UN PROVEEDOR ----
    $list_facturas_proveedor = $pdo->prepare(
        "SELECT  f.id_factura,
                 f.fec_emision,
                 f.fec_vencimiento,
                 f.val_factura,
                 f.val_saldo,
                 f.num_cuotas,
                 f.ind_estado
           FROM  tab_cuentasxpagar f
          WHERE  f.id_proveedor = :wid_proveedor
          ORDER BY f.fec_vencimiento"
    );

    // ---- FACTURAS PENDIENTES (tablero: lo que se debe) ----
    // >>> NUEVO. idx_facturas_estado existe en el modelo justamente para esto,
    //     pero ninguna consulta lo aprovechaba.
    $list_facturas_pendientes = $pdo->prepare(
        "SELECT  f.id_factura,
                 f.id_proveedor,
                 t.nom_tercero,
                 f.fec_vencimiento,
                 f.val_factura,
                 f.val_saldo,
                 f.fec_vencimiento < CURRENT_DATE AS esta_vencida
           FROM  tab_cuentasxpagar f
           JOIN  tab_terceros      t ON t.id_tercero = f.id_proveedor
          WHERE  f.ind_estado = FALSE
          ORDER BY f.fec_vencimiento"
    );

    // ---- CUOTAS DE UNA FACTURA (generadas automáticamente por trigger) ----
    $list_cuotasxfactura = $pdo->prepare(
        "SELECT  id_factura,
                 id_cuota,
                 fec_vencimiento,
                 val_cuota,
                 ind_pagada
           FROM  tab_cuotasxfactura
          WHERE  id_factura = :wid_factura
          ORDER BY id_cuota"
    );

    // ---- INSERT — fun_insert_cuentasxpagar (6 params) ----
    // id_factura, id_proveedor, fec_emision, fec_vencimiento, val_factura, num_cuotas
    // (val_saldo e ind_estado se calculan internamente / las cuotas las genera
    //  un trigger al insertar — no requieren manejo desde PHP)
    // La factura NO se puede actualizar ni eliminar una vez creada.
    $ins_cuentasxpagar = $pdo->prepare(
        "SELECT fun_insert_cuentasxpagar(
            :wid_factura,
            :wid_proveedor,
            :wid_ordencompra,
            :wfec_emision,
            :wfec_vencimiento,
            :wval_factura,
            :wnum_cuotas
        )"
    );

    // =========================================================================
    // 7. TAB_ENC_CRONOPAGOS / TAB_DET_CRONOPAGOS
    // =========================================================================

    // ---- LISTADO DE CRONOGRAMAS DE PAGOS (ENCABEZADO) ----
    // >>> CORREGIDO: faltaba nom_cronograma, que existe en el modelo y que
    //     fun_update_enc_cronopagos exige como parámetro. Sin él, el formulario
    //     de edición no tenía de dónde precargar el nombre.
    $list_enc_cronopagos = $pdo->prepare(
        "SELECT  id_cronograma,
                 nom_cronograma,
                 fec_programacion,
                 total_a_pagar,
                 ind_estado
           FROM  tab_enc_cronopagos
          WHERE  ind_borrado = FALSE
          ORDER BY fec_programacion DESC, id_cronograma DESC"
    );

    // ---- UN CRONOGRAMA (encabezado, para el formulario de edición) ----
    $get_enc_cronopagos = $pdo->prepare(
        "SELECT  id_cronograma,
                 nom_cronograma,
                 fec_programacion,
                 total_a_pagar,
                 ind_estado
           FROM  tab_enc_cronopagos
          WHERE  id_cronograma = :wid_cronograma
            AND  ind_borrado   = FALSE"
    );

    // ---- DETALLE DE UN CRONOGRAMA (cuotas incluidas) ----
    $list_det_cronopagos = $pdo->prepare(
        "SELECT  d.id_cronograma,
                 d.id_factura,
                 d.id_cuota,
                 d.val_a_pagar,
                 c.fec_vencimiento,
                 c.ind_pagada,
                 f.id_proveedor,
                 t.nom_tercero
           FROM  tab_det_cronopagos  d
           JOIN  tab_cuotasxfactura  c ON c.id_factura = d.id_factura AND c.id_cuota = d.id_cuota
           JOIN  tab_cuentasxpagar   f ON f.id_factura = d.id_factura
           JOIN  tab_terceros        t ON t.id_tercero = f.id_proveedor
          WHERE  d.id_cronograma = :wid_cronograma
          ORDER BY t.nom_tercero, d.id_factura, d.id_cuota"
    );

    // ---- CUOTAS PENDIENTES DISPONIBLES PARA ARMAR UN CRONOGRAMA ----
    //
    // >>> CORREGIDO — este era un bug real.
    // tab_det_cronopagos tiene CONSTRAINT uq_cuota_programada UNIQUE
    // (id_factura, id_cuota) a nivel GLOBAL, sin importar ind_borrado del
    // encabezado. La versión anterior filtraba el NOT EXISTS con
    // "ec.ind_borrado = FALSE", así que una cuota que quedó atrapada en un
    // cronograma borrado lógicamente se mostraba como disponible y al
    // agregarla a un cronograma nuevo reventaba con violación de UNIQUE.
    // Ahora el NOT EXISTS mira TODO el detalle, sin filtrar por borrado.
    //
    // Alternativa si prefieres poder reutilizar esas cuotas: que
    // fun_delete_enc_cronopagos borre físicamente las filas de
    // tab_det_cronopagos al hacer el borrado lógico del encabezado. En ese
    // caso este NOT EXISTS queda igual de correcto.
    $list_cuotas_pendientes = $pdo->prepare(
        "SELECT  c.id_factura,
                 c.id_cuota,
                 c.fec_vencimiento,
                 c.val_cuota,
                 f.id_proveedor,
                 t.nom_tercero
           FROM  tab_cuotasxfactura  c
           JOIN  tab_cuentasxpagar   f ON f.id_factura = c.id_factura
           JOIN  tab_terceros        t ON t.id_tercero = f.id_proveedor
          WHERE  c.ind_pagada = FALSE
            AND  NOT EXISTS (
                    SELECT 1
                      FROM tab_det_cronopagos dc
                     WHERE dc.id_factura = c.id_factura
                       AND dc.id_cuota   = c.id_cuota
                 )
          ORDER BY c.fec_vencimiento"
    );

    // ---- INSERT — fun_insert_enc_cronopagos (2 params) ----
    // nom_cronograma, fec_programacion   <-- este es el orden real
    // (id_cronograma se autogenera; total_a_pagar inicia en 0 y se recalcula
    //  por trigger a medida que se insertan filas de detalle)
    $ins_enc_cronopagos = $pdo->prepare(
        "SELECT fun_insert_enc_cronopagos(
            :wnom_cronograma,
            :wfec_programacion
        )"
    );

    // ---- INSERT — fun_insert_det_cronopagos (3 params) ----
    // id_cronograma, id_factura, id_cuota
    // (Se llama una vez por cada cuota seleccionada por el usuario —
    //  val_a_pagar se resuelve internamente consultando tab_cuotasxfactura)
    $ins_det_cronopagos = $pdo->prepare(
        "SELECT fun_insert_det_cronopagos(
            :wid_cronograma,
            :wid_factura,
            :wid_cuota
        )"
    );

    // ---- UPDATE — fun_update_enc_cronopagos (3 params) ----
    // id_cronograma, nom_cronograma, fec_programacion
    // (No permite editar ind_estado ni total_a_pagar ni las cuotas incluidas)
    $upd_enc_cronopagos = $pdo->prepare(
        "SELECT fun_update_enc_cronopagos(
            :wid_cronograma,
            :wnom_cronograma,
            :wfec_programacion
        )"
    );

    // ---- DELETE — fun_delete_enc_cronopagos (1 param) ----
    // Borrado lógico (ind_borrado = TRUE)
    $del_enc_cronopagos = $pdo->prepare(
        "SELECT fun_delete_enc_cronopagos(:wid_cronograma)"
    );

    // ---- DELETE — fun_delete_det_cronopagos (3 params) ----
    // id_cronograma, id_factura, id_cuota
    // Borrado FÍSICO de una línea de detalle. Solo si el cronograma está
    // activo, pendiente (no pagado) y sin archivos planos generados.
    // La función recalcula automáticamente total_a_pagar en el encabezado.
    $del_det_cronopagos = $pdo->prepare(
        "SELECT fun_delete_det_cronopagos(
            :wid_cronograma,
            :wid_factura,
            :wid_cuota
        )"
    );

    // ---- CRONOGRAMAS PENDIENTES (disponibles para generar archivo plano) ----
    $list_cronogramas_pendientes = $pdo->prepare(
        "SELECT  id_cronograma,
                 nom_cronograma,
                 fec_programacion,
                 total_a_pagar
           FROM  tab_enc_cronopagos
          WHERE  ind_borrado = FALSE
            AND  ind_estado  = FALSE
          ORDER BY fec_programacion"
    );

    // ---- ¿EL CRONOGRAMA YA TIENE ARCHIVOS PLANOS? ----
    // >>> NUEVO. fun_delete_det_cronopagos no debe permitir quitar una cuota de
    //     un cronograma que ya se le entregó al banco. Esta consulta permite
    //     desactivar el botón en pantalla antes de que la función falle.
    $check_crono_con_archivo = $pdo->prepare(
        "SELECT  EXISTS (
                    SELECT 1
                      FROM tab_enc_archivo_plano
                     WHERE id_cronograma = :wid_cronograma
                 ) AS tiene_archivo"
    );

    // =========================================================================
    // 8. TAB_ENC_ARCHIVO_PLANO / TAB_DET_ARCHIVO_PLANO
    // =========================================================================

    // ---- CUOTAS DE UN CRONOGRAMA (una fila por cuota) ----
    //
    // >>> CORREGIDO: la consulta anterior ($list_cuotas_crono_con_cuentas)
    //     unía en el mismo SELECT las cuotas del cronograma con las cuentas
    //     del proveedor. Como un proveedor puede tener varias cuentas activas,
    //     cada cuota se repetía tantas veces como cuentas tuviera y el detalle
    //     salía duplicado en pantalla.
    //     Ahora son dos consultas: esta trae las cuotas (una fila por cuota) y
    //     $list_ctas_prov_de_cronograma trae las cuentas de cada proveedor
    //     para agruparlas en PHP por id_proveedor.
    $list_cuotas_de_cronograma = $pdo->prepare(
        "SELECT  d.id_cronograma,
                 d.id_factura,
                 d.id_cuota,
                 d.val_a_pagar,
                 f.id_proveedor,
                 t.nom_tercero
           FROM  tab_det_cronopagos  d
           JOIN  tab_cuentasxpagar   f ON f.id_factura = d.id_factura
           JOIN  tab_terceros        t ON t.id_tercero = f.id_proveedor
          WHERE  d.id_cronograma = :wid_cronograma
          ORDER BY t.nom_tercero, d.id_factura, d.id_cuota"
    );

    // ---- CUENTAS ACTIVAS DE TODOS LOS PROVEEDORES DE UN CRONOGRAMA ----
    // (se agrupa en PHP por id_proveedor para pintar un select por proveedor)
    // >>> CORREGIDO: se agregó el filtro de bancos activos, igual que en
    //     $list_ctas_de_proveedor, para no ofrecer un banco dado de baja.
    $list_ctas_prov_de_cronograma = $pdo->prepare(
        "SELECT DISTINCT
                 bp.id_proveedor,
                 bp.cta_proveedor,
                 bp.id_banco,
                 b.nom_banco,
                 bp.ind_tipocuenta
           FROM  tab_det_cronopagos  d
           JOIN  tab_cuentasxpagar   f  ON f.id_factura    = d.id_factura
           JOIN  tab_bancoxprov      bp ON bp.id_proveedor = f.id_proveedor
           JOIN  tab_bancos          b  ON b.id_banco      = bp.id_banco
          WHERE  d.id_cronograma = :wid_cronograma
            AND  bp.ind_borrado  = FALSE
            AND  b.ind_borrado   = FALSE
            AND  b.ind_estado    = TRUE
          ORDER BY bp.id_proveedor, b.nom_banco, bp.cta_proveedor"
    );

    // ---- PROVEEDORES DE UN CRONOGRAMA SIN CUENTA REGISTRADA ----
    // >>> NUEVO. Si un proveedor incluido en el cronograma no tiene ninguna
    //     cuenta activa en tab_bancoxprov, el archivo plano no se puede armar
    //     (la FK compuesta de tab_det_archivo_plano lo va a rechazar).
    //     Mejor detectarlo antes de empezar a generar filas.
    $list_prov_sin_cuenta = $pdo->prepare(
        "SELECT DISTINCT
                 f.id_proveedor,
                 t.nom_tercero
           FROM  tab_det_cronopagos  d
           JOIN  tab_cuentasxpagar   f ON f.id_factura = d.id_factura
           JOIN  tab_terceros        t ON t.id_tercero = f.id_proveedor
          WHERE  d.id_cronograma = :wid_cronograma
            AND  NOT EXISTS (
                    SELECT 1
                      FROM tab_bancoxprov bp
                     WHERE bp.id_proveedor = f.id_proveedor
                       AND bp.ind_borrado  = FALSE
                 )
          ORDER BY t.nom_tercero"
    );

    // ---- LISTADO DE ARCHIVOS PLANOS (ENCABEZADO) ----
    $list_enc_archivo_plano = $pdo->prepare(
        "SELECT  a.id_archivo_plano,
                 a.id_cronograma,
                 e.nom_cronograma,
                 a.id_banco,
                 b.nom_banco,
                 a.nom_archivo,
                 a.fec_generacion,
                 a.ind_generado
           FROM  tab_enc_archivo_plano a
           JOIN  tab_enc_cronopagos    e ON e.id_cronograma = a.id_cronograma
           JOIN  tab_bancos            b ON b.id_banco      = a.id_banco
          ORDER BY a.id_archivo_plano DESC"
    );

    // ---- UN ARCHIVO PLANO (encabezado) ----
    $get_enc_archivo_plano = $pdo->prepare(
        "SELECT  a.id_archivo_plano,
                 a.id_cronograma,
                 e.nom_cronograma,
                 a.id_banco,
                 b.nom_banco,
                 a.nom_archivo,
                 a.fec_generacion,
                 a.ind_generado
           FROM  tab_enc_archivo_plano a
           JOIN  tab_enc_cronopagos    e ON e.id_cronograma = a.id_cronograma
           JOIN  tab_bancos            b ON b.id_banco      = a.id_banco
          WHERE  a.id_archivo_plano = :wid_archivo_plano"
    );

    // ---- DETALLE DE UN ARCHIVO PLANO (filas de pago empresa -> proveedor) ----
    $list_det_archivo_plano = $pdo->prepare(
        "SELECT  d.id_archivo_plano,
                 d.id_empresa,
                 d.cta_empresa,
                 d.id_proveedor,
                 t.nom_tercero,
                 d.cta_proveedor,
                 d.ind_tipocuenta,
                 d.id_factura,
                 d.id_cuota,
                 d.val_a_pagar
           FROM  tab_det_archivo_plano d
           JOIN  tab_terceros          t ON t.id_tercero = d.id_proveedor
          WHERE  d.id_archivo_plano = :wid_archivo_plano
          ORDER BY t.nom_tercero, d.id_factura, d.id_cuota"
    );

    // ---- TOTAL Y CANTIDAD DE FILAS DE UN ARCHIVO PLANO ----
    // >>> NUEVO. Casi todos los formatos de archivo plano bancario piden un
    //     registro de control con el número de registros y la suma a debitar.
    $sum_det_archivo_plano = $pdo->prepare(
        "SELECT  COUNT(*)                        AS cant_registros,
                 COALESCE(SUM(val_a_pagar), 0)   AS total_a_debitar
           FROM  tab_det_archivo_plano
          WHERE  id_archivo_plano = :wid_archivo_plano"
    );

    // ---- INSERT — fun_insert_enc_archivo_plano (3 params) ----
    // id_cronograma, id_banco, nom_archivo
    // (id_archivo_plano se autogenera internamente con MAX(id_archivo_plano)+1,
    //  igual patrón que fun_insert_enc_cronopagos)
    $ins_enc_archivo_plano = $pdo->prepare(
        "SELECT fun_insert_enc_archivo_plano(
            :wid_cronograma,
            :wid_banco,
            :wnom_archivo
        )"
    );

    // ---- INSERT — fun_insert_det_archivo_plano (8 params) ----
    // id_archivo_plano, id_empresa, cta_empresa, id_proveedor, cta_proveedor,
    // ind_tipocuenta, id_factura, id_cuota
    //
    // >>> CORREGIDO: la versión anterior mandaba 7 placeholders y se saltaba
    //     ind_tipocuenta, que sí existe en tab_det_archivo_plano y es NOT NULL.
    //     Se guarda copiado (no por FK) para que el archivo conserve el tipo
    //     de cuenta que tenía el proveedor el día del pago.
    //
    // >>> FIRMA INFERIDA en cuanto a la posición de ind_tipocuenta: aquí va
    //     después de cta_proveedor, siguiendo el orden de columnas de la tabla.
    //     Si la función lo recibe en otra posición, hay que reordenar.
    //     (val_a_pagar se calcula internamente desde tab_det_cronopagos)
    $ins_det_archivo_plano = $pdo->prepare(
        "SELECT fun_insert_det_archivo_plano(
            :wid_archivo_plano,
            :wid_empresa,
            :wcta_empresa,
            :wid_proveedor,
            :wcta_proveedor,
            :wind_tipocuenta,
            :wid_factura,
            :wid_cuota
        )"
    );

    // ---- UPDATE — fun_update_enc_archivo_plano (3 params) ----
    // id_archivo_plano, id_banco, nom_archivo
    // (No permite editar el cronograma asociado ni las filas de detalle)
    $upd_enc_archivo_plano = $pdo->prepare(
        "SELECT fun_update_enc_archivo_plano(
            :wid_archivo_plano,
            :wid_banco,
            :wnom_archivo
        )"
    );

    // ---- UPDATE — MARCAR ARCHIVO PLANO COMO GENERADO (al descargar el CSV) ----
    // id_archivo_plano
    // (Se ejecuta la primera vez que se descarga el archivo: fija
    //  ind_generado = TRUE y fec_generacion = fecha actual del servidor.
    //  Si ya estaba generado, COALESCE evita pisar la fecha original.)
    //
    // >>> CORREGIDO: antes usaba NOW(), que devuelve timestamp; fec_generacion
    //     es DATE. Se cambia a CURRENT_DATE para no depender del cast implícito.
    $upd_generar_archivo_plano = $pdo->prepare(
        "UPDATE tab_enc_archivo_plano
            SET ind_generado   = TRUE,
                fec_generacion = COALESCE(fec_generacion, CURRENT_DATE)
          WHERE id_archivo_plano = :wid_archivo_plano"
    );

    // =========================================================================
    // 9. TAB_MOTIVOS_RECHAZO                                          (Tabla 13)
    // =========================================================================
    // Catálogo de motivos por los que un banco rechaza un pago. Se usa desde
    // tab_pagos_cxp. ind_borrado permite dar de baja un motivo sin romper los
    // pagos históricos que ya lo referencian.
    //
    // >>> CORREGIDO — este bloque venía con tres problemas:
    //     1) $ins_motivo_rechazo estaba declarado DOS VECES, con firmas
    //        distintas (4 params y 2 params). En PHP la segunda asignación
    //        pisa a la primera, así que la de 4 parámetros nunca se usaba y
    //        quedaba como código muerto que confunde al mantener. Se dejó una
    //        sola: la de 2 parámetros, que es la que sigue el patrón del
    //        proyecto (el id se autogenera; ind_borrado entra por DEFAULT).
    //     2) El comentario "LISTADO COMPLETO" estaba encima del INSERT.
    //     3) La indentación se salía del bloque try (cosmético, pero hacía
    //        difícil ver dónde terminaba el archivo).

    // ---- LISTADO COMPLETO (vista de administración: incluye inactivos) ----
    $list_motivos_rechazo = $pdo->prepare(
        "SELECT  id_motivo_rechazo,
                 des_motivo,
                 cod_bancario
           FROM  tab_motivos_rechazo
           WHERE ind_borrado = FALSE
          ORDER BY des_motivo"
    );

    // ---- UN MOTIVO (para el formulario de edición) ----
    // >>> NUEVO.
    $get_motivo_rechazo = $pdo->prepare(
        "SELECT  id_motivo_rechazo,
                 des_motivo,
                 cod_bancario,
                 ind_borrado
           FROM  tab_motivos_rechazo
          WHERE  id_motivo_rechazo = :wid_motivo_rechazo"
    );

    $ins_motivo_rechazo = $pdo->prepare(
    "SELECT fun_insert_motivos_rechazo(
        :wid_motivo_rechazo,
        :wdes_motivo,
        :wcod_bancario,
        :wind_borrado)"
);

// ---- UPDATE DE UN MOTIVO ----
    // id_motivo_rechazo, des_motivo, cod_bancario, ind_borrado
    $upd_motivo_rechazo = $pdo->prepare(
        "SELECT fun_update_motivos_rechazo(
                :wid_motivo_rechazo,
                :wdes_motivo,
                :wcod_bancario,
                :wind_borrado)"
    );


    // ---- DELETE — fun_delete_motivos_rechazo (1 param) ----
    // Borrado lógico (ind_borrado = TRUE)
    //
    // >>> FIRMA INFERIDA. Debería fallar (o al menos advertir) si el motivo ya
    //     está referenciado por algún pago rechazado, para no dejar huérfano
    //     el histórico. Se puede validar antes con $check_motivo_en_uso.
    $del_motivo_rechazo = $pdo->prepare(
        "SELECT fun_delete_motivos_rechazo(:wid_motivo_rechazo)"
    );

    // ---- VALIDACIÓN — ¿EL MOTIVO YA SE USÓ EN ALGÚN PAGO? ----
    $check_motivo_en_uso = $pdo->prepare(
        "SELECT  EXISTS (
                    SELECT 1
                      FROM tab_pagos_cxp
                     WHERE id_motivo_rechazo = :wid_motivo_rechazo
                 ) AS en_uso"
    );

    // =========================================================================
    // 10. TAB_PAGOS_CXP                                               (Tabla 14)
    // =========================================================================
    // Registra cada INTENTO de pago de una cuota de factura. Los intentos
    // rechazados nunca se borran: son el historial.
    //
    // Reglas del modelo que hay que respetar desde la aplicación:
    //   - estado_pago solo admite 'PENDIENTE', 'APROBADO' o 'RECHAZADO'.
    //   - El índice parcial uq_pago_aprobado_cuota permite UN SOLO pago
    //     APROBADO por cuota. Se puede reintentar cuantas veces haga falta,
    //     pero aprobar una sola vez.
    //   - id_motivo_rechazo es obligatorio cuando el estado es 'RECHAZADO'
    //     (la tabla no lo fuerza con un CHECK, lo valida la aplicación).
    //   - id_archivo_plano va NULL solo si fue un pago manual. Si viene con
    //     valor, la FK compuesta exige que exista la fila
    //     (id_archivo_plano, id_factura, id_cuota) en tab_det_archivo_plano.
    //
    // >>> SIN TRIGGER: al aprobar un pago, marcar la cuota como pagada y
    //     recalcular el saldo de la factura le toca a la aplicación.
    //     Ver $mark_cuota_pagada y $recalc_saldo_factura más abajo.

    // ---- LISTADO GENERAL DE PAGOS ----
    $list_pagos_cxp = $pdo->prepare(
        "SELECT  p.id_pago,
                 p.id_factura,
                 p.id_cuota,
                 f.id_proveedor,
                 t.nom_tercero,
                 p.id_archivo_plano,
                 p.fec_pago,
                 p.val_pago,
                 p.estado_pago,
                 p.referencia_bancaria,
                 p.id_motivo_rechazo,
                 m.des_motivo
           FROM  tab_pagos_cxp        p
           JOIN  tab_cuentasxpagar    f ON f.id_factura = p.id_factura
           JOIN  tab_terceros         t ON t.id_tercero = f.id_proveedor
           LEFT JOIN tab_motivos_rechazo m ON m.id_motivo_rechazo = p.id_motivo_rechazo
          ORDER BY p.fec_pago DESC, p.id_pago DESC"
    );

    // ---- UN PAGO (para el formulario de conciliación) ----
    // >>> NUEVO. No había forma de traer un solo intento de pago por su id.
    $get_pago_cxp = $pdo->prepare(
        "SELECT  p.id_pago,
                 p.id_factura,
                 p.id_cuota,
                 f.id_proveedor,
                 t.nom_tercero,
                 p.id_archivo_plano,
                 p.fec_pago,
                 p.val_pago,
                 p.estado_pago,
                 p.referencia_bancaria,
                 p.id_motivo_rechazo,
                 m.des_motivo
           FROM  tab_pagos_cxp        p
           JOIN  tab_cuentasxpagar    f ON f.id_factura = p.id_factura
           JOIN  tab_terceros         t ON t.id_tercero = f.id_proveedor
           LEFT JOIN tab_motivos_rechazo m ON m.id_motivo_rechazo = p.id_motivo_rechazo
          WHERE  p.id_pago = :wid_pago"
    );

    // ---- PAGOS POR ESTADO (para las pestañas Pendiente / Aprobado / Rechazado) ----
    // >>> CORREGIDO: faltaban p.id_archivo_plano y p.id_motivo_rechazo, que la
    //     pantalla necesita para armar los enlaces y precargar el combo de
    //     motivos al reprocesar un rechazo.
    $list_pagos_por_estado = $pdo->prepare(
        "SELECT  p.id_pago,
                 p.id_factura,
                 p.id_cuota,
                 f.id_proveedor,
                 t.nom_tercero,
                 p.id_archivo_plano,
                 p.fec_pago,
                 p.val_pago,
                 p.referencia_bancaria,
                 p.id_motivo_rechazo,
                 m.des_motivo
           FROM  tab_pagos_cxp        p
           JOIN  tab_cuentasxpagar    f ON f.id_factura = p.id_factura
           JOIN  tab_terceros         t ON t.id_tercero = f.id_proveedor
           LEFT JOIN tab_motivos_rechazo m ON m.id_motivo_rechazo = p.id_motivo_rechazo
          WHERE  p.estado_pago = :westado_pago
          ORDER BY p.fec_pago DESC, p.id_pago DESC"
    );

    // ---- HISTORIAL DE INTENTOS DE UNA CUOTA ----
    // >>> CORREGIDO: faltaba p.id_motivo_rechazo (solo traía la descripción,
    //     así que no se podía enlazar al catálogo desde la pantalla).
    $list_pagos_de_cuota = $pdo->prepare(
        "SELECT  p.id_pago,
                 p.id_archivo_plano,
                 p.fec_pago,
                 p.val_pago,
                 p.estado_pago,
                 p.referencia_bancaria,
                 p.id_motivo_rechazo,
                 m.des_motivo
           FROM  tab_pagos_cxp        p
           LEFT JOIN tab_motivos_rechazo m ON m.id_motivo_rechazo = p.id_motivo_rechazo
          WHERE  p.id_factura = :wid_factura
            AND  p.id_cuota   = :wid_cuota
          ORDER BY p.id_pago DESC"
    );

    // ---- VALIDACIÓN — ¿ESTA CUOTA YA TIENE UN PAGO APROBADO? ----
    // >>> NUEVO. uq_pago_aprobado_cuota es un índice UNIQUE parcial: si se
    //     intenta aprobar un segundo pago de la misma cuota, PostgreSQL lanza
    //     un error de índice duplicado que en pantalla no dice nada útil.
    //     Con esto se valida antes y se muestra un mensaje entendible.
    $check_cuota_pago_aprobado = $pdo->prepare(
        "SELECT  EXISTS (
                    SELECT 1
                      FROM tab_pagos_cxp
                     WHERE id_factura  = :wid_factura
                       AND id_cuota    = :wid_cuota
                       AND estado_pago = 'APROBADO'
                 ) AS ya_pagada"
    );

    // ---- CONCILIACIÓN — FILAS DE UN ARCHIVO PLANO CON SU ÚLTIMO INTENTO ----
    // (pantalla para cargar la respuesta del banco: se ve cada fila enviada y
    //  en qué quedó. El LATERAL trae solo el intento más reciente, si no se
    //  duplicarían las filas que ya tienen varios intentos rechazados.)
    $list_conciliacion_archivo = $pdo->prepare(
        "SELECT  d.id_archivo_plano,
                 d.id_factura,
                 d.id_cuota,
                 d.id_proveedor,
                 t.nom_tercero,
                 d.cta_proveedor,
                 d.val_a_pagar,
                 p.id_pago,
                 p.estado_pago,
                 p.referencia_bancaria
           FROM  tab_det_archivo_plano d
           JOIN  tab_terceros          t ON t.id_tercero = d.id_proveedor
           LEFT JOIN LATERAL (
                    SELECT pg.id_pago, pg.estado_pago, pg.referencia_bancaria
                      FROM tab_pagos_cxp pg
                     WHERE pg.id_archivo_plano = d.id_archivo_plano
                       AND pg.id_factura       = d.id_factura
                       AND pg.id_cuota         = d.id_cuota
                     ORDER BY pg.id_pago DESC
                     LIMIT 1
                 ) p ON TRUE
          WHERE  d.id_archivo_plano = :wid_archivo_plano
          ORDER BY t.nom_tercero, d.id_factura, d.id_cuota"
    );

    // ---- CUOTAS PAGABLES (para registrar un pago manual) ----
    // >>> CORREGIDO: antes solo excluía las cuotas con un pago 'APROBADO', así
    //     que una cuota con un intento todavía en 'PENDIENTE' seguía saliendo
    //     en la lista y se podía registrar dos veces el mismo pago.
    //     Ahora se excluyen APROBADO y PENDIENTE; las RECHAZADAS sí vuelven a
    //     aparecer, que es justamente lo que se quiere para poder reintentar.
    $list_cuotas_pagables = $pdo->prepare(
        "SELECT  c.id_factura,
                 c.id_cuota,
                 c.fec_vencimiento,
                 c.val_cuota,
                 f.id_proveedor,
                 t.nom_tercero
           FROM  tab_cuotasxfactura  c
           JOIN  tab_cuentasxpagar   f ON f.id_factura = c.id_factura
           JOIN  tab_terceros        t ON t.id_tercero = f.id_proveedor
          WHERE  c.ind_pagada = FALSE
            AND  NOT EXISTS (
                    SELECT 1
                      FROM tab_pagos_cxp p
                     WHERE p.id_factura  = c.id_factura
                       AND p.id_cuota    = c.id_cuota
                       AND p.estado_pago IN ('APROBADO', 'PENDIENTE')
                 )
          ORDER BY c.fec_vencimiento"
    );

    // ---- UNA LÍNEA DE ARCHIVO PLANO (para validar e importar la respuesta del banco) ----
    // >>> NUEVO. La respuesta del banco identifica cada giro por
    //     (id_archivo_plano, id_factura, id_cuota) porque ese es el trío que
    //     realmente se le envió al banco (tab_det_archivo_plano). Antes de
    //     crear el pago hay que confirmar que esa línea existe y traer
    //     val_a_pagar, que es el valor real y no debe venir del archivo del
    //     banco (evita que una fila mal armada infle o desinfle el pago).
    $get_linea_archivo_plano = $pdo->prepare(
        "SELECT  d.id_archivo_plano,
                 d.id_factura,
                 d.id_cuota,
                 d.id_proveedor,
                 d.val_a_pagar
           FROM  tab_det_archivo_plano d
          WHERE  d.id_archivo_plano = :wid_archivo_plano
            AND  d.id_factura       = :wid_factura
            AND  d.id_cuota         = :wid_cuota"
    );

    // ---- ¿ESA LÍNEA DE ARCHIVO PLANO YA TIENE UN PAGO REGISTRADO? ----
    // >>> NUEVO. Evita duplicar el historial si el mismo archivo de respuesta
    //     del banco se importa dos veces por error.
    $check_pago_de_linea = $pdo->prepare(
        "SELECT  id_pago, estado_pago
           FROM  tab_pagos_cxp
          WHERE  id_archivo_plano = :wid_archivo_plano
            AND  id_factura       = :wid_factura
            AND  id_cuota         = :wid_cuota"
    );

    // ---- INSERT — fun_insert_pagos_cxp (6 params) ----
    // id_factura, id_cuota, id_archivo_plano, fec_pago, val_pago, referencia_bancaria
    //
    // >>> FIRMA INFERIDA. Supuestos:
    //     - id_pago se autogenera dentro de la función (patrón del proyecto).
    //     - estado_pago entra con el DEFAULT 'PENDIENTE'; el estado final se
    //       fija después con $upd_estado_pago_cxp.
    //     - id_archivo_plano y referencia_bancaria admiten NULL.
    $ins_pago_cxp = $pdo->prepare(
        "SELECT fun_insert_pagos_cxp(
            :wid_factura,
            :wid_cuota,
            :wid_archivo_plano,
            :wfec_pago,
            :wval_pago,
            :wreferencia_bancaria
        )"
    );

    // ---- UPDATE — fun_update_pagos_cxp (4 params) ----
    // id_pago, estado_pago, referencia_bancaria, id_motivo_rechazo
    //
    // >>> FIRMA INFERIDA. Es la que aplica la respuesta del banco.
    //     La función debería validar que id_motivo_rechazo venga con valor
    //     cuando estado_pago = 'RECHAZADO', y NULL en los otros dos casos.
    $upd_estado_pago_cxp = $pdo->prepare(
        "SELECT fun_update_pagos_cxp(
            :wid_pago,
            :westado_pago,
            :wreferencia_bancaria,
            :wid_motivo_rechazo
        )"
    );

    // ---- >>> SIN TRIGGER — EFECTOS DE APROBAR UN PAGO ----
    // Los dos statements de abajo deben ejecutarse junto con
    // $upd_estado_pago_cxp DENTRO DE LA MISMA TRANSACCIÓN, y solo cuando el
    // pago pasa a 'APROBADO', EN ESTE ORDEN:
    //
    //     $pdo->beginTransaction();
    //     $upd_estado_pago_cxp->execute([...]);
    //     $mark_cuota_pagada->execute([':wid_factura' => $f, ':wid_cuota' => $c]);
    //     $recalc_saldo_factura->execute([':wid_factura' => $f]);
    //     $cerrar_cronograma_si_pagado->execute([':wid_cronograma' => $cr]);
    //     $pdo->commit();
    //
    // El orden importa: $recalc_saldo_factura lee el estado de las cuotas, así
    // que la cuota tiene que estar ya marcada como pagada.
    //
    // Si más adelante se agrega un trigger AFTER UPDATE sobre tab_pagos_cxp,
    // estos statements se eliminan de aquí.

    // 1) Marcar la cuota como pagada
    $mark_cuota_pagada = $pdo->prepare(
        "UPDATE tab_cuotasxfactura
            SET ind_pagada = TRUE
          WHERE id_factura = :wid_factura
            AND id_cuota   = :wid_cuota"
    );

    // 2) Recalcular el saldo de la factura y cerrarla si quedó en cero
    //
    // >>> CORREGIDO — reemplaza al antiguo $desc_saldo_factura, que hacía
    //     "val_saldo - :wval_pago" y necesitaba mandar el mismo monto en dos
    //     placeholders distintos (:wval_pago y :wval_pago2) porque PDO_PGSQL
    //     no admite repetir un parámetro con nombre en la misma sentencia.
    //     Tres problemas de aquel enfoque:
    //       a) Restar no es idempotente: si el mismo pago se procesaba dos
    //          veces (doble clic, reintento), el saldo se descontaba dos veces.
    //       b) Si alguien mandaba :wval_pago y :wval_pago2 con valores
    //          distintos, val_saldo e ind_estado quedaban inconsistentes.
    //       c) Un pago parcial mal digitado dejaba el saldo torcido para
    //          siempre, sin forma de corregirlo.
    //     Esta versión recalcula el saldo desde las cuotas que siguen sin
    //     pagar: siempre da el valor correcto, se puede ejecutar las veces que
    //     sea, y de paso usa un solo parámetro.
    //
    //     Si prefieres conservar el nombre anterior en el código que ya llama
    //     a esto, agrega:  $desc_saldo_factura = $recalc_saldo_factura;
    //     pero recuerda que ahora solo recibe :wid_factura.
    $recalc_saldo_factura = $pdo->prepare(
        "UPDATE tab_cuentasxpagar f
            SET val_saldo  = (
                    SELECT COALESCE(SUM(c.val_cuota), 0)
                      FROM tab_cuotasxfactura c
                     WHERE c.id_factura = f.id_factura
                       AND c.ind_pagada = FALSE
                 ),
                ind_estado = NOT EXISTS (
                    SELECT 1
                      FROM tab_cuotasxfactura c2
                     WHERE c2.id_factura = f.id_factura
                       AND c2.ind_pagada = FALSE
                 )
          WHERE f.id_factura = :wid_factura"
    );

    // ---- >>> SIN TRIGGER — CERRAR UN CRONOGRAMA COMPLETAMENTE PAGADO ----
    // Marca ind_estado = TRUE en el encabezado cuando ya no le quedan cuotas
    // sin pagar. Conviene ejecutarlo después de aprobar cada pago.
    $cerrar_cronograma_si_pagado = $pdo->prepare(
        "UPDATE tab_enc_cronopagos e
            SET ind_estado = TRUE
          WHERE e.id_cronograma = :wid_cronograma
            AND e.ind_borrado   = FALSE
            AND NOT EXISTS (
                    SELECT 1
                      FROM tab_det_cronopagos d
                      JOIN tab_cuotasxfactura c
                        ON c.id_factura = d.id_factura
                       AND c.id_cuota   = d.id_cuota
                     WHERE d.id_cronograma = e.id_cronograma
                       AND c.ind_pagada    = FALSE
                 )"
    );

    // ---- CRONOGRAMA AL QUE PERTENECE UNA CUOTA ----
    // >>> NUEVO. $cerrar_cronograma_si_pagado necesita el id_cronograma, y
    //     después de aprobar un pago solo se tiene (id_factura, id_cuota).
    $get_cronograma_de_cuota = $pdo->prepare(
        "SELECT  d.id_cronograma
           FROM  tab_det_cronopagos d
          WHERE  d.id_factura = :wid_factura
            AND  d.id_cuota   = :wid_cuota"
    );

    // =========================================================================
    // 11. TAB_BANCOS  (catálogo compartido)
    // =========================================================================

    // ---- LISTAR (vista principal de bancos.php) ----
    $list_bancos_full = $pdo->prepare(
        "SELECT  id_banco,
                 nom_banco,
                 ind_estado
           FROM  tab_bancos
          WHERE  ind_borrado = FALSE
          ORDER BY nom_banco ASC"
    );

    // ---- UN BANCO (para el formulario de edición) ----
    // >>> NUEVO.
    $get_banco = $pdo->prepare(
        "SELECT  id_banco,
                 nom_banco,
                 ind_estado
           FROM  tab_bancos
          WHERE  id_banco    = :wid_banco
            AND  ind_borrado = FALSE"
    );

    // ---- INSERT — fun_insert_bancos (3 params) ----
    // id_banco, nom_banco, ind_estado
    $ins_banco = $pdo->prepare(
        "SELECT fun_insert_bancos(:wid_banco, :wnom_banco, :wind_estado)"
    );

    // ---- UPDATE — fun_update_bancos (3 params) ----
    // id_banco, nom_banco, ind_estado
    $upd_banco = $pdo->prepare(
        "SELECT fun_update_bancos(:wid_banco, :wnom_banco, :wind_estado)"
    );


    // ---- DELETE — fun_delete_bancos (1 param) ----
    // Borrado lógico
    $del_banco = $pdo->prepare(
        "SELECT fun_delete_bancos(:wid_banco)"
    );

    // ---- VALIDACIÓN — ¿EL BANCO ESTÁ EN USO? ----
    // >>> NUEVO. Antes de dar de baja un banco conviene saber si hay cuentas de
    //     empresa, cuentas de proveedor o archivos planos que lo referencian.
    $check_banco_en_uso = $pdo->prepare(
        "SELECT  EXISTS (SELECT 1 FROM tab_ctas_empresa
                          WHERE id_banco = :wid_banco AND ind_borrado = FALSE)  AS en_ctas_empresa,
                 EXISTS (SELECT 1 FROM tab_bancoxprov
                          WHERE id_banco = :wid_banco2 AND ind_borrado = FALSE) AS en_ctas_proveedor,
                 EXISTS (SELECT 1 FROM tab_enc_archivo_plano
                          WHERE id_banco = :wid_banco3)                         AS en_archivos_planos"
    );
    // OJO con la de arriba: PDO_PGSQL no permite repetir un placeholder con
    // nombre dentro de la misma sentencia, por eso van :wid_banco, :wid_banco2
    // y :wid_banco3. Al ejecutar hay que mandar el mismo valor en los tres.

} catch (PDOException $e) {
    error_log("Error en prepare_tescxp.php: " . $e->getMessage());
    die("Error crítico al preparar consultas. Revise los logs del servidor.");
}
?>
