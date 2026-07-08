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
// 2. CÁLCULO DE FECHA DE VENCIMIENTO EN VIVO
// ============================================================
function addDays(dateStr, days) {
    const date = new Date(dateStr + 'T00:00:00');
    date.setDate(date.getDate() + days);
    return date;
}

function formatDateLocal(date) {
    const d = String(date.getDate()).padStart(2, '0');
    const m = String(date.getMonth() + 1).padStart(2, '0');
    const y = date.getFullYear();
    return `${d}/${m}/${y}`;
}

function updateVencimientoPreview() {
    const selProv       = document.getElementById('new-id-proveedor');
    const fecEmision    = val('new-fec-emision');
    const fecVencInput  = document.getElementById('new-fec-vencimiento');

    if (!selProv || !selProv.value || !fecEmision) {
        if (fecVencInput) {
            fecVencInput.value = '';
            fecVencInput.placeholder = 'Seleccione un proveedor';
        }
        return;
    }

    const dias = parseInt(selProv.selectedOptions[0]?.dataset.dias ?? '0', 10);
    const fechaVenc = addDays(fecEmision, dias);
    if (fecVencInput) {
        fecVencInput.value = `${formatDateLocal(fechaVenc)} (+${dias} días)`;
    }
}

function updateCuotaPreview() {
    const valorFactura = parseFloat(val('new-val-factura')) || 0;
    const numCuotas    = parseInt(val('new-num-cuotas'), 10) || 0;
    const preview      = document.getElementById('new-cuota-preview');
    if (!preview) return;

    if (valorFactura > 0 && numCuotas > 0) {
        const valorCuota = valorFactura / numCuotas;
        preview.style.display = '';
        preview.innerHTML = `<i class="fas fa-info-circle"></i> Se generarán <strong>${numCuotas}</strong> cuota${numCuotas !== 1 ? 's' : ''} de aproximadamente <strong>${formatCurrency(valorCuota)}</strong> cada una.`;
    } else {
        preview.style.display = 'none';
    }
}

// ============================================================
// 3. MODAL: NUEVA FACTURA
// ============================================================
function openNewModal() {
    clearAllFieldErrors('new');
    setVal('new-id-factura', '');
    setVal('new-id-proveedor', '');
    setVal('new-fec-emision', new Date().toISOString().slice(0, 10));
    setVal('new-val-factura', '');
    setVal('new-num-cuotas', '1');
    updateVencimientoPreview();
    updateCuotaPreview();
    show('modal-new-factura');
}

function closeNewModal() {
    hide('modal-new-factura');
}

// ============================================================
// 4. MODAL: DETALLE DE FACTURA (con cuotas)
// ============================================================
function openDetailModal(idFactura) {
    const factura = facturasData.find(f => parseInt(f.id_factura, 10) === idFactura);
    if (!factura) return;

    const pagada      = factura.ind_estado === 't' || factura.ind_estado === true;
    const hoy         = new Date().toISOString().slice(0, 10);
    const estaVencida = !pagada && factura.fec_vencimiento < hoy;
    const estadoTxt   = pagada ? 'Pagada' : (estaVencida ? 'Vencida' : 'En deuda');

    setText('detail-title', `Factura #${factura.id_factura}`);
    setText('detail-subtitle', `${factura.nom_tercero} — ${estadoTxt}`);

    const content = document.getElementById('detail-content');
    content.innerHTML = `
        <div class="detail-grid">
            <div class="detail-item"><span class="detail-label">Proveedor</span><span class="detail-value">${escapeHtml(factura.nom_tercero)}</span></div>
            <div class="detail-item"><span class="detail-label">NIT</span><span class="detail-value">${escapeHtml(factura.id_proveedor)}</span></div>
            <div class="detail-item"><span class="detail-label">Emisión</span><span class="detail-value">${formatDate(factura.fec_emision)}</span></div>
            <div class="detail-item"><span class="detail-label">Vencimiento</span><span class="detail-value">${formatDate(factura.fec_vencimiento)}</span></div>
            <div class="detail-item"><span class="detail-label">Valor Factura</span><span class="detail-value">${formatCurrency(factura.val_factura)}</span></div>
            <div class="detail-item"><span class="detail-label">Saldo Pendiente</span><span class="detail-value">${formatCurrency(factura.val_saldo)}</span></div>
        </div>
        <h4 class="detail-subheading"><i class="fas fa-list"></i> Cuotas (${factura.num_cuotas})</h4>
        <div id="cuotas-list">
            <div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Cargando cuotas...</p></div>
        </div>
    `;

    show('modal-detail');

    // Cargar cuotas vía fetch
    const formData = new FormData();
    formData.append('btn_ver_cuotas', '1');
    formData.append('hid_id_factura', factura.id_factura);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.text())
        .then(text => {
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { success: false }; }
            const cuotasList = document.getElementById('cuotas-list');
            if (!cuotasList) return;

            if (result.success && result.cuotas && result.cuotas.length > 0) {
                cuotasList.innerHTML = result.cuotas.map(c => {
                    const pagadaCuota = c.ind_pagada === 't' || c.ind_pagada === true;
                    return `
                        <div class="cuota-row">
                            <span class="cuota-num">Cuota ${c.id_cuota}</span>
                            <span class="cuota-fecha">${formatDate(c.fec_vencimiento)}</span>
                            <span class="cuota-valor">${formatCurrency(c.val_cuota)}</span>
                            <span class="badge ${pagadaCuota ? 'badge-active' : 'badge-inactive'}">${pagadaCuota ? 'Pagada' : 'Pendiente'}</span>
                        </div>
                    `;
                }).join('');
            } else {
                cuotasList.innerHTML = '<p style="font-size:12px;color:#94a3b8;text-align:center;padding:12px">No se encontraron cuotas para esta factura.</p>';
            }
        })
        .catch(() => {
            const cuotasList = document.getElementById('cuotas-list');
            if (cuotasList) cuotasList.innerHTML = '<p style="font-size:12px;color:#ef4444;text-align:center;padding:12px">Error al cargar las cuotas.</p>';
        });
}

function closeDetailModal() {
    hide('modal-detail');
}

window.openDetailModal = openDetailModal;

// ============================================================
// 5. FILTROS
// ============================================================
function applyFilters() {
    const query  = val('factura-search').toLowerCase().trim();
    const active = document.querySelector('.filter-toggle.active')?.dataset.filter ?? 'all';
    const rows   = document.querySelectorAll('#facturas-tbody tr:not(.empty-row)');
    let visibles = 0;

    rows.forEach(row => {
        const texto = row.children[0]?.textContent.toLowerCase() + ' ' + row.children[1]?.textContent.toLowerCase();
        const matchesQuery  = !query || texto.includes(query);
        const matchesFilter = active === 'all' || row.dataset.estado === active;
        const visible = matchesQuery && matchesFilter;
        row.style.display = visible ? '' : 'none';
        if (visible) visibles++;
    });

    setText('facturas-count', `${visibles} resultado${visibles !== 1 ? 's' : ''}`);

    const clearBtn = document.getElementById('btn-clear-filters');
    if (clearBtn) clearBtn.style.display = (query || active !== 'all') ? '' : 'none';
}

function clearFilters() {
    setVal('factura-search', '');
    document.querySelectorAll('.filter-toggle').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.filter-toggle[data-filter="all"]')?.classList.add('active');
    applyFilters();
}

// ============================================================
// 6. INICIALIZACIÓN GENERAL
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    initFacturaModule();
});

function initFacturaModule() {
    document.getElementById('btn-add-factura')?.addEventListener('click', openNewModal);

    document.getElementById('factura-search')?.addEventListener('input', applyFilters);
    document.getElementById('btn-clear-filters')?.addEventListener('click', clearFilters);

    document.querySelectorAll('.filter-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-toggle').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            applyFilters();
        });
    });

    document.querySelectorAll('.btn-close-new-modal, .btn-cancel-new-modal')
        .forEach(btn => btn.addEventListener('click', closeNewModal));

    document.getElementById('close-detail-modal')?.addEventListener('click', closeDetailModal);
    document.getElementById('close-detail-btn')?.addEventListener('click', closeDetailModal);

    document.getElementById('modal-new-factura')?.addEventListener('click', e => {
        if (e.target.id === 'modal-new-factura') closeNewModal();
    });
    document.getElementById('modal-detail')?.addEventListener('click', e => {
        if (e.target.id === 'modal-detail') closeDetailModal();
    });

    // Recalcular vencimiento y preview de cuotas en vivo
    document.getElementById('new-id-proveedor')?.addEventListener('change', updateVencimientoPreview);
    document.getElementById('new-fec-emision')?.addEventListener('change', updateVencimientoPreview);
    document.getElementById('new-val-factura')?.addEventListener('input', updateCuotaPreview);
    document.getElementById('new-num-cuotas')?.addEventListener('input', updateCuotaPreview);

    // ---------- SUBMIT: NUEVA FACTURA ----------
    document.getElementById('new-btn-save')?.addEventListener('click', async function() {
        const form = document.getElementById('new-factura-form');
        clearAllFieldErrors('new');
        const formData = new FormData(form);

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { success: false, message: 'Respuesta inválida del servidor' }; }

            if (result.success) {
                showToast(result.message, 'success');
                closeNewModal();
                location.reload();
            } else if (result.errors && Object.keys(result.errors).length > 0) {
                Object.entries(result.errors).forEach(([spanId, mensaje]) => {
                    showFieldError(spanId, mensaje);
                });
            } else {
                showToast(result.message || 'Error al guardar', 'error');
            }
        } catch (err) {
            showToast('Error de conexión', 'error');
        }
    });
}
