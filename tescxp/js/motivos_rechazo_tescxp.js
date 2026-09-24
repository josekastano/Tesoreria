'use strict';

// ============================================================
// 1. HELPERS GENERALES (mismos que bancos.js)
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

// ============================================================
// 2. MODAL: NUEVO MOTIVO
// ============================================================
function openNewModal() {
    clearAllFieldErrors('new');
    setVal('new-id-motivo', '');
    setVal('new-des-motivo', '');
    setVal('new-cod-bancario', '');
    show('modal-new-motivo');
}

function closeNewModal() {
    hide('modal-new-motivo');
}

// ============================================================
// 3. MODAL: EDITAR MOTIVO
// ============================================================
function openEditModal(motivo) {
    clearAllFieldErrors('edit');
    setVal('edit-id-motivo-hid',     motivo.id_motivo_rechazo);
    setVal('edit-id-motivo-display', motivo.id_motivo_rechazo);
    setVal('edit-des-motivo',        motivo.des_motivo);
    setVal('edit-cod-bancario',      motivo.cod_bancario ?? '');
    show('modal-edit-motivo');
}

function closeEditModal() {
    hide('modal-edit-motivo');
}

window.openEditModal = openEditModal;

// ============================================================
// 4. FILTROS + PAGINACIÓN + ORDENAMIENTO (mismo sistema que Proveedores)
// ============================================================

// ---- ESTADO DE PAGINACIÓN ----
let motivosPage     = 1;
let motivosPageSize = 25; // debe coincidir con el <option selected> de #motivos-page-size

// Filas que pasan la búsqueda y el filtro Todos/Con código/Sin código (sin tener en cuenta la página)
function getFilteredMotivoRows() {
    const query  = val('motivo-search').toLowerCase().trim();
    const active = document.querySelector('#mod-motivos .filter-toggle.active')?.dataset.filter ?? 'all';
    const filas  = Array.from(document.querySelectorAll('#motivos-tbody tr:not(.empty-row)'));
    return filas.filter(fila => {
        const texto = [0, 1, 2].map(i => (fila.children[i]?.textContent ?? '').toLowerCase()).join(' ');
        const pasaTexto = !query || texto.includes(query);
        const pasaTipo  = active === 'all' || fila.dataset.tipo === active;
        return pasaTexto && pasaTipo;
    });
}

// Aplica búsqueda/tipo y vuelve a la primera página
function applyFilters() {
    motivosPage = 1;
    renderMotivosPage();

    const query    = val('motivo-search').trim();
    const active   = document.querySelector('#mod-motivos .filter-toggle.active')?.dataset.filter ?? 'all';
    const clearBtn = document.getElementById('btn-clear-filters');
    if (clearBtn) clearBtn.style.display = (query || active !== 'all') ? 'flex' : 'none';
}

function clearFilters() {
    setVal('motivo-search', '');
    document.querySelectorAll('#mod-motivos .filter-toggle').forEach(btn => btn.classList.remove('active'));
    document.querySelector('#mod-motivos .filter-toggle[data-filter="all"]')?.classList.add('active');
    applyFilters();
}

// Muestra solo las filas que pasan el filtro Y caen en la página actual
function renderMotivosPage() {
    const todas     = Array.from(document.querySelectorAll('#motivos-tbody tr:not(.empty-row)'));
    const coinciden = getFilteredMotivoRows();
    todas.forEach(fila => { fila.style.display = 'none'; });

    const total        = coinciden.length;
    const esTodos      = motivosPageSize === 'all';
    const size         = esTodos ? total : motivosPageSize;
    const totalPaginas = size > 0 ? Math.max(1, Math.ceil(total / size)) : 1;
    if (motivosPage > totalPaginas) motivosPage = totalPaginas;
    if (motivosPage < 1) motivosPage = 1;

    const start = esTodos ? 0 : (motivosPage - 1) * size;
    const end   = esTodos ? total : Math.min(start + size, total);
    coinciden.slice(start, end).forEach(fila => { fila.style.display = ''; });

    setText('motivos-count', `${total} resultado${total !== 1 ? 's' : ''}`);

    const rangeEl = document.getElementById('motivos-range');
    if (rangeEl) {
        rangeEl.textContent = total === 0 ? '0 de 0' : `${start + 1}–${end} de ${total}`;
    }

    const btnPrev = document.getElementById('motivos-prev');
    const btnNext = document.getElementById('motivos-next');
    if (btnPrev) btnPrev.disabled = motivosPage <= 1;
    if (btnNext) btnNext.disabled = esTodos || motivosPage >= totalPaginas;
}

function initMotivosPagination() {
    document.getElementById('motivos-page-size')?.addEventListener('change', function() {
        motivosPageSize = this.value === 'all' ? 'all' : parseInt(this.value, 10);
        motivosPage = 1;
        renderMotivosPage();
    });
    document.getElementById('motivos-prev')?.addEventListener('click', () => {
        motivosPage--;
        renderMotivosPage();
    });
    document.getElementById('motivos-next')?.addEventListener('click', () => {
        motivosPage++;
        renderMotivosPage();
    });
}

// ---- ORDENAMIENTO ASC / DESC ----
// El código bancario se compara con "numeric" (R2 < R10) y los motivos
// sin código quedan juntos al principio (asc) o al final (desc).
let motivosSortKey = null; // índice de columna, coincide con data-sort-key del <th>
let motivosSortDir = 'asc';

function sortMotivoRows(key, type) {
    const tbody = document.getElementById('motivos-tbody');
    if (!tbody) return;
    const filas = Array.from(tbody.querySelectorAll('tr:not(.empty-row)'));
    if (filas.length === 0) return;

    motivosSortDir = (motivosSortKey === key && motivosSortDir === 'asc') ? 'desc' : 'asc';
    motivosSortKey = key;

    const getValor = fila => {
        const celda = fila.cells[Number(key)];
        const crudo = celda?.dataset.sort;
        const base  = crudo !== undefined ? crudo : (celda?.textContent.trim() ?? '');
        return type === 'number' ? (parseFloat(base) || 0) : base.toLowerCase();
    };

    filas.sort((a, b) => {
        const va  = getValor(a);
        const vb  = getValor(b);
        const cmp = type === 'number' ? (va - vb) : va.localeCompare(vb, 'es', { numeric: true });
        return motivosSortDir === 'asc' ? cmp : -cmp;
    });
    filas.forEach(fila => tbody.appendChild(fila));

    document.querySelectorAll('#mod-motivos .data-table th.sortable').forEach(th => {
        const icon   = th.querySelector('.sort-icon');
        const activo = th.dataset.sortKey === key;
        th.classList.toggle('sort-active', activo);
        if (icon) {
            icon.className = activo
                ? `fas sort-icon ${motivosSortDir === 'asc' ? 'fa-caret-up' : 'fa-caret-down'}`
                : 'fas fa-caret-down sort-icon';
        }
    });

    motivosPage = 1;
    renderMotivosPage();
}

function initMotivosSorting() {
    document.querySelectorAll('#mod-motivos .data-table th.sortable').forEach(th => {
        th.addEventListener('click', () => sortMotivoRows(th.dataset.sortKey, th.dataset.sortType));
    });
}

// ---- ALTO DINÁMICO DE LA TABLA (evita el doble scroll) ----
function ajustarAlturaTablaMotivos() {
    const scrollBox = document.getElementById('motivos-table-scroll');
    if (!scrollBox) return;
    const footer = document.querySelector('#mod-motivos .table-footer');
    const top = scrollBox.getBoundingClientRect().top;
    const alturaFooter = footer ? footer.offsetHeight : 0;

    let margenInferior = 16;
    const contentCard = scrollBox.closest('.content-card') || document.querySelector('.content-card');
    if (contentCard) {
        const cs = getComputedStyle(contentCard);
        margenInferior += (parseFloat(cs.paddingBottom) || 0) + (parseFloat(cs.marginBottom) || 0);
    }

    const disponible = window.innerHeight - top - alturaFooter - margenInferior;
    scrollBox.style.maxHeight = Math.max(220, disponible) + 'px';
}

// ============================================================
// 5. ELIMINAR / RESTAURAR (borrado lógico, funciones globales)
// ============================================================
// ind_borrado es borrado lógico: el motivo deja de ofrecerse al registrar
// un rechazo, pero los pagos históricos que lo referencian lo conservan.
function enviarBorradoLogico(idMotivo, mensajeError) {
    const formData = new FormData();
    formData.append('btn_eliminar', '1');
    formData.append('hid_eliminar_id_motivo', idMotivo);

    fetch(window.location.href, {
        method: 'POST',
        body: formData
    })
    .then(res => res.text())
    .then(text => {
        let result;
        try { result = JSON.parse(text); } catch (e) { result = { success: false, message: 'Respuesta inválida' }; }
        if (result.success) {
            showToast(result.message, 'success');
            location.reload();
        } else {
            showToast(result.message || mensajeError, 'error');
        }
    })
    .catch(() => showToast('Error de conexión', 'error'));
}

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

window.eliminarMotivo = function(idMotivo, desMotivo) {
    abrirConfirmEliminar(
        `Eliminar "${desMotivo}"`,
        `¿Desea eliminar el motivo "${desMotivo}"? Dejará de ofrecerse al registrar un rechazo, pero los pagos históricos lo conservan.`,
        () => enviarBorradoLogico(idMotivo, 'Error al eliminar el motivo')
    );
};

// ============================================================
// 6. INICIALIZACIÓN GENERAL
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    initMotivosModule();
});

function initMotivosModule() {
    document.getElementById('btn-add-motivo')?.addEventListener('click', openNewModal);

    document.getElementById('motivo-search')?.addEventListener('input', applyFilters);
    document.getElementById('btn-clear-filters')?.addEventListener('click', clearFilters);

    document.querySelectorAll('#mod-motivos .filter-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('#mod-motivos .filter-toggle').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            applyFilters();
        });
    });

    // ---------- PAGINACIÓN Y ORDENAMIENTO ----------
    initMotivosPagination();
    initMotivosSorting();
    renderMotivosPage();
    ajustarAlturaTablaMotivos();
    window.addEventListener('resize', ajustarAlturaTablaMotivos);

    document.querySelectorAll('.btn-close-new-modal, .btn-cancel-new-modal')
        .forEach(btn => btn.addEventListener('click', closeNewModal));
    document.querySelectorAll('.btn-close-edit-modal, .btn-cancel-edit-modal')
        .forEach(btn => btn.addEventListener('click', closeEditModal));

    // ---------- MODAL: CONFIRMAR ELIMINACIÓN ----------
    document.getElementById('confirm-eliminar-cancel-btn')?.addEventListener('click', cerrarConfirmEliminar);
    document.getElementById('confirm-eliminar-ok-btn')?.addEventListener('click', () => {
        const accion = _confirmDeleteAction;
        cerrarConfirmEliminar();
        if (typeof accion === 'function') accion();
    });

    // Los modales NO se cierran al hacer clic por fuera (en el fondo oscuro):
    // así no se pierde lo que se lleva escrito en un formulario por un clic
    // accidental. Se cierran solo con la X, "Cancelar" o "Cerrar".

    // ---------- SUBMIT: NUEVO MOTIVO ----------
    document.getElementById('new-btn-save')?.addEventListener('click', async function() {
        const form = document.getElementById('new-motivo-form');
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

    // ---------- SUBMIT: EDITAR MOTIVO ----------
    document.getElementById('edit-btn-save')?.addEventListener('click', async function() {
        const form = document.getElementById('edit-motivo-form');
        clearAllFieldErrors('edit');
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
                closeEditModal();
                location.reload();
            } else if (result.errors && Object.keys(result.errors).length > 0) {
                Object.entries(result.errors).forEach(([spanId, mensaje]) => {
                    showFieldError(spanId, mensaje);
                });
            } else {
                showToast(result.message || 'Error al actualizar', 'error');
            }
        } catch (err) {
            showToast('Error de conexión', 'error');
        }
    });
}
