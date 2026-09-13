'use strict';

// ============================================================
// 1. ESTADO GLOBAL
// ============================================================
let currentDetailId = null;
let newGeoManual  = true;
let editGeoManual = true;
let newTabIndex  = 0;
let editTabIndex = 0;
let pendingDeleteId = null;  // para eliminar con modal

// Registro de instancias CustomSelect activas
const csInstances = {};

// ============================================================
// 2. CLASE CUSTOM SELECT (sin cambios)
// ============================================================
class CustomSelect {
    constructor(nativeId, opts = {}) {
        this.native = document.getElementById(nativeId);
        if (!this.native) return;
        this.mode = opts.mode ?? 'city';
        this.placeholder = opts.placeholder ?? 'Seleccione...';
        this._isOpen = false; 
        this._buildDOM();
        this._bindEvents();
        this._syncFromNative();
    }
    _buildDOM() {
        this.native.classList.add('cs-native');
        this.native.tabIndex = -1;
        this.wrapper = document.createElement('div');
        this.wrapper.className = 'cs-wrapper';
        this.trigger = document.createElement('div');
        this.trigger.className  = 'cs-trigger';
        this.trigger.tabIndex   = 0;
        this.trigger.setAttribute('role', 'combobox');
        this.trigger.setAttribute('aria-haspopup', 'listbox');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.triggerText = document.createElement('span');
        this.triggerText.className = 'cs-trigger-text placeholder';
        this.triggerText.textContent = this.placeholder;
        const chevron = document.createElement('span');
        chevron.className = 'cs-chevron';
        chevron.innerHTML = '&#9660;';
        this.trigger.append(this.triggerText, chevron);
        this.dropdown = document.createElement('div');
        this.dropdown.className = 'cs-dropdown';
        this.dropdown.setAttribute('role', 'listbox');
        const searchWrap = document.createElement('div');
        searchWrap.className = 'cs-search-wrap';
        const searchIcon = document.createElement('span');
        searchIcon.className = 'cs-search-icon';
        searchIcon.innerHTML = '&#128269;';
        this.searchInput = document.createElement('input');
        this.searchInput.type        = 'text';
        this.searchInput.className   = 'cs-search';
        this.searchInput.placeholder = 'Buscar...';
        this.searchInput.autocomplete = 'off';
        searchWrap.append(searchIcon, this.searchInput);
        this.list = document.createElement('div');
        this.list.className = 'cs-list';
        this.dropdown.append(searchWrap, this.list);
        this.native.parentNode.insertBefore(this.wrapper, this.native);
        this.wrapper.append(this.native, this.trigger, this.dropdown);
        this._buildOptions();
    }
    _buildOptions() {
        this.list.innerHTML = '';
        const query = this.searchInput?.value.toLowerCase().trim() ?? '';
        let anyVisible = false;
        Array.from(this.native.options).forEach(opt => {
            if (opt.value === '') return;
            const label = opt.textContent.trim();
            if (query && !label.toLowerCase().includes(query)) return;
            anyVisible = true;
            const item = document.createElement('div');
            item.className   = 'cs-option';
            item.dataset.val = opt.value;
            if (opt.selected) item.classList.add('selected');
            if (this.mode === 'prefix') {
                const parts = label.match(/^\+?(\d+)\s+(.+)$/);
                if (parts) {
                    const badge = document.createElement('span');
                    badge.className   = 'cs-prefix-tag';
                    badge.textContent = '+' + parts[1];
                    const name = document.createElement('span');
                    name.textContent = parts[2];
                    item.append(badge, name);
                } else {
                    item.textContent = label;
                }
            } else {
                item.textContent = label;
            }
            item.addEventListener('click', () => this._select(opt.value, label));
            this.list.appendChild(item);
        });
        if (!anyVisible) {
            const empty = document.createElement('div');
            empty.className   = 'cs-empty';
            empty.textContent = 'Sin resultados';
            this.list.appendChild(empty);
        }
    }
    _select(value, label) {
        this.native.value = value;
        this.native.dispatchEvent(new Event('change', { bubbles: true }));
        this.triggerText.classList.remove('placeholder');
        if (this.mode === 'prefix') {
            const parts = label.match(/^\+?(\d+)\s*(.*)$/);
            if (parts) {
                this.triggerText.innerHTML = '';
                const badge = document.createElement('span');
                badge.className   = 'cs-prefix-badge';
                badge.textContent = '+' + parts[1];
                this.triggerText.appendChild(badge);
            } else {
                this.triggerText.textContent = label;
            }
        } else {
            this.triggerText.textContent = label;
        }
        this.list.querySelectorAll('.cs-option').forEach(el => {
            el.classList.toggle('selected', el.dataset.val === value);
        });
        this._close();
    }
    _syncFromNative() {
        const selected = this.native.options[this.native.selectedIndex];
        if (!selected || selected.value === '') {
            this.triggerText.className   = 'cs-trigger-text placeholder';
            this.triggerText.textContent = this.placeholder;
            return;
        }
        this._select(selected.value, selected.textContent.trim());
    }
    _openDropdown() { 
        this._isOpen = true;
        this.trigger.classList.add('open');
        this.trigger.setAttribute('aria-expanded', 'true');
        this.dropdown.classList.add('open');
        this.searchInput.value = '';
        this._buildOptions();
        this.searchInput.focus();
        this._repositionIfNeeded();
    }
    _close() {
        this._isOpen = false; 
        this.trigger.classList.remove('open');
        this.trigger.setAttribute('aria-expanded', 'false');
        this.dropdown.classList.remove('open');
    }
    _toggleOpen() {
        if (this.dropdown.classList.contains('open')) {
            this._close();
        } else {
            this._openDropdown(); 
        }
    }
    _repositionIfNeeded() {
        const rect = this.dropdown.getBoundingClientRect();
        const vH   = window.innerHeight;
        if (rect.bottom > vH - 10) {
            this.dropdown.style.top    = 'auto';
            this.dropdown.style.bottom = 'calc(100% + 6px)';
        } else {
            this.dropdown.style.top    = 'calc(100% + 6px)';
            this.dropdown.style.bottom = 'auto';
        }
    }
    _bindEvents() {
        this.trigger.addEventListener('click', () => this._toggleOpen());
        this.trigger.addEventListener('keydown', e => {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); this._toggleOpen(); }
            if (e.key === 'Escape') this._close();
        });
        this.searchInput.addEventListener('input', () => this._buildOptions());
        this.searchInput.addEventListener('keydown', e => {
            if (e.key === 'Escape') { e.stopPropagation(); this._close(); this.trigger.focus(); }
        });
        document.addEventListener('click', e => {
            if (!this.wrapper.contains(e.target)) this._close();
        });
    }
    sync() { this._syncFromNative(); }
    reset() {
        this.native.value          = '';
        this.triggerText.className = 'cs-trigger-text placeholder';
        this.triggerText.textContent = this.placeholder;
        this._buildOptions();
    }
}

// ============================================================
// 3. FUNCIONES DE UTILIDAD
// ============================================================
function val(id)           { return document.getElementById(id)?.value ?? ''; }
function setVal(id, value) { const el = document.getElementById(id); if (el) el.value = value ?? ''; }
function setText(id, text) { const el = document.getElementById(id); if (el) el.textContent = text ?? ''; }
function show(id)          { document.getElementById(id)?.classList.remove('hidden'); }
function hide(id)          { document.getElementById(id)?.classList.add('hidden'); }
function setSelectVal(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? '';
}

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

function clearFieldError(spanId) {
    const el = document.getElementById(spanId);
    if (!el) return;
    clearTimeout(el._hideTimer);
    el.classList.remove('visible');
    el.textContent = '';
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

function formatCurrency(n) { return Number(n).toLocaleString('es-CO'); }

function escapeHtml(str) {
    return String(str)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#x27;');
}

function sanitizeText(str) {
    if (str === null || str === undefined) return '';
    return String(str)
        .replace(/[\x00]/g,    '')
        .replace(/'/g,         '')
        .replace(/"/g,         '')
        .replace(/;/g,         '')
        .replace(/--/g,        '')
        .replace(/\/\*/g,      '')
        .replace(/\*\//g,      '')
        .replace(/xp_/gi,      '')
        .replace(/DROP\s/gi,   '')
        .replace(/DELETE\s/gi, '')
        .replace(/INSERT\s/gi, '')
        .replace(/UPDATE\s/gi, '')
        .replace(/EXEC\s/gi,   '')
        .replace(/UNION\s/gi,  '')
        .replace(/SELECT\s/gi, '')
        .replace(/</g,  '&lt;')
        .replace(/>/g,  '&gt;')
        .trim();
}

// ============================================================
// 4. VALIDACIÓN DINÁMICA DE ID SEGÚN TIPO DE DOCUMENTO
// ============================================================
function actualizarValidacionID(prefix) {
    const tipoSelect = document.getElementById(`${prefix}-id-tipo`);
    const idInput = document.getElementById(`${prefix}-supplier-id`);
    const errorSpan = document.getElementById(`err-${prefix}-id`);
    
    if (!tipoSelect || !idInput) return;
    
    const tipo = tipoSelect.value;
    const soloNumeros = ['CC', 'TI', 'RC', 'NIT', 'NUIP'];
    
    if (soloNumeros.includes(tipo)) {
        idInput.placeholder = 'Ej: 900123456 (solo números)';
        idInput.pattern = '[0-9]{7,10}';
        idInput.title = 'Debe tener entre 7 y 10 dígitos numéricos';
    } else {
        idInput.placeholder = 'Ej: AB1234567 (alfanumérico)';
        idInput.pattern = '[A-Z0-9]{7,10}';
        idInput.title = 'Debe tener entre 7 y 10 caracteres alfanuméricos en mayúsculas';
    }
    
    if (errorSpan) clearFieldError(errorSpan.id);
}

// ============================================================
// 5. FUNCIONES DEL MÓDULO PROVEEDORES (AJAX)
// ============================================================

function initCustomSelects() {
    csInstances['new-prefijo-movil'] = new CustomSelect('new-prefijo-movil', {
        mode: 'prefix',
        placeholder: '+'
    });
    csInstances['edit-prefijo-movil'] = new CustomSelect('edit-prefijo-movil', {
        mode: 'prefix',
        placeholder: '+'
    });
    csInstances['new-supplier-ciudad'] = new CustomSelect('new-supplier-ciudad', {
        mode: 'city',
        placeholder: 'Seleccione ciudad...'
    });
    csInstances['edit-supplier-ciudad'] = new CustomSelect('edit-supplier-ciudad', {
        mode: 'city',
        placeholder: 'Seleccione ciudad...'
    });
}

function updateStats(data) {
    const total     = data.length;
    const activos   = data.filter(s => s.ind_estado === 't' || s.ind_estado === true).length;
    const inactivos = total - activos;
    const evaluados = data.filter(s => s.val_puntaje_total !== null && s.val_puntaje_total !== undefined);
    const promedio  = evaluados.length > 0
        ? Math.round(evaluados.reduce((a, s) => a + Number(s.val_puntaje_total), 0) / evaluados.length)
        : 0;
    setText('stat-total',     total);
    setText('stat-activos',   activos);
    setText('stat-inactivos', inactivos);
    setText('stat-promedio',  `${promedio}%`);
}

function applyFilters() {
    const query        = sanitizeText(document.getElementById('supplier-search')?.value ?? '').toLowerCase();
    const activeToggle = document.querySelector('.filter-toggle.active')?.dataset.filter ?? 'all';
    const filas = document.querySelectorAll('#suppliers-tbody tr:not(.empty-row)');
    let visible = 0;
    filas.forEach(fila => {
        const nit    = fila.cells[0]?.textContent.toLowerCase() ?? '';
        const sigla  = fila.cells[1]?.textContent.toLowerCase() ?? '';
        const estado = fila.cells[6]?.textContent.trim().toLowerCase() ?? '';
        const pasaTexto  = !query || nit.includes(query) || sigla.includes(query);
        const pasaEstado = activeToggle === 'all'
            || (activeToggle === 'active'   && estado.includes('activo') && !estado.includes('inactivo'))
            || (activeToggle === 'inactive' && estado.includes('inactivo'));
        const mostrar = pasaTexto && pasaEstado;
        fila.style.display = mostrar ? '' : 'none';
        if (mostrar) visible++;
    });
    setText('suppliers-count', `${visible} resultado${visible !== 1 ? 's' : ''}`);
    const btnClear = document.getElementById('btn-clear-filters');
    if (btnClear) btnClear.style.display = (query || activeToggle !== 'all') ? 'flex' : 'none';
}

function clearFilters() {
    const input = document.getElementById('supplier-search');
    if (input) input.value = '';
    document.querySelectorAll('.filter-toggle').forEach(b => b.classList.remove('active'));
    document.querySelector('.filter-toggle[data-filter="all"]')?.classList.add('active');
    document.querySelectorAll('#suppliers-tbody tr:not(.empty-row)')
        .forEach(fila => fila.style.display = '');
    const btnClear = document.getElementById('btn-clear-filters');
    if (btnClear) btnClear.style.display = 'none';
    setText('suppliers-count', `${suppliersData.length} resultado${suppliersData.length !== 1 ? 's' : ''}`);
}

// ============================================================
// 6. MODALES NUEVO / EDITAR / EVALUAR / DETALLE
// ============================================================

function openNewModal() {
    document.getElementById('new-supplier-form')?.reset();
    document.querySelectorAll('#modal-new-supplier .field-error')
        .forEach(el => clearFieldError(el.id));
    csInstances['new-prefijo-movil']?.reset();
    csInstances['new-supplier-ciudad']?.reset();
    actualizarValidacionID('new');
    newGeoManual = true;
    syncGeoToggle('new', true);
    goToTab('new', 0);
    show('modal-new-supplier');
    document.getElementById('new-supplier-id')?.focus();
}

function closeNewModal() {
    hide('modal-new-supplier');
}

function openEditModal(id) {
    const s = suppliersData.find(s => s.id_tercero === id);
    if (!s) {
        showToast('No se encontraron los datos del proveedor', 'error');
        return;
    }
    document.querySelectorAll('#modal-edit-supplier .field-error')
        .forEach(el => clearFieldError(el.id));
    setVal('hid-edit-id-tercero', s.id_tercero);
    setVal('hid-edit-ind-tipo',   s.ind_tipo_tercero);
    setVal('edit-id-tipo',        s.id_tipo        ?? '');
    setVal('edit-supplier-id',    s.id_tercero     ?? '');
    setVal('edit-tipo-tercero',
        (s.ind_tipo_tercero === 't' || s.ind_tipo_tercero === true)
            ? 'Jurídica' : 'Natural'
    );
    setSelectVal('edit-cat-tercero',       s.id_cat_tercero  ?? '');
    setVal('edit-supplier-razon',          s.nom_tercero     ?? '');
    setVal('edit-supplier-sigla',          s.val_sigla       ?? '');
    setVal('edit-supplier-email',          s.email           ?? '');
    setVal('edit-supplier-direccion',      s.direccion       ?? '');
    setVal('edit-supplier-tel-fijo',       s.tel_fijo        ?? '');
    setVal('edit-supplier-tel-movil',      s.tel_movil       ?? '');
    setSelectVal('edit-prefijo-movil', s.id_prefijo_movil ?? '');
    csInstances['edit-prefijo-movil']?.sync();
    setVal('edit-supplier-contacto',       s.nom_contacto    ?? '');
    setVal('edit-supplier-tel-contacto',   s.tel_contacto    ?? '');
    setVal('edit-supplier-nom-contab',     s.nom_cont_contab ?? '');
    setVal('edit-supplier-tel-contab',     s.tel_cont_contab ?? '');
    setVal('edit-supplier-deuda',          s.val_saldo_deuda    ?? 0);
    setVal('edit-supplier-dias',           s.ind_dias_pago      ?? 0);
    setVal('edit-supplier-tiempo-entrega', s.val_tiempo_entrega ?? 0);
    setSelectVal('edit-supplier-estado',
        (s.ind_estado === 't' || s.ind_estado === true) ? 'true' : 'false'
    );
    setSelectVal('edit-supplier-restriccion', s.id_restriccion ?? '');
    setSelectVal('edit-supplier-ciudad',   s.id_ciudad      ?? '');
    csInstances['edit-supplier-ciudad']?.sync();
    setVal('edit-supplier-lat',            s.val_latitud    ?? '');
    setVal('edit-supplier-lng',            s.val_longitud   ?? '');
    actualizarValidacionID('edit');
    editGeoManual = true;
    syncGeoToggle('edit', true);
    goToTab('edit', 0);
    show('modal-edit-supplier');
}

function closeEditModal() {
    hide('modal-edit-supplier');
}

function openEvalModal(idProv, idEva) {
    const s = suppliersData.find(s => s.id_tercero === idProv);
    if (!s) {
        showToast('No se encontraron los datos del proveedor', 'error');
        return;
    }
    const tieneEval = idEva !== '' && idEva !== null && idEva !== undefined;
    setText('eval-title',    tieneEval ? 'Actualizar Evaluación' : 'Evaluación de Desempeño');
    setText('eval-subtitle', s.nom_tercero ?? idProv);
    setVal('hid-eval-id-prov', idProv);
    setVal('hid-eval-id-eva',  tieneEval ? idEva : '');
    const calidad = tieneEval ? (s.val_punt_calidad ?? 80) : 80;
    const puntual = tieneEval ? (s.val_punt_puntual ?? 80) : 80;
    setVal('eval-calidad', calidad);
    setVal('eval-puntual', puntual);
    setVal('eval-obs',     tieneEval ? (s.val_coments ?? '') : '');
    updateEvalScore();
    clearFieldError('err-eval-obs');
    show('modal-eval');
}

function closeEvalModal() {
    hide('modal-eval');
}

function updateEvalScore() {
    const calidad  = Number(document.getElementById('eval-calidad')?.value ?? 80);
    const puntual  = Number(document.getElementById('eval-puntual')?.value ?? 80);
    const promedio = Math.round((calidad + puntual) / 2);
    setText('eval-calidad-val', `${calidad}%`);
    setText('eval-puntual-val', `${puntual}%`);
    setText('eval-score',       `${promedio}%`);
}

function openDetailModal(id) {
    const s = suppliersData.find(s => s.id_tercero === id);
    if (!s) return;
    currentDetailId = id;
    setText('detail-title', s.nom_tercero ?? 'Detalle Proveedor');

    // Badge de estado
    const esActivo = (s.ind_estado === 't' || s.ind_estado === true);
    const badgeEstado = esActivo
        ? '<span class="badge badge-active">Activo</span>'
        : '<span class="badge badge-inactive">Inactivo</span>';

    // Badge de restricción (si existe)
    const restriccion = s.nom_restriccion ?? 'Sin restricción';

    const container = document.getElementById('detail-content');
    if (!container) return;

    container.innerHTML = `
        <div class="detail-grid">
            <div class="detail-item">
                <div class="label">NIT / ID</div>
                <div class="value">${escapeHtml(s.id_tercero ?? '—')}</div>
            </div>
            <div class="detail-item">
                <div class="label">Sigla</div>
                <div class="value">${escapeHtml(s.val_sigla ?? '—')}</div>
            </div>
            <div class="detail-item" style="grid-column:1/-1">
                <div class="label">Razón Social</div>
                <div class="value">${escapeHtml(s.nom_tercero ?? '—')}</div>
            </div>
            <div class="detail-item">
                <div class="label">Email</div>
                <div class="value">${escapeHtml(s.email ?? '—')}</div>
            </div>
            <div class="detail-item">
                <div class="label">Estado</div>
                <div class="value">${badgeEstado}</div>
            </div>
            <div class="detail-item">
                <div class="label">Tipo de Persona</div>
                <div class="value">${(s.ind_tipo_tercero === 't' || s.ind_tipo_tercero === true) ? 'Jurídica' : 'Natural'}</div>
            </div>
            <div class="detail-item">
                <div class="label">Categoría</div>
                <div class="value">${escapeHtml(s.nom_cat_tercero ?? '—')}</div>
            </div>
            <div class="detail-item">
                <div class="label">Restricción</div>
                <div class="value">${escapeHtml(restriccion)}</div>
            </div>
            <div class="detail-item">
                <div class="label">Dirección</div>
                <div class="value">${escapeHtml(s.direccion ?? '—')}</div>
            </div>
            <div class="detail-item">
                <div class="label">Ciudad</div>
                <div class="value">${escapeHtml(s.nom_ciudad ?? '—')}</div>
            </div>
            <div class="detail-item">
                <div class="label">Teléfono Fijo</div>
                <div class="value">${escapeHtml(String(s.tel_fijo ?? '—'))}</div>
            </div>
            <div class="detail-item">
                <div class="label">Celular</div>
                <div class="value">
                    ${s.id_prefijo_movil ? '+' + s.id_prefijo_movil + ' ' : ''}${escapeHtml(String(s.tel_movil ?? '—'))}
                </div>
            </div>
            <div class="detail-item">
                <div class="label">Contacto Pedidos</div>
                <div class="value">${escapeHtml(s.nom_contacto ?? '—')}</div>
            </div>
            <div class="detail-item">
                <div class="label">Tel. Pedidos</div>
                <div class="value">${escapeHtml(String(s.tel_contacto ?? '—'))}</div>
            </div>
            <div class="detail-item">
                <div class="label">Contacto Contabilidad</div>
                <div class="value">${escapeHtml(s.nom_cont_contab ?? '—')}</div>
            </div>
            <div class="detail-item">
                <div class="label">Tel. Contabilidad</div>
                <div class="value">${escapeHtml(String(s.tel_cont_contab ?? '—'))}</div>
            </div>
            <div class="detail-item">
                <div class="label">Saldo Deuda</div>
                <div class="value">$${formatCurrency(s.val_saldo_deuda ?? 0)}</div>
            </div>
            <div class="detail-item">
                <div class="label">Días de Pago</div>
                <div class="value">${s.ind_dias_pago ?? 0} días</div>
            </div>
            <div class="detail-item">
                <div class="label">Tiempo Entrega</div>
                <div class="value">${s.val_tiempo_entrega ?? 0} días</div>
            </div>
            <div class="detail-item">
                <div class="label">Calificación</div>
                <div class="value">${s.val_puntaje_total != null ? s.val_puntaje_total + '%' : 'Sin evaluación'}</div>
            </div>
            <div class="detail-item" style="grid-column:1/-1">
                <div class="label">Ubicación (lat, lng)</div>
                <div class="value">${escapeHtml(String(s.val_latitud ?? '—'))}, ${escapeHtml(String(s.val_longitud ?? '—'))}</div>
            </div>
        </div>
    `;
    show('modal-detail');
}

function closeDetailModal() {
    hide('modal-detail');
    currentDetailId = null;
}

function editFromDetail() {
    const id = currentDetailId;
    closeDetailModal();
    if (id) openEditModal(id);
}

// ============================================================
// 7. GEOCODING
// ============================================================
function toggleGeo(prefix) {
    if (prefix === 'new') newGeoManual  = !newGeoManual;
    else                  editGeoManual = !editGeoManual;
    syncGeoToggle(prefix, prefix === 'new' ? newGeoManual : editGeoManual);
}

function syncGeoToggle(prefix, isManual) {
    document.getElementById(`${prefix}-geo-toggle`)?.classList.toggle('on', !isManual);
    const hint   = document.getElementById(`${prefix}-geo-hint`);
    const btnGeo = document.getElementById(`${prefix}-btn-geocode`);
    const lat    = document.getElementById(`${prefix}-supplier-lat`);
    const lng    = document.getElementById(`${prefix}-supplier-lng`);
    if (hint)   hint.style.display   = isManual ? 'none' : 'block';
    if (btnGeo) btnGeo.style.display = isManual ? 'none' : 'flex';
    if (lat)    lat.readOnly = !isManual;
    if (lng)    lng.readOnly = !isManual;
}

async function geocodeAddress(prefix) {
    const direccion = sanitizeText(val(`${prefix}-supplier-direccion`));
    if (!direccion) {
        showFieldError(`err-${prefix}-geocode`, 'Ingrese una dirección en la pestaña Contacto primero');
        return;
    }
    clearFieldError(`err-${prefix}-geocode`);
    const btn = document.getElementById(`${prefix}-btn-geocode`);
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Buscando...'; }
    try {
        const url  = `https://nominatim.openstreetmap.org/search?q=${encodeURIComponent(direccion)}&format=json&limit=1&countrycodes=co`;
        const res  = await fetch(url, { headers: { 'Accept-Language': 'es', 'User-Agent': 'ERP-ADSO/1.0' } });
        const data = await res.json();
        if (data.length === 0) {
            showFieldError(`err-${prefix}-geocode`, 'No se encontraron coordenadas. Intente una dirección más general.');
            return;
        }
        setVal(`${prefix}-supplier-lat`, parseFloat(data[0].lat).toFixed(8));
        setVal(`${prefix}-supplier-lng`, parseFloat(data[0].lon).toFixed(8));
        showToast('Coordenadas encontradas correctamente');
    } catch (e) {
        showFieldError(`err-${prefix}-geocode`, 'Error al consultar el servicio de mapas. Verifique su conexión.');
        console.error('Nominatim error:', e);
    } finally {
        if (btn) { btn.disabled = false; btn.innerHTML = '<i class="fas fa-search-location"></i> Buscar coordenadas por dirección'; }
    }
}

// ============================================================
// 8. NAVEGACIÓN DE PESTAÑAS
// ============================================================
function initTabNavigation(prefix) {
    const TOTAL = 4;
    document.querySelectorAll(`#modal-${prefix}-supplier .modal-tab`).forEach((tab, idx) => {
        tab.addEventListener('click', () => {
            const cur = prefix === 'new' ? newTabIndex : editTabIndex;
            if (idx < cur) {
                goToTab(prefix, idx);
            } else if (idx > cur && validateTab(prefix, cur)) {
                goToTab(prefix, idx);
            }
        });
    });
    document.getElementById(`${prefix}-btn-next`)?.addEventListener('click', () => {
        const cur = prefix === 'new' ? newTabIndex : editTabIndex;
        if (validateTab(prefix, cur)) goToTab(prefix, cur + 1);
    });
    document.getElementById(`${prefix}-btn-prev`)?.addEventListener('click', () => {
        const cur = prefix === 'new' ? newTabIndex : editTabIndex;
        goToTab(prefix, cur - 1);
    });
}

function goToTab(prefix, idx) {
    const TOTAL = 4;
    if (idx < 0 || idx >= TOTAL) return;
    if (prefix === 'new')  newTabIndex  = idx;
    else                   editTabIndex = idx;
    document.querySelectorAll(`#modal-${prefix}-supplier .modal-tab`)
        .forEach((t, i) => t.classList.toggle('active', i === idx));
    document.querySelectorAll(`#modal-${prefix}-supplier .tab-content`)
        .forEach((c, i) => c.classList.toggle('active', i === idx));
    const btnPrev = document.getElementById(`${prefix}-btn-prev`);
    const btnNext = document.getElementById(`${prefix}-btn-next`);
    const btnSave = document.getElementById(`${prefix}-btn-save`);
    if (btnPrev) btnPrev.style.display = idx > 0            ? 'flex' : 'none';
    if (btnNext) btnNext.style.display = idx < TOTAL - 1    ? 'flex' : 'none';
    if (btnSave) btnSave.style.display = idx === TOTAL - 1  ? 'flex' : 'none';
}

// ============================================================
// 9. VALIDACIÓN DE PESTAÑAS
// ============================================================
function validateTab(prefix, tabIdx) {
    const AUTO_HIDE_MS = 4000;
    let valid = true;
    const p = prefix;
    const tabId = `${p}-tab-${tabIdx + 1}`;
    document.querySelectorAll(`#${tabId} .field-error`).forEach(el => clearFieldError(el.id));

    if (tabIdx === 0) {
        if (!val(`${p}-id-tipo`)) {
            valid = false;
            showFieldError(`err-${p}-id-tipo`, 'Seleccione el tipo de documento', AUTO_HIDE_MS);
        }

        if (prefix === 'new') {
            const idVal = sanitizeText(val(`${p}-supplier-id`)).trim();
            const tipo = val(`${p}-id-tipo`);
            const soloNumeros = ['CC', 'TI', 'RC', 'NIT', 'NUIP'];

            if (!idVal) {
                valid = false;
                showFieldError(`err-${p}-id`, 'El NIT / identificación es obligatorio', AUTO_HIDE_MS);
            } else {
                if (soloNumeros.includes(tipo)) {
                    if (!/^[0-9]{7,10}$/.test(idVal)) {
                        valid = false;
                        showFieldError(`err-${p}-id`, 'Debe tener entre 7 y 10 dígitos numéricos', AUTO_HIDE_MS);
                    }
                } else {
                    if (!/^[A-Z0-9]{7,10}$/.test(idVal.toUpperCase())) {
                        valid = false;
                        showFieldError(`err-${p}-id`, 'Debe tener entre 7 y 10 caracteres alfanuméricos en mayúsculas', AUTO_HIDE_MS);
                    }
                }
            }

            if (!val(`${p}-tipo-tercero`)) {
                valid = false;
                showFieldError(`err-${p}-tipo-tercero`, 'Seleccione el tipo de persona', AUTO_HIDE_MS);
            }
        }

        if (!val(`${p}-cat-tercero`)) {
            valid = false;
            showFieldError(`err-${p}-cat-tercero`, 'Seleccione una categoría', AUTO_HIDE_MS);
        }
        const razon = sanitizeText(val(`${p}-supplier-razon`)).trim();
        if (!razon) {
            valid = false;
            showFieldError(`err-${p}-razon`, 'La razón social es obligatoria', AUTO_HIDE_MS);
        } else if (razon.length < 4 || razon.length > 50) {
            valid = false;
            showFieldError(`err-${p}-razon`, 'Debe tener entre 4 y 50 caracteres', AUTO_HIDE_MS);
        }
        const sigla = sanitizeText(val(`${p}-supplier-sigla`)).trim();
        if (!sigla) {
            valid = false;
            showFieldError(`err-${p}-sigla`, 'La sigla es obligatoria', AUTO_HIDE_MS);
        } else if (sigla.length < 2 || sigla.length > 10) {
            valid = false;
            showFieldError(`err-${p}-sigla`, 'Debe tener entre 2 y 10 caracteres', AUTO_HIDE_MS);
        }
    }
    else if (tabIdx === 1) {
        const email = sanitizeText(val(`${p}-supplier-email`)).trim();
        if (!email) {
            valid = false;
            showFieldError(`err-${p}-email`, 'El email corporativo es obligatorio', AUTO_HIDE_MS);
        } else if (!/^[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}$/.test(email)) {
            valid = false;
            showFieldError(`err-${p}-email`, 'Ingrese un email válido', AUTO_HIDE_MS);
        }
        if (!sanitizeText(val(`${p}-supplier-direccion`)).trim()) {
            valid = false;
            showFieldError(`err-${p}-direccion`, 'La dirección es obligatoria', AUTO_HIDE_MS);
        }
        const telFijo = val(`${p}-supplier-tel-fijo`).trim();
        if (telFijo && !/^[0-9]{7,10}$/.test(telFijo)) {
            valid = false;
            showFieldError(`err-${p}-tel-fijo`, 'El teléfono fijo debe tener entre 7 y 10 dígitos', AUTO_HIDE_MS);
        }
        const telMovil   = val(`${p}-supplier-tel-movil`).trim();
        const prefijoVal = val(`${p}-prefijo-movil`);
        if (telMovil && !/^[0-9]{7,10}$/.test(telMovil)) {
            valid = false;
            showFieldError(`err-${p}-tel-movil`, 'El celular debe tener entre 7 y 10 dígitos', AUTO_HIDE_MS);
        }
        if (telMovil && !prefijoVal) {
            valid = false;
            showFieldError(`err-${p}-tel-movil`, 'Seleccione el prefijo (+código país) para el celular', AUTO_HIDE_MS);
        }
        if (prefijoVal && !telMovil) {
            valid = false;
            showFieldError(`err-${p}-tel-movil`, 'Si selecciona el prefijo debe ingresar el número de celular', AUTO_HIDE_MS);
        }
        const contacto = sanitizeText(val(`${p}-supplier-contacto`)).trim();
        if (!contacto) {
            valid = false;
            showFieldError(`err-${p}-contacto`, 'El nombre de contacto es obligatorio', AUTO_HIDE_MS);
        } else if (contacto.length < 5 || contacto.length > 60) {
            valid = false;
            showFieldError(`err-${p}-contacto`, 'Debe tener entre 5 y 60 caracteres', AUTO_HIDE_MS);
        }
        const telContacto = val(`${p}-supplier-tel-contacto`).trim();
        if (!telContacto) {
            valid = false;
            showFieldError(`err-${p}-tel-contacto`, 'El teléfono de contacto es obligatorio', AUTO_HIDE_MS);
        } else if (!/^[0-9]{1,10}$/.test(telContacto)) {
            valid = false;
            showFieldError(`err-${p}-tel-contacto`, 'Ingrese un número de máximo 10 dígitos', AUTO_HIDE_MS);
        }
        const nomContab = sanitizeText(val(`${p}-supplier-nom-contab`)).trim();
        if (!nomContab) {
            valid = false;
            showFieldError(`err-${p}-nom-contab`, 'El nombre de contabilidad es obligatorio', AUTO_HIDE_MS);
        } else if (nomContab.length < 5 || nomContab.length > 60) {
            valid = false;
            showFieldError(`err-${p}-nom-contab`, 'Debe tener entre 5 y 60 caracteres', AUTO_HIDE_MS);
        }
        const telContab = val(`${p}-supplier-tel-contab`).trim();
        if (!telContab) {
            valid = false;
            showFieldError(`err-${p}-tel-contab`, 'El teléfono de contabilidad es obligatorio', AUTO_HIDE_MS);
        } else if (!/^[0-9]{1,10}$/.test(telContab)) {
            valid = false;
            showFieldError(`err-${p}-tel-contab`, 'Ingrese un número de máximo 10 dígitos', AUTO_HIDE_MS);
        }
    }
    else if (tabIdx === 2) {
        const deuda = Number(val(`${p}-supplier-deuda`));
        if (isNaN(deuda) || deuda < 0 || deuda > 99999999999) {
            valid = false;
            showFieldError(`err-${p}-deuda`, 'El saldo debe estar entre 0 y 99.999.999.999', AUTO_HIDE_MS);
        }
        const dias = Number(val(`${p}-supplier-dias`));
        if (isNaN(dias) || dias < 0 || dias > 90) {
            valid = false;
            showFieldError(`err-${p}-dias`, 'Los días de pago deben estar entre 0 y 90', AUTO_HIDE_MS);
        }
        const tiempo = Number(val(`${p}-supplier-tiempo-entrega`));
        if (isNaN(tiempo) || tiempo < 0 || tiempo > 365) {
            valid = false;
            showFieldError(`err-${p}-tiempo-entrega`, 'El tiempo de entrega debe estar entre 0 y 365 días', AUTO_HIDE_MS);
        }
        if (!val(`${p}-supplier-restriccion`)) {
            valid = false;
            showFieldError(`err-${p}-restriccion`, 'Seleccione una restricción', AUTO_HIDE_MS);
        }
    }
    else if (tabIdx === 3) {
        if (!val(`${p}-supplier-ciudad`)) {
            valid = false;
            showFieldError(`err-${p}-ciudad`, 'Seleccione una ciudad', AUTO_HIDE_MS);
        }
        const latStr = val(`${p}-supplier-lat`).trim();
        if (latStr) {
            const lat = parseFloat(latStr);
            if (isNaN(lat) || lat < -4 || lat > 80) {
                valid = false;
                showFieldError(`err-${p}-lat`, 'La latitud debe estar entre -4 y 80 (Colombia)', AUTO_HIDE_MS);
            }
        }
        const lngStr = val(`${p}-supplier-lng`).trim();
        if (lngStr) {
            const lng = parseFloat(lngStr);
            if (isNaN(lng) || lng < -80 || lng > -50) {
                valid = false;
                showFieldError(`err-${p}-lng`, 'La longitud debe estar entre -80 y -50 (Colombia)', AUTO_HIDE_MS);
            }
        }
    }
    return valid;
}

// ============================================================
// 10. ENVÍO DE FORMULARIOS VÍA FETCH (AJAX)
// ============================================================

// --- Nuevo Proveedor ---
document.addEventListener('DOMContentLoaded', function() {
    const newTipo = document.getElementById('new-id-tipo');
    const editTipo = document.getElementById('edit-id-tipo');

    if (newTipo) {
        newTipo.addEventListener('change', function() {
            actualizarValidacionID('new');
        });
        actualizarValidacionID('new');
    }

    if (editTipo) {
        editTipo.addEventListener('change', function() {
            actualizarValidacionID('edit');
        });
        actualizarValidacionID('edit');
    }

    const newForm = document.getElementById('new-supplier-form');
    if (newForm) {
        newForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!validateTab('new', newTabIndex)) return;

            const formData = new FormData(this);
            const btn = document.getElementById('new-btn-save');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error('Error al parsear JSON:', parseError);
                    showToast('El servidor devolvió una respuesta inválida. Revisa la consola.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Guardar Proveedor';
                    return;
                }

                if (result.success) {
                    showToast(result.message, 'success');
                    closeNewModal();
                    location.reload();
                } else {
                    if (result.errors && Object.keys(result.errors).length > 0) {
                        for (const [fieldId, msg] of Object.entries(result.errors)) {
                            showFieldError(fieldId, msg);
                        }
                    } else {
                        showToast(result.message || 'Error al guardar el proveedor.', 'error');
                    }
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Guardar Proveedor';
                }
            } catch (error) {
                console.error('Error en fetch:', error);
                showToast('Error de conexión o servidor.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Guardar Proveedor';
            }
        });
    }

    // --- Editar Proveedor ---
    const editForm = document.getElementById('edit-supplier-form');
    if (editForm) {
        editForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            if (!validateTab('edit', editTabIndex)) return;

            const formData = new FormData(this);
            const btn = document.getElementById('edit-btn-save');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualizando...';

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const text = await response.text();
                let result;
                try {
                    result = JSON.parse(text);
                } catch (parseError) {
                    console.error('Error al parsear JSON:', parseError);
                    showToast('El servidor devolvió una respuesta inválida. Revisa la consola.', 'error');
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Actualizar Proveedor';
                    return;
                }

                if (result.success) {
                    showToast(result.message, 'success');
                    closeEditModal();
                    location.reload();
                } else {
                    if (result.errors && Object.keys(result.errors).length > 0) {
                        for (const [fieldId, msg] of Object.entries(result.errors)) {
                            showFieldError(fieldId, msg);
                        }
                    } else {
                        showToast(result.message || 'Error al actualizar el proveedor.', 'error');
                    }
                    btn.disabled = false;
                    btn.innerHTML = '<i class="fas fa-save"></i> Actualizar Proveedor';
                }
            } catch (error) {
                console.error('Error en fetch:', error);
                showToast('Error de conexión o servidor.', 'error');
                btn.disabled = false;
                btn.innerHTML = '<i class="fas fa-save"></i> Actualizar Proveedor';
            }
        });
    }

    // --- Evaluación ---
    const evalForm = document.getElementById('eval-form');
    if (evalForm) {
        const evalBtn = document.getElementById('eval-btn-save');
        if (evalBtn) {
            evalBtn.addEventListener('click', async function(e) {
                e.preventDefault();
                const formData = new FormData(evalForm);
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        body: formData
                    });

                    const text = await response.text();
                    let result;
                    try {
                        result = JSON.parse(text);
                    } catch (parseError) {
                        console.error('Error al parsear JSON:', parseError);
                        showToast('El servidor devolvió una respuesta inválida. Revisa la consola.', 'error');
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-save"></i> Guardar Evaluación';
                        return;
                    }

                    if (result.success) {
                        showToast(result.message, 'success');
                        closeEvalModal();
                        location.reload();
                    } else {
                        if (result.errors && Object.keys(result.errors).length > 0) {
                            for (const [fieldId, msg] of Object.entries(result.errors)) {
                                showFieldError(fieldId, msg);
                            }
                        } else {
                            showToast(result.message || 'Error al guardar la evaluación.', 'error');
                        }
                        this.disabled = false;
                        this.innerHTML = '<i class="fas fa-save"></i> Guardar Evaluación';
                    }
                } catch (error) {
                    console.error('Error en fetch:', error);
                    showToast('Error de conexión o servidor.', 'error');
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-save"></i> Guardar Evaluación';
                }
            });
        }
    }

    // ============================================================
    // 11. ELIMINAR PROVEEDOR CON MODAL PERSONALIZADO
    // ============================================================
    window.confirmarEliminar = function(id, nombre) {
        pendingDeleteId = id;
        document.getElementById('confirm-title').innerText  = `Eliminar "${nombre}"`;
        document.getElementById('confirm-body').innerHTML   = 'El proveedor será marcado como eliminado. ¿Deseas continuar?';
        document.getElementById('confirm-ok-btn').innerText  = 'Sí, eliminar';
        document.getElementById('confirm-ok-btn').style.backgroundColor = '#ef4444';
        document.getElementById('modal-confirm').classList.remove('hidden');
    };

    async function ejecutarEliminar() {
        if (!pendingDeleteId) return;
        const id = pendingDeleteId;
        const formData = new FormData();
        formData.append('btn_eliminar', '1');
        formData.append('hid_del_id', id);

        const btn = document.getElementById('confirm-ok-btn');
        const originalHTML = btn?.innerHTML ?? '';
        if (btn) { btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Eliminando...'; }

        try {
            const response = await fetch(window.location.href, { method: 'POST', body: formData });
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); } catch (e) { result = { success: false, message: 'Respuesta inválida' }; }

            if (result.success) {
                showToast(result.message, 'success');
                cerrarConfirmacion();
                location.reload();
            } else {
                showToast(result.message || 'Error al eliminar', 'error');
            }
        } catch (error) {
            showToast('Error de conexión', 'error');
        } finally {
            if (btn) { btn.disabled = false; btn.innerHTML = originalHTML; }
        }
    }

    function cerrarConfirmacion() {
        document.getElementById('modal-confirm').classList.add('hidden');
        pendingDeleteId = null;
    }

    // Eventos del modal de confirmación
    document.getElementById('confirm-cancel-btn')?.addEventListener('click', cerrarConfirmacion);
    document.getElementById('confirm-ok-btn')?.addEventListener('click', ejecutarEliminar);
    document.getElementById('modal-confirm')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) cerrarConfirmacion();
    });

    // ============================================================
    // 12. INICIALIZACIÓN GENERAL
    // ============================================================
    initCustomSelects();
    initSupplierModule();
});

function initSupplierModule() {
    document.getElementById('btn-add-supplier')?.addEventListener('click', openNewModal);

    document.getElementById('supplier-search')?.addEventListener('input', applyFilters);
    document.querySelectorAll('.filter-toggle').forEach(btn => {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.filter-toggle').forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            applyFilters();
        });
    });
    document.getElementById('btn-clear-filters')?.addEventListener('click', clearFilters);

    document.querySelectorAll('.btn-close-new-modal, .btn-cancel-new-modal')
        .forEach(btn => btn.addEventListener('click', closeNewModal));
    document.querySelectorAll('.btn-close-edit-modal, .btn-cancel-edit-modal')
        .forEach(btn => btn.addEventListener('click', closeEditModal));
    document.getElementById('close-eval-modal')?.addEventListener('click', closeEvalModal);
    document.getElementById('cancel-eval')?.addEventListener('click', closeEvalModal);

    initTabNavigation('new');
    initTabNavigation('edit');

    document.getElementById('new-geo-toggle')?.addEventListener('click', () => toggleGeo('new'));
    document.getElementById('edit-geo-toggle')?.addEventListener('click', () => toggleGeo('edit'));

    document.getElementById('new-btn-geocode')?.addEventListener('click', () => geocodeAddress('new'));
    document.getElementById('edit-btn-geocode')?.addEventListener('click', () => geocodeAddress('edit'));

    document.getElementById('eval-calidad')?.addEventListener('input', updateEvalScore);
    document.getElementById('eval-puntual')?.addEventListener('input', updateEvalScore);

    document.getElementById('modal-detail')?.addEventListener('click', e => {
        if (e.target === e.currentTarget) closeDetailModal();
    });

    updateStats(suppliersData);
    setText('suppliers-count', `${suppliersData.length} resultado${suppliersData.length !== 1 ? 's' : ''}`);
}

// Botones de guardar de los modales (disparan submit)
document.getElementById('new-btn-save')?.addEventListener('click', function() {
    document.getElementById('new-supplier-form')?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
});

document.getElementById('edit-btn-save')?.addEventListener('click', function() {
    document.getElementById('edit-supplier-form')?.dispatchEvent(new Event('submit', { bubbles: true, cancelable: true }));
});