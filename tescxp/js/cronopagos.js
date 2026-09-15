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

    // La barra de búsqueda y filtros solo aplica a la tabla
    document.getElementById('mod-cronograma')?.classList.toggle('vista-calendario', view === 'calendario');

    if (view === 'calendario') {
        renderCalendar();
    }
}

// ============================================================
// 2.1 CALENDARIO DE CRONOGRAMAS
// ============================================================
let calCurrentYear   = new Date().getFullYear();
let calCurrentMonth  = new Date().getMonth(); // 0-11
let calSelectedDate  = new Date().toISOString().slice(0, 10);
let calPrimerRender  = true;

const MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const MESES_CORTO = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];
const DIAS_SEMANA = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];

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

function estaPagado(crono) {
    return crono.ind_estado === 't' || crono.ind_estado === true;
}

function fechaISO(anio, mes, dia) {
    return `${anio}-${String(mes + 1).padStart(2, '0')}-${String(dia).padStart(2, '0')}`;
}

// ---- Panel izquierdo: el día seleccionado y sus cronogramas ----
function renderSidePanel() {
    const [anio, mes, dia] = calSelectedDate.split('-').map(Number);
    const fecha = new Date(anio, mes - 1, dia);

    setText('cal-sel-day', String(dia).padStart(2, '0'));
    setText('cal-sel-weekday', DIAS_SEMANA[fecha.getDay()]);
    setText('cal-sel-month', `${MESES[mes - 1]} ${anio}`);

    const cronosDia = getCronogramasPorFecha()[calSelectedDate] || [];
    const lista = document.getElementById('cal-side-list');
    const btn = document.getElementById('cal-side-detail');
    if (!lista) return;

    if (cronosDia.length === 0) {
        lista.innerHTML = '<li class="cal-side-vacio">No hay pagos programados este día.</li>';
        if (btn) btn.disabled = true;
        return;
    }

    lista.innerHTML = cronosDia.map(c => {
        const pagado = estaPagado(c);
        return `
            <li class="cal-side-item ${pagado ? 'pagado' : ''}">
                <i></i>
                <span>
                    <span class="cal-side-item-desc">${escapeHtml(c.nom_cronograma)}</span>
                    <span class="cal-side-item-meta">${formatCurrency(c.total_a_pagar)} · ${pagado ? 'Pagado' : 'Pendiente'}</span>
                </span>
            </li>`;
    }).join('');

    if (btn) btn.disabled = false;
}

// ---- Tira de meses del año en curso ----
function renderMonthStrip() {
    const strip = document.getElementById('cal-months');
    if (!strip) return;

    strip.innerHTML = MESES_CORTO.map((m, i) => `
        <button type="button" class="cal-month ${i === calCurrentMonth ? 'active' : ''}" data-mes="${i}">${m}</button>
    `).join('');

    strip.querySelectorAll('.cal-month').forEach(btn => {
        btn.addEventListener('click', () => {
            calCurrentMonth = Number(btn.dataset.mes);
            renderCalendar();
        });
    });
}

// Al cambiar de mes se selecciona su primer día con pagos programados, para
// que el panel izquierdo no quede vacío; si no hay ninguno, cae en el día 1.
function seleccionarDiaDelMes() {
    const cronosPorFecha = getCronogramasPorFecha();
    const prefijo = `${calCurrentYear}-${String(calCurrentMonth + 1).padStart(2, '0')}`;

    const tieneP    = (cronosPorFecha[calSelectedDate] || []).length > 0;
    const mismoMes  = calSelectedDate.startsWith(prefijo);

    // Se respeta la selección del usuario; solo se reubica al cambiar de mes
    // o en la primera carga, si el día de hoy no tiene nada programado.
    if (mismoMes && (tieneP || !calPrimerRender)) {
        calPrimerRender = false;
        return;
    }
    calPrimerRender = false;

    const conPagos = Object.keys(cronosPorFecha)
        .filter(f => f.startsWith(prefijo))
        .sort();

    calSelectedDate = conPagos[0] || `${prefijo}-01`;
}

function renderCalendar() {
    const grid = document.getElementById('calendar-grid');
    if (!grid) return;

    seleccionarDiaDelMes();

    setText('cal-year-label', calCurrentYear);
    renderMonthStrip();

    const cronosPorFecha = getCronogramasPorFecha();
    const primerDiaSemana = new Date(calCurrentYear, calCurrentMonth, 1).getDay(); // 0=Dom
    const diasEnMes = new Date(calCurrentYear, calCurrentMonth + 1, 0).getDate();
    const diasMesPrevio = new Date(calCurrentYear, calCurrentMonth, 0).getDate();
    const hoyStr = new Date().toISOString().slice(0, 10);

    // La rejilla siempre muestra seis semanas completas: los días del mes
    // vecino se dibujan atenuados en vez de dejar huecos.
    const celdas = [];

    for (let i = primerDiaSemana; i > 0; i--) {
        celdas.push({ dia: diasMesPrevio - i + 1, otroMes: true, fecha: null });
    }
    for (let dia = 1; dia <= diasEnMes; dia++) {
        celdas.push({ dia, otroMes: false, fecha: fechaISO(calCurrentYear, calCurrentMonth, dia) });
    }
    let siguiente = 1;
    while (celdas.length % 7 !== 0 || celdas.length < 42) {
        celdas.push({ dia: siguiente++, otroMes: true, fecha: null });
        if (celdas.length >= 42) break;
    }

    grid.innerHTML = celdas.map(celda => {
        if (celda.otroMes) {
            return `<div class="cal-day otro-mes"><span class="cal-day-num">${String(celda.dia).padStart(2, '0')}</span></div>`;
        }

        const cronosDia = cronosPorFecha[celda.fecha] || [];
        const clases = ['cal-day'];
        let dotHtml = '';

        if (cronosDia.length > 0) {
            clases.push('has-crono');
            const hayPendientes = cronosDia.some(c => !estaPagado(c));
            dotHtml = `<span class="cal-day-dot ${hayPendientes ? 'pendiente' : 'pagado'}"></span>`;
        }
        if (celda.fecha === hoyStr) clases.push('hoy');
        if (celda.fecha === calSelectedDate) clases.push('sel');

        return `
            <div class="${clases.join(' ')}" data-fecha="${celda.fecha}">
                <span class="cal-day-num">${String(celda.dia).padStart(2, '0')}</span>
                ${dotHtml}
            </div>`;
    }).join('');

    // Un clic selecciona el día: el detalle se muestra en el panel izquierdo
    grid.querySelectorAll('.cal-day.has-crono').forEach(dayEl => {
        dayEl.addEventListener('click', () => {
            calSelectedDate = dayEl.dataset.fecha;
            renderCalendar();
            renderSidePanel();
        });
    });

    renderSidePanel();
}

function changeYear(delta) {
    calCurrentYear += delta;
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
    window._cronoDetalleActual = crono;
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
                        ${!pagado ? `<button class="btn-icon-sm reject btn-del-detalle"
                            data-cronograma="${crono.id_cronograma}"
                            data-factura="${d.id_factura}"
                            data-cuota="${d.id_cuota}"
                            title="Eliminar cuota del cronograma">
                            <i class="fas fa-trash"></i>
                        </button>` : '<span></span>'}
                    </div>
                `).join('');

                // Conectar los botones de eliminar recién creados
                detalleList.querySelectorAll('.btn-del-detalle').forEach(btn => {
                    btn.addEventListener('click', () => eliminarDetalleCronograma(
                        btn.dataset.cronograma,
                        btn.dataset.factura,
                        btn.dataset.cuota
                    ));
                });
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
// 5.1 MODAL DE CONFIRMACIÓN DE ELIMINACIÓN (estilo módulo Compras)
// ============================================================
let _confirmDeleteAction = null;

function abrirConfirmEliminar(titulo, mensaje, accion) {
    setText('confirm-eliminar-title', titulo);
    setText('confirm-eliminar-body', mensaje);
    _confirmDeleteAction = accion;
    show('modal-confirm-eliminar');
}

function cerrarConfirmEliminar() {
    hide('modal-confirm-eliminar');
    _confirmDeleteAction = null;
}

// ============================================================
// 5.2 ELIMINAR LÍNEA DE DETALLE DEL CRONOGRAMA
// ============================================================
function eliminarDetalleCronograma(idCronograma, idFactura, idCuota) {
    abrirConfirmEliminar(
        `Eliminar Cuota ${idCuota}`,
        `¿Eliminar la Cuota ${idCuota} de la Factura #${idFactura} del cronograma? Esta acción no se puede deshacer.`,
        () => ejecutarEliminarDetalleCronograma(idCronograma, idFactura, idCuota)
    );
}

function ejecutarEliminarDetalleCronograma(idCronograma, idFactura, idCuota) {
    const formData = new FormData();
    formData.append('btn_eliminar_detalle', '1');
    formData.append('hid_det_id_cronograma', idCronograma);
    formData.append('hid_det_id_factura',    idFactura);
    formData.append('hid_det_id_cuota',      idCuota);

    fetch(window.location.href, { method: 'POST', body: formData })
        .then(res => res.text())
        .then(text => {
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { success: false, message: 'Respuesta inválida' }; }

            if (result.success) {
                showToast(result.message, 'success');
                // Recargar el detalle sin cerrar el modal
                const cronoActual = window._cronoDetalleActual;
                if (cronoActual) {
                    // Actualizar el total en el header del modal (el trigger lo recalculó en BD)
                    openDetailModal(cronoActual);
                }
            } else {
                showToast(result.message || 'Error al eliminar la cuota', 'error');
            }
        })
        .catch(() => showToast('Error de conexión', 'error'));
}

// ============================================================
// 6. ELIMINAR CRONOGRAMA (función global)
// ============================================================
window.eliminarCronograma = function(id, nombre) {
    abrirConfirmEliminar(
        `Eliminar "${nombre}"`,
        `¿Desea eliminar el cronograma "${nombre}"? Esta acción es reversible desde la base de datos.`,
        () => ejecutarEliminarCronograma(id)
    );
};

function ejecutarEliminarCronograma(id) {
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
}

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
    document.getElementById('cal-prev-year')?.addEventListener('click', () => changeYear(-1));
    document.getElementById('cal-next-year')?.addEventListener('click', () => changeYear(1));
    document.getElementById('cal-side-detail')?.addEventListener('click', () => openDayModal(calSelectedDate));
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

    // ---------- MODAL: CONFIRMAR ELIMINACIÓN ----------
    document.getElementById('confirm-eliminar-cancel-btn')?.addEventListener('click', cerrarConfirmEliminar);
    document.getElementById('confirm-eliminar-ok-btn')?.addEventListener('click', () => {
        const accion = _confirmDeleteAction;
        cerrarConfirmEliminar();
        if (typeof accion === 'function') accion();
    });
    document.getElementById('modal-confirm-eliminar')?.addEventListener('click', e => {
        if (e.target.id === 'modal-confirm-eliminar') cerrarConfirmEliminar();
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
