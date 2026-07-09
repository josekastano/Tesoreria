    <?php
/**
 * CENTRAL DE INSTRUCCIONES PREPARADAS - Módulo Tesorería y CxP
 * Versión: 1.0 — Festivos
 *
 * Orden de los bloques: igual al orden de creación de tablas en
 * script_BD_tesore_cxp_V2_7.sql, para que sea fácil ubicar cada
 * consulta comparando contra el modelo de datos.
 *
 *   1.  tab_festivos
 *   2.  tab_enc_caja_menor / tab_det_caja_menor
 *   3.  tab_pmtros_tescxp (+ tab_pmtros_grales, solo lectura)
 *   4.  tab_ctas_empresa
 *   5.  tab_bancoxprov
 *   6.  tab_cuentasxpagar / tab_cuotasxfactura
 *   7.  tab_enc_cronopagos / tab_det_cronopagos
 *   8.  tab_enc_archivo_plano / tab_det_archivo_plano
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
    $list_enc_caja_menor = $pdo->prepare(
        "SELECT  id_caja_menor,
                 nom_caja_menor,
                 monto_asignado,
                 monto_disponible,
                 fecha_apertura,
                 fecha_cierre,
                 ind_estado_caja_m
           FROM  tab_enc_caja_menor
          ORDER BY fecha_apertura DESC, id_caja_menor DESC"
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
          WHERE  id_caja_menor = :id_caja_menor
          ORDER BY id_movimiento DESC"
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

    // ---- DELETE (CIERRE LÓGICO) — fun_delete_enc_caja_menor (1 param) ----
    // id_caja_menor
    // (Fija fecha_cierre = CURRENT_DATE e ind_estado_caja_m = FALSE. Falla si
    //  quedan movimientos en estado Pendiente o Aprobado sin reembolsar.)
    $del_enc_caja_menor = $pdo->prepare(
        "SELECT fun_delete_enc_caja_menor(
            :wid_caja_menor
        )"
    );

    // =========================================================================
    // 3. TAB_PMTROS_TESCXP (+ TAB_PMTROS_GRALES, solo lectura)
    // =========================================================================

    // ---- PARÁMETROS GENERALES DE LA EMPRESA (solo lectura — id_empresa fijo) ----
    $list_pmtros_grales = $pdo->prepare(
        "SELECT  id_empresa
           FROM  tab_pmtros_grales
          LIMIT  1"
    );

    // ---- PARÁMETROS DE TESORERÍA Y CXP (registro único de configuración) ----
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

    // =========================================================================
    // 4. TAB_CTAS_EMPRESA
    // =========================================================================

    // ---- SELECT MAESTRO — BANCOS (catálogo para selects) ----
    $list_bancos = $pdo->prepare(
        "SELECT  id_banco, nom_banco
           FROM  tab_bancos
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

    // ---- SELECT MAESTRO — PROVEEDORES (catálogo para selects con NIT + razón social) ----
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

    // ---- CUOTAS DE UNA FACTURA (generadas automáticamente por trigger) ----
    $list_cuotasxfactura = $pdo->prepare(
        "SELECT  id_factura,
                 id_cuota,
                 fec_vencimiento,
                 val_cuota,
                 ind_pagada
           FROM  tab_cuotasxfactura
          WHERE  id_factura = :id_factura
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

    // ---- DETALLE DE UN CRONOGRAMA (cuotas incluidas) ----
    $list_det_cronopagos = $pdo->prepare(
        "SELECT  d.id_cronograma,
                 d.id_factura,
                 d.id_cuota,
                 d.val_a_pagar,
                 c.fec_vencimiento,
                 f.id_proveedor,
                 t.nom_tercero
           FROM  tab_det_cronopagos  d
           JOIN  tab_cuotasxfactura  c ON c.id_factura = d.id_factura AND c.id_cuota = d.id_cuota
           JOIN  tab_cuentasxpagar   f ON f.id_factura = d.id_factura
           JOIN  tab_terceros        t ON t.id_tercero = f.id_proveedor
          WHERE  d.id_cronograma = :id_cronograma
          ORDER BY d.id_factura, d.id_cuota"
    );

    // ---- CUOTAS PENDIENTES DISPONIBLES PARA ARMAR UN CRONOGRAMA ----
    // (cuotas no pagadas que aún no están incluidas en ningún cronograma activo)
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
                      JOIN tab_enc_cronopagos ec ON ec.id_cronograma = dc.id_cronograma
                     WHERE dc.id_factura = c.id_factura
                       AND dc.id_cuota   = c.id_cuota
                       AND ec.ind_borrado = FALSE
                 )
          ORDER BY c.fec_vencimiento"
    );

    // ---- INSERT — fun_insert_enc_cronopagos (2 params) ----
    // fec_programacion, nom_cronograma
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

    // ---- CUOTAS DE UN CRONOGRAMA, CON PROVEEDOR Y SUS CUENTAS DISPONIBLES ----
    // (para construir el formulario dinámico de selección de cuenta por proveedor)
    $list_cuotas_crono_con_cuentas = $pdo->prepare(
        "SELECT  d.id_cronograma,
                 d.id_factura,
                 d.id_cuota,
                 d.val_a_pagar,
                 f.id_proveedor,
                 t.nom_tercero,
                 bp.cta_proveedor,
                 bp.ind_tipocuenta,
                 b.nom_banco AS nom_banco_proveedor
           FROM  tab_det_cronopagos  d
           JOIN  tab_cuentasxpagar   f  ON f.id_factura   = d.id_factura
           JOIN  tab_terceros        t  ON t.id_tercero   = f.id_proveedor
           JOIN  tab_bancoxprov      bp ON bp.id_proveedor = f.id_proveedor AND bp.ind_borrado = FALSE
           JOIN  tab_bancos          b  ON b.id_banco     = bp.id_banco
          WHERE  d.id_cronograma = :id_cronograma
          ORDER BY t.nom_tercero, d.id_factura, d.id_cuota, bp.cta_proveedor"
    );

    // =========================================================================
    // 8. TAB_ENC_ARCHIVO_PLANO / TAB_DET_ARCHIVO_PLANO
    // =========================================================================

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
          WHERE  d.id_archivo_plano = :id_archivo_plano
          ORDER BY t.nom_tercero, d.id_factura, d.id_cuota"
    );

    // ---- INSERT — fun_insert_enc_archivo_plano (4 params) ----
    // id_archivo_plano (NULL para autogenerar vía COALESCE), id_cronograma,
    // id_banco, nom_archivo
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
    // (Se llama una vez por cada cuota del cronograma, con las cuentas ya
    //  resueltas — val_a_pagar se calcula internamente)
    $ins_det_archivo_plano = $pdo->prepare(
        "SELECT fun_insert_det_archivo_plano(
            :wid_archivo_plano,
            :wid_empresa,
            :wcta_empresa,
            :wid_proveedor,
            :wcta_proveedor,
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
    //  ind_generado = TRUE y fec_generacion = fecha/hora actual del servidor.
    //  Si ya estaba generado, COALESCE evita pisar la fecha original.)
    $upd_generar_archivo_plano = $pdo->prepare(
        "UPDATE tab_enc_archivo_plano
            SET ind_generado   = TRUE,
                fec_generacion = COALESCE(fec_generacion, NOW())
          WHERE id_archivo_plano = :wid_archivo_plano"
    );

    // =========================================================================
    // 9. DASHBOARD — CONSULTAS DE APOYO (KPIs, alertas, gráficos)
    // =========================================================================

    // ---- CUOTAS PENDIENTES AGRUPADAS POR SEMANA (próximos 8 semanas) ----
    // Para el gráfico de barras de flujo de caja proyectado.
    $list_cuotas_por_semana = $pdo->prepare(
        "SELECT  DATE_TRUNC('week', c.fec_vencimiento)::date AS semana,
                 SUM(c.val_cuota) AS total_semana
           FROM  tab_cuotasxfactura c
          WHERE  c.ind_pagada = FALSE
            AND  c.fec_vencimiento BETWEEN CURRENT_DATE
                 AND CURRENT_DATE + INTERVAL '8 weeks'
          GROUP BY DATE_TRUNC('week', c.fec_vencimiento)
          ORDER BY semana"
    );

    // ---- FACTURAS VENCIDAS (alerta) ----
    $list_facturas_vencidas = $pdo->prepare(
        "SELECT  f.id_factura,
                 t.nom_tercero,
                 f.fec_vencimiento,
                 f.val_saldo
           FROM  tab_cuentasxpagar f
           JOIN  tab_terceros      t ON t.id_tercero = f.id_proveedor
          WHERE  f.ind_estado = FALSE
            AND  f.fec_vencimiento < CURRENT_DATE
          ORDER BY f.fec_vencimiento
          LIMIT  8"
    );

    // ---- CAJAS MENORES CON SALDO BAJO (alerta — menos del 20% disponible) ----
    $list_cajas_saldo_bajo = $pdo->prepare(
        "SELECT  id_caja_menor,
                 nom_caja_menor,
                 monto_asignado,
                 monto_disponible
           FROM  tab_enc_caja_menor
          WHERE  ind_estado_caja_m = TRUE
            AND  monto_asignado > 0
            AND  (monto_disponible::numeric / monto_asignado::numeric) < 0.20
          ORDER BY (monto_disponible::numeric / monto_asignado::numeric)
          LIMIT  6"
    );

    // ---- CRONOGRAMAS PENDIENTES PRÓXIMOS (alerta) ----
    $list_cronogramas_proximos = $pdo->prepare(
        "SELECT  id_cronograma,
                 nom_cronograma,
                 fec_programacion,
                 total_a_pagar
           FROM  tab_enc_cronopagos
          WHERE  ind_borrado = FALSE
            AND  ind_estado  = FALSE
          ORDER BY fec_programacion
          LIMIT  6"
    );

} catch (PDOException $e) {
    error_log("Error en prepare_tescxp.php: " . $e->getMessage());
    die("Error crítico al preparar consultas. Revise los logs del servidor.");
}
?>
