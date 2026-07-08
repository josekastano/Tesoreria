// ============================================================
// DASHBOARD JS — Tesorería
// ============================================================

let cashflowChart = null;

document.addEventListener('DOMContentLoaded', function() {
    initializeLineChart();
    updateDonutChart(0, 0, 0);
});

function initializeLineChart() {
    const ctx = document.getElementById('cashflowChart').getContext('2d');
    const months = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Sep', 'Oct', 'Nov', 'Dic'];

    cashflowChart = new Chart(ctx, {
        type: 'line',
        data: {
            labels: months,
            datasets: [
                {
                    label: 'Proyectado',
                    data: Array(12).fill(0),
                    borderColor: '#94a3b8',
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    borderDash: [6, 4],
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#94a3b8',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                },
                {
                    label: 'Ejecutado',
                    data: Array(12).fill(0),
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.04)',
                    borderWidth: 2.5,
                    tension: 0.3,
                    pointRadius: 3,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#3b82f6',
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    fill: true,
                }
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: { display: false },
                tooltip: {
                    backgroundColor: '#0f172a',
                    callbacks: {
                        label: function(context) {
                            return `${context.dataset.label}: $${context.raw}M`;
                        }
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    max: 100,
                    grid: { color: '#f1f5f9', drawBorder: false },
                    ticks: {
                        callback: (val) => val === 0 ? '0M' : `${val}M`,
                        font: { size: 10, weight: '500' },
                        color: '#94a3b8'
                    }
                },
                x: {
                    grid: { display: false },
                    ticks: {
                        font: { size: 10, weight: '500' },
                        color: '#64748b'
                    }
                }
            }
        }
    });
}

// Donut de 3 segmentos: Proveedores, Nómina, Impuestos.
// Cada círculo SVG se rota individualmente para que los arcos no se sobrepongan,
// dibujándose en secuencia alrededor de la circunferencia.
function updateDonutChart(proveedores, nomina, impuestos) {
    const total = proveedores + nomina + impuestos;
    const radius = 68;
    const circumference = 2 * Math.PI * radius;

    const proveedoresPercent = total > 0 ? (proveedores / total) : 0;
    const nominaPercent      = total > 0 ? (nomina / total) : 0;
    const impuestosPercent   = total > 0 ? (impuestos / total) : 0;

    const proveedoresDash = proveedoresPercent * circumference;
    const nominaDash      = nominaPercent * circumference;
    const impuestosDash   = impuestosPercent * circumference;

    // Offset acumulado: cada segmento empieza donde terminó el anterior.
    // Se usa stroke-dashoffset negativo combinado con la rotación base de -90deg.
    const proveedoresOffset = 0;
    const nominaOffset      = -proveedoresDash;
    const impuestosOffset   = -(proveedoresDash + nominaDash);

    const proveedoresCircle = document.getElementById('donut-proveedores-svg');
    const nominaCircle      = document.getElementById('donut-nomina-svg');
    const impuestosCircle   = document.getElementById('donut-impuestos-svg');

    if (proveedoresCircle) {
        proveedoresCircle.setAttribute('stroke-dasharray', `${proveedoresDash} ${circumference}`);
        proveedoresCircle.setAttribute('stroke-dashoffset', `${proveedoresOffset}`);
    }
    if (nominaCircle) {
        nominaCircle.setAttribute('stroke-dasharray', `${nominaDash} ${circumference}`);
        nominaCircle.setAttribute('stroke-dashoffset', `${nominaOffset}`);
    }
    if (impuestosCircle) {
        impuestosCircle.setAttribute('stroke-dasharray', `${impuestosDash} ${circumference}`);
        impuestosCircle.setAttribute('stroke-dashoffset', `${impuestosOffset}`);
    }

    document.getElementById('donut-total').innerText = formatMoney(total);
    document.getElementById('donut-label-proveedores').innerText = formatMoney(proveedores);
    document.getElementById('donut-label-nomina').innerText = formatMoney(nomina);
    document.getElementById('donut-label-impuestos').innerText = formatMoney(impuestos);
}

// Funciones para conexión a BD
async function fetchDashboardData() {
    try {
        console.log('Dashboard de Tesorería listo para conectar a la base de datos');
    } catch (error) {
        console.error('Error:', error);
    }
}

function updateDashboardUI(data) {
    if (data.totalCxp !== undefined)
        document.getElementById('stat-total-cxp').innerText = formatMoney(data.totalCxp);
    if (data.facturasVencidas !== undefined)
        document.getElementById('stat-facturas-vencidas').innerText = formatNumber(data.facturasVencidas);
    if (data.proximosVencimientos !== undefined)
        document.getElementById('stat-proximos-vencimientos').innerText = formatNumber(data.proximosVencimientos);
    if (data.proveedoresConSaldo !== undefined)
        document.getElementById('stat-proveedores-saldo').innerText = formatNumber(data.proveedoresConSaldo);

    if (data.flujoCaja && cashflowChart) {
        const proyectado = data.flujoCaja.proyectado || Array(12).fill(0);
        const ejecutado = data.flujoCaja.ejecutado || Array(12).fill(0);

        cashflowChart.data.datasets[0].data = proyectado;
        cashflowChart.data.datasets[1].data = ejecutado;

        const maxVal = Math.max(...proyectado, ...ejecutado, 100);
        cashflowChart.options.scales.y.max = Math.ceil(maxVal / 10) * 10;
        cashflowChart.update();
    }

    if (data.composicionPagos) {
        updateDonutChart(
            data.composicionPagos.proveedores || 0,
            data.composicionPagos.nomina || 0,
            data.composicionPagos.impuestos || 0
        );
    }

    if (data.actividadReciente) {
        renderActivityList(data.actividadReciente);
    }

    if (data.mayoresProveedores) {
        renderSuppliersList(data.mayoresProveedores);
    }
}

function renderActivityList(activities) {
    const container = document.getElementById('dashboard-activity');
    if (!activities || activities.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-clock"></i>
                <p>No hay actividad reciente</p>
                <span>Los datos se mostrarán al conectar la base de datos</span>
            </div>
        `;
        return;
    }

    let html = '';
    activities.forEach(act => {
        let dotClass = act.type === 'pending' ? 'amber' : (act.type === 'done' ? 'green' : 'rose');
        html += `
            <div class="activity-item">
                <div class="activity-dot ${dotClass}"></div>
                <span class="activity-label">${escapeHtml(act.label)}</span>
                <span class="activity-time">${escapeHtml(act.time)}</span>
            </div>
        `;
    });
    container.innerHTML = html;
}

function renderSuppliersList(suppliers) {
    const container = document.getElementById('top-suppliers-list');
    if (!suppliers || suppliers.length === 0) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-trophy"></i>
                <p>No hay proveedores registrados</p>
                <span>Los datos se mostrarán al conectar la base de datos</span>
            </div>
        `;
        return;
    }

    let maxAmount = suppliers[0]?.amount || 1;
    let html = '';
    suppliers.forEach((sup, idx) => {
        let percentBar = (sup.amount / maxAmount) * 100;
        let rankClass = idx === 0 ? 'top' : '';
        html += `
            <div class="supplier-item">
                <div class="supplier-rank ${rankClass}">${idx + 1}</div>
                <span class="supplier-name">${escapeHtml(sup.name)}</span>
                <span class="supplier-amount">${formatMoney(sup.amount)}</span>
                <div class="supplier-bar-wrap"><div class="supplier-bar" style="width: ${percentBar}%"></div></div>
            </div>
        `;
    });
    container.innerHTML = html;
}

function formatNumber(num) {
    if (!num && num !== 0) return '0';
    return num.toString().replace(/\B(?=(\d{3})+(?!\d))/g, ',');
}

function formatMoney(amount) {
    if (!amount && amount !== 0) return '$0';
    if (amount >= 1000000) {
        return '$' + (amount / 1000000).toFixed(1) + 'M';
    }
    if (amount >= 1000) {
        return '$' + (amount / 1000).toFixed(0) + 'K';
    }
    return '$' + formatNumber(amount);
}

function escapeHtml(text) {
    if (!text) return '';
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

window.fetchDashboardData = fetchDashboardData;
window.updateDashboardUI = updateDashboardUI;
