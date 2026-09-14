<?php
/**
 * CENTRAL DE INSTRUCCIONES PREPARADAS - Módulo Compras y Proveedores
 * Versión: 5.3 — sentencias de pmtros_compras restauradas (sistema mono-empresa)
 */

require_once('config.php');

try {
    $pdo = getDBConnection();

    // =========================================================================
    // SELECTS MAESTROS
    // =========================================================================

    $list_tipos = $pdo->prepare(
        "SELECT id_tipo, nom_tipo
           FROM tab_tipo_identidad
          ORDER BY nom_tipo"
    );

    $list_categorias = $pdo->prepare(
        "SELECT id_cat_tercero, nom_cat_tercero
           FROM tab_cat_terceros
          ORDER BY nom_cat_tercero"
    );

    $list_ciudades = $pdo->prepare(
        "SELECT id_ciudad, nom_ciudad
           FROM tab_ciudades
          WHERE ind_borrado = FALSE
          ORDER BY nom_ciudad"
    );

    $list_restricciones = $pdo->prepare(
        "SELECT id_restriccion, nom_restriccion
           FROM tab_restricciones
          ORDER BY nom_restriccion"
    );

    $list_prefijos = $pdo->prepare(
        "SELECT id_prefijo AS id_prefijo_movil, nom_pais
           FROM tab_tel_prefijo
          ORDER BY nom_pais"
    );

    $list_areas = $pdo->prepare(
        "SELECT id_area, nom_area
           FROM tab_areas
          WHERE ind_estado  = TRUE
            AND ind_borrado = FALSE
          ORDER BY nom_area"
    );

    // =========================================================================
    // LISTADO PRINCIPAL DE PROVEEDORES — CON JOINs PARA DETALLES
    // =========================================================================

    $list_proveedores = $pdo->prepare(
        "SELECT  t.id_tercero,
                 t.nom_tercero,
                 t.ind_estado,
                 t.id_tipo,
                 t.id_cat_tercero,
                 t.id_ciudad,
                 t.id_restriccion,
                 t.ind_tipo_tercero,
                 (t.dir_tercero).email              AS email,
                 (t.dir_tercero).direccion          AS direccion,
                 (t.dir_tercero).tel_fijo           AS tel_fijo,
                 (t.dir_tercero).id_prefijo_movil   AS id_prefijo_movil,
                 (t.dir_tercero).tel_movil          AS tel_movil,
                 cat.nom_cat_tercero,
                 ciu.nom_ciudad,
                 res.nom_restriccion,
                 p.val_sigla,
                 p.nom_contacto,
                 p.tel_contacto,
                 p.nom_cont_contab,
                 p.tel_cont_contab,
                 p.ind_dias_pago,
                 p.val_saldo_deuda,
                 p.val_tiempo_entrega,
                 p.val_latitud,
                 p.val_longitud,
                 e.id_eva_prov,
                 e.val_puntaje_total,
                 e.val_punt_calidad,
                 e.val_punt_puntual,
                 e.val_coments,
                 CASE WHEN e.id_eva_prov IS NOT NULL
                      THEN TRUE ELSE FALSE END AS has_eval
           FROM  tab_proveedores p
           JOIN  tab_terceros    t ON t.id_tercero   = p.id_proveedor
      LEFT JOIN  tab_cat_terceros  cat ON cat.id_cat_tercero = t.id_cat_tercero
      LEFT JOIN  tab_ciudades      ciu ON ciu.id_ciudad      = t.id_ciudad
      LEFT JOIN  tab_restricciones res ON res.id_restriccion = t.id_restriccion
      LEFT JOIN  tab_eval_prov   e ON e.id_proveedor = p.id_proveedor
                                  AND e.ind_borrado  = FALSE
          WHERE  p.ind_borrado = FALSE
            AND  t.ind_borrado = FALSE
          ORDER BY t.nom_tercero"
    );

    // =========================================================================
    // LISTADO DE PRODUCTOS
    // =========================================================================

    $list_productos = $pdo->prepare(
        "SELECT  p.id_producto,
                 p.ind_tip_producto,
                 p.ind_tipo_bien,
                 p.id_area,
                 a.nom_area,
                 p.nom_producto,
                 p.val_poriva,
                 p.val_exist,
                 p.val_venta,
                 p.ind_disponible,
                 p.ind_estado
           FROM  tab_productos p
           JOIN  tab_areas     a ON a.id_area = p.id_area
          WHERE  p.ind_borrado = FALSE
          ORDER BY p.nom_producto"
    );

    // Verifica si ya existe un producto activo con ese nombre (usado al crear,
    // para dar un error claro de duplicado antes de llamar fun_insert_productos)
    $check_producto_dup = $pdo->prepare(
        "SELECT 1 FROM tab_productos
          WHERE LOWER(nom_producto) = LOWER(:nom)
            AND ind_borrado = FALSE
          LIMIT 1"
    );

    // Trae el val_exist actual de un producto (usado al editar: el stock no
    // se toca desde el formulario de edición, se conserva el valor vigente)
    $get_producto_stock_actual = $pdo->prepare(
        "SELECT val_exist FROM tab_productos WHERE id_producto = :id AND ind_borrado = FALSE"
    );

    // =========================================================================
    // LISTADO DE PRODUCTOS POR PROVEEDOR
    // =========================================================================

    $list_prodxprov = $pdo->prepare(
        "SELECT  pp.id_proveedor,
                 pp.id_producto,
                 p.nom_producto,
                 p.ind_tipo_bien,
                 p.val_poriva,
                 pp.val_costo,
                 pp.val_pordesc,
                 pp.ind_disponible
           FROM  tab_prodxprov  pp
           JOIN  tab_productos   p ON p.id_producto = pp.id_producto
          WHERE  pp.ind_borrado = FALSE
            AND   p.ind_borrado = FALSE
          ORDER BY p.nom_producto"
    );

    $list_prodxprov_by_prov = $pdo->prepare(
        "SELECT  pp.id_proveedor,
                 pp.id_producto,
                 p.nom_producto,
                 p.ind_tipo_bien,
                 p.val_poriva,
                 pp.val_costo,
                 pp.val_pordesc,
                 pp.ind_disponible
           FROM  tab_prodxprov  pp
           JOIN  tab_productos   p ON p.id_producto = pp.id_producto
          WHERE  pp.ind_borrado  = FALSE
            AND   p.ind_borrado  = FALSE
            AND  pp.id_proveedor = :id_proveedor
          ORDER BY p.nom_producto"
    );

    // =========================================================================
    // PARÁMETROS DE COMPRAS (sistema mono-empresa: siempre hay a lo sumo
    // UNA fila activa en tab_pmtros_grales, por eso el LIMIT 1 es correcto)
    // =========================================================================

    // Trae la (única) empresa activa junto con su config. de compras, si existe
    $list_pmtros_compras = $pdo->prepare(
        "SELECT  g.id_empresa,
                 g.nom_empresa,
                 c.val_puntaje_sel_prov
           FROM  tab_pmtros_grales  g
      LEFT JOIN  tab_pmtros_compras c ON c.id_empresa = g.id_empresa
          WHERE  g.ind_borrado = FALSE
          ORDER BY g.id_empresa
          LIMIT 1"
    );

    // Verifica si la empresa ya tiene fila en tab_pmtros_compras
    // (decide INSERT vs UPDATE en parametros_compras.php)
    $check_pmtros_compras = $pdo->prepare(
        "SELECT 1 FROM tab_pmtros_compras WHERE id_empresa = :id_empresa"
    );

    $ins_pmtros_compras = $pdo->prepare(
        "SELECT fun_insert_pmtros_compras(
            :id_empresa,
            :val_puntaje_sel_prov
        )"
    );

    $upd_pmtros_compras = $pdo->prepare(
        "SELECT fun_update_pmtros_compras(
            :id_empresa,
            :val_puntaje_sel_prov
        )"
    );

    // =========================================================================
    // INSERT — TERCEROS / PROVEEDORES / EVALUACIÓN / PRODUCTOS / PRODXPROV
    // =========================================================================

    $ins_tercero = $pdo->prepare(
        "SELECT fun_insert_terceros(
            :id_tipo, :id_tercero, :ind_tipo_tercero, :id_cat_tercero,
            :nom_tercero, :nom_corto, :direccion, :tel_fijo,
            :id_prefijo_movil, :tel_movil, :email, :id_ciudad,
            :id_restriccion, :ind_estado
        )"
    );

    $ins_proveedor = $pdo->prepare(
        "SELECT fun_insert_proveedores(
            :id_proveedor, :val_sigla, :nom_contacto, :tel_contacto,
            :nom_cont_contab, :tel_cont_contab, :ind_dias_pago,
            :val_saldo_deuda, :val_tiempo_entrega, :val_latitud, :val_longitud
        )"
    );

    $ins_eval_prov = $pdo->prepare(
        "SELECT fun_insert_eval_prov(
            :id_proveedor, :val_punt_calidad, :val_punt_puntual, :val_coments
        )"
    );

    $ins_producto = $pdo->prepare(
        "SELECT fun_insert_productos(
            :ind_tip_producto, :ind_tipo_bien, :id_area, :nom_producto,
            :val_poriva, :val_exist, :val_venta, :ind_disponible, :ind_estado
        )"
    );

    $ins_prodxprov = $pdo->prepare(
        "SELECT fun_insert_prodxprov(
            :id_proveedor, :id_producto, :val_costo, :val_pordesc, :ind_disponible
        )"
    );

    // =========================================================================
    // UPDATE
    // =========================================================================

    $upd_tercero = $pdo->prepare(
        "SELECT fun_update_terceros(
            :id_tercero, :ind_tipo_tercero, :id_cat_tercero, :nom_tercero,
            :nom_corto, :direccion, :tel_fijo, :id_prefijo_movil, :tel_movil,
            :email, :id_ciudad, :id_restriccion, :ind_estado
        )"
    );

    $upd_proveedor = $pdo->prepare(
        "SELECT fun_update_proveedores(
            :id_proveedor, :val_sigla, :val_latitud, :val_longitud,
            :nom_contacto, :tel_contacto, :nom_cont_contab, :tel_cont_contab,
            :ind_dias_pago, :val_tiempo_entrega
        )"
    );

    $upd_eval_prov = $pdo->prepare(
        "SELECT fun_update_eval_prov(
            :id_eva_prov, :val_punt_calidad, :val_punt_puntual, :val_coments
        )"
    );

    $upd_producto = $pdo->prepare(
        "SELECT fun_update_productos(
            :id_producto, :ind_tip_producto, :ind_tipo_bien, :id_area,
            :nom_producto, :val_poriva, :val_exist, :val_venta,
            :ind_disponible, :ind_estado
        )"
    );

    $upd_prodxprov = $pdo->prepare(
        "SELECT fun_update_prodxprov(
            :id_proveedor, :id_producto, :val_costo, :val_pordesc, :ind_disponible
        )"
    );

    // =========================================================================
    // DELETE
    // =========================================================================

    $del_proveedor = $pdo->prepare(
        "SELECT fun_delete_proveedores(:id_proveedor)"
    );

    $del_tercero = $pdo->prepare(
        "SELECT fun_delete_terceros(:id_tercero)"
    );

    $del_eval_prov = $pdo->prepare(
        "SELECT fun_delete_eval_prov(:id_eva_prov)"
    );

    $del_producto = $pdo->prepare(
        "SELECT fun_delete_productos(:id_producto)"
    );

    $del_prodxprov = $pdo->prepare(
        "SELECT fun_delete_prodxprov(:id_proveedor, :id_producto)"
    );

    // =========================================================================
    // DASHBOARD — STAT 1: Total proveedores activos
    // =========================================================================

    $dash_total_proveedores = $pdo->prepare(
        "SELECT COUNT(p.id_proveedor) AS total
           FROM tab_proveedores p
           JOIN tab_terceros    t ON t.id_tercero = p.id_proveedor
          WHERE p.ind_borrado = FALSE
            AND t.ind_borrado = FALSE
            AND t.ind_estado  = TRUE"
    );

    // =========================================================================
    // DASHBOARD — STAT 2: Órdenes de compra pendientes (ind_estado = 2)
    // =========================================================================

    $dash_ordenes_pendientes = $pdo->prepare(
        "SELECT COUNT(id_ordencompra) AS total
           FROM tab_enc_ordcomp
          WHERE ind_estado = 2"
    );

    // =========================================================================
    // DASHBOARD — STAT 3: Bienes con stock disponible (val_exist > 0)
    // =========================================================================

    $dash_productos_stock = $pdo->prepare(
        "SELECT SUM(val_exist) AS total
           FROM tab_productos
          WHERE ind_borrado   = FALSE
            AND ind_tipo_bien = TRUE
            AND ind_estado    = TRUE
            AND val_exist     > 0"
    );

    // =========================================================================
    // DASHBOARD — STAT 4: Convenios activos
    // =========================================================================

    $dash_convenios_activos = $pdo->prepare(
        "SELECT COUNT(pp.id_producto) AS total
           FROM tab_prodxprov   pp
           JOIN tab_proveedores  p  ON p.id_proveedor  = pp.id_proveedor
           JOIN tab_productos    pr ON pr.id_producto  = pp.id_producto
          WHERE pp.ind_borrado    = FALSE
            AND pp.ind_disponible = TRUE
            AND  p.ind_borrado    = FALSE
            AND pr.ind_borrado    = FALSE
            AND pr.ind_estado     = TRUE"
    );

    // =========================================================================
    // DASHBOARD — EJECUCIÓN PRESUPUESTAL mes a mes
    // =========================================================================

    $dash_ejecucion_presupuestal = $pdo->prepare(
        "SELECT pp.mes_periodo                              AS mes,
                COALESCE(SUM(dp.monto_aprobado),   0)      AS proyectado,
                COALESCE(SUM(dp.monto_ejecutado),  0)      AS ejecutado
           FROM tab_det_presupuesto    dp
           JOIN tab_enc_presupuesto    ep ON ep.id_presupuesto = dp.id_presupuesto
           JOIN tab_period_presupuesto pp ON pp.id_periodo     = ep.id_periodo
          WHERE pp.anio_periodo = :anio
            AND ep.ind_borrado  = FALSE
            AND dp.ind_borrado  = FALSE
          GROUP BY pp.mes_periodo
          ORDER BY pp.mes_periodo ASC"
    );

    // =========================================================================
    // DASHBOARD — COMPOSICIÓN DEL GASTO bienes vs servicios
    // =========================================================================

    $dash_composicion_gasto = $pdo->prepare(
        "SELECT pr.ind_tipo_bien,
                COALESCE(SUM(d.val_neto), 0) AS total
           FROM tab_det_ordcomp d
           JOIN tab_enc_ordcomp o  ON o.id_ordencompra = d.id_ordencompra
           JOIN tab_productos   pr ON pr.id_producto   = d.id_producto
          WHERE o.ind_estado   = 1
            AND pr.ind_borrado = FALSE
          GROUP BY pr.ind_tipo_bien"
    );

    // =========================================================================
    // DASHBOARD — ACTIVIDAD RECIENTE (últimas 5 órdenes)
    // =========================================================================

    $dash_actividad_reciente = $pdo->prepare(
        "SELECT o.id_ordencompra,
                t.nom_tercero  AS proveedor,
                p.val_sigla    AS sigla,
                o.fec_emision,
                o.ind_estado,
                o.val_total,
                o.met_pago
           FROM tab_enc_ordcomp   o
           JOIN tab_terceros      t ON t.id_tercero   = o.id_proveedor
      LEFT JOIN tab_proveedores   p ON p.id_proveedor = o.id_proveedor
                                    AND p.ind_borrado  = FALSE
          WHERE o.ind_estado IN (1, 2, 3)
          ORDER BY o.fec_emision DESC
          LIMIT 5"
    );

    // =========================================================================
    // DASHBOARD — MEJORES PROVEEDORES top 5
    // =========================================================================

    $dash_mejores_proveedores = $pdo->prepare(
        "SELECT e.id_proveedor,
                t.nom_tercero        AS nom_proveedor,
                e.val_puntaje_total
           FROM tab_eval_prov   e,
                tab_proveedores  p,
                tab_terceros     t
          WHERE e.id_proveedor  = p.id_proveedor
            AND p.id_proveedor  = t.id_tercero
            AND e.ind_borrado   = FALSE
            AND p.ind_borrado   = FALSE
            AND t.ind_borrado   = FALSE
            AND t.ind_estado    = TRUE
            AND e.val_puntaje_total > 0
          ORDER BY e.val_puntaje_total DESC
          LIMIT 5"
    );

    // =========================================================================
    // LISTADO DE SOLICITUDES DE COMPRA (encabezado + detalle agregado)
    // =========================================================================

    $list_solcomp = $pdo->prepare(
        "SELECT  e.id_solcompra,
                 e.id_area,
                 a.nom_area,
                 e.fec_solicitud,
                 e.fec_requerida,
                 e.val_justificacion,
                 e.ind_estado,
                 COALESCE(
                     json_agg(
                         json_build_object(
                             'id_producto',  d.id_producto,
                             'nom_producto', p.nom_producto,
                             'val_cantidad', d.val_cantidad,
                             'val_venta',    p.val_venta
                         ) ORDER BY p.nom_producto
                     ) FILTER (WHERE d.id_producto IS NOT NULL),
                     '[]'
                 ) AS productos
           FROM  tab_enc_solcomp e
           JOIN  tab_areas       a ON a.id_area = e.id_area
      LEFT JOIN  tab_det_solcomp d ON d.id_solcompra = e.id_solcompra
      LEFT JOIN  tab_productos   p ON p.id_producto  = d.id_producto
          GROUP BY e.id_solcompra, e.id_area, a.nom_area, e.fec_solicitud,
                   e.fec_requerida, e.val_justificacion, e.ind_estado
          ORDER BY e.id_solcompra DESC"
    );

    // =========================================================================
    // LISTADO DE SOLICITUDES DE COMPRA POR ÁREA (para "Mis Solicitudes")
    // Mismo shape que $list_solcomp, filtrado por id_area. Filtro manual
    // temporal hasta que exista login-por-área.
    // =========================================================================

    $list_solcomp_by_area = $pdo->prepare(
        "SELECT  e.id_solcompra,
                 e.id_area,
                 a.nom_area,
                 e.fec_solicitud,
                 e.fec_requerida,
                 e.val_justificacion,
                 e.ind_estado,
                 COALESCE(
                     json_agg(
                         json_build_object(
                             'id_producto',  d.id_producto,
                             'nom_producto', p.nom_producto,
                             'val_cantidad', d.val_cantidad,
                             'val_venta',    p.val_venta
                         ) ORDER BY p.nom_producto
                     ) FILTER (WHERE d.id_producto IS NOT NULL),
                     '[]'
                 ) AS productos
           FROM  tab_enc_solcomp e
           JOIN  tab_areas       a ON a.id_area = e.id_area
      LEFT JOIN  tab_det_solcomp d ON d.id_solcompra = e.id_solcompra
      LEFT JOIN  tab_productos   p ON p.id_producto  = d.id_producto
          WHERE  e.id_area = :id_area
          GROUP BY e.id_solcompra, e.id_area, a.nom_area, e.fec_solicitud,
                   e.fec_requerida, e.val_justificacion, e.ind_estado
          ORDER BY e.id_solcompra DESC"
    );

    // =========================================================================
    // INSERT — fun_insert_solcomp (5 params)
    // id_area, fec_requerida, val_justificacion, prod[]::INTEGER[], cant[]::INTEGER[]
    // Los arreglos llegan desde PHP como literales '{1,2,3}' y se castean aquí.
    // =========================================================================

    $ins_solcomp = $pdo->prepare(
        "SELECT fun_insert_solcomp(
            :id_area,
            :fec_requerida,
            :val_justificacion,
            :prod::INTEGER[],
            :cant::INTEGER[]
        )"
    );

    // =========================================================================
    // INSERT — fun_insert_det_solcomp (3 params)
    // Agrega un producto a una solicitud ya existente (pendiente)
    // =========================================================================

    $ins_det_solcomp = $pdo->prepare(
        "SELECT fun_insert_det_solcomp(
            :id_solcompra,
            :id_producto,
            :val_cantidad
        )"
    );
    // =========================================================================
    // UPDATE — fun_update_solcomp (3 params, fecha y/o justificación opcionales)
    // =========================================================================

    $upd_solcomp = $pdo->prepare(
        "SELECT fun_update_solcomp(
            :id_solcompra,
            :fec_requerida,
            :val_justificacion
        )"
    );
    // =========================================================================
    // UPDATE — fun_update_det_solcomp (3 params)
    // =========================================================================

    $upd_det_solcomp = $pdo->prepare(
        "SELECT fun_update_det_solcomp(
            :id_solcompra,
            :id_producto,
            :val_cantidad
        )"
    );

    // =========================================================================
    // DELETE — fun_delete_det_solcomp (2 params)
    // =========================================================================

    $del_det_solcomp = $pdo->prepare(
        "SELECT fun_delete_det_solcomp(
            :id_solcompra,
            :id_producto
        )"
    );

    // =========================================================================
    // fun_aprobar_solcomp (1 param)
    // =========================================================================

    $aprobar_solcomp = $pdo->prepare(
        "SELECT fun_aprobar_solcomp(:id_solcompra)"
    );

    // =========================================================================
    // fun_anular_solcomp (2 params)
    // id_solcompra, val_justificacion (motivo de anulación, reemplaza la original)
    // =========================================================================

    $anular_solcomp = $pdo->prepare(
        "SELECT fun_anular_solcomp(
            :id_solcompra,
            :val_justificacion
        )"
    );

    // =========================================================================
    // PRODUCTOS PARA SOLICITAR — incluye tiempo de entrega (MAX entre
    // proveedores disponibles), igual que valida fun_insert_det_solcomp.
    // val_tiempo_entrega = NULL si el producto no tiene ningún proveedor.
    // =========================================================================

    $list_productos_solicitar = $pdo->prepare(
        "SELECT  p.id_producto,
                 p.id_area,
                 p.nom_producto,
                 p.val_venta,
                 MAX(prv.val_tiempo_entrega) AS val_tiempo_entrega
           FROM  tab_productos    p
      LEFT JOIN  tab_prodxprov    pp  ON pp.id_producto   = p.id_producto
                                      AND pp.ind_borrado   = FALSE
      LEFT JOIN  tab_proveedores  prv ON prv.id_proveedor  = pp.id_proveedor
                                      AND prv.ind_borrado   = FALSE
          WHERE  p.ind_borrado = FALSE
            AND  p.ind_estado  = TRUE
          GROUP BY p.id_producto, p.id_area, p.nom_producto, p.val_venta
          ORDER BY p.nom_producto"
    );

    // =========================================================================
    // LISTADO DE PROVEEDORES ACTIVOS (versión simple para el wizard de órdenes)
    // =========================================================================

    $list_proveedores_activos = $pdo->prepare(
        "SELECT  p.id_proveedor,
                 t.nom_tercero AS nombre,
                 p.val_sigla,
                 p.val_tiempo_entrega
           FROM  tab_proveedores p
           JOIN  tab_terceros    t ON t.id_tercero = p.id_proveedor
          WHERE  p.ind_borrado = FALSE
            AND  t.ind_borrado = FALSE
          ORDER BY t.nom_tercero"
    );

    // =========================================================================
    // LISTADO DE ÓRDENES DE COMPRA (encabezado + detalle agregado)
    // =========================================================================

    $list_ordencompra = $pdo->prepare(
        "SELECT  e.id_ordencompra,
                 e.id_proveedor,
                 t.nom_tercero AS proveedor_nombre,
                 e.id_solcompra,
                 e.fec_emision,
                 e.id_ciudad,
                 c.nom_ciudad AS ciudad_nombre,
                 e.ind_estado,
                 e.val_total,
                 e.met_pago,
                 COALESCE(
                     json_agg(
                         json_build_object(
                             'id_producto',   d.id_producto,
                             'nom_producto',  p.nom_producto,
                             'val_cantidad',  d.val_cantidad,
                             'val_descuento', d.val_descuento,
                             'val_iva',       d.val_iva,
                             'val_neto',      d.val_neto
                         ) ORDER BY p.nom_producto
                     ) FILTER (WHERE d.id_producto IS NOT NULL),
                     '[]'
                 ) AS productos
           FROM  tab_enc_ordcomp e
           JOIN  tab_terceros     t ON t.id_tercero  = e.id_proveedor
           JOIN  tab_ciudades     c ON c.id_ciudad   = e.id_ciudad
      LEFT JOIN  tab_det_ordcomp  d ON d.id_ordencompra = e.id_ordencompra
      LEFT JOIN  tab_productos    p ON p.id_producto    = d.id_producto
          GROUP BY e.id_ordencompra, e.id_proveedor, t.nom_tercero, e.id_solcompra,
                   e.fec_emision, e.id_ciudad, c.nom_ciudad, e.ind_estado, e.val_total, e.met_pago
          ORDER BY e.id_ordencompra DESC"
    );

    // =========================================================================
    // ÓRDENES DE COMPRA — última orden creada para un proveedor. Se usa para
    // reportar al usuario el número de orden que quedó asignado justo después
    // de un insert exitoso, ya que fun_insert_ordencompra no retorna el ID
    // directamente (solo BOOLEAN, como el resto de funciones fun_insert_*).
    // =========================================================================

    $get_ultima_orden_proveedor = $pdo->prepare(
        "SELECT id_ordencompra, val_total
           FROM tab_enc_ordcomp
          WHERE id_proveedor = :id_proveedor
          ORDER BY id_ordencompra DESC
          LIMIT 1"
    );

    // =========================================================================
    // ÓRDENES DE COMPRA PENDIENTES POR PROVEEDOR (para detectar duplicados en JS)
    // =========================================================================

    $ordencompra_pendiente_prov = $pdo->prepare(
        "SELECT id_ordencompra
           FROM tab_enc_ordcomp
          WHERE id_proveedor = :id_proveedor
            AND ind_estado   = 2"
    );

    // =========================================================================
    // INSERT — fun_insert_ordencompra (5 params)
    // :detalle llega desde PHP como json_encode([...]) y se castea aquí a JSONB
    // =========================================================================

    $ins_ordencompra = $pdo->prepare(
        "SELECT fun_insert_ordencompra(
            :id_proveedor::VARCHAR,
            :id_solcompra::NUMERIC,
            :id_ciudad::VARCHAR,
            :met_pago::BOOLEAN,
            :detalle::JSONB
        )"
    );

    // =========================================================================
    // UPDATE — fun_update_ordencompra (4 params)
    // Edita ciudad / forma de pago / estado de una orden Pendiente
    // =========================================================================

    $upd_ordencompra = $pdo->prepare(
        "SELECT fun_update_ordencompra(
            :id_ordencompra::NUMERIC,
            :id_ciudad::VARCHAR,
            :met_pago::BOOLEAN,
            :ind_estado::NUMERIC
        )"
    );

    // =========================================================================
    // UPDATE — fun_update_ordencompra_detalle (2 params)
    // Agrega productos al detalle de una orden Pendiente ya existente
    // =========================================================================

    $upd_ordencompra_detalle = $pdo->prepare(
        "SELECT fun_update_ordencompra_detalle(
            :id_ordencompra::NUMERIC,
            :detalle::JSONB
        )"
    );

    // =========================================================================
    // DELETE — fun_delete_ordencompra (1 param, anulación lógica)
    // =========================================================================

    $del_ordencompra = $pdo->prepare(
        "SELECT fun_delete_ordencompra(:id_ordencompra::NUMERIC)"
    );

    // =========================================================================
    // UPDATE — fun_sync_ordencompra_detalle (2 params)
    // Sincroniza el detalle completo de una orden Pendiente: agrega, actualiza
    // cantidades, o elimina productos según el detalle final que se envíe.
    // =========================================================================

    $sync_ordencompra_detalle = $pdo->prepare(
        "SELECT fun_sync_ordencompra_detalle(
            :id_ordencompra::NUMERIC,
            :detalle::JSONB
        )"
    );

} catch (PDOException $e) {
    error_log("Error en prepare_compro.php: " . $e->getMessage());
    die("Error crítico al preparar consultas. Revise los logs del servidor.");
}
?>