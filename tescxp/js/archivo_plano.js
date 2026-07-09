'use strict';

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
let filasCronogramaActual = [];

function onCronogramaChange() {
    const idCronograma = val('new-id-cronograma');
    const hint = document.getElementById('prov-cuentas-hint');
    const list = document.getElementById('prov-cuentas-list');

    if (!idCronograma) {
        filasCronogramaActual = [];
        setVal('hid-filas-archivo', '');
        if (hint) { hint.style.display = ''; hint.textContent = 'Seleccione primero un cronograma para ver los proveedores a pagar.'; }
        if (list) { list.style.display = 'none'; list.innerHTML = ''; }
        return;
    }

    if (hint) { hint.style.display = ''; hint.textContent = 'Cargando proveedores del cronograma...'; }
    if (list) { list.style.display = 'none'; list.innerHTML = ''; }

    const formData = new FormData();
    formData.append('btn_cargar_crono', '1');
    formData.append('hid_id_cronograma', idCronograma);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.text())
        .then(text => {
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { success: false }; }

            if (!result.success || !result.filas || result.filas.length === 0) {
                if (hint) { hint.style.display = ''; hint.textContent = 'Este cronograma no tiene proveedores con cuentas bancarias registradas.'; }
                filasCronogramaActual = [];
                setVal('hid-filas-archivo', '');
                return;
            }

            filasCronogramaActual = result.filas;
            renderProveedorCuentas(result.filas);
        })
        .catch(() => {
            if (hint) { hint.style.display = ''; hint.textContent = 'Error al cargar el cronograma.'; }
        });
}

function renderProveedorCuentas(filas) {
    const hint = document.getElementById('prov-cuentas-hint');
    const list = document.getElementById('prov-cuentas-list');
    if (!list) return;

    // Agrupar filas por proveedor: cada proveedor puede tener varias cuotas y
    // varias cuentas bancarias disponibles; el usuario elige una cuenta por proveedor.
    const grupos = {};
    filas.forEach(f => {
        if (!grupos[f.id_proveedor]) {
            grupos[f.id_proveedor] = {
                nom_tercero: f.nom_tercero,
                cuentas: new Map(),
                cuotas: []
            };
        }
        const grupo = grupos[f.id_proveedor];
        grupo.cuentas.set(f.cta_proveedor, { tipo: f.ind_tipocuenta, banco: f.nom_banco_proveedor });
        const yaExiste = grupo.cuotas.some(c => c.id_factura === f.id_factura && c.id_cuota === f.id_cuota);
        if (!yaExiste) {
            grupo.cuotas.push({ id_factura: f.id_factura, id_cuota: f.id_cuota, val_a_pagar: f.val_a_pagar });
        }
    });

    if (hint) hint.style.display = 'none';
    list.style.display = '';
    list.innerHTML = Object.entries(grupos).map(([idProv, grupo]) => {
        const cuentasArr = Array.from(grupo.cuentas.entries());
        const totalProveedor = grupo.cuotas.reduce((s, c) => s + parseFloat(c.val_a_pagar || 0), 0);
        const cuotasTexto = grupo.cuotas.map(c => `Fact. #${c.id_factura} Cta. ${c.id_cuota}`).join(', ');

        let selectorHtml;
        if (cuentasArr.length === 1) {
            const [cta, info] = cuentasArr[0];
            const tipoTxt = (info.tipo === 't' || info.tipo === true) ? 'Corriente' : 'Ahorros';
            selectorHtml = `<span class="cuota-picker-fecha">${escapeHtml(info.banco)} — ${escapeHtml(cta)} (${tipoTxt})</span>`;
        } else {
            selectorHtml = `
                <select class="form-select prov-cta-select" data-id-prov="${escapeHtml(idProv)}" style="margin-top:4px">
                    ${cuentasArr.map(([cta, info]) => {
                        const tipoTxt = (info.tipo === 't' || info.tipo === true) ? 'Corriente' : 'Ahorros';
                        return `<option value="${escapeHtml(cta)}:${info.tipo}">${escapeHtml(info.banco)} — ${escapeHtml(cta)} (${tipoTxt})</option>`;
                    }).join('')}
                </select>
            `;
        }

        return `
            <div class="cuota-picker-row" style="cursor:default" data-id-prov="${escapeHtml(idProv)}">
                <i class="fas fa-building" style="color:#94a3b8"></i>
                <div class="cuota-picker-info">
                    <span class="cuota-picker-prov">${escapeHtml(grupo.nom_tercero)}</span>
                    <span class="cuota-picker-fecha">${escapeHtml(cuotasTexto)}</span>
                    ${selectorHtml}
                </div>
                <span class="cuota-picker-valor">${formatCurrency(totalProveedor)}</span>
            </div>
        `;
    }).join('');

    list.querySelectorAll('.prov-cta-select').forEach(sel => {
        sel.addEventListener('change', buildFilasArchivoPayload);
    });

    buildFilasArchivoPayload();
}

function buildFilasArchivoPayload() {
    // Construye "id_factura:id_cuota:id_proveedor:cta_proveedor:tipocuenta,..."
    // resolviendo, por cada proveedor, la cuenta elegida.
    const cuentaPorProveedorSelect = {};
    document.querySelectorAll('#prov-cuentas-list [data-id-prov]').forEach(row => {
        const idProv = row.dataset.idProv;
        const select = row.querySelector('.prov-cta-select');
        if (select) {
            cuentaPorProveedorSelect[idProv] = select.value; // "cta:tipo"
        }
    });

    // Para proveedores sin select (una sola cuenta), tomar esa cuenta directo de los datos originales
    const cuentaUnicaPorProveedor = {};
    filasCronogramaActual.forEach(f => {
        if (!cuentaUnicaPorProveedor[f.id_proveedor]) {
            cuentaUnicaPorProveedor[f.id_proveedor] = `${f.cta_proveedor}:${f.ind_tipocuenta}`;
        }
    });

    // IMPORTANTE: filasCronogramaActual trae una fila por cada combinación
    // cuota x cuenta_bancaria_del_proveedor. Si un proveedor tiene más de una
    // cuenta registrada en tab_bancoxprov, la MISMA cuota (id_factura+id_cuota)
    // aparece repetida en el arreglo. Hay que deduplicar por cuota aquí, o se
    // termina mandando la misma fila dos veces en el mismo payload y el backend
    // la intenta insertar dos veces en el mismo archivo plano.
    const cuotasVistas = new Set();
    const filasPayload = [];
    filasCronogramaActual.forEach(f => {
        const claveCuota = `${f.id_factura}:${f.id_cuota}`;
        if (cuotasVistas.has(claveCuota)) return;
        cuotasVistas.add(claveCuota);

        const cuentaElegida = cuentaPorProveedorSelect[f.id_proveedor] || cuentaUnicaPorProveedor[f.id_proveedor];
        const [cta, tipo] = cuentaElegida.split(':');
        filasPayload.push(`${f.id_factura}:${f.id_cuota}:${f.id_proveedor}:${cta}:${tipo}`);
    });

    setVal('hid-filas-archivo', filasPayload.join(','));
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
    setVal('hid-filas-archivo', '');
    filasCronogramaActual = [];

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
                    const tipoTxt = (d.ind_tipocuenta === 't' || d.ind_tipocuenta === true) ? 'Corriente' : 'Ahorros';
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
// Pendiente de implementar. Por ahora solo se avisa al usuario.
window.descargarArchivoPlano = function(id) {
    showToast('La descarga del archivo plano se implementará próximamente.');
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
document.addEventListener('DOMContentLoaded', function() {
    initArchivoModule();
});

function initArchivoModule() {
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
        buildFilasArchivoPayload();
        const formData = new FormData(form);

        try {
            const response = await fetch(window.location.href, { method: 'POST', body: formData });
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { success: false, message: 'Respuesta inválida del servidor' }; }

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
