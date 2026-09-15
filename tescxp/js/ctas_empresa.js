'use strict';

// ============================================================
// 1. HELPERS GENERALES (mismos que tescxp.js)
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
// 2. MODAL: NUEVA CUENTA
// ============================================================
function openNewModal() {
    clearAllFieldErrors('new');
    setVal('new-id-banco', '');
    setVal('new-cta-empresa', '');
    setVal('new-tipocuenta', 'false');
    show('modal-new-cuenta');
}

function closeNewModal() {
    hide('modal-new-cuenta');
}

// ============================================================
// 3. MODAL: EDITAR CUENTA
// ============================================================
function openEditModal(cuenta) {
    clearAllFieldErrors('edit');
    setVal('edit-id-empresa-hid', cuenta.id_empresa);
    setVal('edit-cta-empresa-hid', cuenta.cta_empresa);
    setVal('edit-id-empresa-display', cuenta.id_empresa);
    setVal('edit-cta-empresa-display', cuenta.cta_empresa);
    setVal('edit-id-banco', cuenta.id_banco);
    const esCorriente = cuenta.ind_tipocuenta === 't' || cuenta.ind_tipocuenta === true;
    setVal('edit-tipocuenta', esCorriente ? 'true' : 'false');
    show('modal-edit-cuenta');
}

function closeEditModal() {
    hide('modal-edit-cuenta');
}

window.openEditModal = openEditModal;

// ============================================================
// 4. FILTROS
// ============================================================
function applyFilters() {
    const query  = val('cuenta-search').toLowerCase().trim();
    const active = document.querySelector('.filter-toggle.active')?.dataset.filter ?? 'all';
    const rows   = document.querySelectorAll('#cuentas-tbody tr:not(.empty-row)');
    let visibles = 0;

    rows.forEach(row => {
        const cuenta = row.children[1]?.textContent.toLowerCase() + ' ' + row.children[2]?.textContent.toLowerCase();
        const matchesQuery  = !query || cuenta.includes(query);
        const matchesFilter = active === 'all' || row.dataset.tipo === active;
        const visible = matchesQuery && matchesFilter;
        row.style.display = visible ? '' : 'none';
        if (visible) visibles++;
    });

    setText('cuentas-count', `${visibles} resultado${visibles !== 1 ? 's' : ''}`);

    const clearBtn = document.getElementById('btn-clear-filters');
    if (clearBtn) clearBtn.style.display = (query || active !== 'all') ? '' : 'none';
}

function clearFilters() {
    setVal('cuenta-search', '');
    document.querySelectorAll('.filter-toggle').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.filter-toggle[data-filter="all"]')?.classList.add('active');
    applyFilters();
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
// 5.1 ELIMINAR CUENTA (función global)
// ============================================================
window.eliminarCuenta = function(idEmpresa, ctaEmpresa, nombreBanco) {
    abrirConfirmEliminar(
        `Eliminar cuenta ${ctaEmpresa}`,
        `¿Desea eliminar la cuenta ${ctaEmpresa} (${nombreBanco})? Esta acción es reversible desde la base de datos.`,
        () => ejecutarEliminarCuenta(idEmpresa, ctaEmpresa)
    );
};

function ejecutarEliminarCuenta(idEmpresa, ctaEmpresa) {
    const formData = new FormData();
    formData.append('btn_eliminar', '1');
    formData.append('hid_del_id_empresa', idEmpresa);
    formData.append('hid_del_cta_empresa', ctaEmpresa);
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
// 6. INICIALIZACIÓN GENERAL
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    initCuentaModule();
});

function initCuentaModule() {
    document.getElementById('btn-add-cuenta')?.addEventListener('click', openNewModal);

    document.getElementById('cuenta-search')?.addEventListener('input', applyFilters);
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
    document.querySelectorAll('.btn-close-edit-modal, .btn-cancel-edit-modal')
        .forEach(btn => btn.addEventListener('click', closeEditModal));

    document.getElementById('modal-new-cuenta')?.addEventListener('click', e => {
        if (e.target.id === 'modal-new-cuenta') closeNewModal();
    });
    document.getElementById('modal-edit-cuenta')?.addEventListener('click', e => {
        if (e.target.id === 'modal-edit-cuenta') closeEditModal();
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

    // ---------- SUBMIT: NUEVA CUENTA ----------
    document.getElementById('new-btn-save')?.addEventListener('click', async function() {
        const form = document.getElementById('new-cuenta-form');
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

    // ---------- SUBMIT: EDITAR CUENTA ----------
    document.getElementById('edit-btn-save')?.addEventListener('click', async function() {
        const form = document.getElementById('edit-cuenta-form');
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
