<?php
// ========== PERSONALIZAR PÁGINA ==========
$pageTitle        = 'ERP ADSO — Tesorería';
$activeModule     = 'tescxp';
$page_title       = "ADSOERP | Motivos de Rechazo";
$page_description = "Catálogo de motivos por los que el banco puede rechazar un pago";
$page_icon        = "bi-x-octagon";
$page_extra_css   = ["../modules/tescxp/css/motivos_rechazo.css"];
$page_extra_js    = ["../modules/tescxp/js/motivos_rechazo.js"];
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
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $respuesta = ['success' => false, 'message' => '', 'errors' => []];

    try {
        // ---------- NUEVO MOTIVO ----------
        if (isset($_POST['btn_nuevo'])) {
            $id_motivo    = trim($_POST['txt_id_motivo_rechazo'] ?? '');
            $des_motivo   = trim($_POST['txt_des_motivo']        ?? '');
            $cod_bancario = trim($_POST['txt_cod_bancario']      ?? '');
            $ind_borrado  = ($_POST['sel_estado'] ?? 'false') === 'true';

            $errores = [];

            // id_motivo_rechazo DECIMAL(3,0) CHECK(>= 1 AND <= 999)
            if ($id_motivo === '' || !ctype_digit($id_motivo)) {
                $errores['err-new-id'] = 'El ID debe ser un número entero.';
            } elseif ((int)$id_motivo < 1 || (int)$id_motivo > 999) {
                $errores['err-new-id'] = 'El ID debe estar entre 1 y 999.';
            }

            // des_motivo VARCHAR(100) CHECK(LENGTH >= 3)
            if (mb_strlen($des_motivo) < 3 || mb_strlen($des_motivo) > 100) {
                $errores['err-new-descripcion'] = 'La descripción debe tener entre 3 y 100 caracteres.';
            }

            // cod_bancario VARCHAR(10), opcional
            if ($cod_bancario !== '' && mb_strlen($cod_bancario) > 10) {
                $errores['err-new-codigo'] = 'El código bancario no puede superar 10 caracteres.';
            }

            if (!empty($errores)) {
                $respuesta['errors'] = $errores;
                echo json_encode($respuesta);
                exit;
            }

            $ins_motivo_rechazo->execute([
                ':wid_motivo_rechazo' => (int)$id_motivo,
                ':wdes_motivo'        => $des_motivo,
                ':wcod_bancario'      => $cod_bancario !== '' ? $cod_bancario : null,
                ':wind_borrado'       => $ind_borrado ? 'true' : 'false',
            ]);

            $respuesta['success'] = true;
            $respuesta['message'] = 'Motivo de rechazo registrado correctamente.';
            echo json_encode($respuesta);
            exit;
        }

        // ---------- EDITAR MOTIVO ----------
        if (isset($_POST['btn_editar'])) {
            $id_motivo    = trim($_POST['hid_edit_id_motivo']    ?? '');
            $des_motivo   = trim($_POST['txt_edit_des_motivo']   ?? '');
            $cod_bancario = trim($_POST['txt_edit_cod_bancario'] ?? '');
            $ind_borrado  = ($_POST['sel_edit_estado'] ?? 'false') === 'true';

            $errores = [];

            if ($id_motivo === '' || !ctype_digit($id_motivo)) {
                $errores['err-edit-descripcion'] = 'Motivo no válido.';
            }
            if (mb_strlen($des_motivo) < 3 || mb_strlen($des_motivo) > 100) {
                $errores['err-edit-descripcion'] = 'La descripción debe tener entre 3 y 100 caracteres.';
            }
            if ($cod_bancario !== '' && mb_strlen($cod_bancario) > 10) {
                $errores['err-edit-codigo'] = 'El código bancario no puede superar 10 caracteres.';
            }

            if (!empty($errores)) {
                $respuesta['errors'] = $errores;
                echo json_encode($respuesta);
                exit;
            }

            $upd_motivo_rechazo->execute([
                ':wid_motivo_rechazo' => (int)$id_motivo,
                ':wdes_motivo'        => $des_motivo,
                ':wcod_bancario'      => $cod_bancario !== '' ? $cod_bancario : null,
                ':wind_borrado'       => $ind_borrado ? 'true' : 'false',
            ]);

            $respuesta['success'] = true;
            $respuesta['message'] = 'Motivo de rechazo actualizado correctamente.';
            echo json_encode($respuesta);
            exit;
        }

        // ---------- ACTIVAR / DESACTIVAR (borrado lógico) ----------
        if (isset($_POST['btn_toggle_estado'])) {
            $id_motivo   = trim($_POST['hid_toggle_id_motivo'] ?? '');
            $ind_borrado = ($_POST['hid_toggle_borrado'] ?? 'false') === 'true';

            if ($id_motivo === '' || !ctype_digit($id_motivo)) {
                throw new Exception('Motivo no válido.');
            }

            $upd_motivo_rechazo_estado->execute([
                ':wid_motivo_rechazo' => (int)$id_motivo,
                ':wind_borrado'       => $ind_borrado ? 'true' : 'false',
            ]);

            $respuesta['success'] = true;
            $respuesta['message'] = $ind_borrado
                ? 'Motivo desactivado correctamente.'
                : 'Motivo activado correctamente.';
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
        // Llave primaria duplicada -> ID de motivo repetido
        if (stripos($mensaje, 'duplicate key') !== false || stripos($mensaje, 'llave duplicada') !== false) {
            $mensaje = 'Ya existe un motivo de rechazo con ese ID.';
        }
        $respuesta['message'] = $mensaje;
        echo json_encode($respuesta);
        exit;
    }
}

// ============================================================
// CARGAR TABLA (SOLO PARA LA VISTA INICIAL)
// ============================================================
$list_motivos_rechazo->execute();
$motivos = $list_motivos_rechazo->fetchAll(PDO::FETCH_ASSOC);

// Helper: ind_borrado llega como 't'/'f' en PDO pgsql
function motivo_esta_borrado($valor): bool {
    return $valor === 't' || $valor === true || $valor === 1 || $valor === '1';
}

// ============================================================
// INICIO DEL HTML
// ============================================================
ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="modules/tescxp/css/motivos_rechazo_tescxp.css">
<div id="mod-motivos" class="app-view active">

    <!-- ENCABEZADO -->
    <div class="module-header">
        <div class="module-header-text">
            <h1>Motivos de Rechazo</h1>
            <p>Catálogo de motivos por los que el banco puede rechazar un pago</p>
        </div>
        <button id="btn-add-motivo" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Motivo
        </button>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <?php
        $total     = count($motivos);
        $inactivos = count(array_filter($motivos, fn($m) => motivo_esta_borrado($m['ind_borrado'])));
        $activos   = $total - $inactivos;
        ?>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-list-ul"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total motivos</div>
                <div class="stat-value" id="stat-total"><?= $total ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-label">Activos</div>
                <div class="stat-value" id="stat-activos"><?= $activos ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-ban"></i></div>
            <div class="stat-info">
                <div class="stat-label">Inactivos</div>
                <div class="stat-value" id="stat-inactivos"><?= $inactivos ?></div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filter-bar">
        <div class="search-wrapper">
            <span class="search-icon"><i class="fas fa-search"></i></span>
            <input type="text" id="motivo-search" class="search-input" placeholder="Buscar por descripción o código bancario...">
        </div>
        <div class="filter-toggle-group">
            <button class="filter-toggle active" data-filter="all">Todos</button>
            <button class="filter-toggle" data-filter="activo">Activos</button>
            <button class="filter-toggle" data-filter="inactivo">Inactivos</button>
        </div>
        <button id="btn-clear-filters" class="btn-clear-filter" style="display:none">
            <i class="fas fa-times"></i> Limpiar
        </button>
        <span class="filter-info" id="motivos-count"><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <!-- TABLA -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Descripción del Motivo</th>
                    <th>Código Bancario</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="motivos-tbody">
            <?php if (empty($motivos)): ?>
                <tr class="empty-row">
                    <td colspan="5">
                        <div class="empty-state">
                            <i class="fas fa-x-octagon"></i>
                            <p>No hay motivos de rechazo registrados</p>
                            <span>Haga clic en "Nuevo Motivo" para agregar el primero</span>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($motivos as $m):
                    $borrado = motivo_esta_borrado($m['ind_borrado']);
                    $filtro  = $borrado ? 'inactivo' : 'activo';
                    $cod     = trim((string)($m['cod_bancario'] ?? ''));
                ?>
                <tr data-tipo="<?= $filtro ?>" class="<?= $borrado ? 'inactivo' : '' ?>">
                    <td><strong><?= htmlspecialchars($m['id_motivo_rechazo']) ?></strong></td>
                    <td><?= htmlspecialchars($m['des_motivo']) ?></td>
                    <td>
                        <?php if ($cod !== ''): ?>
                            <span class="cod-bancario"><?= htmlspecialchars($cod) ?></span>
                        <?php else: ?>
                            <span class="cod-vacio">Sin código</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <?php if ($borrado): ?>
                            <span class="badge badge-inactive">Inactivo</span>
                        <?php else: ?>
                            <span class="badge badge-active">Activo</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <button class="btn-icon-sm toggle" title="<?= $borrado ? 'Activar' : 'Desactivar' ?>"
                                onclick="toggleEstadoMotivo(<?= (int)$m['id_motivo_rechazo'] ?>, <?= $borrado ? 'false' : 'true' ?>)">
                            <i class="fas fa-power-off"></i>
                        </button>
                        <button class="btn-icon-sm edit" title="Editar"
                                onclick='openEditModal(<?= json_encode($m, JSON_HEX_APOS | JSON_HEX_QUOT) ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: NUEVO MOTIVO -->
<div id="modal-new-motivo" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header green">
            <div>
                <h2>Nuevo Motivo de Rechazo</h2>
                <p>Registre un motivo en el catálogo del sistema</p>
            </div>
            <button class="modal-close btn-close-new-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="new-motivo-form" novalidate>
                <input type="hidden" name="btn_nuevo" value="1">
                <div class="form-field">
                    <label class="form-label">ID del Motivo <span class="required">*</span></label>
                    <input type="number" id="new-id-motivo" name="txt_id_motivo_rechazo" class="form-input" placeholder="Ej: 8" min="1" max="999">
                    <span class="field-error" id="err-new-id"></span>
                </div>
                <div class="form-field">
                    <label class="form-label">Descripción del Motivo <span class="required">*</span></label>
                    <input type="text" id="new-des-motivo" name="txt_des_motivo" class="form-input" placeholder="Ej: Cuenta embargada" minlength="3" maxlength="100">
                    <span class="field-error" id="err-new-descripcion"></span>
                </div>
                <div class="form-field">
                    <label class="form-label">Código Bancario</label>
                    <input type="text" id="new-cod-bancario" name="txt_cod_bancario" class="form-input" placeholder="Ej: R05" maxlength="10">
                    <span class="field-error" id="err-new-codigo"></span>
                    <p class="field-hint">Opcional. Es el código con que el banco devuelve el rechazo en su archivo de respuesta.</p>
                </div>
                <div class="form-field">
                    <label class="form-label">Estado</label>
                    <select id="new-estado" name="sel_estado" class="form-select">
                        <option value="false">Activo</option>
                        <option value="true">Inactivo</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel-new-modal">Cancelar</button>
            <button type="button" id="new-btn-save" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Motivo</button>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR MOTIVO -->
<div id="modal-edit-motivo" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header blue">
            <div>
                <h2>Editar Motivo de Rechazo</h2>
                <p>Modifique la descripción, el código o el estado</p>
            </div>
            <button class="modal-close btn-close-edit-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="edit-motivo-form" novalidate>
                <input type="hidden" name="btn_editar" value="1">
                <input type="hidden" name="hid_edit_id_motivo" id="edit-id-motivo-hid" value="">
                <div class="form-field">
                    <label class="form-label">ID del Motivo</label>
                    <input type="text" id="edit-id-motivo-display" class="form-input" disabled="disabled">
                    <p class="field-hint">El ID es la llave primaria y no se puede modificar.</p>
                </div>
                <div class="form-field">
                    <label class="form-label">Descripción del Motivo <span class="required">*</span></label>
                    <input type="text" id="edit-des-motivo" name="txt_edit_des_motivo" class="form-input" minlength="3" maxlength="100">
                    <span class="field-error" id="err-edit-descripcion"></span>
                </div>
                <div class="form-field">
                    <label class="form-label">Código Bancario</label>
                    <input type="text" id="edit-cod-bancario" name="txt_edit_cod_bancario" class="form-input" maxlength="10">
                    <span class="field-error" id="err-edit-codigo"></span>
                </div>
                <div class="form-field">
                    <label class="form-label">Estado</label>
                    <select id="edit-estado" name="sel_edit_estado" class="form-select">
                        <option value="false">Activo</option>
                        <option value="true">Inactivo</option>
                    </select>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel-edit-modal">Cancelar</button>
            <button type="button" id="edit-btn-save" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar Motivo</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="hidden"><span id="toast-message"></span></div>

<!-- SCRIPTS JS -->
<script src="modules/tescxp/js/motivos_rechazo_tescxp.js"></script>


<?php
$moduleContent = ob_get_clean();
echo $moduleContent;
?>
