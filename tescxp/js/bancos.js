'use strict';

// ============================================================
// 1. HELPERS GENERALES (mismos que ctas_proveedores.js)
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
// 2. MODAL: NUEVO BANCO
// ============================================================
function openNewModal() {
    clearAllFieldErrors('new');
    setVal('new-id-banco', '');
    setVal('new-nom-banco', '');
    setVal('new-estado', 'true');
    show('modal-new-banco');
}

function closeNewModal() {
    hide('modal-new-banco');
}

// ============================================================
// 3. MODAL: EDITAR BANCO
// ============================================================
function openEditModal(banco) {
    clearAllFieldErrors('edit');
    setVal('edit-id-banco-hid', banco.id_banco);
    setVal('edit-id-banco-display', banco.id_banco);
    setVal('edit-nom-banco', banco.nom_banco);
    const esActivo = banco.ind_estado === 't' || banco.ind_estado === true;
    setVal('edit-estado', esActivo ? 'true' : 'false');
    show('modal-edit-banco');
}

function closeEditModal() {
    hide('modal-edit-banco');
}

window.openEditModal = openEditModal;

// ============================================================
// 4. FILTROS + PAGINACIÓN + ORDENAMIENTO (mismo sistema que Proveedores)
// ============================================================

// ---- ESTADO DE PAGINACIÓN ----
let bancosPage     = 1;
let bancosPageSize = 25; // debe coincidir con el <option selected> de #bancos-page-size

// Filas que pasan la búsqueda y el filtro Todos/Activos/Inactivos (sin tener en cuenta la página)
function getFilteredBancoRows() {
    const query  = val('banco-search').toLowerCase().trim();
    const active = document.querySelector('#mod-bancos .filter-toggle.active')?.dataset.filter ?? 'all';
    const filas  = Array.from(document.querySelectorAll('#bancos-tbody tr:not(.empty-row)'));
    return filas.filter(fila => {
        const texto = (fila.children[0]?.textContent.toLowerCase() ?? '') + ' '
                    + (fila.children[1]?.textContent.toLowerCase() ?? '');
        const pasaTexto  = !query || texto.includes(query);
        const pasaEstado = active === 'all' || fila.dataset.tipo === active;
        return pasaTexto && pasaEstado;
    });
}

// Aplica búsqueda/estado y vuelve a la primera página
function applyFilters() {
    bancosPage = 1;
    renderBancosPage();

    const query    = val('banco-search').trim();
    const active   = document.querySelector('#mod-bancos .filter-toggle.active')?.dataset.filter ?? 'all';
    const clearBtn = document.getElementById('btn-clear-filters');
    if (clearBtn) clearBtn.style.display = (query || active !== 'all') ? 'flex' : 'none';
}

function clearFilters() {
    setVal('banco-search', '');
    document.querySelectorAll('.filter-toggle').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.filter-toggle[data-filter="all"]')?.classList.add('active');
    applyFilters();
}

// Muestra solo las filas que pasan el filtro Y caen en la página actual
function renderBancosPage() {
    const todas     = Array.from(document.querySelectorAll('#bancos-tbody tr:not(.empty-row)'));
    const coinciden = getFilteredBancoRows();
    todas.forEach(fila => { fila.style.display = 'none'; });

    const total        = coinciden.length;
    const esTodos      = bancosPageSize === 'all';
    const size         = esTodos ? total : bancosPageSize;
    const totalPaginas = size > 0 ? Math.max(1, Math.ceil(total / size)) : 1;
    if (bancosPage > totalPaginas) bancosPage = totalPaginas;
    if (bancosPage < 1) bancosPage = 1;

    const start = esTodos ? 0 : (bancosPage - 1) * size;
    const end   = esTodos ? total : Math.min(start + size, total);
    coinciden.slice(start, end).forEach(fila => { fila.style.display = ''; });

    setText('bancos-count', `${total} resultado${total !== 1 ? 's' : ''}`);

    const rangeEl = document.getElementById('bancos-range');
    if (rangeEl) {
        rangeEl.textContent = total === 0 ? '0 de 0' : `${start + 1}–${end} de ${total}`;
    }

    const btnPrev = document.getElementById('bancos-prev');
    const btnNext = document.getElementById('bancos-next');
    if (btnPrev) btnPrev.disabled = bancosPage <= 1;
    if (btnNext) btnNext.disabled = esTodos || bancosPage >= totalPaginas;
}

function initBancosPagination() {
    document.getElementById('bancos-page-size')?.addEventListener('change', function() {
        bancosPageSize = this.value === 'all' ? 'all' : parseInt(this.value, 10);
        bancosPage = 1;
        renderBancosPage();
    });
    document.getElementById('bancos-prev')?.addEventListener('click', () => {
        bancosPage--;
        renderBancosPage();
    });
    document.getElementById('bancos-next')?.addEventListener('click', () => {
        bancosPage++;
        renderBancosPage();
    });
}

// ---- ORDENAMIENTO ASC / DESC ----
// El texto se compara en español y con "numeric": así los códigos con
// números quedan en orden natural (0000123 < 0001000 < 1234567) y las
// tildes no descuadran los nombres.
let bancosSortKey = null; // índice de columna, coincide con data-sort-key del <th>
let bancosSortDir = 'asc';

function sortBancoRows(key, type) {
    const tbody = document.getElementById('bancos-tbody');
    if (!tbody) return;
    const filas = Array.from(tbody.querySelectorAll('tr:not(.empty-row)'));
    if (filas.length === 0) return;

    bancosSortDir = (bancosSortKey === key && bancosSortDir === 'asc') ? 'desc' : 'asc';
    bancosSortKey = key;

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
        return bancosSortDir === 'asc' ? cmp : -cmp;
    });
    filas.forEach(fila => tbody.appendChild(fila));

    document.querySelectorAll('#mod-bancos .data-table th.sortable').forEach(th => {
        const icon   = th.querySelector('.sort-icon');
        const activo = th.dataset.sortKey === key;
        th.classList.toggle('sort-active', activo);
        if (icon) {
            icon.className = activo
                ? `fas sort-icon ${bancosSortDir === 'asc' ? 'fa-caret-up' : 'fa-caret-down'}`
                : 'fas fa-caret-down sort-icon';
        }
    });

    bancosPage = 1;
    renderBancosPage();
}

function initBancosSorting() {
    document.querySelectorAll('#mod-bancos .data-table th.sortable').forEach(th => {
        th.addEventListener('click', () => sortBancoRows(th.dataset.sortKey, th.dataset.sortType));
    });
}

// ---- ALTO DINÁMICO DE LA TABLA (evita el doble scroll) ----
function ajustarAlturaTablaBancos() {
    const scrollBox = document.getElementById('bancos-table-scroll');
    if (!scrollBox) return;
    const footer = document.querySelector('#mod-bancos .table-footer');
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
// 5. MODAL DE CONFIRMACIÓN DE ELIMINACIÓN (estilo módulo Compras)
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
// 5.1 ELIMINAR BANCO (borrado lógico, función global)
// ============================================================
window.eliminarBanco = function(idBanco, nombreBanco) {
    abrirConfirmEliminar(
        `Eliminar "${nombreBanco}"`,
        `¿Desea eliminar el banco ${nombreBanco} (${idBanco})? Esta acción es reversible desde la base de datos.`,
        () => ejecutarEliminarBanco(idBanco)
    );
};

function ejecutarEliminarBanco(idBanco) {
    const formData = new FormData();
    formData.append('btn_eliminar', '1');
    formData.append('hid_del_id_banco', idBanco);
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
            showToast(result.message || 'Error al eliminar', 'error');
        }
    })
    .catch(() => showToast('Error de conexión', 'error'));
}

// ============================================================
// 6. ACTIVAR / DESACTIVAR RÁPIDO (función global)
// ============================================================
window.toggleEstadoBanco = function(idBanco, nuevoEstado) {
    const formData = new FormData();
    formData.append('btn_toggle_estado', '1');
    formData.append('hid_toggle_id_banco', idBanco);
    formData.append('hid_toggle_estado', nuevoEstado ? 'true' : 'false');
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
            showToast(result.message || 'Error al actualizar el estado', 'error');
        }
    })
    .catch(() => showToast('Error de conexión', 'error'));
};

// ============================================================
// 7. INICIALIZACIÓN GENERAL
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    initBancosModule();
});

function initBancosModule() {
    document.getElementById('btn-add-banco')?.addEventListener('click', openNewModal);

    document.getElementById('banco-search')?.addEventListener('input', applyFilters);
    document.getElementById('btn-clear-filters')?.addEventListener('click', clearFilters);

    document.querySelectorAll('.filter-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-toggle').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            applyFilters();
        });
    });

    // ---------- PAGINACIÓN Y ORDENAMIENTO ----------
    initBancosPagination();
    initBancosSorting();
    renderBancosPage();
    ajustarAlturaTablaBancos();
    window.addEventListener('resize', ajustarAlturaTablaBancos);

    document.querySelectorAll('.btn-close-new-modal, .btn-cancel-new-modal')
        .forEach(btn => btn.addEventListener('click', closeNewModal));
    document.querySelectorAll('.btn-close-edit-modal, .btn-cancel-edit-modal')
        .forEach(btn => btn.addEventListener('click', closeEditModal));

    // Los modales NO se cierran al hacer clic por fuera (en el fondo oscuro):
    // así no se pierde lo que se lleva escrito en un formulario por un clic
    // accidental. Se cierran solo con la X, "Cancelar" o "Cerrar".

    // ---------- MODAL: CONFIRMAR ELIMINACIÓN ----------
    document.getElementById('confirm-eliminar-cancel-btn')?.addEventListener('click', cerrarConfirmEliminar);
    document.getElementById('confirm-eliminar-ok-btn')?.addEventListener('click', () => {
        const accion = _confirmDeleteAction;
        cerrarConfirmEliminar();
        if (typeof accion === 'function') accion();
    });

    // ---------- SUBMIT: NUEVO BANCO ----------
    document.getElementById('new-btn-save')?.addEventListener('click', async function() {
        const form = document.getElementById('new-banco-form');
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

    // ---------- SUBMIT: EDITAR BANCO ----------
    document.getElementById('edit-btn-save')?.addEventListener('click', async function() {
        const form = document.getElementById('edit-banco-form');
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
