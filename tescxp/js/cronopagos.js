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
// 2. VISTA: TOGGLE TABLA / CALENDARIO
// ============================================================
function switchView(view) {
    document.querySelectorAll('.view-toggle').forEach(btn => {
        btn.classList.toggle('active', btn.dataset.view === view);
    });
    document.getElementById('view-tabla')?.classList.toggle('active', view === 'tabla');
    document.getElementById('view-calendario')?.classList.toggle('active', view === 'calendario');

    if (view === 'calendario') {
        renderCalendar();
    }
}

// ============================================================
// 2.1 CALENDARIO DE CRONOGRAMAS
// ============================================================
let calCurrentYear  = new Date().getFullYear();
let calCurrentMonth = new Date().getMonth(); // 0-11

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];

function getCronogramasPorFecha() {
    const mapa = {};
    (cronogramasData || []).forEach(c => {
        const fecha = c.fec_programacion; // 'YYYY-MM-DD'
        if (!fecha) return;
        if (!mapa[fecha]) mapa[fecha] = [];
        mapa[fecha].push(c);
    });
    return mapa;
}

function renderCalendar() {
    const grid = document.getElementById('calendar-grid');
    const label = document.getElementById('cal-month-label');
    if (!grid || !label) return;

    label.textContent = `${MESES[calCurrentMonth]} ${calCurrentYear}`;

    const cronosPorFecha = getCronogramasPorFecha();
    const primerDiaSemana = new Date(calCurrentYear, calCurrentMonth, 1).getDay(); // 0=Dom
    const diasEnMes = new Date(calCurrentYear, calCurrentMonth + 1, 0).getDate();
    const hoyStr = new Date().toISOString().slice(0, 10);

    let html = '';

    // Celdas vacías antes del día 1
    for (let i = 0; i < primerDiaSemana; i++) {
        html += `<div class="calendar-day empty"></div>`;
    }

    for (let dia = 1; dia <= diasEnMes; dia++) {
        const fechaStr = `${calCurrentYear}-${String(calCurrentMonth + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
        const cronosDia = cronosPorFecha[fechaStr] || [];
        const esHoy = fechaStr === hoyStr;

        let badgeHtml = '';
        let claseEstado = '';
        if (cronosDia.length > 0) {
            const hayPendientes = cronosDia.some(c => !(c.ind_estado === 't' || c.ind_estado === true));
            claseEstado = hayPendientes ? 'pendiente' : 'pagado';
            badgeHtml = `<span class="calendar-day-badge ${claseEstado}">${cronosDia.length}</span>`;
        }

        html += `
            <div class="calendar-day ${esHoy ? 'today' : ''} ${cronosDia.length > 0 ? 'has-crono' : ''}" data-fecha="${fechaStr}">
                <span class="calendar-day-num">${dia}</span>
                ${badgeHtml}
            </div>
        `;
    }

    grid.innerHTML = html;

    grid.querySelectorAll('.calendar-day.has-crono').forEach(dayEl => {
        dayEl.addEventListener('click', () => openDayModal(dayEl.dataset.fecha));
    });
}

function changeMonth(delta) {
    calCurrentMonth += delta;
    if (calCurrentMonth > 11) { calCurrentMonth = 0; calCurrentYear++; }
    if (calCurrentMonth < 0) { calCurrentMonth = 11; calCurrentYear--; }
    renderCalendar();
}

function goToToday() {
    const hoy = new Date();
    calCurrentYear = hoy.getFullYear();
    calCurrentMonth = hoy.getMonth();
    renderCalendar();
}

// ============================================================
// 2.2 MODAL: CRONOGRAMAS DE UN DÍA
// ============================================================
function openDayModal(fechaStr) {
    const cronosPorFecha = getCronogramasPorFecha();
    const cronosDia = cronosPorFecha[fechaStr] || [];

    setText('day-modal-title', 'Cronogramas del día');
    setText('day-modal-subtitle', formatDate(fechaStr));

    const list = document.getElementById('day-modal-list');
    if (!list) return;

    if (cronosDia.length === 0) {
        list.innerHTML = '<p style="font-size:12px;color:#94a3b8;text-align:center;padding:12px">No hay cronogramas programados este día.</p>';
    } else {
        list.innerHTML = cronosDia.map(c => {
            const pagado = c.ind_estado === 't' || c.ind_estado === true;
            const badge = pagado
                ? '<span class="badge badge-active">Pagado</span>'
                : '<span class="badge badge-inactive" style="background:#fffbeb;color:#f59e0b">Pendiente</span>';
            return `
                <div class="day-crono-row" data-id="${c.id_cronograma}">
                    <div class="day-crono-info">
                        <span class="day-crono-nombre">${escapeHtml(c.nom_cronograma)}</span>
                        <span class="day-crono-total">${formatCurrency(c.total_a_pagar)}</span>
                    </div>
                    ${badge}
                    <i class="fas fa-chevron-right" style="color:#94a3b8;font-size:12px"></i>
                </div>
            `;
        }).join('');

        list.querySelectorAll('.day-crono-row').forEach(row => {
            row.addEventListener('click', () => {
                const crono = cronosDia.find(c => String(c.id_cronograma) === row.dataset.id);
                if (crono) {
                    closeDayModal();
                    openDetailModal(crono);
                }
            });
        });
    }

    show('modal-day');
}

function closeDayModal() {
    hide('modal-day');
}

// ============================================================
// 3. SELECCIÓN DE CUOTAS (checkboxes + total en vivo)
// ============================================================
function updateSeleccionCuotas() {
    const checkboxes = document.querySelectorAll('.cuota-checkbox:checked');
    const claves = Array.from(checkboxes).map(cb => cb.value);
    const total  = Array.from(checkboxes).reduce((sum, cb) => sum + parseFloat(cb.dataset.valor || '0'), 0);

    setVal('hid-cuotas-seleccionadas', claves.join(','));
    setText('crono-total-seleccionado', formatCurrency(total));
}

// ============================================================
// 3. MODAL: NUEVO CRONOGRAMA
// ============================================================
function openNewModal() {
    clearAllFieldErrors('new');
    setVal('new-nom-crono', '');
    setVal('new-fec-prog', '');
    setVal('hid-cuotas-seleccionadas', '');
    document.querySelectorAll('.cuota-checkbox').forEach(cb => cb.checked = false);
    updateSeleccionCuotas();
    show('modal-new-crono');
}

function closeNewModal() {
    hide('modal-new-crono');
}

// ============================================================
// 4. MODAL: EDITAR CRONOGRAMA
// ============================================================
function openEditModal(crono) {
    clearAllFieldErrors('edit');
    setVal('edit-crono-id', crono.id_cronograma);
    setVal('edit-nom-crono', crono.nom_cronograma);
    setVal('edit-fec-prog', crono.fec_programacion);
    show('modal-edit-crono');
}

function closeEditModal() {
    hide('modal-edit-crono');
}

window.openEditModal = openEditModal;

// ============================================================
// 5. MODAL: DETALLE DE CRONOGRAMA
// ============================================================
function openDetailModal(crono) {
    const pagado = crono.ind_estado === 't' || crono.ind_estado === true;

    setText('detail-title', crono.nom_cronograma);
    setText('detail-subtitle', `#${crono.id_cronograma} — ${pagado ? 'Pagado' : 'Pendiente'}`);

    const content = document.getElementById('detail-content');
    content.innerHTML = `
        <div class="detail-grid">
            <div class="detail-item"><span class="detail-label">Fecha Programada</span><span class="detail-value">${formatDate(crono.fec_programacion)}</span></div>
            <div class="detail-item"><span class="detail-label">Total a Pagar</span><span class="detail-value">${formatCurrency(crono.total_a_pagar)}</span></div>
        </div>
    `;

    show('modal-detail');

    const detalleList = document.getElementById('detalle-cuotas-list');
    if (detalleList) detalleList.innerHTML = '<div class="empty-state"><i class="fas fa-spinner fa-spin"></i><p>Cargando...</p></div>';

    const formData = new FormData();
    formData.append('btn_ver_detalle', '1');
    formData.append('hid_id_cronograma', crono.id_cronograma);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.text())
        .then(text => {
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { success: false }; }
            if (!detalleList) return;

            if (result.success && result.detalle && result.detalle.length > 0) {
                detalleList.innerHTML = result.detalle.map(d => `
                    <div class="cuota-row">
                        <span class="cuota-num">${escapeHtml(d.nom_tercero)}<br><small style="color:#94a3b8;font-weight:400">Factura #${d.id_factura}, Cuota ${d.id_cuota}</small></span>
                        <span class="cuota-fecha">${formatDate(d.fec_vencimiento)}</span>
                        <span class="cuota-valor">${formatCurrency(d.val_a_pagar)}</span>
                    </div>
                `).join('');
            } else {
                detalleList.innerHTML = '<p style="font-size:12px;color:#94a3b8;text-align:center;padding:12px">No se encontraron cuotas para este cronograma.</p>';
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
// 6. ELIMINAR CRONOGRAMA (función global)
// ============================================================
window.eliminarCronograma = function(id, nombre) {
    if (!confirm(`¿Desea eliminar el cronograma "${nombre}"?\nEsta acción es reversible desde la base de datos.`)) return;
    const formData = new FormData();
    formData.append('btn_eliminar', '1');
    formData.append('hid_del_id', id);
    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.text())
        .then(text => {
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { success: false, message: 'Respuesta inválida' }; }
            if (result.success) {
                showToast(result.message, 'success');
                location.reload();
            } else {
                showToast(result.message || 'Error al eliminar', 'error');
            }
        })
        .catch(() => showToast('Error de conexión', 'error'));
};

// ============================================================
// 7. FILTROS
// ============================================================
function applyFilters() {
    const query  = val('crono-search').toLowerCase().trim();
    const active = document.querySelector('.filter-toggle.active')?.dataset.filter ?? 'all';
    const rows   = document.querySelectorAll('#cronos-tbody tr:not(.empty-row)');
    let visibles = 0;

    rows.forEach(row => {
        const nombre = row.children[0]?.textContent.toLowerCase() ?? '';
        const matchesQuery  = !query || nombre.includes(query);
        const matchesFilter = active === 'all' || row.dataset.estado === active;
        const visible = matchesQuery && matchesFilter;
        row.style.display = visible ? '' : 'none';
        if (visible) visibles++;
    });

    setText('cronos-count', `${visibles} resultado${visibles !== 1 ? 's' : ''}`);

    const clearBtn = document.getElementById('btn-clear-filters');
    if (clearBtn) clearBtn.style.display = (query || active !== 'all') ? '' : 'none';
}

function clearFilters() {
    setVal('crono-search', '');
    document.querySelectorAll('.filter-toggle').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.filter-toggle[data-filter="all"]')?.classList.add('active');
    applyFilters();
}

// ============================================================
// 8. INICIALIZACIÓN GENERAL
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    initCronoModule();
});

function initCronoModule() {
    document.getElementById('btn-add-crono')?.addEventListener('click', openNewModal);

    document.getElementById('crono-search')?.addEventListener('input', applyFilters);
    document.getElementById('btn-clear-filters')?.addEventListener('click', clearFilters);

    document.querySelectorAll('.filter-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-toggle').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            applyFilters();
        });
    });

    // ---------- TOGGLE TABLA / CALENDARIO ----------
    document.querySelectorAll('.view-toggle').forEach(btn => {
        btn.addEventListener('click', () => switchView(btn.dataset.view));
    });
    document.getElementById('cal-prev-month')?.addEventListener('click', () => changeMonth(-1));
    document.getElementById('cal-next-month')?.addEventListener('click', () => changeMonth(1));
    document.getElementById('cal-today-btn')?.addEventListener('click', goToToday);
    document.getElementById('close-day-modal')?.addEventListener('click', closeDayModal);
    document.getElementById('close-day-btn')?.addEventListener('click', closeDayModal);
    document.getElementById('modal-day')?.addEventListener('click', e => {
        if (e.target.id === 'modal-day') closeDayModal();
    });

    document.querySelectorAll('.cuota-checkbox').forEach(cb => {
        cb.addEventListener('change', updateSeleccionCuotas);
    });

    document.querySelectorAll('.btn-close-new-modal, .btn-cancel-new-modal')
        .forEach(btn => btn.addEventListener('click', closeNewModal));
    document.querySelectorAll('.btn-close-edit-modal, .btn-cancel-edit-modal')
        .forEach(btn => btn.addEventListener('click', closeEditModal));

    document.getElementById('close-detail-modal')?.addEventListener('click', closeDetailModal);
    document.getElementById('close-detail-btn')?.addEventListener('click', closeDetailModal);

    document.getElementById('modal-new-crono')?.addEventListener('click', e => {
        if (e.target.id === 'modal-new-crono') closeNewModal();
    });
    document.getElementById('modal-edit-crono')?.addEventListener('click', e => {
        if (e.target.id === 'modal-edit-crono') closeEditModal();
    });
    document.getElementById('modal-detail')?.addEventListener('click', e => {
        if (e.target.id === 'modal-detail') closeDetailModal();
    });

    // ---------- SUBMIT: NUEVO CRONOGRAMA ----------
    document.getElementById('new-btn-save')?.addEventListener('click', async function() {
        const form = document.getElementById('new-crono-form');
        clearAllFieldErrors('new');
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
                showToast(result.message || 'Error al guardar', 'error');
            }
        } catch (err) {
            showToast('Error de conexión', 'error');
        }
    });

    // ---------- SUBMIT: EDITAR CRONOGRAMA ----------
    document.getElementById('edit-btn-save')?.addEventListener('click', async function() {
        const form = document.getElementById('edit-crono-form');
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
