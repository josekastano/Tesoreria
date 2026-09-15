<?php
// ========== PERSONALIZAR PÁGINA ==========
$pageTitle        = 'ERP ADSO — Tesorería';
$activeModule     = 'tescxp';
$page_title       = "ADSOERP | Historial de Pagos";
$page_description = "Consulta de todos los pagos registrados sobre cuotas de facturas";
$page_icon        = "bi-receipt";
$page_extra_css   = ["../modules/tescxp/css/historial_pagos.css"];
$page_extra_js    = ["../modules/tescxp/js/historial_pagos.js"];
$show_welcome     = false;
// ==========================================

if (!defined('INCLUDE_MENU_PRINCIPAL')) {
    header("Location: menu_principal.php");
    exit();
}

require_once('prepare_tescxp.php');

// ============================================================
// LIMPIAR MENSAJE DE ERROR DE POSTGRESQL
// ============================================================
function limpiar_error_pgsql(string $msg): string {
    if (preg_match('/ERROR:\s*ERROR:\s*(.+?)(?:\s+CONTEXT:|$)/s', $msg, $m)) {
        return trim($m[1]);
    }
    if (preg_match('/ERROR:\s*(.+?)(?:\s+CONTEXT:|$)/s', $msg, $m)) {
        return trim($m[1]);
    }
    return $msg;
}

// ============================================================
// MANEJO DE PETICIONES POST (SIEMPRE RESPONDEN CON JSON)
// ============================================================
// Este módulo es de consulta: el único POST es el detalle de un pago.
// La exportación a CSV se arma en el navegador con las filas ya filtradas.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $respuesta = ['success' => false, 'message' => '', 'errors' => []];

    try {
        // ---------- VER DETALLE DE UN PAGO ----------
        if (isset($_POST['btn_ver_pago'])) {
            $id_pago = trim($_POST['hid_id_pago'] ?? '');

            if ($id_pago === '' || !ctype_digit($id_pago)) {
                throw new Exception('Pago no válido.');
            }

            $get_pago_cxp->execute([':wid_pago' => (int)$id_pago]);
            $pago = $get_pago_cxp->fetch(PDO::FETCH_ASSOC);

            if (!$pago) {
                throw new Exception('El pago no existe.');
            }

            $respuesta['success'] = true;
            $respuesta['pago']    = $pago;
            echo json_encode($respuesta);
            exit;
        }

        // ---------- APLICAR RESPUESTA DEL BANCO (CSV) ----------
        // >>> CORREGIDO: esta importación es la que CREA el historial de pagos,
        // no la actualización de un pago que ya existía. Cada fila identifica el
        // giro por (id_archivo_plano, id_factura, id_cuota) — el mismo trío que
        // ya se le envió al banco en tab_det_archivo_plano — y de ahí se toma
        // val_a_pagar (nunca del archivo del banco, para que nadie pueda inflar
        // o desinflar un pago con una fila mal armada).
        if (isset($_POST['btn_importar'])) {
            $filas_json = $_POST['hid_filas_importar'] ?? '';
            $filas      = json_decode($filas_json, true);

            if (!is_array($filas) || count($filas) === 0) {
                throw new Exception('No se recibió ninguna fila para aplicar.');
            }

            // Mapa cod_bancario -> id_motivo_rechazo (solo motivos activos)
            $list_motivos_rechazo->execute();
            $mapa_motivos = [];
            foreach ($list_motivos_rechazo->fetchAll(PDO::FETCH_ASSOC) as $m) {
                $cod = trim((string)($m['cod_bancario'] ?? ''));
                if ($cod !== '') {
                    $mapa_motivos[strtoupper($cod)] = (int)$m['id_motivo_rechazo'];
                }
            }

            $aplicadas = 0;
            $omitidas  = [];

            $pdo->beginTransaction();

            foreach ($filas as $f) {
                $id_archivo = trim((string)($f['id_archivo_plano'] ?? ''));
                $id_factura = trim((string)($f['id_factura'] ?? ''));
                $id_cuota   = trim((string)($f['id_cuota'] ?? ''));
                $estado     = strtoupper(trim((string)($f['estado_pago'] ?? '')));
                $ref        = trim((string)($f['referencia_bancaria'] ?? ''));
                $cod        = strtoupper(trim((string)($f['cod_bancario'] ?? '')));
                $fec_pago   = trim((string)($f['fec_pago'] ?? '')) ?: date('Y-m-d');

                $etiqueta = "Archivo {$id_archivo} / Factura {$id_factura} / Cuota {$id_cuota}";

                if ($id_archivo === '' || !ctype_digit($id_archivo) ||
                    $id_factura === '' || !ctype_digit($id_factura) ||
                    $id_cuota   === '' || !ctype_digit($id_cuota)) {
                    $omitidas[] = "{$etiqueta}: identificadores no válidos.";
                    continue;
                }

                // estado_pago CHECK IN ('PENDIENTE','APROBADO','RECHAZADO')
                if (!in_array($estado, ['APROBADO', 'RECHAZADO'], true)) {
                    $omitidas[] = "{$etiqueta}: el estado '{$estado}' no es APROBADO ni RECHAZADO.";
                    continue;
                }

                // referencia_bancaria VARCHAR(30)
                if (strlen($ref) > 30) {
                    $omitidas[] = "{$etiqueta}: la referencia bancaria supera 30 caracteres.";
                    continue;
                }

                // id_motivo_rechazo es obligatorio si el estado es RECHAZADO
                $id_motivo = null;
                if ($estado === 'RECHAZADO') {
                    if ($cod === '') {
                        $omitidas[] = "{$etiqueta}: rechazado sin código bancario.";
                        continue;
                    }
                    if (!isset($mapa_motivos[$cod])) {
                        $omitidas[] = "{$etiqueta}: el código '{$cod}' no está en el catálogo de motivos.";
                        continue;
                    }
                    $id_motivo = $mapa_motivos[$cod];
                }

                // La línea tiene que existir de verdad en el archivo plano enviado al banco
                $get_linea_archivo_plano->execute([
                    ':wid_archivo_plano' => (int)$id_archivo,
                    ':wid_factura'       => (int)$id_factura,
                    ':wid_cuota'         => (int)$id_cuota,
                ]);
                $linea = $get_linea_archivo_plano->fetch(PDO::FETCH_ASSOC);

                if (!$linea) {
                    $omitidas[] = "{$etiqueta}: esa línea no existe en el archivo plano.";
                    continue;
                }

                // No duplicar el historial si el archivo de respuesta se importa dos veces
                $check_pago_de_linea->execute([
                    ':wid_archivo_plano' => (int)$id_archivo,
                    ':wid_factura'       => (int)$id_factura,
                    ':wid_cuota'         => (int)$id_cuota,
                ]);
                if ($check_pago_de_linea->fetch(PDO::FETCH_ASSOC)) {
                    $omitidas[] = "{$etiqueta}: ya tiene un pago registrado (¿archivo reimportado?).";
                    continue;
                }

                // ---- CREAR EL PAGO — el valor sale de tab_det_archivo_plano, no del CSV ----
                $ins_pago_cxp->execute([
                    ':wid_factura'          => (int)$id_factura,
                    ':wid_cuota'            => (int)$id_cuota,
                    ':wid_archivo_plano'    => (int)$id_archivo,
                    ':wfec_pago'            => $fec_pago,
                    ':wval_pago'            => (float)$linea['val_a_pagar'],
                    ':wreferencia_bancaria' => $ref !== '' ? $ref : null,
                ]);
                // >>> SUPUESTO: fun_insert_pagos_cxp devuelve el id_pago recién
                // creado (mismo patrón que el resto de funciones fun_insert_* del
                // proyecto). Si tu función no retorna el id, hay que reemplazar
                // esta línea por una consulta que lo busque por los datos recién
                // insertados.
                $id_pago_nuevo = (int)$ins_pago_cxp->fetchColumn();

                // ---- FIJAR EL ESTADO FINAL QUE TRAJO EL BANCO ----
                $upd_estado_pago_cxp->execute([
                    ':wid_pago'             => $id_pago_nuevo,
                    ':westado_pago'         => $estado,
                    ':wreferencia_bancaria' => $ref !== '' ? $ref : null,
                    ':wid_motivo_rechazo'   => $id_motivo,
                ]);

                // ---- SI QUEDÓ APROBADO: cuota pagada, saldo recalculado, cronograma cerrado ----
                if ($estado === 'APROBADO') {
                    $mark_cuota_pagada->execute([
                        ':wid_factura' => (int)$id_factura,
                        ':wid_cuota'   => (int)$id_cuota,
                    ]);
                    $recalc_saldo_factura->execute([
                        ':wid_factura' => (int)$id_factura,
                    ]);
                    $get_cronograma_de_cuota->execute([
                        ':wid_factura' => (int)$id_factura,
                        ':wid_cuota'   => (int)$id_cuota,
                    ]);
                    $cronograma = $get_cronograma_de_cuota->fetch(PDO::FETCH_ASSOC);
                    if ($cronograma) {
                        $cerrar_cronograma_si_pagado->execute([
                            ':wid_cronograma' => (int)$cronograma['id_cronograma'],
                        ]);
                    }
                }

                $aplicadas++;
            }

            $pdo->commit();

            $respuesta['success']   = $aplicadas > 0;
            $respuesta['aplicadas'] = $aplicadas;
            $respuesta['omitidas']  = $omitidas;
            $respuesta['message']   = $aplicadas > 0
                ? "Se registraron {$aplicadas} pago(s) a partir de la respuesta del banco." . (count($omitidas) ? ' ' . count($omitidas) . ' fila(s) omitida(s).' : '')
                : 'Ninguna fila se pudo aplicar.';
            echo json_encode($respuesta);
            exit;
        }

        $respuesta['message'] = 'Acción no válida.';
        echo json_encode($respuesta);
        exit;

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = limpiar_error_pgsql($e->getMessage());
        if (empty($mensaje)) {
            $mensaje = $e->getMessage();
        }
        $respuesta['message'] = $mensaje;
        echo json_encode($respuesta);
        exit;
    }
}

// ============================================================
// CARGAR TABLA (SOLO PARA LA VISTA INICIAL)
// ============================================================
// $list_pagos_cxp_full debe traer, además de tab_pagos_cxp:
//   nom_tercero  (join con tab_terceros vía tab_facturasxproveedor)
//   nom_archivo  (join con tab_encabezado_archivo_plano, NULL si fue pago manual)
//   des_motivo   (join con tab_motivos_rechazo, NULL si no fue rechazado)
//   cod_bancario (join con tab_motivos_rechazo)
$list_pagos_cxp->execute();
$pagos = $list_pagos_cxp->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// TOTALES POR ESTADO
// ============================================================
$total      = count($pagos);
$aprobados  = 0;
$pendientes = 0;
$rechazados = 0;
$val_aprobado = 0.0;

foreach ($pagos as $p) {
    switch ($p['estado_pago']) {
        case 'APROBADO':
            $aprobados++;
            $val_aprobado += (float)$p['val_pago'];
            break;
        case 'PENDIENTE':
            $pendientes++;
            break;
        case 'RECHAZADO':
            $rechazados++;
            break;
    }
}

// dd/mm/aaaa para mostrar
function fecha_larga(?string $iso): string {
    if (empty($iso)) return '—';
    $d = date_create($iso);
    return $d ? $d->format('d/m/Y') : htmlspecialchars($iso);
}

function estado_badge(string $estado): string {
    $clase = 'badge-' . strtolower($estado);
    $texto = ucfirst(strtolower($estado));
    return '<span class="badge ' . $clase . '">' . $texto . '</span>';
}

// ============================================================
// INICIO DEL HTML
// ============================================================
ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="modules/tescxp/css/pagos_tescxp.css">
<div id="mod-historial" class="app-view active">

    <!-- ENCABEZADO -->
    <div class="module-header">
        <div class="module-header-text">
            <h1>Historial de Pagos</h1>
            <p>Consulta de todos los pagos registrados sobre cuotas de facturas</p>
        </div>
        <button id="btn-import-historial" class="btn btn-primary">
            <i class="fas fa-upload"></i> Importar respuesta del banco
        </button>
        <input type="file" id="csv-input" accept=".csv,text/csv" style="display:none">
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-receipt"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total pagos</div>
                <div class="stat-value" id="stat-total"><?= $total ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-circle-check"></i></div>
            <div class="stat-info">
                <div class="stat-label">Aprobados</div>
                <div class="stat-value" id="stat-aprobados"><?= $aprobados ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-label">Pendientes</div>
                <div class="stat-value" id="stat-pendientes"><?= $pendientes ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-circle-xmark"></i></div>
            <div class="stat-info">
                <div class="stat-label">Rechazados</div>
                <div class="stat-value" id="stat-rechazados"><?= $rechazados ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <div class="stat-label">Valor aprobado</div>
                <div class="stat-value" id="stat-valor" style="font-size:16px">$<?= number_format($val_aprobado, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filter-bar">
        <div class="search-wrapper">
            <span class="search-icon"><i class="fas fa-search"></i></span>
            <input type="text" id="pago-search" class="search-input" placeholder="Buscar por pago, factura o referencia bancaria...">
        </div>
        <div class="filter-toggle-group">
            <button class="filter-toggle active" data-filter="all">Todos</button>
            <button class="filter-toggle" data-filter="APROBADO">Aprobados</button>
            <button class="filter-toggle" data-filter="PENDIENTE">Pendientes</button>
            <button class="filter-toggle" data-filter="RECHAZADO">Rechazados</button>
        </div>
        <div class="filter-dates">
            <label for="fec-desde">Desde</label>
            <input type="date" id="fec-desde" class="filter-date-input">
            <label for="fec-hasta">Hasta</label>
            <input type="date" id="fec-hasta" class="filter-date-input">
        </div>
        <button id="btn-clear-filters" class="btn-clear-filter" style="display:none">
            <i class="fas fa-times"></i> Limpiar
        </button>
        <span class="filter-info" id="pagos-count"><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <!-- TABLA -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Pago</th>
                    <th>Factura / Cuota</th>
                    <th>Origen</th>
                    <th>Fecha de Pago</th>
                    <th class="text-right">Valor</th>
                    <th>Referencia Bancaria</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="pagos-tbody">
            <?php if (empty($pagos)): ?>
                <tr class="empty-row">
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-receipt"></i>
                            <p>No hay pagos registrados</p>
                            <span>Los pagos aparecen aquí cuando se registran desde el archivo plano o como pago manual</span>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($pagos as $p):
                    $rechazado = $p['estado_pago'] === 'RECHAZADO';
                    $ref       = trim((string)($p['referencia_bancaria'] ?? ''));
                    $archivo   = trim((string)($p['nom_archivo'] ?? ''));
                ?>
                <tr data-id-pago="<?= (int)$p['id_pago'] ?>"
                    data-estado="<?= htmlspecialchars($p['estado_pago']) ?>"
                    data-fecha="<?= htmlspecialchars($p['fec_pago']) ?>"
                    class="<?= $rechazado ? 'rechazado' : '' ?>">
                    <td><strong>#<?= (int)$p['id_pago'] ?></strong></td>
                    <td>
                        Fact. #<?= (int)$p['id_factura'] ?> — Cuota <?= (int)$p['id_cuota'] ?><br>
                        <small style="color:#94a3b8;font-size:11px"><?= htmlspecialchars($p['nom_tercero'] ?? '') ?></small>
                    </td>
                    <td>
                        <?php if ($archivo !== ''): ?>
                            <?= htmlspecialchars($archivo) ?>
                        <?php else: ?>
                            <span class="origen-manual">Pago manual</span>
                        <?php endif; ?>
                    </td>
                    <td><?= fecha_larga($p['fec_pago']) ?></td>
                    <td class="text-right">$<?= number_format((float)$p['val_pago'], 0, ',', '.') ?></td>
                    <td class="ref-bancaria<?= $ref === '' ? ' ref-vacia' : '' ?>">
                        <?= $ref !== '' ? htmlspecialchars($ref) : 'Sin referencia' ?>
                    </td>
                    <td class="text-center"><?= estado_badge($p['estado_pago']) ?></td>
                    <td class="text-center">
                        <button class="btn-icon-sm view" title="Ver detalle"
                                onclick='openDetailModal(<?= json_encode($p, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            <i class="fas fa-eye"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: IMPORTAR RESPUESTA DEL BANCO -->
<div id="modal-import" class="modal-overlay hidden">
    <div class="modal-box" style="max-width:700px">
        <div class="modal-header green">
            <div>
                <h2>Importar respuesta del banco</h2>
                <p id="import-subtitle">Revise las filas antes de aplicarlas</p>
            </div>
            <button class="modal-close" id="close-import-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="import-form" novalidate>
                <input type="hidden" name="btn_importar" value="1">
                <input type="hidden" name="hid_filas_importar" id="hid-filas-importar" value="">

                <p class="config-hint">
                    <i class="fas fa-info-circle"></i>
                    <span>El archivo debe traer las columnas <strong>id_archivo_plano</strong>, <strong>id_factura</strong>,
                    <strong>id_cuota</strong>, <strong>estado_pago</strong> (APROBADO o RECHAZADO),
                    <strong>referencia_bancaria</strong> y <strong>cod_bancario</strong> (obligatorio en los rechazos).
                    Cada fila crea el pago en el historial; si esa línea ya tiene un pago registrado, se omite.</span>
                </p>

                <div class="import-resumen" id="import-resumen">
                    <div class="import-resumen-item ok">
                        <span class="import-resumen-label">Por aplicar</span>
                        <span class="import-resumen-valor" id="import-validas">0</span>
                    </div>
                    <div class="import-resumen-item bad">
                        <span class="import-resumen-label">Con errores</span>
                        <span class="import-resumen-valor" id="import-invalidas">0</span>
                    </div>
                    <div class="import-resumen-item">
                        <span class="import-resumen-label">Filas leídas</span>
                        <span class="import-resumen-valor" id="import-total">0</span>
                    </div>
                </div>

                <h4 class="detail-subheading"><i class="fas fa-list-check"></i> Filas del archivo</h4>
                <div id="import-filas-list" class="import-list"></div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" id="cancel-import-modal">Cancelar</button>
            <button type="button" id="import-btn-apply" class="btn btn-primary" disabled>
                <i class="fas fa-check"></i> Aplicar pagos
            </button>
        </div>
    </div>
</div>

<!-- MODAL: DETALLE DEL PAGO -->
<div id="modal-detail" class="modal-overlay hidden">
    <div class="modal-box" style="max-width:560px">
        <div class="modal-header blue">
            <div>
                <h2 id="detail-title">Detalle del Pago</h2>
                <p id="detail-subtitle"></p>
            </div>
            <button class="modal-close" id="close-detail-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="detail-content"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="close-detail-btn">Cerrar</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="hidden"><span id="toast-message"></span></div>

<script>
// Datos completos para el detalle y la exportación a CSV
const pagosData = <?= json_encode($pagos) ?>;
</script>

<script src="modules/tescxp/js/pagos_tescxp.js"></script>


<?php
$moduleContent = ob_get_clean();
echo $moduleContent;
?>
