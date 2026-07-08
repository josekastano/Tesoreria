<?php
// ========== PERSONALIZAR PÁGINA ==========
$pageTitle        = 'ERP ADSO — Tesorería';
$activeModule     = 'tescxp';
$page_title       = "ADSOERP | Parámetros de Tesorería";
$page_description = "Configuración general de días de pago y reembolsos de caja menor";
$page_icon        = "bi-gear";
$page_extra_css   = ["../modules/tescxp/css/pmtros_tescxp.css"];
$page_extra_js    = ["../modules/tescxp/js/pmtros_tescxp.js"];
$show_welcome     = false;
// ==========================================

if (!defined('INCLUDE_MENU_PRINCIPAL')) {
    header("Location: menu_principal.php");
    exit();
}

require_once('prepare_tescxp.php');

// ============================================================
// NIT DE LA EMPRESA (fijo, viene de Parámetros Generales)
// ============================================================
$list_pmtros_grales->execute();
$pmtros_grales = $list_pmtros_grales->fetch(PDO::FETCH_ASSOC);
$id_empresa_fijo = $pmtros_grales['id_empresa'] ?? '';

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
// DÍAS DE LA SEMANA VÁLIDOS PARA PAGO (1=Lunes ... 6=Sábado)
// ============================================================
const DIAS_SEMANA = [
    1 => 'Lunes',
    2 => 'Martes',
    3 => 'Miércoles',
    4 => 'Jueves',
    5 => 'Viernes',
    6 => 'Sábado',
];

// ============================================================
// MANEJO DE PETICIONES POST (SIEMPRE RESPONDEN CON JSON)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $respuesta = ['success' => false, 'message' => '', 'errors' => []];

    try {
        // ---------- GUARDAR PARÁMETROS (INSERT o UPDATE según exista) ----------
        if (isset($_POST['btn_guardar'])) {
            $fec_diapago1       = (int)($_POST['sel_diapago1']         ?? 0);
            $fec_diapago2       = (int)($_POST['sel_diapago2']         ?? 0);
            $fec_diapago3       = (int)($_POST['sel_diapago3']         ?? 0);
            $val_min_reembolso  = (float)($_POST['txt_min_reembolso']  ?? 0);
            $existe             = ($_POST['hid_existe']                ?? '0') === '1';

            $errores = [];

            if (empty($id_empresa_fijo)) {
                $errores['err-id-empresa'] = 'No se encontró el NIT de la empresa en Parámetros Generales. Configure primero ese módulo.';
            }
            if ($fec_diapago1 < 1 || $fec_diapago1 > 6) {
                $errores['err-diapago1'] = 'Seleccione un día válido (Lunes a Sábado).';
            }
            if ($fec_diapago2 < 1 || $fec_diapago2 > 6) {
                $errores['err-diapago2'] = 'Seleccione un día válido (Lunes a Sábado).';
            }
            if ($fec_diapago3 < 1 || $fec_diapago3 > 6) {
                $errores['err-diapago3'] = 'Seleccione un día válido (Lunes a Sábado).';
            }
            if (!empty($fec_diapago1) && !empty($fec_diapago2) && !empty($fec_diapago3)) {
                $dias = [$fec_diapago1, $fec_diapago2, $fec_diapago3];
                if (count($dias) !== count(array_unique($dias))) {
                    $errores['err-diapago3'] = 'Los tres días de pago deben ser diferentes entre sí.';
                }
            }
            if ($val_min_reembolso < 0 || $val_min_reembolso > 99999999) {
                $errores['err-min-reembolso'] = 'El valor mínimo de reembolso debe estar entre 0 y 99.999.999.';
            }

            if (!empty($errores)) {
                $respuesta['errors'] = $errores;
                echo json_encode($respuesta);
                exit;
            }

            $params = [
                ':wid_empresa'        => $id_empresa_fijo,
                ':wfec_diapago1'      => $fec_diapago1,
                ':wfec_diapago2'      => $fec_diapago2,
                ':wfec_diapago3'      => $fec_diapago3,
                ':wval_min_reembolso' => $val_min_reembolso,
            ];

            if ($existe) {
                $upd_pmtros_tescxp->execute($params);
                $respuesta['message'] = 'Parámetros actualizados correctamente.';
            } else {
                $ins_pmtros_tescxp->execute($params);
                $respuesta['message'] = 'Parámetros guardados correctamente.';
            }

            $respuesta['success'] = true;
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
// CARGAR PARÁMETROS ACTUALES (SOLO PARA LA VISTA INICIAL)
// ============================================================
$list_pmtros_tescxp->execute();
$pmtros = $list_pmtros_tescxp->fetch(PDO::FETCH_ASSOC);
$existe_pmtros = $pmtros !== false;

// ============================================================
// INICIO DEL HTML
// ============================================================
ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<div id="mod-pmtros-tescxp" class="app-view active">

    <!-- ENCABEZADO -->
    <div class="module-header">
        <div class="module-header-text">
            <h1>Parámetros de Tesorería</h1>
            <p>Configuración general de días de pago y reembolso de caja menor</p>
        </div>
    </div>

    <!-- KPIs -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon <?= $existe_pmtros ? 'green' : 'yellow' ?>">
                <i class="fas <?= $existe_pmtros ? 'fa-check-circle' : 'fa-exclamation-circle' ?>"></i>
            </div>
            <div class="stat-info">
                <div class="stat-label">Estado</div>
                <div class="stat-value" id="kpi-estado" style="font-size:16px"><?= $existe_pmtros ? 'Configurado' : 'Sin configurar' ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-calendar-week"></i></div>
            <div class="stat-info">
                <div class="stat-label">Días de pago definidos</div>
                <div class="stat-value" id="kpi-dias">
                    <?php
                    $dias_definidos = count(array_filter([
                        $pmtros['fec_diapago1'] ?? null,
                        $pmtros['fec_diapago2'] ?? null,
                        $pmtros['fec_diapago3'] ?? null,
                    ]));
                    echo $dias_definidos . '/3';
                    ?>
                </div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <div class="stat-label">Reembolso mínimo actual</div>
                <div class="stat-value" id="kpi-reembolso">$<?= number_format((float)($pmtros['val_min_reembolso'] ?? 0), 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- FORMULARIO DE CONFIGURACIÓN -->
    <form id="pmtros-form" novalidate>
        <input type="hidden" name="btn_guardar" value="1">
        <input type="hidden" name="hid_existe" id="hid-existe" value="<?= $existe_pmtros ? '1' : '0' ?>">

        <div class="config-grid">

            <!-- COLUMNA PRINCIPAL: DÍAS DE PAGO -->
            <div class="config-card span-8">
                <h3><i class="fas fa-calendar-week"></i> Días de Pago Programados</h3>
                <p class="config-hint">Seleccione los 3 días de la semana en los que la empresa programa sus pagos a proveedores.</p>

                <div class="week-strip" id="week-strip">
                    <?php
                    $dias_corto = [1 => 'L', 2 => 'M', 3 => 'M', 4 => 'J', 5 => 'V', 6 => 'S'];
                    $seleccionados = array_filter([
                        (int)($pmtros['fec_diapago1'] ?? 0),
                        (int)($pmtros['fec_diapago2'] ?? 0),
                        (int)($pmtros['fec_diapago3'] ?? 0),
                    ]);
                    foreach ($dias_corto as $num => $letra):
                        $activo = in_array($num, $seleccionados);
                    ?>
                        <div class="week-day <?= $activo ? 'active' : '' ?>" data-dia="<?= $num ?>">
                            <span class="week-day-letter"><?= $letra ?></span>
                            <span class="week-day-name"><?= DIAS_SEMANA[$num] ?></span>
                        </div>
                    <?php endforeach; ?>
                </div>

                <div class="form-grid-3">
                    <div class="form-field">
                        <label class="form-label">Día de Pago #1 <span class="required">*</span></label>
                        <select id="diapago1" name="sel_diapago1" class="form-select diapago-select">
                            <option value="">Seleccione...</option>
                            <?php foreach (DIAS_SEMANA as $num => $nombre): ?>
                                <option value="<?= $num ?>" <?= (int)($pmtros['fec_diapago1'] ?? 0) === $num ? 'selected' : '' ?>><?= $nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-error" id="err-diapago1"></span>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Día de Pago #2 <span class="required">*</span></label>
                        <select id="diapago2" name="sel_diapago2" class="form-select diapago-select">
                            <option value="">Seleccione...</option>
                            <?php foreach (DIAS_SEMANA as $num => $nombre): ?>
                                <option value="<?= $num ?>" <?= (int)($pmtros['fec_diapago2'] ?? 0) === $num ? 'selected' : '' ?>><?= $nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-error" id="err-diapago2"></span>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Día de Pago #3 <span class="required">*</span></label>
                        <select id="diapago3" name="sel_diapago3" class="form-select diapago-select">
                            <option value="">Seleccione...</option>
                            <?php foreach (DIAS_SEMANA as $num => $nombre): ?>
                                <option value="<?= $num ?>" <?= (int)($pmtros['fec_diapago3'] ?? 0) === $num ? 'selected' : '' ?>><?= $nombre ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-error" id="err-diapago3"></span>
                    </div>
                </div>
            </div>

            <!-- COLUMNA LATERAL: EMPRESA + CAJA MENOR -->
            <div class="config-side span-4">
                <div class="config-card">
                    <h3><i class="fas fa-building"></i> Empresa</h3>
                    <div class="form-field">
                        <label class="form-label">NIT de la Empresa</label>
                        <input type="text" id="id-empresa" class="form-input" value="<?= htmlspecialchars($id_empresa_fijo) ?>" disabled>
                        <span class="field-error" id="err-id-empresa"></span>
                        <p class="field-hint">Definido en Parámetros Generales. No se puede modificar desde aquí.</p>
                    </div>
                </div>

                <div class="config-card">
                    <h3><i class="fas fa-coins"></i> Caja Menor</h3>
                    <div class="form-field">
                        <label class="form-label">Valor Mínimo de Reembolso <span class="required">*</span></label>
                        <input type="number" id="min-reembolso" name="txt_min_reembolso" class="form-input"
                               placeholder="Ej: 50000" min="0" max="99999999" step="1"
                               value="<?= htmlspecialchars($pmtros['val_min_reembolso'] ?? '0') ?>">
                        <span class="field-error" id="err-min-reembolso"></span>
                        <p class="field-hint">Monto a partir del cual se habilita la creación de una nueva caja menor.</p>
                    </div>
                </div>
            </div>

        </div>

        <div class="config-actions">
            <button type="button" id="btn-guardar-pmtros" class="btn btn-primary btn-lg">
                <i class="fas fa-save"></i> <?= $existe_pmtros ? 'Actualizar Parámetros' : 'Guardar Parámetros' ?>
            </button>
        </div>
    </form>
</div>

<!-- TOAST -->
<div id="toast" class="hidden"><span id="toast-message"></span></div>

<?php
$moduleContent = ob_get_clean();
echo $moduleContent;
?>
