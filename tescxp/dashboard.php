<?php
// ========== PERSONALIZAR PÁGINA ==========
$pageTitle    = 'ERP ADSO — Dashboard';
$activeModule = 'dashboard';
$page_title   = "ADSOERP | Dashboard";
$page_description = "Panel de Control General - Tesorería y cuentas por pagar";
$page_icon    = "bi-speedometer2";
$page_extra_css = ["../modules/tescxp/css/dashboard.css"];
$page_extra_js  = ["../modules/tescxp/js/dashboard.js"];
$show_welcome   = false;
// ==========================================

if (!defined('INCLUDE_MENU_PRINCIPAL')) {
    header("Location: menu_principal.php");
    exit();
}

ob_start();
?>

<!-- Font Awesome (no incluido en menu_principal) -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">

<div id="mod-dashboard" class="app-view active">
  <div class="page-header">
    <div>
      <div class="page-title">Panel de Control General</div>
      <div class="page-subtitle">Tesorería y flujo de cuentas por pagar</div>
    </div>
  </div>

  <div class="stats-grid">
    <div class="stat-card">
      <div class="stat-icon blue"><i class="fas fa-file-invoice-dollar"></i></div>
      <div>
        <div class="stat-label">Total por Pagar</div>
        <div class="stat-value" id="stat-total-cxp">$0</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon rose"><i class="fas fa-triangle-exclamation"></i></div>
      <div>
        <div class="stat-label">Facturas Vencidas</div>
        <div class="stat-value" id="stat-facturas-vencidas">0</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon amber"><i class="fas fa-calendar-day"></i></div>
      <div>
        <div class="stat-label">Próximos Vencimientos</div>
        <div class="stat-value" id="stat-proximos-vencimientos">0</div>
      </div>
    </div>
    <div class="stat-card">
      <div class="stat-icon emerald"><i class="fas fa-building"></i></div>
      <div>
        <div class="stat-label">Proveedores con Saldo</div>
        <div class="stat-value" id="stat-proveedores-saldo">0</div>
      </div>
    </div>
  </div>

  <div class="dash-charts-row">
    <!-- Line Chart -->
    <div class="chart-card">
      <div class="chart-header">
        <div>
          <div class="chart-card-title">Flujo de Caja</div>
          <div class="chart-card-subtitle">Comparativa proyectado vs. ejecutado ($M) · Ene - Dic</div>
        </div>
        <div class="chart-legend">
          <div class="legend-item">
            <div class="legend-dot projected"></div>
            <span>Proyectado</span>
          </div>
          <div class="legend-item">
            <div class="legend-dot executed"></div>
            <span>Ejecutado</span>
          </div>
        </div>
      </div>
      <div class="chart-scroll-wrapper">
        <div class="chart-container">
          <canvas id="cashflowChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Donut Chart -->
    <div class="chart-card">
      <div class="chart-card-title">Composición de Pagos</div>
      <div class="chart-card-subtitle">Pagos por categoría</div>

      <div class="donut-wrapper">
        <div class="donut-container">
          <svg width="160" height="160" viewBox="0 0 160 160">
            <circle cx="80" cy="80" r="68" fill="none" stroke="#e2e8f0" stroke-width="12" opacity="0.15"/>
            <circle id="donut-proveedores-svg" cx="80" cy="80" r="68" fill="none" stroke="#3b82f6" stroke-width="12"
                    stroke-dasharray="0 427" stroke-linecap="round" transform="rotate(-90 80 80)"/>
            <circle id="donut-nomina-svg" cx="80" cy="80" r="68" fill="none" stroke="#10b981" stroke-width="12"
                    stroke-dasharray="0 427" stroke-linecap="round" transform="rotate(-90 80 80)"/>
            <circle id="donut-impuestos-svg" cx="80" cy="80" r="68" fill="none" stroke="#f59e0b" stroke-width="12"
                    stroke-dasharray="0 427" stroke-linecap="round" transform="rotate(-90 80 80)"/>
          </svg>
          <div class="donut-center">
            <span id="donut-total">$0</span>
            <span>Total Pagado</span>
          </div>
        </div>

        <div class="donut-legend">
          <div class="donut-legend-item blue">
            <div class="donut-legend-dot" style="background:#3b82f6"></div>
            <span>Proveedores</span>
            <strong id="donut-label-proveedores">$0</strong>
          </div>
          <div class="donut-legend-item green">
            <div class="donut-legend-dot" style="background:#10b981"></div>
            <span>Nómina</span>
            <strong id="donut-label-nomina">$0</strong>
          </div>
          <div class="donut-legend-item amber">
            <div class="donut-legend-dot" style="background:#f59e0b"></div>
            <span>Impuestos</span>
            <strong id="donut-label-impuestos">$0</strong>
          </div>
        </div>
      </div>
    </div>
  </div>

  <div class="dash-bottom-row">
    <div class="chart-card">
      <div class="chart-card-title">Actividad Reciente</div>
      <div id="dashboard-activity" class="empty-state">
        <i class="fas fa-clock"></i>
        <p>No hay actividad reciente</p>
        <span>Los datos se mostrarán al conectar la base de datos</span>
      </div>
    </div>

    <div class="chart-card">
      <div class="chart-card-title">Mayores Proveedores por Pago</div>
      <div id="top-suppliers-list" class="empty-state">
        <i class="fas fa-trophy"></i>
        <p>No hay proveedores registrados</p>
        <span>Los datos se mostrarán al conectar la base de datos</span>
      </div>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>

<?php
$moduleContent = ob_get_clean();
echo $moduleContent;
?>
