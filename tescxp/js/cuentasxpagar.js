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
    return `${m}/${d}/${y}`;
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
// 2.1 ORDEN DE COMPRA DE LA FACTURA
// ============================================================
// Solo se listan las órdenes aprobadas sin factura. La búsqueda vive en su
// propio modal; el formulario solo guarda el id y muestra un resumen.
let ocPendiente = '';   // marcada dentro del modal, aún sin confirmar
let ocMetodo    = 'all';

function tieneOrdenCompra() {
    return document.querySelector('input[name="rad_tiene_oc"]:checked')?.value === 'si';
}

function esCredito(orden) {
    return orden.met_pago === 't' || orden.met_pago === true;
}

function buscarOrden(idOC) {
    return (ordenesData || []).find(o => String(o.id_ordencompra) === String(idOC));
}

// Nombre del proveedor de una orden para mostrar en el selector. La columna
// que devuelve la consulta se llama "proveedor_nombre" (no "nom_tercero").
// Si por cualquier motivo no viene, se muestra al menos el NIT del
// proveedor en vez de dejarlo en blanco (o mostrar "undefined").
function nombreProveedorOC(orden) {
    const nombre = (orden?.proveedor_nombre ?? '').trim();
    if (nombre) return nombre;
    return orden?.id_proveedor ? `Proveedor NIT ${orden.id_proveedor}` : 'Proveedor desconocido';
}

// Valor a mostrar junto a la orden: si ya tiene algo facturado (orden
// parcialmente usada), se muestra el saldo disponible en vez del total,
// para que quede claro cuánto queda realmente por facturar.
function valorMostrarOC(orden) {
    const facturado = Number(orden?.val_facturado ?? 0);
    if (facturado > 0 && orden?.val_saldo_disponible != null) {
        return `${formatCurrency(orden.val_saldo_disponible)} disponible`;
    }
    return formatCurrency(orden?.val_total);
}

// ---- Resumen en el formulario ----
function renderOCSelected() {
    const idOC = val('new-id-ordencompra');
    const orden = idOC ? buscarOrden(idOC) : null;
    const caja = document.getElementById('oc-selected');

    if (!orden) {
        caja?.classList.remove('sel');
        setText('oc-selected-num', 'Ninguna orden seleccionada');
        setText('oc-selected-meta', 'Busque la orden que corresponde a esta factura.');
        return;
    }

    caja?.classList.add('sel');
    setText('oc-selected-num', `OC #${orden.id_ordencompra} — ${nombreProveedorOC(orden)}`);
    setText('oc-selected-meta', `${formatDate(orden.fec_emision)} · ${orden.ciudad_nombre ?? ''} · ${esCredito(orden) ? 'Crédito' : 'Contado'} · ${valorMostrarOC(orden)}`);
}

function limpiarSeleccionOC() {
    setVal('new-id-ordencompra', '');
    ocPendiente = '';
    renderOCSelected();
}

// ---- Lista dentro del modal ----
function renderOrdenes() {
    const lista = document.getElementById('oc-list');
    if (!lista) return;

    const query  = val('oc-search').toLowerCase().trim();
    const prov   = val('oc-filtro-proveedor');
    const desde  = val('oc-fec-desde');
    const hasta  = val('oc-fec-hasta');

    const visibles = (ordenesData || []).filter(o => {
        const texto = `${o.id_ordencompra} ${nombreProveedorOC(o)}`.toLowerCase();
        if (query && !texto.includes(query)) return false;
        if (prov && o.id_proveedor !== prov) return false;
        if (desde && o.fec_emision < desde) return false;
        if (hasta && o.fec_emision > hasta) return false;
        if (ocMetodo === 'credito' && !esCredito(o)) return false;
        if (ocMetodo === 'contado' && esCredito(o)) return false;
        return true;
    });

    setText('oc-count', `${visibles.length} ${visibles.length === 1 ? 'orden' : 'órdenes'}`);

    // El proveedor bloqueado (por el proveedor ya elegido en el formulario)
    // no cuenta como "filtro activo" para mostrar el botón Limpiar, porque
    // Limpiar no puede (ni debe) quitarlo.
    const filtroProvEl  = document.getElementById('oc-filtro-proveedor');
    const provOpcional  = (filtroProvEl && !filtroProvEl.disabled) ? prov : '';

    const clearBtn = document.getElementById('btn-oc-clear');
    if (clearBtn) {
        clearBtn.style.display = (query || provOpcional || desde || hasta || ocMetodo !== 'all') ? '' : 'none';
    }

    if (visibles.length === 0) {
        lista.innerHTML = '<p class="oc-vacio">Ninguna orden coincide con estos filtros.</p>';
    } else {
        lista.innerHTML = visibles.map(o => `
            <label class="oc-row ${String(o.id_ordencompra) === ocPendiente ? 'sel' : ''}" data-id-oc="${escapeHtml(o.id_ordencompra)}">
                <input type="radio" name="rad_oc_sel" value="${escapeHtml(o.id_ordencompra)}"
                       ${String(o.id_ordencompra) === ocPendiente ? 'checked' : ''}>
                <span class="oc-row-info">
                    <span class="oc-row-num">OC #${escapeHtml(o.id_ordencompra)} — ${escapeHtml(nombreProveedorOC(o))}</span>
                    <span class="oc-row-meta">${formatDate(o.fec_emision)} · ${escapeHtml(o.ciudad_nombre ?? '')} · ${esCredito(o) ? 'Crédito' : 'Contado'}</span>
                </span>
                <span class="oc-row-valor">${valorMostrarOC(o)}</span>
            </label>
        `).join('');

        lista.querySelectorAll('.oc-row').forEach(row => {
            row.addEventListener('click', () => {
                ocPendiente = row.dataset.idOc;
                lista.querySelectorAll('.oc-row').forEach(r => r.classList.remove('sel'));
                row.classList.add('sel');
                const btn = document.getElementById('btn-confirm-oc');
                if (btn) btn.disabled = false;
            });
        });
    }

    const btn = document.getElementById('btn-confirm-oc');
    if (btn) btn.disabled = !ocPendiente;
}

function clearOCFilters() {
    setVal('oc-search', '');
    // El filtro de proveedor solo se limpia si no está bloqueado por el
    // proveedor ya elegido en el formulario principal.
    const filtroProv = document.getElementById('oc-filtro-proveedor');
    if (filtroProv && !filtroProv.disabled) {
        setVal('oc-filtro-proveedor', '');
    }
    setVal('oc-fec-desde', '');
    setVal('oc-fec-hasta', '');
    ocMetodo = 'all';
    document.querySelectorAll('.oc-toggle').forEach(b => b.classList.remove('active'));
    document.querySelector('.oc-toggle[data-met="all"]')?.classList.add('active');
    renderOrdenes();
}

// ---- Abrir / cerrar el modal del selector ----
function openOCModal() {
    ocPendiente = val('new-id-ordencompra');

    // El filtro arranca en el proveedor ya elegido para la factura. Si ya hay
    // un proveedor elegido, el filtro se BLOQUEA para que solo se puedan ver
    // y seleccionar órdenes de ese proveedor (antes se podía cambiar/borrar
    // el filtro y elegir una orden de otro proveedor, que luego pisaba
    // silenciosamente el proveedor del formulario). Si aún no hay proveedor
    // elegido, el filtro queda libre y la orden que se elija define el
    // proveedor de la factura, como ya funcionaba.
    const prov = val('new-id-proveedor');
    setVal('oc-search', '');
    setVal('oc-filtro-proveedor', prov || '');
    setVal('oc-fec-desde', '');
    setVal('oc-fec-hasta', '');
    ocMetodo = 'all';
    document.querySelectorAll('.oc-toggle').forEach(b => b.classList.remove('active'));
    document.querySelector('.oc-toggle[data-met="all"]')?.classList.add('active');

    const filtroProv = document.getElementById('oc-filtro-proveedor');
    if (filtroProv) filtroProv.disabled = !!prov;
    setOCProvLockHint(prov);

    renderOrdenes();
    show('modal-oc');
}

// Muestra un aviso cuando el filtro de proveedor está bloqueado por el
// proveedor ya elegido en el formulario principal.
function setOCProvLockHint(prov) {
    const hint = document.getElementById('oc-prov-lock-hint');
    if (!hint) return;
    if (prov) {
        const selProv = document.getElementById('new-id-proveedor');
        const nombre  = selProv?.selectedOptions?.[0]?.textContent?.trim() ?? '';
        hint.textContent = `Mostrando solo órdenes de ${nombre}. Para ver otras, cambie el proveedor de la factura.`;
        hint.style.display = '';
    } else {
        hint.textContent = '';
        hint.style.display = 'none';
    }
}

function closeOCModal() {
    hide('modal-oc');
}

function confirmOC() {
    if (!ocPendiente) return;

    const orden = buscarOrden(ocPendiente);
    setVal('new-id-ordencompra', ocPendiente);

    // La orden manda: su proveedor y su total pasan a la factura
    if (orden) {
        setVal('new-id-proveedor', orden.id_proveedor);
        setVal('new-val-factura', Number(orden.val_total));
        updateVencimientoPreview();
        updateCuotaPreview();
    }

    renderOCSelected();
    closeOCModal();
}

function toggleOCPicker() {
    const picker = document.getElementById('oc-picker');
    if (!picker) return;

    if (tieneOrdenCompra()) {
        picker.style.display = '';
        renderOCSelected();
        // Decir "sí" lleva directo a buscar la orden
        if (!val('new-id-ordencompra')) openOCModal();
    } else {
        picker.style.display = 'none';
        limpiarSeleccionOC();
    }
}

// ============================================================
// 3. MODAL: NUEVA FACTURA
// ============================================================
function openNewModal() {
    clearAllFieldErrors('new');
    const radNo = document.querySelector('input[name="rad_tiene_oc"][value="no"]');
    if (radNo) radNo.checked = true;
    limpiarSeleccionOC();
    toggleOCPicker();
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

    document.querySelectorAll('input[name="rad_tiene_oc"]')
        .forEach(r => r.addEventListener('change', toggleOCPicker));
    document.getElementById('btn-open-oc-modal')?.addEventListener('click', openOCModal);
    document.getElementById('close-oc-modal')?.addEventListener('click', closeOCModal);
    document.getElementById('cancel-oc-modal')?.addEventListener('click', closeOCModal);
    document.getElementById('btn-confirm-oc')?.addEventListener('click', confirmOC);
    document.getElementById('modal-oc')?.addEventListener('click', e => {
        if (e.target.id === 'modal-oc') closeOCModal();
    });

    ['oc-search', 'oc-filtro-proveedor', 'oc-fec-desde', 'oc-fec-hasta'].forEach(id => {
        const el = document.getElementById(id);
        el?.addEventListener(id === 'oc-search' ? 'input' : 'change', renderOrdenes);
    });
    document.getElementById('btn-oc-clear')?.addEventListener('click', clearOCFilters);

    document.querySelectorAll('.oc-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.oc-toggle').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            ocMetodo = this.dataset.met;
            renderOrdenes();
        });
    });

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
    document.getElementById('new-id-proveedor')?.addEventListener('change', function() {
        updateVencimientoPreview();
        // Una orden de otro proveedor ya no aplica a esta factura
        const orden = buscarOrden(val('new-id-ordencompra'));
        if (orden && orden.id_proveedor !== this.value) limpiarSeleccionOC();
    });
    document.getElementById('new-fec-emision')?.addEventListener('change', updateVencimientoPreview);
    document.getElementById('new-val-factura')?.addEventListener('input', updateCuotaPreview);
    document.getElementById('new-num-cuotas')?.addEventListener('input', updateCuotaPreview);

    // ---------- SUBMIT: NUEVA FACTURA ----------
    document.getElementById('new-btn-save')?.addEventListener('click', async function() {
        const form = document.getElementById('new-factura-form');
        clearAllFieldErrors('new');
        if (tieneOrdenCompra() && !val('new-id-ordencompra')) {
            showFieldError('err-new-oc', 'Seleccione la orden de compra de la factura.');
            return;
        }

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
