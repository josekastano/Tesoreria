'use strict';

// ============================================================
// 1. HELPERS GENERALES (mismos que tescxp.js)
// ============================================================
function val(id)           { return document.getElementById(id)?.value ?? ''; }
function setVal(id, value) { const el = document.getElementById(id); if (el) el.value = value ?? ''; }

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

function clearAllFieldErrors() {
    document.querySelectorAll('#pmtros-form [id^="err-"]').forEach(el => {
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
    const ico = toast.querySelector('i');
    if (ico) ico.className = type === 'error' ? 'fas fa-exclamation-circle' : 'fas fa-check-circle';
    toast.classList.remove('hidden');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.add('hidden'), 3500);
}

// ============================================================
// 2. ESTADO DEL FORMULARIO (cambios sin guardar)
// ============================================================
const CAMPOS = ['diapago1', 'diapago2', 'diapago3', 'min-reembolso'];
let snapshotInicial = {};

function leerFormulario() {
    const datos = {};
    CAMPOS.forEach(id => { datos[id] = val(id); });
    return datos;
}

function tomarSnapshot() {
    snapshotInicial = leerFormulario();
    marcarEstado();
}

function hayCambios() {
    const actual = leerFormulario();
    return CAMPOS.some(id => actual[id] !== snapshotInicial[id]);
}

function marcarEstado() {
    const sucio = hayCambios();
    const ind   = document.getElementById('pmt-chg-ind');
    const btn   = document.getElementById('btn-guardar-pmtros');
    if (ind) ind.classList.toggle('visible', sucio);
    if (btn) btn.disabled = !sucio;
}

// ============================================================
// 3. SINCRONIZAR LÍNEA DE LA SEMANA CON LOS SELECTS
// ============================================================
function updateWeekStrip() {
    const seleccionados = ['diapago1', 'diapago2', 'diapago3']
        .map(id => parseInt(val(id), 10))
        .filter(n => !isNaN(n));

    document.querySelectorAll('#week-strip .week-day').forEach(dayEl => {
        const dia = parseInt(dayEl.dataset.dia, 10);
        dayEl.classList.toggle('active', seleccionados.includes(dia));
    });

    const kpiDias = document.getElementById('kpi-dias');
    if (kpiDias) kpiDias.textContent = `${seleccionados.length}/3`;

    const NOMBRES_DIA = { 1: 'Lunes', 2: 'Martes', 3: 'Miércoles', 4: 'Jueves', 5: 'Viernes', 6: 'Sábado' };
    const kpiNombres = document.getElementById('kpi-dias-nombres');
    if (kpiNombres) {
        kpiNombres.textContent = seleccionados.length
            ? seleccionados.sort((a, b) => a - b).map(n => NOMBRES_DIA[n]).join(' · ')
            : 'Sin definir';
    }
}

// ============================================================
// 4. INICIALIZACIÓN
// ============================================================
document.addEventListener('DOMContentLoaded', initPmtrosModule);

function initPmtrosModule() {
    const form = document.getElementById('pmtros-form');
    if (!form) return;   // La página se cargó sin empresa configurada

    tomarSnapshot();
    updateWeekStrip();

    document.querySelectorAll('.diapago-select').forEach(sel => {
        sel.addEventListener('change', () => { updateWeekStrip(); marcarEstado(); });
    });

    document.getElementById('min-reembolso')?.addEventListener('input', marcarEstado);

    // ---------- RESTABLECER ----------
    document.getElementById('btn-reset-pmtros')?.addEventListener('click', () => {
        CAMPOS.forEach(id => setVal(id, snapshotInicial[id]));
        clearAllFieldErrors();
        updateWeekStrip();
        marcarEstado();
    });

    // ---------- GUARDAR ----------
    document.getElementById('btn-guardar-pmtros')?.addEventListener('click', async function () {
        const btn = this;
        clearAllFieldErrors();

        btn.disabled = true;
        const htmlOriginal = btn.innerHTML;
        btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Guardando...';

        try {
            const response = await fetch(window.location.href, {
                method: 'POST',
                body: new FormData(form)
            });
            const text = await response.text();
            let result;
            try { result = JSON.parse(text); }
            catch (e) { result = { success: false, message: 'Respuesta inválida del servidor' }; }

            if (result.success) {
                showToast(result.message, 'success');
                setVal('hid-existe', '1');

                // KPI: estado
                const kpiEstado = document.getElementById('kpi-estado');
                if (kpiEstado) kpiEstado.textContent = 'Configurado';

                const iconEstado = document.getElementById('stat-estado-icon');
                if (iconEstado) {
                    iconEstado.classList.remove('amber', 'yellow');
                    iconEstado.classList.add('green');
                    iconEstado.innerHTML = '<i class="fas fa-check-circle"></i>';
                }

                // KPI: reembolso
                const reembolso = parseFloat(val('min-reembolso')) || 0;
                const kpiReembolso = document.getElementById('kpi-reembolso');
                if (kpiReembolso) kpiReembolso.textContent = '$' + reembolso.toLocaleString('es-CO');

                tomarSnapshot();   // el estado guardado pasa a ser el nuevo punto de partida
            } else if (result.errors && Object.keys(result.errors).length > 0) {
                Object.entries(result.errors).forEach(([spanId, mensaje]) => showFieldError(spanId, mensaje));
            } else {
                showToast(result.message || 'Error al guardar', 'error');
            }
        } catch (err) {
            showToast('Error de conexión', 'error');
        } finally {
            btn.innerHTML = htmlOriginal;
            marcarEstado();
        }
    });
}
