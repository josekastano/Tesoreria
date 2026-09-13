'use strict';

// ============================================================
// 1. HELPERS GENERALES (mismos que bancos.js)
// ============================================================
function val(id)           { return document.getElementById(id)?.value ?? ''; }
function setVal(id, value) { const el = document.getElementById(id); if (el) el.value = value ?? ''; }
function setText(id, text) { const el = document.getElementById(id); if (el) el.textContent = text ?? ''; }
function show(id)          { document.getElementById(id)?.classList.remove('hidden'); }
function hide(id)          { document.getElementById(id)?.classList.add('hidden'); }

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

function formatMoney(valor) {
    return '$' + Number(valor || 0).toLocaleString('es-CO');
}

function formatFecha(iso) {
    if (!iso) return '—';
    const [y, m, d] = String(iso).split('-');
    return (y && m && d) ? `${d}/${m}/${y}` : iso;
}

function esc(texto) {
    return String(texto ?? '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;')
        .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
}

function capEstado(estado) {
    const e = String(estado || '');
    return e.charAt(0) + e.slice(1).toLowerCase();
}

// ============================================================
// 2. FILTROS (búsqueda + estado + rango de fechas)
// ============================================================
// Devuelve los id_pago visibles, en el orden de la tabla, para exportarlos.
function applyFilters() {
    const query  = val('pago-search').toLowerCase().trim();
    const active = document.querySelector('.filter-toggle.active')?.dataset.filter ?? 'all';
    const desde  = val('fec-desde');
    const hasta  = val('fec-hasta');
    const rows   = document.querySelectorAll('#pagos-tbody tr:not(.empty-row)');

    let visibles = 0;
    let valorAprobado = 0;
    const idsVisibles = [];

    rows.forEach(row => {
        const fecha  = row.dataset.fecha ?? '';
        const estado = row.dataset.estado ?? '';

        const matchesQuery  = !query || row.textContent.toLowerCase().includes(query);
        const matchesEstado = active === 'all' || estado === active;
        const matchesDesde  = !desde || fecha >= desde;
        const matchesHasta  = !hasta || fecha <= hasta;

        const visible = matchesQuery && matchesEstado && matchesDesde && matchesHasta;
        row.style.display = visible ? '' : 'none';

        if (visible) {
            visibles++;
            idsVisibles.push(Number(row.dataset.idPago));
            if (estado === 'APROBADO') {
                const pago = pagosData.find(p => Number(p.id_pago) === Number(row.dataset.idPago));
                if (pago) valorAprobado += Number(pago.val_pago);
            }
        }
    });

    setText('pagos-count', `${visibles} resultado${visibles !== 1 ? 's' : ''}`);
    setText('stat-valor', formatMoney(valorAprobado));

    const clearBtn = document.getElementById('btn-clear-filters');
    if (clearBtn) {
        clearBtn.style.display = (query || active !== 'all' || desde || hasta) ? '' : 'none';
    }

    return idsVisibles;
}

function clearFilters() {
    setVal('pago-search', '');
    setVal('fec-desde', '');
    setVal('fec-hasta', '');
    document.querySelectorAll('.filter-toggle').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.filter-toggle[data-filter="all"]')?.classList.add('active');
    applyFilters();
}

// ============================================================
// 3. MODAL: DETALLE DEL PAGO
// ============================================================
function openDetailModal(pago) {
    setText('detail-title', `Pago #${pago.id_pago}`);
    setText('detail-subtitle', `${capEstado(pago.estado_pago)} — ${formatFecha(pago.fec_pago)}`);

    const rechazado = pago.estado_pago === 'RECHAZADO';
    const archivo   = (pago.nom_archivo ?? '').trim();

    const bloqueMotivo = rechazado ? `
        <div class="motivo-rechazo">
            <i class="fas fa-circle-exclamation"></i>
            <div class="motivo-rechazo-texto">
                <span class="motivo-rechazo-label">Motivo del rechazo</span>
                <span class="motivo-rechazo-valor">${esc(pago.des_motivo) || 'Sin motivo registrado'}${pago.cod_bancario ? ' (' + esc(pago.cod_bancario) + ')' : ''}</span>
            </div>
        </div>` : '';

    const origen = archivo !== ''
        ? `Archivo plano #${esc(pago.id_archivo_plano)} — ${esc(archivo)}`
        : 'Pago manual (cheque o transferencia individual)';

    document.getElementById('detail-content').innerHTML = `
        ${bloqueMotivo}
        <div class="detail-grid">
            <div class="detail-item">
                <span class="detail-label">Proveedor</span>
                <span class="detail-value">${esc(pago.nom_tercero) || '—'}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Factura / Cuota</span>
                <span class="detail-value">#${esc(pago.id_factura)} — Cuota ${esc(pago.id_cuota)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Valor del Pago</span>
                <span class="detail-value">${formatMoney(pago.val_pago)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Estado</span>
                <span class="detail-value">
                    <span class="badge-${String(pago.estado_pago).toLowerCase()}">${capEstado(pago.estado_pago)}</span>
                </span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Fecha de Pago</span>
                <span class="detail-value">${formatFecha(pago.fec_pago)}</span>
            </div>
            <div class="detail-item">
                <span class="detail-label">Referencia Bancaria</span>
                <span class="detail-value">${esc(pago.referencia_bancaria) || '—'}</span>
            </div>
            <div class="detail-item" style="grid-column:1/-1">
                <span class="detail-label">Origen</span>
                <span class="detail-value">${origen}</span>
            </div>
        </div>
    `;

    show('modal-detail');
}

function closeDetailModal() {
    hide('modal-detail');
}

window.openDetailModal = openDetailModal;

// ============================================================
// 4. EXPORTAR A CSV
// ============================================================
// Exporta exactamente lo que el usuario está viendo: se respetan los filtros.
function csvCampo(valor) {
    const texto = String(valor ?? '');
    // Comillas dobles y separador: se escapa el campo completo
    return /[";\n\r]/.test(texto) ? '"' + texto.replace(/"/g, '""') + '"' : texto;
}

function exportarCSV() {
    const idsVisibles = applyFilters();

    if (idsVisibles.length === 0) {
        showToast('No hay pagos que exportar con los filtros actuales.', 'error');
        return;
    }

    const encabezados = [
        'ID Pago', 'ID Factura', 'ID Cuota', 'ID Archivo Plano', 'Archivo Plano',
        'Proveedor', 'Fecha de Pago', 'Valor', 'Estado',
        'Referencia Bancaria', 'ID Motivo Rechazo', 'Motivo de Rechazo', 'Codigo Bancario'
    ];

    const filas = idsVisibles.map(id => {
        const p = pagosData.find(x => Number(x.id_pago) === id);
        if (!p) return null;
        return [
            p.id_pago,
            p.id_factura,
            p.id_cuota,
            p.id_archivo_plano ?? '',
            p.nom_archivo ?? 'Pago manual',
            p.nom_tercero ?? '',
            p.fec_pago ?? '',
            p.val_pago,
            p.estado_pago,
            p.referencia_bancaria ?? '',
            p.id_motivo_rechazo ?? '',
            p.des_motivo ?? '',
            p.cod_bancario ?? ''
        ];
    }).filter(Boolean);

    // Separador ';' y BOM para que Excel en español abra el archivo bien
    const csv = [encabezados, ...filas]
        .map(fila => fila.map(csvCampo).join(';'))
        .join('\r\n');

    const hoy = new Date().toISOString().slice(0, 10);
    const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
    const url  = URL.createObjectURL(blob);

    const link = document.createElement('a');
    link.href = url;
    link.download = `historial_pagos_${hoy}.csv`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    showToast(`${filas.length} pago${filas.length !== 1 ? 's' : ''} exportado${filas.length !== 1 ? 's' : ''}.`, 'success');
}

// ============================================================
// 5. INICIALIZACIÓN GENERAL
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    initHistorialModule();
});

function initHistorialModule() {
    document.getElementById('pago-search')?.addEventListener('input', applyFilters);
    document.getElementById('fec-desde')?.addEventListener('change', applyFilters);
    document.getElementById('fec-hasta')?.addEventListener('change', applyFilters);
    document.getElementById('btn-clear-filters')?.addEventListener('click', clearFilters);

    document.querySelectorAll('.filter-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-toggle').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            applyFilters();
        });
    });

    document.getElementById('btn-export-historial')?.addEventListener('click', exportarCSV);

    document.getElementById('close-detail-modal')?.addEventListener('click', closeDetailModal);
    document.getElementById('close-detail-btn')?.addEventListener('click', closeDetailModal);
    document.getElementById('modal-detail')?.addEventListener('click', e => {
        if (e.target.id === 'modal-detail') closeDetailModal();
    });

    applyFilters();
}
