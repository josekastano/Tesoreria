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
    toast.classList.remove('hidden');
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => toast.classList.add('hidden'), 3500);
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
}

// ============================================================
// 4. INICIALIZACIÓN
// ============================================================
document.addEventListener('DOMContentLoaded', function() {
    initPmtrosModule();
});

function initPmtrosModule() {
    document.querySelectorAll('.diapago-select').forEach(sel => {
        sel.addEventListener('change', updateWeekStrip);
    });

    document.getElementById('btn-guardar-pmtros')?.addEventListener('click', async function() {
        const form = document.getElementById('pmtros-form');
        clearAllFieldErrors();
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
                // Si era un insert, ahora ya existe el registro: cambiar el texto del botón
                // sin necesidad de recargar toda la página.
                setVal('hid-existe', '1');
                const btn = document.getElementById('btn-guardar-pmtros');
                if (btn) btn.innerHTML = '<i class="fas fa-save"></i> Actualizar Parámetros';

                // Actualizar KPIs en vivo
                const kpiEstado = document.getElementById('kpi-estado');
                if (kpiEstado) kpiEstado.textContent = 'Configurado';
                const iconEstado = document.querySelector('#mod-pmtros-tescxp .stat-card .stat-icon.yellow i.fa-exclamation-circle')?.closest('.stat-icon');
                if (iconEstado) {
                    iconEstado.classList.remove('yellow');
                    iconEstado.classList.add('green');
                    iconEstado.innerHTML = '<i class="fas fa-check-circle"></i>';
                }
                const reembolso = parseFloat(val('min-reembolso')) || 0;
                const kpiReembolso = document.getElementById('kpi-reembolso');
                if (kpiReembolso) kpiReembolso.textContent = '$' + reembolso.toLocaleString('es-CO');
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
