<?php
// ========== PERSONALIZAR PÁGINA ==========
$pageTitle        = 'ERP ADSO — Tesorería';
$activeModule     = 'tescxp';
$page_title       = "ADSOERP | Cronograma de Pagos";
$page_description = "Programación de pagos agrupando cuotas pendientes de facturas";
$page_icon        = "bi-calendar2-check";
$page_extra_css   = ["../modules/tescxp/css/cronopagos.css"];
$page_extra_js    = ["../modules/tescxp/js/cronopagos.js"];
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
        // ---------- OBTENER DETALLE DE UN CRONOGRAMA (para el modal de detalle) ----------
        if (isset($_POST['btn_ver_detalle'])) {
            $id_cronograma = (int)($_POST['hid_id_cronograma'] ?? 0);
            if ($id_cronograma <= 0) {
                throw new Exception('Cronograma no válido.');
            }

            $list_det_cronopagos->execute([':wid_cronograma' => $id_cronograma]);
            $detalle = $list_det_cronopagos->fetchAll(PDO::FETCH_ASSOC);

            $respuesta['success'] = true;
            $respuesta['detalle'] = $detalle;
            echo json_encode($respuesta);
            exit;
        }

        // ---------- NUEVO CRONOGRAMA ----------
        if (isset($_POST['btn_nuevo'])) {
            $nom_cronograma   = trim($_POST['txt_nom_cronograma']  ?? '');
            $fec_programacion = trim($_POST['txt_fec_programacion'] ?? '');
            $cuotas_raw       = $_POST['hid_cuotas_seleccionadas']  ?? '';

            $errores = [];

            if (strlen($nom_cronograma) < 3 || strlen($nom_cronograma) > 30) {
                $errores['err-new-nombre'] = 'El nombre debe tener entre 3 y 30 caracteres.';
            }
            $hoy = date('Y-m-d');
            if (empty($fec_programacion)) {
                $errores['err-new-fecha'] = 'La fecha de programación es obligatoria.';
            } elseif ($fec_programacion < $hoy) {
                $errores['err-new-fecha'] = 'La fecha de programación no puede ser anterior a hoy.';
            }

            // Decodificar las cuotas seleccionadas: "id_factura:id_cuota,id_factura:id_cuota,..."
            $cuotas_seleccionadas = [];
            if (!empty($cuotas_raw)) {
                foreach (explode(',', $cuotas_raw) as $par) {
                    [$idf, $idc] = array_pad(explode(':', $par), 2, null);
                    if ($idf !== null && $idc !== null && (int)$idf > 0 && (int)$idc > 0) {
                        $cuotas_seleccionadas[] = [(int)$idf, (int)$idc];
                    }
                }
            }
            if (empty($cuotas_seleccionadas)) {
                $errores['err-new-cuotas'] = 'Seleccione al menos una cuota para el cronograma.';
            }

            if (!empty($errores)) {
                $respuesta['errors'] = $errores;
                echo json_encode($respuesta);
                exit;
            }

            $pdo->beginTransaction();

            $ins_enc_cronopagos->execute([
                ':wfec_programacion' => $fec_programacion,
                ':wnom_cronograma'   => $nom_cronograma
            ]);

            // Recuperar el id del cronograma recién creado
            $id_nuevo = (int)$pdo->query("SELECT MAX(id_cronograma) FROM tab_enc_cronopagos")->fetchColumn();

            foreach ($cuotas_seleccionadas as [$idFactura, $idCuota]) {
                $ins_det_cronopagos->execute([
                    ':wid_cronograma' => $id_nuevo,
                    ':wid_factura'    => $idFactura,
                    ':wid_cuota'      => $idCuota,
                ]);
            }

            $pdo->commit();

            $respuesta['success'] = true;
            $respuesta['message'] = 'Cronograma creado correctamente con ' . count($cuotas_seleccionadas) . ' cuota(s).';
            echo json_encode($respuesta);
            exit;
        }

        // ---------- EDITAR CRONOGRAMA (nombre y fecha) ----------
        if (isset($_POST['btn_editar'])) {
            $id_cronograma    = (int)($_POST['hid_edit_id']             ?? 0);
            $nom_cronograma   = trim($_POST['txt_edit_nom_cronograma']  ?? '');
            $fec_programacion = trim($_POST['txt_edit_fec_programacion'] ?? '');

            $errores = [];

            if ($id_cronograma <= 0) {
                $errores['err-edit-nombre'] = 'Cronograma no válido.';
            }
            if (strlen($nom_cronograma) < 3 || strlen($nom_cronograma) > 30) {
                $errores['err-edit-nombre'] = 'El nombre debe tener entre 3 y 30 caracteres.';
            }
            if (empty($fec_programacion)) {
                $errores['err-edit-fecha'] = 'La fecha de programación es obligatoria.';
            }

            if (!empty($errores)) {
                $respuesta['errors'] = $errores;
                echo json_encode($respuesta);
                exit;
            }

            $upd_enc_cronopagos->execute([
                ':wid_cronograma'    => $id_cronograma,
                ':wnom_cronograma'   => $nom_cronograma,
                ':wfec_programacion' => $fec_programacion,
            ]);

            $respuesta['success'] = true;
            $respuesta['message'] = 'Cronograma actualizado correctamente.';
            echo json_encode($respuesta);
            exit;
        }

        // ---------- ELIMINAR CRONOGRAMA ----------
        if (isset($_POST['btn_eliminar'])) {
            $id_cronograma = (int)($_POST['hid_del_id'] ?? 0);
            if ($id_cronograma <= 0) {
                throw new Exception('Cronograma no válido.');
            }

            $del_enc_cronopagos->execute([':wid_cronograma' => $id_cronograma]);

            $respuesta['success'] = true;
            $respuesta['message'] = 'Cronograma eliminado correctamente.';
            echo json_encode($respuesta);
            exit;
        }

        // ---------- ELIMINAR LÍNEA DE DETALLE DEL CRONOGRAMA ----------
        if (isset($_POST['btn_eliminar_detalle'])) {
            $id_cronograma = (int)($_POST['hid_det_id_cronograma'] ?? 0);
            $id_factura    = (int)($_POST['hid_det_id_factura']    ?? 0);
            $id_cuota      = (int)($_POST['hid_det_id_cuota']      ?? 0);

            if ($id_cronograma <= 0 || $id_factura <= 0 || $id_cuota <= 0) {
                throw new Exception('Datos de la cuota no válidos.');
            }

            $del_det_cronopagos->execute([
                ':wid_cronograma' => $id_cronograma,
                ':wid_factura'    => $id_factura,
                ':wid_cuota'      => $id_cuota,
            ]);

            $respuesta['success'] = true;
            $respuesta['message'] = 'Cuota eliminada del cronograma correctamente.';
            echo json_encode($respuesta);
            exit;
        }

        // Si no se reconoce ninguna acción
        $respuesta['message'] = 'Acción no válida.';
        echo json_encode($respuesta);
        exit;

    } catch (Exception $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
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
// CARGAR DATOS PARA LA VISTA INICIAL
// ============================================================
$list_enc_cronopagos->execute();
$cronogramas = $list_enc_cronopagos->fetchAll(PDO::FETCH_ASSOC);

$list_cuotas_pendientes->execute();
$cuotas_pendientes = $list_cuotas_pendientes->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// INICIO DEL HTML
// ============================================================
ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="modules/tescxp/css/cronopagos.css">
<div id="mod-cronograma" class="app-view active">

    <!-- ENCABEZADO -->
    <div class="module-header">
        <div class="module-header-text">
            <h1>Cronograma de Pagos</h1>
            <p>Agrupe cuotas pendientes de facturas para programar su pago</p>
        </div>
        <button id="btn-add-crono" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Cronograma
        </button>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <?php
        $total      = count($cronogramas);
        $pendientes = count(array_filter($cronogramas, fn($c) => !($c['ind_estado'] === 't' || $c['ind_estado'] === true)));
        $pagados    = $total - $pendientes;
        $total_programado = array_sum(array_column($cronogramas, 'total_a_pagar'));
        ?>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-calendar-alt"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total cronogramas</div>
                <div class="stat-value" id="stat-total"><?= $total ?></div>
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
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-label">Pagados</div>
                <div class="stat-value" id="stat-pagados"><?= $pagados ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-coins"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total programado</div>
                <div class="stat-value" id="stat-programado" style="font-size:16px">$<?= number_format($total_programado, 0, ',', '.') ?></div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filter-bar">
        <div class="search-wrapper">
            <span class="search-icon"><i class="fas fa-search"></i></span>
            <input type="text" id="crono-search" class="search-input" placeholder="Buscar por nombre de cronograma...">
        </div>
        <div class="filter-toggle-group">
            <button class="filter-toggle active" data-filter="all">Todos</button>
            <button class="filter-toggle" data-filter="pendiente">Pendientes</button>
            <button class="filter-toggle" data-filter="pagado">Pagados</button>
        </div>
        <button id="btn-clear-filters" class="btn-clear-filter" style="display:none">
            <i class="fas fa-times"></i> Limpiar
        </button>
        <span class="filter-info" id="cronos-count"><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <!-- TOGGLE DE VISTA -->
    <div class="view-toggle-group">
        <button class="view-toggle active" data-view="tabla"><i class="fas fa-table"></i> Tabla</button>
        <button class="view-toggle" data-view="calendario"><i class="fas fa-calendar-days"></i> Calendario</button>
    </div>

    <!-- VISTA: TABLA -->
    <div id="view-tabla" class="view-pane active">

    <!-- TABLA -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Cronograma</th>
                    <th>Fecha Programada</th>
                    <th class="text-right">Total a Pagar</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="cronos-tbody">
            <?php if (empty($cronogramas)): ?>
                <tr class="empty-row">
                    <td colspan="5">
                        <div class="empty-state">
                            <i class="fas fa-calendar-alt"></i>
                            <p>No hay cronogramas registrados</p>
                            <span>Haga clic en "Nuevo Cronograma" para agregar el primero</span>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($cronogramas as $c):
                    $pagado      = ($c['ind_estado'] === 't' || $c['ind_estado'] === true);
                    $badge       = $pagado
                        ? '<span class="badge badge-active">Pagado</span>'
                        : '<span class="badge badge-inactive" style="background:#fffbeb;color:#f59e0b">Pendiente</span>';
                    $filtro      = $pagado ? 'pagado' : 'pendiente';
                    $id_esc      = htmlspecialchars($c['id_cronograma']);
                ?>
                <tr data-estado="<?= $filtro ?>" onclick='openDetailModal(<?= json_encode($c) ?>)'>
                    <td>
                        <strong><?= htmlspecialchars($c['nom_cronograma']) ?></strong><br>
                        <small style="color:#94a3b8;font-size:11px">#<?= $id_esc ?></small>
                    </td>
                    <td><?= htmlspecialchars(date('d/m/Y', strtotime($c['fec_programacion']))) ?></td>
                    <td>$<?= number_format((float)$c['total_a_pagar'], 0, ',', '.') ?></td>
                    <td class="text-center"><?= $badge ?></td>
                    <td class="text-center" onclick="event.stopPropagation()">
                        <button class="btn-icon-sm edit" onclick='openEditModal(<?= json_encode($c) ?>)'>
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn-icon-sm reject" onclick="eliminarCronograma(<?= $id_esc ?>, '<?= htmlspecialchars($c['nom_cronograma'], ENT_QUOTES) ?>')">
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
    <!-- /VISTA: TABLA -->

    <!-- VISTA: CALENDARIO -->
    <div id="view-calendario" class="view-pane">
        <div class="cal-shell">

            <!-- Panel del día seleccionado -->
            <aside class="cal-side">
                <div>
                    <span class="cal-side-day" id="cal-sel-day">—</span>
                    <span class="cal-side-weekday" id="cal-sel-weekday"></span>
                    <span class="cal-side-month" id="cal-sel-month"></span>
                </div>

                <div class="cal-side-events">
                    <span class="cal-side-label">Cronogramas del día</span>
                    <ul class="cal-side-list" id="cal-side-list"></ul>
                </div>

                <button type="button" class="cal-side-action" id="cal-side-detail" disabled="disabled">
                    <span>Ver detalle del día</span>
                    <i class="fas fa-circle-plus"></i>
                </button>
            </aside>

            <!-- Rejilla del mes -->
            <div class="cal-main">
                <div class="cal-year-nav">
                    <button type="button" id="cal-prev-year" class="cal-arrow" title="Año anterior"><i class="fas fa-chevron-left"></i></button>
                    <h3 id="cal-year-label">—</h3>
                    <button type="button" id="cal-next-year" class="cal-arrow" title="Año siguiente"><i class="fas fa-chevron-right"></i></button>
                </div>

                <div class="cal-months" id="cal-months"></div>

                <div class="cal-weekdays">
                    <span>Dom</span><span>Lun</span><span>Mar</span><span>Mié</span><span>Jue</span><span>Vie</span><span>Sáb</span>
                </div>

                <div id="calendar-grid" class="cal-grid"></div>

                <div class="cal-legend">
                    <span class="cal-legend-item"><i class="cal-dot pendiente"></i> Con pendientes</span>
                    <span class="cal-legend-item"><i class="cal-dot pagado"></i> Todos pagados</span>
                </div>
            </div>
        </div>
    </div>
    <!-- /VISTA: CALENDARIO -->
</div>

<!-- DATOS PARA EL JS (calendario) -->
<script>
const cronogramasData = <?= json_encode(array_values($cronogramas), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
</script>

<!-- MODAL: CRONOGRAMAS DE UN DÍA -->
<div id="modal-day" class="modal-overlay hidden">
    <div class="modal-box" style="max-width:480px">
        <div class="modal-header blue">
            <div><h2 id="day-modal-title">Cronogramas del día</h2><p id="day-modal-subtitle"></p></div>
            <button class="modal-close" id="close-day-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="day-modal-list"></div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="close-day-btn">Cerrar</button>
        </div>
    </div>
</div>

<!-- MODAL: NUEVO CRONOGRAMA -->
<div id="modal-new-crono" class="modal-overlay hidden">
    <div class="modal-box" style="max-width:600px">
        <div class="modal-header green">
            <div>
                <h2>Nuevo Cronograma de Pagos</h2>
                <p>Seleccione las cuotas pendientes a incluir</p>
            </div>
            <button class="modal-close btn-close-new-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="new-crono-form" novalidate>
                <input type="hidden" name="btn_nuevo" value="1">
                <input type="hidden" name="hid_cuotas_seleccionadas" id="hid-cuotas-seleccionadas" value="">
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label">Nombre del Cronograma <span class="required">*</span></label>
                        <input type="text" id="new-nom-crono" name="txt_nom_cronograma" class="form-input" placeholder="Ej: Pagos viernes" minlength="3" maxlength="30">
                        <span class="field-error" id="err-new-nombre"></span>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Fecha de Programación <span class="required">*</span></label>
                        <input type="date" id="new-fec-prog" name="txt_fec_programacion" class="form-input" min="<?= date('Y-m-d') ?>">
                        <span class="field-error" id="err-new-fecha"></span>
                    </div>
                </div>

                <h4 class="detail-subheading"><i class="fas fa-list-check"></i> Cuotas Pendientes Disponibles</h4>
                <span class="field-error" id="err-new-cuotas"></span>
                <div id="cuotas-pendientes-list" class="cuota-picker-list">
                    <?php if (empty($cuotas_pendientes)): ?>
                        <p style="font-size:12px;color:#94a3b8;text-align:center;padding:12px">No hay cuotas pendientes disponibles para programar.</p>
                    <?php else: ?>
                        <?php foreach ($cuotas_pendientes as $cu):
                            $clave = $cu['id_factura'] . ':' . $cu['id_cuota'];
                        ?>
                        <label class="cuota-picker-row">
                            <input type="checkbox" class="cuota-checkbox" value="<?= htmlspecialchars($clave) ?>" data-valor="<?= htmlspecialchars($cu['val_cuota']) ?>">
                            <div class="cuota-picker-info">
                                <span class="cuota-picker-prov"><?= htmlspecialchars($cu['nom_tercero']) ?> — Factura #<?= htmlspecialchars($cu['id_factura']) ?>, Cuota <?= htmlspecialchars($cu['id_cuota']) ?></span>
                                <span class="cuota-picker-fecha">Vence: <?= htmlspecialchars(date('d/m/Y', strtotime($cu['fec_vencimiento']))) ?></span>
                            </div>
                            <span class="cuota-picker-valor">$<?= number_format((float)$cu['val_cuota'], 0, ',', '.') ?></span>
                        </label>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
                <div class="cuota-picker-total">
                    <span>Total seleccionado</span>
                    <strong id="crono-total-seleccionado">$0</strong>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel-new-modal">Cancelar</button>
            <button type="button" id="new-btn-save" class="btn btn-primary"><i class="fas fa-save"></i> Guardar Cronograma</button>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR CRONOGRAMA -->
<div id="modal-edit-crono" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header blue">
            <div>
                <h2>Editar Cronograma</h2>
                <p>Modifique el nombre o la fecha programada</p>
            </div>
            <button class="modal-close btn-close-edit-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="edit-crono-form" novalidate>
                <input type="hidden" name="btn_editar" value="1">
                <input type="hidden" name="hid_edit_id" id="edit-crono-id" value="">
                <div class="form-field">
                    <label class="form-label">Nombre del Cronograma <span class="required">*</span></label>
                    <input type="text" id="edit-nom-crono" name="txt_edit_nom_cronograma" class="form-input" minlength="3" maxlength="30">
                    <span class="field-error" id="err-edit-nombre"></span>
                </div>
                <div class="form-field">
                    <label class="form-label">Fecha de Programación <span class="required">*</span></label>
                    <input type="date" id="edit-fec-prog" name="txt_edit_fec_programacion" class="form-input" min="<?= date('Y-m-d') ?>">
                    <span class="field-error" id="err-edit-fecha"></span>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel-edit-modal">Cancelar</button>
            <button type="button" id="edit-btn-save" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar Cronograma</button>
        </div>
    </div>
</div>

<!-- MODAL: DETALLE DE CRONOGRAMA -->
<div id="modal-detail" class="modal-overlay hidden">
    <div class="modal-box" style="max-width:560px">
        <div class="modal-header blue">
            <div><h2 id="detail-title">Detalle de Cronograma</h2><p id="detail-subtitle"></p></div>
            <button class="modal-close" id="close-detail-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="detail-content"></div>
            <h4 class="detail-subheading"><i class="fas fa-list"></i> Cuotas Incluidas</h4>
            <div id="detalle-cuotas-list">
                <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Cargando...</p></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="close-detail-btn">Cerrar</button>
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

<script src="modules/tescxp/js/cronopagos.js"></script>


<?php
$moduleContent = ob_get_clean();
echo $moduleContent;
?>
