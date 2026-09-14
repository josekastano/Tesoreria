'use strict';

// ============================================================
// 0. AISLAMIENTO DEL MÓDULO
// ============================================================
// Este archivo se referencia dos veces desde archivo_plano.php
// ($page_extra_js con "../modules/..." y el <script> del final con
// "modules/..."). Si ambas rutas resuelven, la segunda copia moría con
// "Identifier 'proveedoresCronograma' has already been declared" y ensuciaba
// la consola. Al encerrar todo en una IIFE cada copia tiene su propio ámbito,
// y el guard de initArchivoModule() evita que se registren los listeners dos
// veces. Las funciones que se usan desde atributos onclick del HTML se siguen
// exponiendo explícitamente en window (ver más abajo).
(function () {

// ============================================================
// 1. HELPERS GENERALES
// ============================================================
function val(id)           { return document.getElementById(id)?.value ?? ''; }
function setVal(id, value) { const el = document.getElementById(id); if (el) el.value = value ?? ''; }
function setText(id, text) { const el = document.getElementById(id); if (el) el.textContent = text ?? ''; }
function show(id)          { document.getElementById(id)?.classList.remove('hidden'); }
function hide(id)          { document.getElementById(id)?.classList.add('hidden'); }

function showFieldError(spanId, mensaje, autoHideMs = 4000) {
    const el = document.getElementById(spanId);
    if (!el) return;
    el.textContent = mensaje;
    el.classList.add('visible');
    clearTimeout(el._hideTimer);
    el._hideTimer = setTimeout(() => {
        el.classList.remove('visible');
        setTimeout(() => { el.textContent = ''; }, 300);
    }, autoHideMs);
}

function clearAllFieldErrors(prefix) {
    document.querySelectorAll(`[id^="err-${prefix}-"]`).forEach(el => {
        clearTimeout(el._hideTimer);
        el.classList.remove('visible');
        el.textContent = '';
    });
}

function showToast(message, type = 'success') {
    const toast = document.getElementById('toast');
    const span  = document.getElementById('toast-message');
    if (!toast || !span) return;
    const text = message && message.trim() ? message : (type === 'success' ? 'Operación exitosa' : 'Ocurrió un error');
    span.textContent = text;
    toast.style.background = type === 'error' ? '#ef4444' : '#0f172a';
    toast.classList.remove('hidden');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.add('hidden'), 3500);
}

function formatCurrency(n) { return '$' + Number(n || 0).toLocaleString('es-CO'); }

function formatDate(isoStr) {
    if (!isoStr) return '—';
    const [y, m, d] = isoStr.split('-');
    return `${d}/${m}/${y}`;
}

function escapeHtml(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;');
}

// ============================================================
// 2. CARGA DINÁMICA DE PROVEEDORES/CUENTAS AL ELEGIR CRONOGRAMA
// ============================================================
// El backend ya no devuelve una fila por (cuota x cuenta): devuelve un arreglo
// de proveedores, cada uno con sus cuotas y sus cuentas bancarias activas. El
// front solo tiene que elegir una cuenta por proveedor.
let proveedoresCronograma = [];

function tipoCuentaTxt(valor) {
    return (valor === 't' || valor === true || valor === 'true' || valor === '1') ? 'Corriente' : 'Ahorros';
}

function onCronogramaChange() {
    const idCronograma = val('new-id-cronograma');
    const hint = document.getElementById('prov-cuentas-hint');
    const list = document.getElementById('prov-cuentas-list');

    proveedoresCronograma = [];
    setVal('hid-ctas-proveedor', '');
    if (list) { list.style.display = 'none'; list.innerHTML = ''; }

    if (!idCronograma) {
        if (hint) { hint.style.display = ''; hint.textContent = 'Seleccione primero un cronograma para ver los proveedores a pagar.'; }
        return;
    }

    if (hint) { hint.style.display = ''; hint.textContent = 'Cargando proveedores del cronograma...'; }

    const formData = new FormData();
    formData.append('btn_cargar_crono', '1');
    formData.append('hid_id_cronograma', idCronograma);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.text())
        .then(text => {
            let result;
            try {
                result = JSON.parse(text);
            } catch (e) {
                console.error('[archivo_plano] Respuesta no JSON en btn_cargar_crono:', text);
                result = { success: false, message: 'El servidor no devolvió JSON (revise la consola).' };
            }

            if (!result.success) {
                console.error('[archivo_plano] btn_cargar_crono falló:', result.message || text);
                if (result.debug_origen)        console.error('[archivo_plano] origen:', result.debug_origen);
                if (result.debug_salida_previa) console.error('[archivo_plano] salida previa del servidor:', result.debug_salida_previa);
                if (hint) {
                    hint.style.display = '';
                    hint.textContent = 'Error al cargar el cronograma: ' + (result.message || 'sin detalle del servidor.');
                }
                return;
            }

            if (!Array.isArray(result.proveedores) || result.proveedores.length === 0) {
                if (hint) {
                    hint.style.display = '';
                    hint.textContent = 'Este cronograma no tiene cuotas asociadas.';
                }
                return;
            }

            proveedoresCronograma = result.proveedores;
            renderProveedorCuentas(proveedoresCronograma);
        })
        .catch(err => {
            console.error('[archivo_plano] Error de red en btn_cargar_crono:', err);
            if (hint) { hint.style.display = ''; hint.textContent = 'Error de conexión al cargar el cronograma.'; }
        });
}

function renderProveedorCuentas(proveedores) {
    const hint = document.getElementById('prov-cuentas-hint');
    const list = document.getElementById('prov-cuentas-list');
    if (!list) return;

    const sinCuenta = proveedores.filter(p => !p.cuentas || p.cuentas.length === 0);

    if (hint) {
        if (sinCuenta.length > 0) {
            hint.style.display = '';
            hint.textContent = sinCuenta.length + ' proveedor(es) sin cuenta bancaria activa. Regístrela en Bancos por Proveedor para poder generar el archivo.';
        } else {
            hint.style.display = 'none';
        }
    }

    list.style.display = '';
    list.innerHTML = proveedores.map(p => {
        const cuentas     = p.cuentas || [];
        const cuotasTexto = (p.cuotas || []).map(c => `Fact. #${c.id_factura} Cta. ${c.id_cuota}`).join(', ');

        let selectorHtml;
        if (cuentas.length === 0) {
            selectorHtml = '<span class="field-error visible">Sin cuenta bancaria activa registrada</span>';
        } else if (cuentas.length === 1) {
            const c = cuentas[0];
            selectorHtml = `<span class="cuota-picker-fecha">${escapeHtml(c.nom_banco)} — ${escapeHtml(c.cta_proveedor)} (${tipoCuentaTxt(c.ind_tipocuenta)})</span>`;
        } else {
            selectorHtml = `
                <select class="form-select prov-cta-select" data-id-prov="${escapeHtml(p.id_proveedor)}" style="margin-top:4px">
                    ${cuentas.map(c => `<option value="${escapeHtml(c.cta_proveedor)}">${escapeHtml(c.nom_banco)} — ${escapeHtml(c.cta_proveedor)} (${tipoCuentaTxt(c.ind_tipocuenta)})</option>`).join('')}
                </select>
            `;
        }

        return `
            <div class="cuota-picker-row" style="cursor:default" data-id-prov="${escapeHtml(p.id_proveedor)}">
                <i class="fas fa-building" style="color:#94a3b8"></i>
                <div class="cuota-picker-info">
                    <span class="cuota-picker-prov">${escapeHtml(p.nom_tercero)}</span>
                    <span class="cuota-picker-fecha">${escapeHtml(cuotasTexto)}</span>
                    ${selectorHtml}
                </div>
                <span class="cuota-picker-valor">${formatCurrency(p.total)}</span>
            </div>
        `;
    }).join('');

    list.querySelectorAll('.prov-cta-select').forEach(sel => {
        sel.addEventListener('change', buildCtasProveedorPayload);
    });

    buildCtasProveedorPayload();
}

function buildCtasProveedorPayload() {
    // Construye "id_proveedor:cta_proveedor,...". Las cuotas ya no se envían:
    // el servidor las lee del cronograma.
    const elegidas = {};
    document.querySelectorAll('#prov-cuentas-list .prov-cta-select').forEach(sel => {
        elegidas[sel.dataset.idProv] = sel.value;
    });

    const pares = proveedoresCronograma
        .filter(p => p.cuentas && p.cuentas.length > 0)
        .map(p => {
            const cta = elegidas[String(p.id_proveedor)] || p.cuentas[0].cta_proveedor;
            return `${p.id_proveedor}:${cta}`;
        });

    setVal('hid-ctas-proveedor', pares.join(','));
}

// ============================================================
// 3. MODAL: NUEVO ARCHIVO PLANO
// ============================================================
function openNewModal() {
    clearAllFieldErrors('new');
    setVal('new-id-cronograma', '');
    setVal('new-id-banco', '');
    setVal('new-nom-archivo', '');
    setVal('new-cta-empresa', '');
    setVal('hid-ctas-proveedor', '');
    proveedoresCronograma = [];

    const hint = document.getElementById('prov-cuentas-hint');
    const list = document.getElementById('prov-cuentas-list');
    if (hint) { hint.style.display = ''; hint.textContent = 'Seleccione primero un cronograma para ver los proveedores a pagar.'; }
    if (list) { list.style.display = 'none'; list.innerHTML = ''; }

    show('modal-new-archivo');
}

function closeNewModal() {
    hide('modal-new-archivo');
}

// ============================================================
// 4. MODAL: EDITAR ARCHIVO PLANO
// ============================================================
function openEditModal(archivo) {
    clearAllFieldErrors('edit');
    setVal('edit-archivo-id', archivo.id_archivo_plano);
    setVal('edit-id-banco', archivo.id_banco);
    setVal('edit-nom-archivo', archivo.nom_archivo);
    show('modal-edit-archivo');
}

function closeEditModal() {
    hide('modal-edit-archivo');
}

window.openEditModal = openEditModal;

// ============================================================
// 5. MODAL: DETALLE DE ARCHIVO PLANO
// ============================================================
let idArchivoDetalleActual = null;

function openDetailModal(archivo) {
    const generado = archivo.ind_generado === 't' || archivo.ind_generado === true;

    idArchivoDetalleActual = archivo.id_archivo_plano;

    setText('detail-title', archivo.nom_archivo);
    setText('detail-subtitle', `#${archivo.id_archivo_plano} — ${generado ? 'Generado' : 'Por generar'}`);

    const content = document.getElementById('detail-content');
    content.innerHTML = `
        <div class="detail-grid">
            <div class="detail-item"><span class="detail-label">Cronograma</span><span class="detail-value">${escapeHtml(archivo.nom_cronograma)}</span></div>
            <div class="detail-item"><span class="detail-label">Banco Destino</span><span class="detail-value">${escapeHtml(archivo.nom_banco)}</span></div>
            <div class="detail-item"><span class="detail-label">Fecha de Generación</span><span class="detail-value">${formatDate(archivo.fec_generacion)}</span></div>
        </div>
    `;

    show('modal-detail');

    const detalleList = document.getElementById('detalle-pagos-list');
    if (detalleList) detalleList.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Cargando...</p></div>';

    const formData = new FormData();
    formData.append('btn_ver_detalle', '1');
    formData.append('hid_id_archivo_plano', archivo.id_archivo_plano);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.text())
        .then(text => {
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { success: false }; }
            if (!detalleList) return;

            if (result.success && result.detalle && result.detalle.length > 0) {
                detalleList.innerHTML = result.detalle.map(d => {
                    const tipoTxt = tipoCuentaTxt(d.ind_tipocuenta);
                    return `
                        <div class="cuota-row">
                            <span class="cuota-num">${escapeHtml(d.nom_tercero)}<br><small style="color:#94a3b8;font-weight:400">Cta. ${escapeHtml(d.cta_proveedor)} (${tipoTxt}) — Fact. #${d.id_factura}/${d.id_cuota}</small></span>
                            <span class="cuota-fecha"></span>
                            <span class="cuota-valor">${formatCurrency(d.val_a_pagar)}</span>
                        </div>
                    `;
                }).join('');
            } else {
                detalleList.innerHTML = '<p style="font-size:12px;color:#94a3b8;text-align:center;padding:12px">No se encontraron pagos para este archivo.</p>';
            }
        })
        .catch(() => {
            if (detalleList) detalleList.innerHTML = '<p style="font-size:12px;color:#ef4444;text-align:center;padding:12px">Error al cargar el detalle.</p>';
        });
}

function closeDetailModal() {
    hide('modal-detail');
}

window.openDetailModal = openDetailModal;

// ============================================================
// 5.1 DESCARGA DEL ARCHIVO PLANO (CSV)
// ============================================================
// Se pide por POST (mismo endpoint que el resto del módulo). Si el servidor
// pudo generar el CSV responde con Content-Type text/csv y el archivo se
// descarga como blob; si algo falló, responde JSON como las demás acciones
// y se muestra el mensaje de error en un toast.
window.descargarArchivoPlano = async function(id) {
    if (!id) return;

    const formData = new FormData();
    formData.append('btn_descargar_archivo_plano', '1');
    formData.append('hid_id_archivo_plano', id);

    try {
        const response = await fetch(window.location.href, { method: 'POST', body: formData });
        const contentType = response.headers.get('Content-Type') || '';

        if (contentType.includes('application/json')) {
            let result;
            try { result = await response.json(); } catch (e) { result = {}; }
            console.error('[archivo_plano] btn_descargar_archivo_plano falló:', result);
            if (result.debug_origen)        console.error('[archivo_plano] origen:', result.debug_origen);
            if (result.debug_salida_previa) console.error('[archivo_plano] salida previa del servidor:', result.debug_salida_previa);
            showToast(result.message || 'No se pudo descargar el archivo.', 'error');
            return;
        }

        if (!response.ok) {
            showToast('No se pudo descargar el archivo (error del servidor).', 'error');
            return;
        }

        const blob = await response.blob();

        // Nombre sugerido a partir del header Content-Disposition; si por
        // algún motivo no viene, se arma uno genérico como respaldo.
        let nombreArchivo = `archivo_plano_${id}.csv`;
        const disposition = response.headers.get('Content-Disposition') || '';
        const match = disposition.match(/filename="?([^"]+)"?/);
        if (match && match[1]) nombreArchivo = match[1].trim();

        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = nombreArchivo;
        document.body.appendChild(a);
        a.click();
        a.remove();
        setTimeout(() => URL.revokeObjectURL(url), 1000);

        showToast('Archivo plano descargado correctamente.');
        // Pequeña espera para no interrumpir la descarga antes de refrescar
        // la tabla (el estado pasa a "Generado" la primera vez que se baja).
        setTimeout(() => location.reload(), 600);
    } catch (err) {
        console.error('[archivo_plano] Error de red al descargar:', err);
        showToast('Error de conexión al descargar el archivo.', 'error');
    }
};

// ============================================================
// 6. FILTROS
// ============================================================
function applyFilters() {
    const query  = val('archivo-search').toLowerCase().trim();
    const active = document.querySelector('.filter-toggle.active')?.dataset.filter ?? 'all';
    const rows   = document.querySelectorAll('#archivos-tbody tr:not(.empty-row)');
    let visibles = 0;

    rows.forEach(row => {
        const texto = row.children[0]?.textContent.toLowerCase() + ' ' + row.children[1]?.textContent.toLowerCase();
        const matchesQuery  = !query || texto.includes(query);
        const matchesFilter = active === 'all' || row.dataset.estado === active;
        const visible = matchesQuery && matchesFilter;
        row.style.display = visible ? '' : 'none';
        if (visible) visibles++;
    });

    setText('archivos-count', `${visibles} resultado${visibles !== 1 ? 's' : ''}`);

    const clearBtn = document.getElementById('btn-clear-filters');
    if (clearBtn) clearBtn.style.display = (query || active !== 'all') ? '' : 'none';
}

function clearFilters() {
    setVal('archivo-search', '');
    document.querySelectorAll('.filter-toggle').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.filter-toggle[data-filter="all"]')?.classList.add('active');
    applyFilters();
}

// ============================================================
// 7. INICIALIZACIÓN GENERAL
// ============================================================
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initArchivoModule);
} else {
    // El script se cargó después de que el DOM ya estaba listo.
    initArchivoModule();
}

function initArchivoModule() {
    // Guard: si el archivo se carga dos veces, los listeners se registran una sola vez.
    if (window.__archivoPlanoInit) return;
    window.__archivoPlanoInit = true;

    document.getElementById('btn-add-archivo')?.addEventListener('click', openNewModal);

    document.getElementById('archivo-search')?.addEventListener('input', applyFilters);
    document.getElementById('btn-clear-filters')?.addEventListener('click', clearFilters);

    document.querySelectorAll('.filter-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-toggle').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            applyFilters();
        });
    });

    document.getElementById('new-id-cronograma')?.addEventListener('change', onCronogramaChange);

    document.querySelectorAll('.btn-close-new-modal, .btn-cancel-new-modal')
        .forEach(btn => btn.addEventListener('click', closeNewModal));
    document.querySelectorAll('.btn-close-edit-modal, .btn-cancel-edit-modal')
        .forEach(btn => btn.addEventListener('click', closeEditModal));

    document.getElementById('close-detail-modal')?.addEventListener('click', closeDetailModal);
    document.getElementById('close-detail-btn')?.addEventListener('click', closeDetailModal);
    document.getElementById('detail-btn-download')?.addEventListener('click', function() {
        descargarArchivoPlano(idArchivoDetalleActual);
    });

    document.getElementById('modal-new-archivo')?.addEventListener('click', e => {
        if (e.target.id === 'modal-new-archivo') closeNewModal();
    });
    document.getElementById('modal-edit-archivo')?.addEventListener('click', e => {
        if (e.target.id === 'modal-edit-archivo') closeEditModal();
    });
    document.getElementById('modal-detail')?.addEventListener('click', e => {
        if (e.target.id === 'modal-detail') closeDetailModal();
    });

    // ---------- SUBMIT: NUEVO ARCHIVO PLANO ----------
    document.getElementById('new-btn-save')?.addEventListener('click', async function() {
        const btn = this;
        if (btn.disabled) return; // evita doble envío por doble clic
        btn.disabled = true;

        const form = document.getElementById('new-archivo-form');
        clearAllFieldErrors('new');

        try {
            buildCtasProveedorPayload();
        } catch (e) {
            // Nunca debe abortar el envío: si el front no logra armar la
            // selección de cuentas, el servidor usa la primera cuenta activa
            // de cada proveedor.
            console.error('[archivo_plano] No se pudo armar la selección de cuentas:', e);
        }

        const formData = new FormData(form);

        try {
            const response = await fetch(window.location.href, { method: 'POST', body: formData });
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { success: false, message: 'Respuesta inválida del servidor (revise la consola).' }; }

            if (!result.success) {
                console.error('[archivo_plano] btn_nuevo falló. Respuesta cruda:', text);
                if (result.debug_origen)        console.error('[archivo_plano] origen:', result.debug_origen);
                if (result.debug_salida_previa) console.error('[archivo_plano] salida previa del servidor:', result.debug_salida_previa);
            }

            if (result.success) {
                showToast(result.message, 'success');
                closeNewModal();
                location.reload();
            } else if (result.errors && Object.keys(result.errors).length > 0) {
                Object.entries(result.errors).forEach(([spanId, mensaje]) => showFieldError(spanId, mensaje));
            } else {
                showToast(result.message || 'Error al generar el archivo', 'error');
            }
        } catch (err) {
            showToast('Error de conexión', 'error');
        } finally {
            btn.disabled = false;
        }
    });

    // ---------- SUBMIT: EDITAR ARCHIVO PLANO ----------
    document.getElementById('edit-btn-save')?.addEventListener('click', async function() {
        const form = document.getElementById('edit-archivo-form');
        clearAllFieldErrors('edit');
        const formData = new FormData(form);

        try {
            const response = await fetch(window.location.href, { method: 'POST', body: formData });
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { success: false, message: 'Respuesta inválida del servidor' }; }

            if (result.success) {
                showToast(result.message, 'success');
                closeEditModal();
                location.reload();
            } else if (result.errors && Object.keys(result.errors).length > 0) {
                Object.entries(result.errors).forEach(([spanId, mensaje]) => showFieldError(spanId, mensaje));
            } else {
                showToast(result.message || 'Error al actualizar', 'error');
            }
        } catch (err) {
            showToast('Error de conexión', 'error');
        }
    });
}

})();
