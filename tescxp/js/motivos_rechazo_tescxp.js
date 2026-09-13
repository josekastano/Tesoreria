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

// ind_borrado llega como 't'/'f' desde PDO pgsql
function estaBorrado(valor) {
    return valor === 't' || valor === true || valor === 1 || valor === '1';
}

// ============================================================
// 2. MODAL: NUEVO MOTIVO
// ============================================================
function openNewModal() {
    clearAllFieldErrors('new');
    setVal('new-id-motivo', '');
    setVal('new-des-motivo', '');
    setVal('new-cod-bancario', '');
    setVal('new-estado', 'false');
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
    setVal('edit-estado', estaBorrado(motivo.ind_borrado) ? 'true' : 'false');
    show('modal-edit-motivo');
}

function closeEditModal() {
    hide('modal-edit-motivo');
}

window.openEditModal = openEditModal;

// ============================================================
// 4. FILTROS
// ============================================================
function applyFilters() {
    const query  = val('motivo-search').toLowerCase().trim();
    const active = document.querySelector('.filter-toggle.active')?.dataset.filter ?? 'all';
    const rows   = document.querySelectorAll('#motivos-tbody tr:not(.empty-row)');
    let visibles = 0;

    rows.forEach(row => {
        const texto = (row.children[0]?.textContent ?? '').toLowerCase() + ' '
                    + (row.children[1]?.textContent ?? '').toLowerCase() + ' '
                    + (row.children[2]?.textContent ?? '').toLowerCase();
        const matchesQuery  = !query || texto.includes(query);
        const matchesFilter = active === 'all' || row.dataset.tipo === active;
        const visible = matchesQuery && matchesFilter;
        row.style.display = visible ? '' : 'none';
        if (visible) visibles++;
    });

    setText('motivos-count', `${visibles} resultado${visibles !== 1 ? 's' : ''}`);

    const clearBtn = document.getElementById('btn-clear-filters');
    if (clearBtn) clearBtn.style.display = (query || active !== 'all') ? '' : 'none';
}

function clearFilters() {
    setVal('motivo-search', '');
    document.querySelectorAll('.filter-toggle').forEach(btn => btn.classList.remove('active'));
    document.querySelector('.filter-toggle[data-filter="all"]')?.classList.add('active');
    applyFilters();
}

// ============================================================
// 5. ACTIVAR / DESACTIVAR (borrado lógico, función global)
// ============================================================
window.toggleEstadoMotivo = function(idMotivo, nuevoBorrado) {
    const accion = nuevoBorrado ? 'desactivar' : 'activar';
    if (nuevoBorrado && !confirm(`¿Desea ${accion} este motivo de rechazo?\nDejará de ofrecerse al registrar un pago, pero los pagos históricos lo conservan.`)) return;

    const formData = new FormData();
    formData.append('btn_toggle_estado', '1');
    formData.append('hid_toggle_id_motivo', idMotivo);
    formData.append('hid_toggle_borrado', nuevoBorrado ? 'true' : 'false');

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
// 6. INICIALIZACIÓN GENERAL
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    initMotivosModule();
});

function initMotivosModule() {
    document.getElementById('btn-add-motivo')?.addEventListener('click', openNewModal);

    document.getElementById('motivo-search')?.addEventListener('input', applyFilters);
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

    document.getElementById('modal-new-motivo')?.addEventListener('click', e => {
        if (e.target.id === 'modal-new-motivo') closeNewModal();
    });
    document.getElementById('modal-edit-motivo')?.addEventListener('click', e => {
        if (e.target.id === 'modal-edit-motivo') closeEditModal();
    });

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
