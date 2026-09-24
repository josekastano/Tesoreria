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
// RESPUESTA JSON A PRUEBA DE SALIDA ESPURIA
// ============================================================
// Un warning o notice de PHP impreso antes del JSON (por ejemplo "Undefined
// variable") hacía que JSON.parse fallara en el front y que el módulo mostrara
// un mensaje genérico en lugar de la causa real. Todo el bloque POST corre
// dentro de un buffer: lo que se haya impreso de más se descarta del cuerpo y
// se devuelve en el campo debug_salida_previa para poder verlo en la consola.
function responder_json(array $respuesta): void {
    // Se vacían TODOS los buffers abiertos, incluido cualquiera que haya abierto
    // el layout: si quedara uno abierto, PHP lo volcaría al terminar el script y
    // esa salida aparecería después del JSON, rompiendo el parseo igualmente.
    $basura = '';
    while (ob_get_level() > 0) {
        $basura = ((string)ob_get_clean()) . $basura;
    }

    $basura = trim(strip_tags($basura));
    if ($basura !== '' && preg_match('/(fatal|error|warning|notice|deprecated|exception)/i', $basura)) {
        $respuesta['debug_salida_previa'] = mb_substr($basura, 0, 800);
    }

    $json = json_encode($respuesta, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
    if ($json === false) {
        $json = json_encode([
            'success' => false,
            'message' => 'No se pudo serializar la respuesta: ' . json_last_error_msg(),
            'errors'  => [],
        ]);
    }

    echo $json;
    exit;
}

// ============================================================
// RESPUESTA CSV PARA DESCARGA DE ARCHIVOS
// ============================================================
// Igual que responder_json(): se descartan buffers abiertos antes de mandar
// las cabeceras, para que ningún warning/HTML de por medio termine metido
// dentro del archivo CSV que el usuario va a abrir en Excel/el banco.
function responder_csv(string $nombre_archivo, array $filas, array $encabezados_csv): void {
    // El CSV se arma primero en un stream de MEMORIA (php://temp), no directo
    // a la salida real. Así, si PHP imprime un warning/notice/deprecated
    // mientras se genera (p.ej. el aviso de fputcsv() sobre el parámetro
    // $escape en PHP 8.4), ese texto cae en el buffer de salida normal y se
    // descarta más abajo junto con cualquier sobrante del layout, en vez de
    // colarse entre las filas del archivo.
    //
    // $escape se pasa explícito ('\\', el valor histórico por defecto) para
    // no depender del default de fputcsv(): PHP 8.4 marcó como deprecated no
    // pasarlo, y a partir de PHP 9 el default cambia de comportamiento.
    $mem = fopen('php://temp', 'w+');
    fputcsv($mem, $encabezados_csv, ';', '"', '\\');
    foreach ($filas as $fila) {
        fputcsv($mem, $fila, ';', '"', '\\');
    }
    rewind($mem);
    $contenido_csv = stream_get_contents($mem);
    fclose($mem);

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $nombre_archivo . '.csv"');
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: public');

    // BOM UTF-8: sin esto Excel en Windows muestra mal tildes y "ñ".
    echo "\xEF\xBB\xBF" . $contenido_csv;
    exit;
}

// Evita el "Call to a member function execute() on null" cuando una consulta
// preparada no existe en prepare_tescxp.php: lanza una excepción con nombre y
// todo, que sí viaja al front como JSON.
function requerir_stmt($stmt, string $nombre): PDOStatement {
    if (!($stmt instanceof PDOStatement)) {
        throw new Exception('La consulta preparada $' . $nombre . ' no está definida en prepare_tescxp.php (o no es un PDOStatement).');
    }
    return $stmt;
}

// PostgreSQL devuelve los booleanos vía PDO como 't' / 'f'.
function es_verdadero($valor): bool {
    return in_array($valor, ['t', 'T', 'true', 'TRUE', '1', 1, true], true);
}

// ============================================================
// CUOTAS Y CUENTAS DE UN CRONOGRAMA, AGRUPADAS POR PROVEEDOR
// ============================================================
// prepare_tescxp.php ya no expone $list_cuotas_crono_con_cuentas: esa consulta
// se partió en dos ($list_cuotas_de_cronograma, una fila por cuota, y
// $list_ctas_prov_de_cronograma, las cuentas activas de cada proveedor) para
// que las cuotas no se multiplicaran por cada cuenta del proveedor. El cruce
// se hace aquí, en PHP, tal como indica el comentario de esa consulta.
function cargar_proveedores_cronograma(int $id_cronograma, $stmt_cuotas, $stmt_ctas): array {
    $stmt_cuotas = requerir_stmt($stmt_cuotas, 'list_cuotas_de_cronograma');
    $stmt_ctas   = requerir_stmt($stmt_ctas,   'list_ctas_prov_de_cronograma');

    $stmt_cuotas->execute([':wid_cronograma' => $id_cronograma]);
    $cuotas = $stmt_cuotas->fetchAll(PDO::FETCH_ASSOC);

    $stmt_ctas->execute([':wid_cronograma' => $id_cronograma]);
    $cuentas = $stmt_ctas->fetchAll(PDO::FETCH_ASSOC);

    // Cuentas activas indexadas por proveedor
    $ctas_por_prov = [];
    foreach ($cuentas as $c) {
        $ctas_por_prov[(string)$c['id_proveedor']][] = [
            'cta_proveedor'  => $c['cta_proveedor'],
            'id_banco'       => $c['id_banco'],
            'nom_banco'      => $c['nom_banco'],
            'ind_tipocuenta' => es_verdadero($c['ind_tipocuenta']) ? 't' : 'f',
        ];
    }

    // Una entrada por proveedor, con sus cuotas y sus cuentas
    $proveedores = [];
    foreach ($cuotas as $q) {
        $idp = (string)$q['id_proveedor'];
        if (!isset($proveedores[$idp])) {
            $proveedores[$idp] = [
                'id_proveedor' => $q['id_proveedor'],
                'nom_tercero'  => $q['nom_tercero'],
                'cuentas'      => $ctas_por_prov[$idp] ?? [],
                'cuotas'       => [],
                'total'        => 0.0,
            ];
        }
        $proveedores[$idp]['cuotas'][] = [
            'id_factura'  => (int)$q['id_factura'],
            'id_cuota'    => (int)$q['id_cuota'],
            'val_a_pagar' => (float)$q['val_a_pagar'],
        ];
        $proveedores[$idp]['total'] += (float)$q['val_a_pagar'];
    }

    return array_values($proveedores);
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
    ob_start();
    header('Content-Type: application/json');
    $respuesta = ['success' => false, 'message' => '', 'errors' => []];

    try {
        // ---------- OBTENER CUOTAS+CUENTAS DE UN CRONOGRAMA (para armar el form dinámico) ----------
        if (isset($_POST['btn_cargar_crono'])) {
            $id_cronograma = (int)($_POST['hid_id_cronograma'] ?? 0);
            if ($id_cronograma <= 0) {
                throw new Exception('Cronograma no válido.');
            }

            $respuesta['success']     = true;
            $respuesta['proveedores'] = cargar_proveedores_cronograma(
                $id_cronograma,
                $list_cuotas_de_cronograma    ?? null,
                $list_ctas_prov_de_cronograma ?? null
            );
            responder_json($respuesta);
        }

        // ---------- OBTENER DETALLE DE UN ARCHIVO PLANO (para el modal de detalle) ----------
        if (isset($_POST['btn_ver_detalle'])) {
            $id_archivo_plano = (int)($_POST['hid_id_archivo_plano'] ?? 0);
            if ($id_archivo_plano <= 0) {
                throw new Exception('Archivo plano no válido.');
            }

            $stmt_det = requerir_stmt($list_det_archivo_plano ?? null, 'list_det_archivo_plano');
            // El placeholder de $list_det_archivo_plano es :wid_archivo_plano
            // (antes se enviaba :id_archivo_plano y PDO tumbaba la consulta).
            $stmt_det->execute([':wid_archivo_plano' => $id_archivo_plano]);
            $detalle = $stmt_det->fetchAll(PDO::FETCH_ASSOC);

            $respuesta['success'] = true;
            $respuesta['detalle'] = $detalle;
            responder_json($respuesta);
        }

        // ---------- DESCARGAR ARCHIVO PLANO (CSV) ----------
        // Si algo falla acá, la excepción cae en el catch de más abajo y viaja
        // como JSON normal; el front distingue error (JSON) de éxito (CSV) por
        // el Content-Type de la respuesta.
        if (isset($_POST['btn_descargar_archivo_plano'])) {
            $id_archivo_plano = (int)($_POST['hid_id_archivo_plano'] ?? 0);
            if ($id_archivo_plano <= 0) {
                throw new Exception('Archivo plano no válido.');
            }

            $stmt_enc = requerir_stmt($get_enc_archivo_plano ?? null, 'get_enc_archivo_plano');
            $stmt_enc->execute([':wid_archivo_plano' => $id_archivo_plano]);
            $encabezado = $stmt_enc->fetch(PDO::FETCH_ASSOC);
            if (!$encabezado) {
                throw new Exception('El archivo plano solicitado no existe.');
            }

            $stmt_det = requerir_stmt($list_det_archivo_plano ?? null, 'list_det_archivo_plano');
            $stmt_det->execute([':wid_archivo_plano' => $id_archivo_plano]);
            $detalle = $stmt_det->fetchAll(PDO::FETCH_ASSOC);
            if (empty($detalle)) {
                throw new Exception('Este archivo plano no tiene pagos registrados para descargar.');
            }

            $stmt_sum = requerir_stmt($sum_det_archivo_plano ?? null, 'sum_det_archivo_plano');
            $stmt_sum->execute([':wid_archivo_plano' => $id_archivo_plano]);
            $totales = $stmt_sum->fetch(PDO::FETCH_ASSOC) ?: [
                'cant_registros'  => count($detalle),
                'total_a_debitar' => array_sum(array_column($detalle, 'val_a_pagar')),
            ];

            // Se marca como generado la primera vez que se descarga. El
            // COALESCE de la consulta evita pisar fec_generacion si el
            // archivo ya se había descargado antes.
            requerir_stmt($upd_generar_archivo_plano ?? null, 'upd_generar_archivo_plano')
                ->execute([':wid_archivo_plano' => $id_archivo_plano]);

            $filas = [];
            foreach ($detalle as $d) {
                $filas[] = [
                    'DET',
                    $d['id_empresa'],
                    $d['cta_empresa'],
                    $d['id_proveedor'],
                    $d['nom_tercero'],
                    $d['cta_proveedor'],
                    es_verdadero($d['ind_tipocuenta']) ? 'CORRIENTE' : 'AHORROS',
                    $d['id_factura'],
                    $d['id_cuota'],
                    number_format((float)$d['val_a_pagar'], 2, '.', ''),
                ];
            }
            // Registro de control al final: número de pagos y valor total a
            // debitar de la cuenta de la empresa (útil para cuadrar contra
            // el banco antes de subir el archivo).
            $filas[] = [
                'TOTAL', '', '', '', '', '', '',
                (int)$totales['cant_registros'],
                '',
                number_format((float)$totales['total_a_debitar'], 2, '.', ''),
            ];

            $nombre_archivo = preg_replace('/[^A-Za-z0-9_\-]/', '_', (string)$encabezado['nom_archivo']);
            if ($nombre_archivo === '') {
                $nombre_archivo = 'archivo_plano_' . $id_archivo_plano;
            }

            responder_csv($nombre_archivo, $filas, [
                'tipo_registro', 'id_empresa', 'cta_empresa', 'id_proveedor',
                'nombre_proveedor', 'cta_proveedor', 'tipo_cuenta',
                'id_factura', 'id_cuota', 'valor_a_pagar',
            ]);
        }

        // ---------- NUEVO ARCHIVO PLANO ----------
        if (isset($_POST['btn_nuevo'])) {
            $id_cronograma  = (int)($_POST['sel_id_cronograma']  ?? 0);
            $id_banco       = trim($_POST['sel_id_banco']        ?? '');
            $nom_archivo    = trim($_POST['txt_nom_archivo']     ?? '');
            $cta_empresa_combo = trim($_POST['sel_cta_empresa']  ?? '');
            // El front solo manda la CUENTA ELEGIDA POR PROVEEDOR:
            // "id_proveedor:cta_proveedor,...". Las cuotas ya no viajan por el
            // formulario: se leen del cronograma en el servidor, que es la
            // única fuente confiable y hace imposible mandar cuotas repetidas
            // o ajenas al cronograma.
            $ctas_raw = $_POST['hid_ctas_proveedor'] ?? '';

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
            } else {
                // La cuenta de origen puede ser de cualquier banco registrado
                // para la empresa, sin importar el banco destino del archivo.
                // Solo se valida que la combinación empresa+cuenta exista.
                $cta_emp_ok = false;
                foreach ($ctas_empresa_disp as $ce) {
                    if ((string)$ce['id_empresa'] === (string)$id_empresa
                        && (string)$ce['cta_empresa'] === (string)$cta_empresa) {
                        $cta_emp_ok = true;
                        break;
                    }
                }
                if (!$cta_emp_ok) {
                    $errores['err-new-cuenta-empresa'] = 'La cuenta de la empresa seleccionada no está registrada.';
                }
            }

            // Cuenta elegida por el usuario para cada proveedor
            $cta_elegida = [];
            foreach (explode(',', (string)$ctas_raw) as $par) {
                $partes = explode(':', $par);
                if (count($partes) === 2) {
                    $cta_elegida[trim($partes[0])] = trim($partes[1]);
                }
            }

            // ---------------------------------------------------------------
            // ARMAR EL DETALLE EN EL SERVIDOR
            // ---------------------------------------------------------------
            // Una fila por cuota del cronograma. La cuenta del proveedor es la
            // que eligió el usuario, validada contra las cuentas activas del
            // proveedor; si no eligió (o mandó una que ya no está activa) se
            // usa la primera cuenta activa. Así la generación funciona aunque
            // el JS falle, y el ind_tipocuenta sale de la base, no del front.
            $filas      = [];
            $sin_cuenta = [];

            if ($id_cronograma > 0) {
                $proveedores = cargar_proveedores_cronograma(
                    $id_cronograma,
                    $list_cuotas_de_cronograma    ?? null,
                    $list_ctas_prov_de_cronograma ?? null
                );

                foreach ($proveedores as $prov) {
                    if (empty($prov['cuentas'])) {
                        $sin_cuenta[] = $prov['nom_tercero'];
                        continue;
                    }

                    $idp    = (string)$prov['id_proveedor'];
                    $cuenta = null;
                    if (isset($cta_elegida[$idp])) {
                        foreach ($prov['cuentas'] as $c) {
                            if ((string)$c['cta_proveedor'] === $cta_elegida[$idp]) {
                                $cuenta = $c;
                                break;
                            }
                        }
                    }
                    if ($cuenta === null) {
                        $cuenta = $prov['cuentas'][0];
                    }

                    foreach ($prov['cuotas'] as $q) {
                        $filas[] = [
                            'id_proveedor'   => $prov['id_proveedor'],
                            'cta_proveedor'  => $cuenta['cta_proveedor'],
                            'ind_tipocuenta' => $cuenta['ind_tipocuenta'],
                            'id_factura'     => $q['id_factura'],
                            'id_cuota'       => $q['id_cuota'],
                        ];
                    }
                }

                if (!empty($sin_cuenta)) {
                    $errores['err-new-cronograma'] = 'Sin cuenta bancaria activa: ' . implode(', ', $sin_cuenta)
                        . '. Regístrela en Bancos por Proveedor antes de generar el archivo.';
                } elseif (empty($filas)) {
                    $errores['err-new-cronograma'] = 'El cronograma seleccionado no tiene cuotas para pagar.';
                }
            }

            if (!empty($errores)) {
                $respuesta['errors'] = $errores;
                responder_json($respuesta);
            }

            $stmt_ins_enc = requerir_stmt($ins_enc_archivo_plano ?? null, 'ins_enc_archivo_plano');
            $stmt_ins_det = requerir_stmt($ins_det_archivo_plano ?? null, 'ins_det_archivo_plano');

            $pdo->beginTransaction();

            $stmt_ins_enc->execute([
                ':wid_cronograma'    => $id_cronograma,
                ':wid_banco'         => $id_banco,
                ':wnom_archivo'      => $nom_archivo,
            ]);

            // Recuperar el id del archivo plano recién creado. Se filtra por
            // cronograma + nombre en lugar de un MAX() global: si otro usuario
            // genera un archivo al mismo tiempo, el MAX() suelto podía devolver
            // el id ajeno y el detalle se colgaba del archivo equivocado.
            $sel_id_nuevo = $pdo->prepare(
                "SELECT MAX(id_archivo_plano)
                   FROM tab_enc_archivo_plano
                  WHERE id_cronograma = :id_cronograma
                    AND nom_archivo   = :nom_archivo"
            );
            $sel_id_nuevo->execute([
                ':id_cronograma' => $id_cronograma,
                ':nom_archivo'   => $nom_archivo,
            ]);
            $id_nuevo = (int)$sel_id_nuevo->fetchColumn();

            if ($id_nuevo <= 0) {
                throw new Exception('No se pudo recuperar el encabezado del archivo plano recién creado.');
            }

            foreach ($filas as $fila) {
                $stmt_ins_det->execute([
                    ':wid_archivo_plano' => $id_nuevo,
                    ':wid_empresa'       => $id_empresa,
                    ':wcta_empresa'      => $cta_empresa,
                    ':wid_proveedor'     => $fila['id_proveedor'],
                    ':wcta_proveedor'    => $fila['cta_proveedor'],
                    // Faltaba: $ins_det_archivo_plano declara 8 placeholders y
                    // ind_tipocuenta es NOT NULL en tab_det_archivo_plano.
                    ':wind_tipocuenta'   => $fila['ind_tipocuenta'],
                    ':wid_factura'       => $fila['id_factura'],
                    ':wid_cuota'         => $fila['id_cuota'],
                ]);
            }

            $pdo->commit();

            $respuesta['success'] = true;
            $respuesta['message'] = 'Archivo plano generado correctamente con ' . count($filas) . ' registro(s) de pago.';
            responder_json($respuesta);
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
                responder_json($respuesta);
            }

            requerir_stmt($upd_enc_archivo_plano ?? null, 'upd_enc_archivo_plano')->execute([
                ':wid_archivo_plano' => $id_archivo_plano,
                ':wid_banco'         => $id_banco,
                ':wnom_archivo'      => $nom_archivo,
            ]);

            $respuesta['success'] = true;
            $respuesta['message'] = 'Archivo plano actualizado correctamente.';
            responder_json($respuesta);
        }

        // Si no se reconoce ninguna acción
        $respuesta['message'] = 'Acción no válida.';
        responder_json($respuesta);

    } catch (Throwable $e) {
        // Throwable y no Exception: un TypeError o un Error de PHP también debe
        // salir como JSON, o el front recibe HTML y no puede mostrar la causa.
        if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $mensaje = limpiar_error_pgsql($e->getMessage());
        if (empty($mensaje)) {
            $mensaje = $e->getMessage();
        }
        $respuesta['message']      = $mensaje;
        $respuesta['debug_origen'] = basename($e->getFile()) . ':' . $e->getLine();
        responder_json($respuesta);
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
        $generados  = count(array_filter($archivos, fn($a) => es_verdadero($a['ind_generado'])));
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
        <div class="pagination-size">
            <label for="archivos-page-size">Filas por página</label>
            <select id="archivos-page-size">
                <option value="10">10</option>
                <option value="25" selected>25</option>
                <option value="50">50</option>
                <option value="all">Todos</option>
            </select>
        </div>
        <span class="filter-info" id="archivos-count"><?= $total ?> resultado<?= $total !== 1 ? 's' : '' ?></span>
    </div>

    <!-- TABLA -->
    <div class="table-container">
      <div class="table-scroll" id="archivos-table-scroll">
        <table class="data-table">
            <!-- Anchos fijos: las columnas no se mueven al ordenar o cambiar de página -->
            <colgroup>
                <col class="col-archivo">
                <col class="col-cronograma">
                <col class="col-banco">
                <col class="col-generacion">
                <col class="col-estado">
                <col class="col-acciones">
            </colgroup>
            <thead>
                <tr>
                    <th class="sortable" data-sort-key="0" data-sort-type="text">Archivo <i class="fas fa-caret-down sort-icon"></i></th>
                    <th class="sortable" data-sort-key="1" data-sort-type="text">Cronograma <i class="fas fa-caret-down sort-icon"></i></th>
                    <th class="sortable" data-sort-key="2" data-sort-type="text">Banco Destino <i class="fas fa-caret-down sort-icon"></i></th>
                    <th class="sortable" data-sort-key="3" data-sort-type="text">Generación <i class="fas fa-caret-down sort-icon"></i></th>
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
                    $generado    = es_verdadero($a['ind_generado']);
                    $badge       = $generado
                        ? '<span class="badge badge-active">Generado</span>'
                        : '<span class="badge badge-inactive" style="background:#fffbeb;color:#f59e0b">Por generar</span>';
                    $filtro      = $generado ? 'generado' : 'pendiente';
                    $id_esc      = htmlspecialchars($a['id_archivo_plano']);
                    $fecha_gen   = $a['fec_generacion'] ? date('d/m/Y', strtotime($a['fec_generacion'])) : '—';
                ?>
                <tr data-estado="<?= $filtro ?>" onclick='openDetailModal(<?= json_encode($a) ?>)'>
                    <td data-sort="<?= htmlspecialchars($a['nom_archivo']) ?>">
                        <strong><?= htmlspecialchars($a['nom_archivo']) ?></strong><br>
                        <small style="color:#94a3b8;font-size:11px">#<?= $id_esc ?></small>
                    </td>
                    <td data-sort="<?= htmlspecialchars($a['nom_cronograma']) ?>"><?= htmlspecialchars($a['nom_cronograma']) ?></td>
                    <td data-sort="<?= htmlspecialchars($a['nom_banco']) ?>"><?= htmlspecialchars($a['nom_banco']) ?></td>
                    <td data-sort="<?= $a['fec_generacion'] ? htmlspecialchars(date('Y-m-d', strtotime($a['fec_generacion']))) : '' ?>"><?= htmlspecialchars($fecha_gen) ?></td>
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

        <!-- PAGINACIÓN -->
        <div class="table-footer">
            <div class="pagination-nav">
                <span class="pagination-range" id="archivos-range">0 de 0</span>
                <button type="button" id="archivos-prev" class="pagination-btn" disabled aria-label="Página anterior">
                    <i class="fas fa-chevron-left"></i>
                </button>
                <button type="button" id="archivos-next" class="pagination-btn" disabled aria-label="Página siguiente">
                    <i class="fas fa-chevron-right"></i>
                </button>
            </div>
        </div>
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
                <input type="hidden" name="hid_ctas_proveedor" id="hid-ctas-proveedor" value="">

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
                                $tipoTxt = es_verdadero($ce['ind_tipocuenta']) ? 'Corriente' : 'Ahorros';
                            ?>
                            <option value="<?= htmlspecialchars($ce['id_empresa']) ?>:<?= htmlspecialchars($ce['cta_empresa']) ?>"
                                    data-id-banco="<?= htmlspecialchars($ce['id_banco']) ?>">
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
