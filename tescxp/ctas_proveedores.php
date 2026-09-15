<?php
// ========== PERSONALIZAR PÁGINA ==========
$pageTitle        = 'ERP ADSO — Tesorería';
$activeModule     = 'tescxp';
$page_title       = "ADSOERP | Bancos por Proveedor";
$page_description = "Gestión de cuentas bancarias de proveedores para el pago de facturas";
$page_icon        = "bi-bank2";
$page_extra_css   = ["../modules/tescxp/css/ctas_proveedores.css"];
$page_extra_js    = ["../modules/tescxp/js/ctas_proveedores.js"];
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
$list_bancos->execute();
$bancos = $list_bancos->fetchAll(PDO::FETCH_ASSOC);

$list_proveedores_select->execute();
$proveedores_select = $list_proveedores_select->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// MANEJO DE PETICIONES POST (SIEMPRE RESPONDEN CON JSON)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $respuesta = ['success' => false, 'message' => '', 'errors' => []];

    try {
        // ---------- NUEVA CUENTA ----------
        if (isset($_POST['btn_nuevo'])) {
            $id_proveedor   = trim($_POST['sel_id_proveedor']    ?? '');
            $cta_proveedor  = trim($_POST['txt_cta_proveedor']   ?? '');
            $id_banco       = trim($_POST['sel_id_banco']        ?? '');
            $ind_tipocuenta = ($_POST['sel_tipocuenta']          ?? 'false') === 'true';

            $errores = [];

            if (empty($id_proveedor)) {
                $errores['err-new-proveedor'] = 'Seleccione un proveedor.';
            }
            if (!preg_match('/^[0-9]{10,16}$/', $cta_proveedor)) {
                $errores['err-new-cta'] = 'El número de cuenta debe tener entre 10 y 16 dígitos numéricos.';
            }
            if (empty($id_banco)) {
                $errores['err-new-banco'] = 'Seleccione un banco.';
            }

            if (!empty($errores)) {
                $respuesta['errors'] = $errores;
                echo json_encode($respuesta);
                exit;
            }

            $ins_bancoxprov->execute([
                ':wid_proveedor'   => $id_proveedor,
                ':wcta_proveedor'  => $cta_proveedor,
                ':wid_banco'       => $id_banco,
                ':wind_tipocuenta' => $ind_tipocuenta ? 'true' : 'false',
            ]);

            $respuesta['success'] = true;
            $respuesta['message'] = 'Cuenta bancaria del proveedor registrada correctamente.';
            echo json_encode($respuesta);
            exit;
        }

        // ---------- EDITAR CUENTA ----------
        if (isset($_POST['btn_editar'])) {
            $id_proveedor   = trim($_POST['hid_edit_id_proveedor']  ?? '');
            $cta_proveedor  = trim($_POST['hid_edit_cta_proveedor'] ?? '');
            $id_banco       = trim($_POST['sel_edit_id_banco']      ?? '');
            $ind_tipocuenta = ($_POST['sel_edit_tipocuenta']        ?? 'false') === 'true';

            $errores = [];

            if (empty($id_proveedor) || empty($cta_proveedor)) {
                $errores['err-edit-banco'] = 'Cuenta no válida.';
            }
            if (empty($id_banco)) {
                $errores['err-edit-banco'] = 'Seleccione un banco.';
            }

            if (!empty($errores)) {
                $respuesta['errors'] = $errores;
                echo json_encode($respuesta);
                exit;
            }

            $upd_bancoxprov->execute([
                ':wid_proveedor'   => $id_proveedor,
                ':wcta_proveedor'  => $cta_proveedor,
                ':wid_banco'       => $id_banco,
                ':wind_tipocuenta' => $ind_tipocuenta ? 'true' : 'false',
            ]);

            $respuesta['success'] = true;
            $respuesta['message'] = 'Cuenta bancaria del proveedor actualizada correctamente.';
            echo json_encode($respuesta);
            exit;
        }

        // ---------- ELIMINAR CUENTA ----------
        if (isset($_POST['btn_eliminar'])) {
            $id_proveedor  = trim($_POST['hid_del_id_proveedor']  ?? '');
            $cta_proveedor = trim($_POST['hid_del_cta_proveedor'] ?? '');

            if (empty($id_proveedor) || empty($cta_proveedor)) {
                throw new Exception('Cuenta no válida.');
            }

            $del_bancoxprov->execute([
                ':wid_proveedor'  => $id_proveedor,
                ':wcta_proveedor' => $cta_proveedor,
            ]);

            $respuesta['success'] = true;
            $respuesta['message'] = 'Cuenta bancaria del proveedor eliminada correctamente.';
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
$list_bancoxprov->execute();
$bancoxprov = $list_bancoxprov->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// INICIO DEL HTML
// ============================================================
ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="modules/tescxp/css/ctas_proveedores.css">
<div id="mod-bancoxprov" class="app-view active">

    <!-- ENCABEZADO -->
    <div class="module-header">
        <div class="module-header-text">
            <h1>Bancos por Proveedor</h1>
            <p>Cuentas bancarias registradas para el pago de facturas a proveedores</p>
        </div>
        <button id="btn-add-cuenta" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nueva Cuenta
        </button>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <?php
        $total      = count($bancoxprov);
        $corrientes = count(array_filter($bancoxprov, fn($c) => $c['ind_tipocuenta'] === 't' || $c['ind_tipocuenta'] === true));
        $ahorros    = $total - $corrientes;
        $prov_unicos   = count(array_unique(array_column($bancoxprov, 'id_proveedor')));
        ?>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-university"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total cuentas</div>
                <div class="stat-value" id="stat-total"><?= $total ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-money-check-alt"></i></div>
            <div class="stat-info">
                <div class="stat-label">Corrientes</div>
                <div class="stat-value" id="stat-corrientes"><?= $corrientes ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-piggy-bank"></i></div>
            <div class="stat-info">
                <div class="stat-label">Ahorros</div>
                <div class="stat-value" id="stat-ahorros"><?= $ahorros ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-handshake"></i></div>
            <div class="stat-info">
                <div class="stat-label">Proveedores con cuenta</div>
                <div class="stat-value" id="stat-proveedores"><?= $prov_unicos ?></div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filter-bar">
        <div class="search-wrapper">
            <span class="search-icon"><i class="fas fa-search"></i></span>
            <input type="text" id="cuenta-search" class="search-input" placeholder="Buscar por proveedor, cuenta o banco...">
        </div>
        <div class="filter-toggle-group">
            <button class="filter-toggle active" data-filter="all">Todas</button>
            <button class="filter-toggle" data-filter="corriente">Corrientes</button>
            <button class="filter-toggle" data-filter="ahorros">Ahorros</button>
        </div>
        <button id="btn-clear-filters" class="btn-clear-filter" style="display:none">
            <i class="fas fa-times"></i> Limpiar
        </button>
        <span class="filter-info" id="cuentas-count"><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <!-- TABLA -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Proveedor</th>
                    <th>Banco</th>
                    <th>Número de Cuenta</th>
                    <th class="text-center">Tipo</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="cuentas-tbody">
            <?php if (empty($bancoxprov)): ?>
                <tr class="empty-row">
                    <td colspan="5">
                        <div class="empty-state">
                            <i class="fas fa-university"></i>
                            <p>No hay cuentas de proveedores registradas</p>
                            <span>Haga clic en "Nueva Cuenta" para agregar la primera</span>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($bancoxprov as $c):
                    $es_corriente = ($c['ind_tipocuenta'] === 't' || $c['ind_tipocuenta'] === true);
                    $badge_tipo   = $es_corriente
                        ? '<span class="badge badge-active">Corriente</span>'
                        : '<span class="badge badge-inactive" style="background:#fffbeb;color:#f59e0b">Ahorros</span>';
                    $id_prov_esc  = htmlspecialchars($c['id_proveedor']);
                    $cta_esc      = htmlspecialchars($c['cta_proveedor']);
                    $filtro_tipo  = $es_corriente ? 'corriente' : 'ahorros';
                ?>
                <tr data-tipo="<?= $filtro_tipo ?>">
                    <td>
                        <strong><?= htmlspecialchars($c['nom_tercero']) ?></strong><br>
                        <small style="color:#94a3b8;font-size:11px"><?= $id_prov_esc ?></small>
                    </td>
                    <td><?= htmlspecialchars($c['nom_banco']) ?></td>
                    <td><?= $cta_esc ?></td>
                    <td class="text-center"><?= $badge_tipo ?></td>
                    <td class="text-center">
                        <button class="btn-icon-sm edit" onclick='openEditModal(<?= json_encode($c) ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon-sm reject" onclick="eliminarCuenta('<?= $id_prov_esc ?>', '<?= $cta_esc ?>', '<?= htmlspecialchars($c['nom_tercero'], ENT_QUOTES) ?>')">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: NUEVA CUENTA -->
<div id="modal-new-cuenta" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header green">
            <div>
                <h2>Nueva Cuenta de Proveedor</h2>
                <p>Registre la cuenta bancaria de un proveedor</p>
            </div>
            <button class="modal-close btn-close-new-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="new-cuenta-form" novalidate>
                <input type="hidden" name="btn_nuevo" value="1">
                <div class="form-field">
                    <label class="form-label">Proveedor <span class="required">*</span></label>
                    <select id="new-id-proveedor" name="sel_id_proveedor" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach ($proveedores_select as $p): ?>
                            <option value="<?= htmlspecialchars($p['id_proveedor']) ?>">
                                <?= htmlspecialchars($p['nom_tercero']) ?> — <?= htmlspecialchars($p['id_proveedor']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" id="err-new-proveedor"></span>
                </div>
                <div class="form-field">
                    <label class="form-label">Banco <span class="required">*</span></label>
                    <select id="new-id-banco" name="sel_id_banco" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach ($bancos as $b): ?>
                            <option value="<?= htmlspecialchars($b['id_banco']) ?>"><?= htmlspecialchars($b['nom_banco']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" id="err-new-banco"></span>
                </div>
                <div class="form-field">
                    <label class="form-label">Número de Cuenta <span class="required">*</span></label>
                    <input type="text" id="new-cta-proveedor" name="txt_cta_proveedor" class="form-input" placeholder="Ej: 1234567890" minlength="10" maxlength="16">
                    <span class="field-error" id="err-new-cta"></span>
                </div>
                <div class="form-field">
                    <label class="form-label">Tipo de Cuenta</label>
                    <select id="new-tipocuenta" name="sel_tipocuenta" class="form-select">
                        <option value="false">Ahorros</option>
                        <option value="true">Corriente</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel-new-modal">Cancelar</button>
            <button type="button" id="new-btn-save" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cuenta</button>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR CUENTA -->
<div id="modal-edit-cuenta" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header blue">
            <div>
                <h2>Editar Cuenta de Proveedor</h2>
                <p>Modifique el banco o el tipo de cuenta</p>
            </div>
            <button class="modal-close btn-close-edit-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="edit-cuenta-form" novalidate>
                <input type="hidden" name="btn_editar" value="1">
                <input type="hidden" name="hid_edit_id_proveedor" id="edit-id-proveedor-hid" value="">
                <input type="hidden" name="hid_edit_cta_proveedor" id="edit-cta-proveedor-hid" value="">
                <div class="form-field">
                    <label class="form-label">Proveedor</label>
                    <input type="text" id="edit-proveedor-display" class="form-input" disabled>
                </div>
                <div class="form-field">
                    <label class="form-label">Número de Cuenta</label>
                    <input type="text" id="edit-cta-proveedor-display" class="form-input" disabled>
                </div>
                <div class="form-field">
                    <label class="form-label">Banco <span class="required">*</span></label>
                    <select id="edit-id-banco" name="sel_edit_id_banco" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach ($bancos as $b): ?>
                            <option value="<?= htmlspecialchars($b['id_banco']) ?>"><?= htmlspecialchars($b['nom_banco']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" id="err-edit-banco"></span>
                </div>
                <div class="form-field">
                    <label class="form-label">Tipo de Cuenta</label>
                    <select id="edit-tipocuenta" name="sel_edit_tipocuenta" class="form-select">
                        <option value="false">Ahorros</option>
                        <option value="true">Corriente</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel-edit-modal">Cancelar</button>
            <button type="button" id="edit-btn-save" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar Cuenta</button>
        </div>
    </div>
</div>

<!-- MODAL: CONFIRMAR ELIMINACIÓN (estilo módulo Compras) -->
<div id="modal-confirm-eliminar" class="modal-overlay hidden">
    <div class="modal-box confirm-box">
        <div class="confirm-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <h4 id="confirm-eliminar-title"></h4>
        <p id="confirm-eliminar-body"></p>
        <div class="confirm-buttons">
            <button type="button" class="btn-cancelar" id="confirm-eliminar-cancel-btn">Cancelar</button>
            <button type="button" class="btn-eliminar" id="confirm-eliminar-ok-btn">Sí, eliminar</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="hidden"><span id="toast-message"></span></div>

<!-- SCRIPTS JS -->
<script src="modules/tescxp/js/ctas_proveedores.js"></script>


<?php
$moduleContent = ob_get_clean();
echo $moduleContent;
?>
