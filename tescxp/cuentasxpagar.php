<?php
// ========== PERSONALIZAR PÁGINA ==========
$pageTitle        = 'ERP ADSO — Tesorería';
$activeModule     = 'tescxp';
$page_title       = "ADSOERP | Cuentas por Pagar";
$page_description = "Registro y seguimiento de facturas de proveedores";
$page_icon        = "bi-receipt";
$page_extra_css   = ["../modules/tescxp/css/cuentasxpagar.css"];
$page_extra_js    = ["../modules/tescxp/js/cuentasxpagar.js"];
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
// CARGAR SELECTS (siempre se cargan para la vista inicial)
// ============================================================
$list_proveedores_dias_pago->execute();
$proveedores_dp = $list_proveedores_dias_pago->fetchAll(PDO::FETCH_ASSOC);

// Indexado por id_proveedor para resolver los días de pago en PHP al validar
$proveedores_dp_idx = [];
foreach ($proveedores_dp as $p) {
    $proveedores_dp_idx[$p['id_proveedor']] = (int)$p['ind_dias_pago'];
}

// ============================================================
// MANEJO DE PETICIONES POST (SIEMPRE RESPONDEN CON JSON)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $respuesta = ['success' => false, 'message' => '', 'errors' => []];

    try {
        // ---------- OBTENER CUOTAS DE UNA FACTURA (para el modal de detalle) ----------
        if (isset($_POST['btn_ver_cuotas'])) {
            $id_factura = (int)($_POST['hid_id_factura'] ?? 0);
            if ($id_factura <= 0) {
                throw new Exception('Factura no válida.');
            }

            $list_cuotasxfactura->execute([':id_factura' => $id_factura]);
            $cuotas = $list_cuotasxfactura->fetchAll(PDO::FETCH_ASSOC);

            $respuesta['success'] = true;
            $respuesta['cuotas']  = $cuotas;
            echo json_encode($respuesta);
            exit;
        }

        // ---------- NUEVA FACTURA ----------
        if (isset($_POST['btn_nuevo'])) {
            $id_factura   = (int)($_POST['txt_id_factura']    ?? 0);
            $id_proveedor = trim($_POST['sel_id_proveedor']   ?? '');
            $fec_emision  = trim($_POST['txt_fec_emision']    ?? '');
            $val_factura  = (float)($_POST['txt_val_factura'] ?? 0);
            $num_cuotas   = (int)($_POST['txt_num_cuotas']    ?? 0);

            $errores = [];

            if ($id_factura < 1 || $id_factura > 99999999) {
                $errores['err-new-id-factura'] = 'El número de factura debe estar entre 1 y 99.999.999.';
            }
            if (empty($id_proveedor) || !isset($proveedores_dp_idx[$id_proveedor])) {
                $errores['err-new-proveedor'] = 'Seleccione un proveedor válido.';
            }
            if (empty($fec_emision)) {
                $errores['err-new-emision'] = 'La fecha de emisión es obligatoria.';
            }
            if ($val_factura < 0 || $val_factura > 9999999999) {
                $errores['err-new-valor'] = 'El valor de la factura debe estar entre 0 y 9.999.999.999.';
            }
            if ($num_cuotas < 1 || $num_cuotas > 99) {
                $errores['err-new-cuotas'] = 'El número de cuotas debe estar entre 1 y 99.';
            }

            if (!empty($errores)) {
                $respuesta['errors'] = $errores;
                echo json_encode($respuesta);
                exit;
            }

            // Fecha de vencimiento = fecha de emisión + días de pago del proveedor
            $dias_pago       = $proveedores_dp_idx[$id_proveedor];
            $fec_vencimiento = date('Y-m-d', strtotime($fec_emision . " +{$dias_pago} days"));

            $ins_cuentasxpagar->execute([
                ':wid_factura'      => $id_factura,
                ':wid_proveedor'    => $id_proveedor,
                ':wfec_emision'     => $fec_emision,
                ':wfec_vencimiento' => $fec_vencimiento,
                ':wval_factura'     => $val_factura,
                ':wnum_cuotas'      => $num_cuotas,
            ]);

            $respuesta['success'] = true;
            $respuesta['message'] = 'Factura registrada correctamente. Las cuotas se generaron automáticamente.';
            echo json_encode($respuesta);
            exit;
        }

        // Si no se reconoce ninguna acción
        $respuesta['message'] = 'Acción no válida.';
        echo json_encode($respuesta);
        exit;

    } catch (Exception $e) {
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
$list_cuentasxpagar->execute();
$facturas = $list_cuentasxpagar->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// INICIO DEL HTML
// ============================================================
ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="modules/tescxp/css/cuentasxpagar.css">
<div id="mod-cuentasxpagar" class="app-view active">

    <!-- ENCABEZADO -->
    <div class="module-header">
        <div class="module-header-text">
            <h1>Cuentas por Pagar</h1>
            <p>Registro de facturas de proveedores y seguimiento de saldos</p>
        </div>
        <button id="btn-add-factura" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nueva Factura
        </button>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <?php
        $total       = count($facturas);
        $pendientes  = count(array_filter($facturas, fn($f) => !($f['ind_estado'] === 't' || $f['ind_estado'] === true)));
        $pagadas     = $total - $pendientes;
        $hoy         = date('Y-m-d');
        $vencidas    = count(array_filter($facturas, fn($f) =>
            !($f['ind_estado'] === 't' || $f['ind_estado'] === true) && $f['fec_vencimiento'] < $hoy
        ));
        $saldo_total = array_sum(array_column($facturas, 'val_saldo'));
        ?>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total facturas</div>
                <div class="stat-value" id="stat-total"><?= $total ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-label">En deuda</div>
                <div class="stat-value" id="stat-pendientes"><?= $pendientes ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon red"><i class="fas fa-exclamation-triangle"></i></div>
            <div class="stat-info">
                <div class="stat-label">Vencidas</div>
                <div class="stat-value" id="stat-vencidas"><?= $vencidas ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <div class="stat-label">Saldo total por pagar</div>
                <div class="stat-value" id="stat-saldo" style="font-size:16px">$<?= number_format($saldo_total, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filter-bar">
        <div class="search-wrapper">
            <span class="search-icon"><i class="fas fa-search"></i></span>
            <input type="text" id="factura-search" class="search-input" placeholder="Buscar por proveedor o número de factura...">
        </div>
        <div class="filter-toggle-group">
            <button class="filter-toggle active" data-filter="all">Todas</button>
            <button class="filter-toggle" data-filter="pendiente">En deuda</button>
            <button class="filter-toggle" data-filter="vencida">Vencidas</button>
            <button class="filter-toggle" data-filter="pagada">Pagadas</button>
        </div>
        <button id="btn-clear-filters" class="btn-clear-filter" style="display:none">
            <i class="fas fa-times"></i> Limpiar
        </button>
        <span class="filter-info" id="facturas-count"><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <!-- TABLA -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Factura</th>
                    <th>Proveedor</th>
                    <th>Emisión</th>
                    <th>Vencimiento</th>
                    <th class="text-right">Valor</th>
                    <th class="text-right">Saldo</th>
                    <th class="text-center">Cuotas</th>
                    <th class="text-center">Estado</th>
                </tr>
            </thead>
            <tbody id="facturas-tbody">
            <?php if (empty($facturas)): ?>
                <tr class="empty-row">
                    <td colspan="8">
                        <div class="empty-state">
                            <i class="fas fa-file-invoice"></i>
                            <p>No hay facturas registradas</p>
                            <span>Haga clic en "Nueva Factura" para agregar la primera</span>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($facturas as $f):
                    $pagada      = ($f['ind_estado'] === 't' || $f['ind_estado'] === true);
                    $esta_vencida = !$pagada && $f['fec_vencimiento'] < $hoy;

                    if ($pagada) {
                        $badge = '<span class="badge badge-active">Pagada</span>';
                        $filtro_estado = 'pagada';
                    } elseif ($esta_vencida) {
                        $badge = '<span class="badge badge-inactive">Vencida</span>';
                        $filtro_estado = 'vencida';
                    } else {
                        $badge = '<span class="badge badge-inactive" style="background:#fffbeb;color:#f59e0b">En deuda</span>';
                        $filtro_estado = 'pendiente';
                    }

                    $id_fact_esc = htmlspecialchars($f['id_factura']);
                ?>
                <tr data-estado="<?= $filtro_estado ?>" onclick="openDetailModal(<?= $id_fact_esc ?>)">
                    <td><strong>#<?= $id_fact_esc ?></strong></td>
                    <td>
                        <?= htmlspecialchars($f['nom_tercero']) ?><br>
                        <small style="color:#94a3b8;font-size:11px"><?= htmlspecialchars($f['id_proveedor']) ?></small>
                    </td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($f['fec_emision']))) ?></td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($f['fec_vencimiento']))) ?></td>
                    <td>$<?= number_format((float)$f['val_factura'], 0, ',', '.') ?></td>
                    <td>$<?= number_format((float)$f['val_saldo'], 0, ',', '.') ?></td>
                    <td class="text-center"><?= htmlspecialchars($f['num_cuotas']) ?></td>
                    <td class="text-center"><?= $badge ?></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: NUEVA FACTURA -->
<div id="modal-new-factura" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header green">
            <div>
                <h2>Nueva Factura</h2>
                <p>Registre una factura de proveedor</p>
            </div>
            <button class="modal-close btn-close-new-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="new-factura-form" novalidate>
                <input type="hidden" name="btn_nuevo" value="1">
                <div class="form-field">
                    <label class="form-label">Número de Factura <span class="required">*</span></label>
                    <input type="number" id="new-id-factura" name="txt_id_factura" class="form-input" placeholder="Ej: 1024" min="1" max="99999999">
                    <span class="field-error" id="err-new-id-factura"></span>
                </div>
                <div class="form-field">
                    <label class="form-label">Proveedor <span class="required">*</span></label>
                    <select id="new-id-proveedor" name="sel_id_proveedor" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach ($proveedores_dp as $p): ?>
                            <option value="<?= htmlspecialchars($p['id_proveedor']) ?>" data-dias="<?= (int)$p['ind_dias_pago'] ?>">
                                <?= htmlspecialchars($p['nom_tercero']) ?> — <?= (int)$p['ind_dias_pago'] ?> días de pago
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" id="err-new-proveedor"></span>
                </div>
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label">Fecha de Emisión <span class="required">*</span></label>
                        <input type="date" id="new-fec-emision" name="txt_fec_emision" class="form-input" max="<?= date('Y-m-d') ?>">
                        <span class="field-error" id="err-new-emision"></span>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Fecha de Vencimiento</label>
                        <input type="text" id="new-fec-vencimiento" class="form-input" disabled placeholder="Seleccione un proveedor">
                        <p class="field-hint">Calculada automáticamente: emisión + días de pago del proveedor.</p>
                    </div>
                </div>
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label">Valor de la Factura <span class="required">*</span></label>
                        <input type="number" id="new-val-factura" name="txt_val_factura" class="form-input" placeholder="Ej: 1500000" min="0" max="9999999999" step="1">
                        <span class="field-error" id="err-new-valor"></span>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Número de Cuotas <span class="required">*</span></label>
                        <input type="number" id="new-num-cuotas" name="txt_num_cuotas" class="form-input" placeholder="Ej: 1" min="1" max="99" value="1">
                        <span class="field-error" id="err-new-cuotas"></span>
                    </div>
                </div>
                <p class="config-hint" id="new-cuota-preview" style="display:none"></p>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel-new-modal">Cancelar</button>
            <button type="button" id="new-btn-save" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Factura</button>
        </div>
    </div>
</div>

<!-- MODAL: DETALLE DE FACTURA (con cuotas) -->
<div id="modal-detail" class="modal-overlay hidden">
    <div class="modal-box" style="max-width:560px">
        <div class="modal-header blue">
            <div><h2 id="detail-title">Detalle de Factura</h2><p id="detail-subtitle"></p></div>
            <button class="modal-close" id="close-detail-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="detail-content">
                <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Cargando...</p></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="close-detail-btn">Cerrar</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="hidden"><span id="toast-message"></span></div>

<!-- DATOS PARA EL JS -->
<script>
const facturasData = <?= json_encode(array_values($facturas), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<!-- SCRIPTS JS -->
<script src="modules/tescxp/js/cuentasxpagar.js"></script>

<?php
$moduleContent = ob_get_clean();
echo $moduleContent;
?>
