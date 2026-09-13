
<?php
// ========== PERSONALIZAR PÁGINA ==========
$pageTitle        = 'ERP ADSO — Tesorería';
$activeModule     = 'tescxp';
$page_title       = "ADSOERP | Archivo Plano";
$page_description = "Generación del archivo plano para pago de cronogramas al banco";
$page_icon        = "bi-file-earmark-spreadsheet";
$page_extra_css   = ["../modules/tescxp/css/archivo_plano.css"];
$page_extra_js    = ["../modules/tescxp/js/archivo_plano.js"];
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

$list_ctas_empresa->execute();
$ctas_empresa_disp = $list_ctas_empresa->fetchAll(PDO::FETCH_ASSOC);

$list_cronogramas_pendientes->execute();
$cronogramas_pend = $list_cronogramas_pendientes->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// MANEJO DE PETICIONES POST (SIEMPRE RESPONDEN CON JSON)
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $respuesta = ['success' => false, 'message' => '', 'errors' => []];

    try {
        // ---------- OBTENER CUOTAS+CUENTAS DE UN CRONOGRAMA (para armar el form dinámico) ----------
        if (isset($_POST['btn_cargar_crono'])) {
            $id_cronograma = (int)($_POST['hid_id_cronograma'] ?? 0);
            if ($id_cronograma <= 0) {
                throw new Exception('Cronograma no válido.');
            }

            $list_cuotas_crono_con_cuentas->execute([':id_cronograma' => $id_cronograma]);
            $filas = $list_cuotas_crono_con_cuentas->fetchAll(PDO::FETCH_ASSOC);

            $respuesta['success'] = true;
            $respuesta['filas']   = $filas;
            echo json_encode($respuesta);
            exit;
        }

        // ---------- OBTENER DETALLE DE UN ARCHIVO PLANO (para el modal de detalle) ----------
        if (isset($_POST['btn_ver_detalle'])) {
            $id_archivo_plano = (int)($_POST['hid_id_archivo_plano'] ?? 0);
            if ($id_archivo_plano <= 0) {
                throw new Exception('Archivo plano no válido.');
            }

            $list_det_archivo_plano->execute([':id_archivo_plano' => $id_archivo_plano]);
            $detalle = $list_det_archivo_plano->fetchAll(PDO::FETCH_ASSOC);

            $respuesta['success'] = true;
            $respuesta['detalle'] = $detalle;
            echo json_encode($respuesta);
            exit;
        }

        // ---------- NUEVO ARCHIVO PLANO ----------
        if (isset($_POST['btn_nuevo'])) {
            $id_cronograma  = (int)($_POST['sel_id_cronograma']  ?? 0);
            $id_banco       = trim($_POST['sel_id_banco']        ?? '');
            $nom_archivo    = trim($_POST['txt_nom_archivo']     ?? '');
            $cta_empresa_combo = trim($_POST['sel_cta_empresa']  ?? '');
            $cuotas_raw     = $_POST['hid_filas_archivo']        ?? '';

            $errores = [];

            if ($id_cronograma <= 0) {
                $errores['err-new-cronograma'] = 'Seleccione un cronograma.';
            }
            if (empty($id_banco)) {
                $errores['err-new-banco'] = 'Seleccione el banco destino del archivo.';
            }
            if (strlen($nom_archivo) < 3 || strlen($nom_archivo) > 30) {
                $errores['err-new-nombre'] = 'El nombre debe tener entre 3 y 30 caracteres.';
            }

            // El combo de cuenta de empresa viene como "id_empresa:cta_empresa"
            $id_empresa  = '';
            $cta_empresa = '';
            if (!empty($cta_empresa_combo) && strpos($cta_empresa_combo, ':') !== false) {
                [$id_empresa, $cta_empresa] = explode(':', $cta_empresa_combo, 2);
            }
            if (empty($id_empresa) || empty($cta_empresa)) {
                $errores['err-new-cuenta-empresa'] = 'Seleccione la cuenta de la empresa de origen.';
            }

            // Decodificar filas: "id_factura:id_cuota:id_proveedor:cta_proveedor:tipocuenta,..."
            // Se deduplica por (id_factura, id_cuota) como defensa adicional: un
            // proveedor con varias cuentas registradas puede hacer que el front
            // envíe la misma cuota más de una vez, y eso rompería el insert de
            // detalle (choca contra la PK compuesta del mismo archivo plano).
            $filas = [];
            $cuotas_vistas = [];
            if (!empty($cuotas_raw)) {
                foreach (explode(',', $cuotas_raw) as $fila) {
                    $partes = explode(':', $fila);
                    if (count($partes) === 5) {
                        [$idf, $idc, $idprov, $ctaprov, $tipo] = $partes;
                        $clave_cuota = $idf . ':' . $idc;
                        if (isset($cuotas_vistas[$clave_cuota])) {
                            continue;
                        }
                        $cuotas_vistas[$clave_cuota] = true;
                        $filas[] = [
                            'id_factura'     => (int)$idf,
                            'id_cuota'       => (int)$idc,
                            'id_proveedor'   => $idprov,
                            'cta_proveedor'  => $ctaprov,
                            'ind_tipocuenta' => $tipo === 'true' ? 'true' : 'false',
                        ];
                    }
                }
            }
            if (empty($filas)) {
                $errores['err-new-cronograma'] = 'El cronograma seleccionado no tiene cuotas con cuentas resueltas.';
            }

            if (!empty($errores)) {
                $respuesta['errors'] = $errores;
                echo json_encode($respuesta);
                exit;
            }

            $pdo->beginTransaction();

            $ins_enc_archivo_plano->execute([
                ':wid_cronograma'    => $id_cronograma,
                ':wid_banco'         => $id_banco,
                ':wnom_archivo'      => $nom_archivo,
            ]);

            // Recuperar el id del archivo plano recién creado
            $id_nuevo = (int)$pdo->query("SELECT MAX(id_archivo_plano) FROM tab_enc_archivo_plano")->fetchColumn();

            foreach ($filas as $fila) {
                $ins_det_archivo_plano->execute([
                    ':wid_archivo_plano' => $id_nuevo,
                    ':wid_empresa'       => $id_empresa,
                    ':wcta_empresa'      => $cta_empresa,
                    ':wid_proveedor'     => $fila['id_proveedor'],
                    ':wcta_proveedor'    => $fila['cta_proveedor'],
                    ':wid_factura'       => $fila['id_factura'],
                    ':wid_cuota'         => $fila['id_cuota'],
                ]);
            }

            $pdo->commit();

            $respuesta['success'] = true;
            $respuesta['message'] = 'Archivo plano generado correctamente con ' . count($filas) . ' registro(s) de pago.';
            echo json_encode($respuesta);
            exit;
        }

        // ---------- EDITAR ARCHIVO PLANO (banco y nombre) ----------
        if (isset($_POST['btn_editar'])) {
            $id_archivo_plano = (int)($_POST['hid_edit_id']         ?? 0);
            $id_banco          = trim($_POST['sel_edit_id_banco']    ?? '');
            $nom_archivo       = trim($_POST['txt_edit_nom_archivo'] ?? '');

            $errores = [];

            if ($id_archivo_plano <= 0) {
                $errores['err-edit-nombre'] = 'Archivo plano no válido.';
            }
            if (empty($id_banco)) {
                $errores['err-edit-banco'] = 'Seleccione un banco.';
            }
            if (strlen($nom_archivo) < 3 || strlen($nom_archivo) > 30) {
                $errores['err-edit-nombre'] = 'El nombre debe tener entre 3 y 30 caracteres.';
            }

            if (!empty($errores)) {
                $respuesta['errors'] = $errores;
                echo json_encode($respuesta);
                exit;
            }

            $upd_enc_archivo_plano->execute([
                ':wid_archivo_plano' => $id_archivo_plano,
                ':wid_banco'         => $id_banco,
                ':wnom_archivo'      => $nom_archivo,
            ]);

            $respuesta['success'] = true;
            $respuesta['message'] = 'Archivo plano actualizado correctamente.';
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
// CARGAR TABLA (SOLO PARA LA VISTA INICIAL)
// ============================================================
$list_enc_archivo_plano->execute();
$archivos = $list_enc_archivo_plano->fetchAll(PDO::FETCH_ASSOC);

// ============================================================
// INICIO DEL HTML
// ============================================================
ob_start();
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
<link rel="stylesheet" href="modules/tescxp/css/archivo_plano.css">
<div id="mod-archivo-plano" class="app-view active">

    <!-- ENCABEZADO -->
    <div class="module-header">
        <div class="module-header-text">
            <h1>Archivo Plano</h1>
            <p>Genere el archivo de pagos para enviar al banco a partir de un cronograma</p>
        </div>
        <button id="btn-add-archivo" class="btn btn-primary">
            <i class="fas fa-plus"></i> Nuevo Archivo Plano
        </button>
    </div>

    <!-- STATS -->
    <div class="stats-grid">
        <?php
        $total      = count($archivos);
        $generados  = count(array_filter($archivos, fn($a) => $a['ind_generado'] === 't' || $a['ind_generado'] === true));
        $pendientes = $total - $generados;
        ?>
        <div class="stat-card">
            <div class="stat-icon blue"><i class="fas fa-file-invoice"></i></div>
            <div class="stat-info">
                <div class="stat-label">Total archivos</div>
                <div class="stat-value" id="stat-total"><?= $total ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon green"><i class="fas fa-check-circle"></i></div>
            <div class="stat-info">
                <div class="stat-label">Generados</div>
                <div class="stat-value" id="stat-generados"><?= $generados ?></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon yellow"><i class="fas fa-clock"></i></div>
            <div class="stat-info">
                <div class="stat-label">Por generar</div>
                <div class="stat-value" id="stat-pendientes"><?= $pendientes ?></div>
            </div>
        </div>
    </div>

    <!-- FILTROS -->
    <div class="filter-bar">
        <div class="search-wrapper">
            <span class="search-icon"><i class="fas fa-search"></i></span>
            <input type="text" id="archivo-search" class="search-input" placeholder="Buscar por nombre o cronograma...">
        </div>
        <div class="filter-toggle-group">
            <button class="filter-toggle active" data-filter="all">Todos</button>
            <button class="filter-toggle" data-filter="generado">Generados</button>
            <button class="filter-toggle" data-filter="pendiente">Por generar</button>
        </div>
        <button id="btn-clear-filters" class="btn-clear-filter" style="display:none">
            <i class="fas fa-times"></i> Limpiar
        </button>
        <span class="filter-info" id="archivos-count"><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <!-- TABLA -->
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th>Cronograma</th>
                    <th>Banco Destino</th>
                    <th>Generación</th>
                    <th class="text-center">Estado</th>
                    <th class="text-center">Acciones</th>
                </tr>
            </thead>
            <tbody id="archivos-tbody">
            <?php if (empty($archivos)): ?>
                <tr class="empty-row">
                    <td colspan="6">
                        <div class="empty-state">
                            <i class="fas fa-file-invoice"></i>
                            <p>No hay archivos planos generados</p>
                            <span>Haga clic en "Nuevo Archivo Plano" para crear el primero</span>
                        </div>
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($archivos as $a):
                    $generado    = ($a['ind_generado'] === 't' || $a['ind_generado'] === true);
                    $badge       = $generado
                        ? '<span class="badge badge-active">Generado</span>'
                        : '<span class="badge badge-inactive" style="background:#fffbeb;color:#f59e0b">Por generar</span>';
                    $filtro      = $generado ? 'generado' : 'pendiente';
                    $id_esc      = htmlspecialchars($a['id_archivo_plano']);
                    $fecha_gen   = $a['fec_generacion'] ? date('d/m/Y', strtotime($a['fec_generacion'])) : '—';
                ?>
                <tr data-estado="<?= $filtro ?>" onclick='openDetailModal(<?= json_encode($a) ?>)'>
                    <td>
                        <strong><?= htmlspecialchars($a['nom_archivo']) ?></strong><br>
                        <small style="color:#94a3b8;font-size:11px">#<?= $id_esc ?></small>
                    </td>
                    <td><?= htmlspecialchars($a['nom_cronograma']) ?></td>
                    <td><?= htmlspecialchars($a['nom_banco']) ?></td>
                    <td><?= htmlspecialchars($fecha_gen) ?></td>
                    <td class="text-center"><?= $badge ?></td>
                    <td class="text-center" onclick="event.stopPropagation()">
                        <button class="btn-icon-sm view" onclick="descargarArchivoPlano('<?= $id_esc ?>')" title="Descargar CSV">
                            <i class="fas fa-download"></i>
                        </button>
                        <button class="btn-icon-sm edit" onclick='openEditModal(<?= json_encode($a) ?>)'>
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

<!-- MODAL: NUEVO ARCHIVO PLANO -->
<div id="modal-new-archivo" class="modal-overlay hidden">
    <div class="modal-box" style="max-width:640px">
        <div class="modal-header green">
            <div>
                <h2>Nuevo Archivo Plano</h2>
                <p>Genere el archivo de pagos a partir de un cronograma</p>
            </div>
            <button class="modal-close btn-close-new-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="new-archivo-form" novalidate>
                <input type="hidden" name="btn_nuevo" value="1">
                <input type="hidden" name="hid_filas_archivo" id="hid-filas-archivo" value="">

                <div class="form-field">
                    <label class="form-label">Cronograma Pendiente <span class="required">*</span></label>
                    <select id="new-id-cronograma" class="form-select" name="sel_id_cronograma">
                        <option value="">Seleccione...</option>
                        <?php foreach ($cronogramas_pend as $cr): ?>
                            <option value="<?= htmlspecialchars($cr['id_cronograma']) ?>">
                                <?= htmlspecialchars($cr['nom_cronograma']) ?> — <?= htmlspecialchars(date('d/m/Y', strtotime($cr['fec_programacion']))) ?> — $<?= number_format((float)$cr['total_a_pagar'], 0, ',', '.') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" id="err-new-cronograma"></span>
                </div>

                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label">Banco Destino del Archivo <span class="required">*</span></label>
                        <select id="new-id-banco" name="sel_id_banco" class="form-select">
                            <option value="">Seleccione...</option>
                            <?php foreach ($bancos as $b): ?>
                                <option value="<?= htmlspecialchars($b['id_banco']) ?>"><?= htmlspecialchars($b['nom_banco']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span class="field-error" id="err-new-banco"></span>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Nombre del Archivo <span class="required">*</span></label>
                        <input type="text" id="new-nom-archivo" name="txt_nom_archivo" class="form-input" placeholder="Ej: pagos_julio_bancolombia" minlength="3" maxlength="30">
                        <span class="field-error" id="err-new-nombre"></span>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label">Cuenta de Origen (Empresa) <span class="required">*</span></label>
                    <select id="new-cta-empresa" name="sel_cta_empresa" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach ($ctas_empresa_disp as $ce): ?>
                            <?php
                                $tipoTxt = ($ce['ind_tipocuenta'] === 't' || $ce['ind_tipocuenta'] === true) ? 'Corriente' : 'Ahorros';
                            ?>
                            <option value="<?= htmlspecialchars($ce['id_empresa']) ?>:<?= htmlspecialchars($ce['cta_empresa']) ?>">
                                <?= htmlspecialchars($ce['nom_banco']) ?> — <?= htmlspecialchars($ce['cta_empresa']) ?> (<?= $tipoTxt ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" id="err-new-cuenta-empresa"></span>
                </div>

                <h4 class="detail-subheading"><i class="fas fa-university"></i> Cuentas de Proveedores</h4>
                <p class="config-hint" id="prov-cuentas-hint">Seleccione primero un cronograma para ver los proveedores a pagar.</p>
                <div id="prov-cuentas-list" class="cuota-picker-list" style="display:none"></div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel-new-modal">Cancelar</button>
            <button type="button" id="new-btn-save" class="btn btn-primary"><i class="fas fa-save"></i> Generar Archivo Plano</button>
        </div>
    </div>
</div>

<!-- MODAL: EDITAR ARCHIVO PLANO -->
<div id="modal-edit-archivo" class="modal-overlay hidden">
    <div class="modal-box">
        <div class="modal-header blue">
            <div>
                <h2>Editar Archivo Plano</h2>
                <p>Modifique el banco destino o el nombre</p>
            </div>
            <button class="modal-close btn-close-edit-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <form id="edit-archivo-form" novalidate>
                <input type="hidden" name="btn_editar" value="1">
                <input type="hidden" name="hid_edit_id" id="edit-archivo-id" value="">
                <div class="form-field">
                    <label class="form-label">Banco Destino <span class="required">*</span></label>
                    <select id="edit-id-banco" name="sel_edit_id_banco" class="form-select">
                        <option value="">Seleccione...</option>
                        <?php foreach ($bancos as $b): ?>
                            <option value="<?= htmlspecialchars($b['id_banco']) ?>"><?= htmlspecialchars($b['nom_banco']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <span class="field-error" id="err-edit-banco"></span>
                </div>
                <div class="form-field">
                    <label class="form-label">Nombre del Archivo <span class="required">*</span></label>
                    <input type="text" id="edit-nom-archivo" name="txt_edit_nom_archivo" class="form-input" minlength="3" maxlength="30">
                    <span class="field-error" id="err-edit-nombre"></span>
                </div>
            </form>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary btn-cancel-edit-modal">Cancelar</button>
            <button type="button" id="edit-btn-save" class="btn btn-primary"><i class="fas fa-save"></i> Actualizar Archivo</button>
        </div>
    </div>
</div>

<!-- MODAL: DETALLE DE ARCHIVO PLANO -->
<div id="modal-detail" class="modal-overlay hidden">
    <div class="modal-box" style="max-width:600px">
        <div class="modal-header blue">
            <div><h2 id="detail-title">Detalle de Archivo Plano</h2><p id="detail-subtitle"></p></div>
            <button class="modal-close" id="close-detail-modal"><i class="fas fa-times"></i></button>
        </div>
        <div class="modal-body">
            <div id="detail-content"></div>
            <h4 class="detail-subheading"><i class="fas fa-list"></i> Pagos Incluidos</h4>
            <div id="detalle-pagos-list">
                <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Cargando...</p></div>
            </div>
        </div>
        <div class="modal-footer">
            <button class="btn btn-secondary" id="close-detail-btn">Cerrar</button>
            <button type="button" id="detail-btn-download" class="btn btn-primary"><i class="fas fa-download"></i> Descargar CSV</button>
        </div>
    </div>
</div>

<!-- TOAST -->
<div id="toast" class="hidden"><span id="toast-message"></span></div>

<!-- SCRIPTS JS -->
<script src="modules/tescxp/js/archivo_plano.js"></script>

<?php
$moduleContent = ob_get_clean();
echo $moduleContent;
?>
