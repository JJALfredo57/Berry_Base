@extends('layouts.app')

@push('styles')
<style>
  .commission-hero {
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:1rem;
    padding:1.25rem;
    margin-bottom:1rem;
    border:1px solid rgba(15,23,42,.08);
    border-radius:8px;
    background:linear-gradient(135deg,#ffffff 0%,#f8fafc 54%,#f0fdf4 100%);
    box-shadow:0 12px 30px rgba(15,23,42,.06);
  }
  .commission-kpis {
    display:grid;
    grid-template-columns:repeat(4,minmax(0,1fr));
    gap:.85rem;
    margin-bottom:1rem;
  }
  .commission-kpi,
  .commission-panel {
    border:1px solid rgba(15,23,42,.08);
    border-radius:8px;
    background:#fff;
    box-shadow:0 10px 26px rgba(15,23,42,.055);
  }
  .commission-kpi {
    padding:1rem;
    min-height:118px;
  }
  .commission-kpi-icon {
    width:40px;
    height:40px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    margin-bottom:.75rem;
  }
  .commission-kpi-value {
    font-size:clamp(1.15rem,2.4vw,1.55rem);
    font-weight:800;
    line-height:1.15;
    color:#0f172a;
    overflow-wrap:anywhere;
  }
  .commission-kpi-label {
    color:#64748b;
    font-size:.78rem;
    font-weight:700;
    text-transform:uppercase;
    letter-spacing:0;
    margin-top:.25rem;
  }
  .commission-panel-head {
    display:flex;
    justify-content:space-between;
    align-items:center;
    gap:1rem;
    padding:1rem 1.15rem;
    border-bottom:1px solid rgba(15,23,42,.08);
  }
  .commission-chart-wrap {
    position:relative;
    height:390px;
    padding:1rem;
  }
  .commission-shop-row {
    display:grid;
    grid-template-columns:minmax(0,1.7fr) minmax(120px,.7fr) minmax(120px,.7fr) minmax(90px,.45fr);
    gap:1rem;
    align-items:start;
    padding:1rem 1.15rem;
    border-bottom:1px solid rgba(15,23,42,.08);
  }
  .commission-shop-row:last-child { border-bottom:0; }
  .commission-shop-main {
    display:flex;
    align-items:flex-start;
    gap:.8rem;
    min-width:0;
  }
  .commission-shop-name {
    font-weight:700;
    line-height:1.2;
    overflow:hidden;
    text-overflow:ellipsis;
    white-space:nowrap;
  }
  .commission-shop-meta {
    color:#64748b;
    font-size:.75rem;
    line-height:1.25;
    margin-top:.15rem;
  }
  .commission-shop-metric {
    min-width:0;
  }
  .commission-shop-metric-label {
    color:#64748b;
    font-size:.72rem;
    line-height:1.15;
  }
  .commission-shop-metric strong {
    display:block;
    margin-top:.18rem;
    color:#0f172a;
    font-size:.9rem;
    line-height:1.2;
    overflow-wrap:anywhere;
  }
  .commission-rank {
    width:34px;
    height:34px;
    display:flex;
    align-items:center;
    justify-content:center;
    border-radius:8px;
    background:#eef2ff;
    color:#3730a3;
    font-weight:800;
    flex-shrink:0;
  }
  .commission-bar {
    height:7px;
    border-radius:999px;
    background:#e2e8f0;
    overflow:hidden;
    margin-top:.45rem;
  }
  .commission-bar span {
    display:block;
    height:100%;
    border-radius:999px;
    background:linear-gradient(90deg,#16a34a,#2563eb);
  }
  @media (max-width: 991.98px) {
    .commission-kpis { grid-template-columns:repeat(2,minmax(0,1fr)); }
    .commission-chart-wrap { height:340px; }
    .commission-shop-row { grid-template-columns:1fr 1fr; }
  }
  @media (max-width: 575.98px) {
    .commission-hero {
      align-items:flex-start;
      flex-direction:column;
      padding:1rem;
    }
    .commission-kpis { grid-template-columns:1fr; }
    .commission-chart-wrap {
      height:320px;
      padding:.75rem;
    }
    .commission-panel-head {
      align-items:flex-start;
      flex-direction:column;
    }
    .commission-shop-row {
      grid-template-columns:1fr 1fr;
      gap:.85rem .75rem;
      padding:1rem;
    }
    .commission-shop-main {
      grid-column:1 / -1;
    }
    .commission-shop-name {
      white-space:normal;
      overflow:visible;
      text-overflow:clip;
    }
    .commission-shop-meta {
      font-size:.72rem;
    }
    .commission-shop-metric {
      padding:.65rem .7rem;
      border:1px solid rgba(15,23,42,.08);
      border-radius:8px;
      background:#f8fafc;
    }
  }
</style>
@endpush

@section('content')
<div class="commission-hero">
  <div>
    <h4 class="cs-page-title mb-1"><i class="bi bi-graph-up-arrow me-2" style="color:#16a34a"></i>Commission Analytics</h4>
    <p class="cs-page-sub mb-0">{{ $platform->platform_name ?? 'Cake Shop Platform' }} - Platform commission overview</p>
  </div>
  <div class="d-flex gap-2 flex-wrap">
    <a href="{{ route('superadmin.dashboard') }}" class="btn btn-outline-secondary btn-sm"><i class="bi bi-arrow-left me-1"></i>Dashboard</a>
    <a href="{{ route('superadmin.sellers') }}" class="btn btn-primary btn-sm"><i class="bi bi-shop me-1"></i>Sellers</a>
  </div>
</div>

<div class="commission-kpis">
  <div class="commission-kpi">
    <div class="commission-kpi-icon" style="background:#f3e5f5"><i class="bi bi-cash-stack" style="color:#6A1B9A"></i></div>
    <div class="commission-kpi-value">&#8369;{{ number_format($totalCommission, 2) }}</div>
    <div class="commission-kpi-label">Total Commission</div>
  </div>
  <div class="commission-kpi">
    <div class="commission-kpi-icon" style="background:#e0f2f1"><i class="bi bi-calendar2-check" style="color:#00695C"></i></div>
    <div class="commission-kpi-value">&#8369;{{ number_format($commissionMonth, 2) }}</div>
    <div class="commission-kpi-label">This Month</div>
  </div>
  <div class="commission-kpi">
    <div class="commission-kpi-icon" style="background:#e3f2fd"><i class="bi bi-receipt" style="color:#1565C0"></i></div>
    <div class="commission-kpi-value">{{ number_format($paidOrdersMonth) }}</div>
    <div class="commission-kpi-label">Paid Orders</div>
  </div>
  <div class="commission-kpi">
    <div class="commission-kpi-icon" style="background:#fff7ed"><i class="bi bi-bar-chart-line" style="color:#c2410c"></i></div>
    <div class="commission-kpi-value">&#8369;{{ number_format($grossSalesMonth, 2) }}</div>
    <div class="commission-kpi-label">Gross Sales</div>
  </div>
</div>

<div class="row g-4">
  <div class="col-xl-8">
    <div class="commission-panel h-100">
      <div class="commission-panel-head">
        <div>
          <div class="fw-bold"><i class="bi bi-activity me-2" style="color:#2563eb"></i>Monthly Commission Trend</div>
          <div class="text-muted" style="font-size:.8rem">Last 6 months from paid orders with active seller commission</div>
        </div>
        <span class="badge" style="background:#dcfce7;color:#166534">Live Data</span>
      </div>
      <div class="commission-chart-wrap">
        <canvas id="commissionTrendChart"></canvas>
      </div>
    </div>
  </div>

  <div class="col-xl-4">
    <div class="commission-panel h-100">
      <div class="commission-panel-head">
        <div>
          <div class="fw-bold"><i class="bi bi-trophy me-2" style="color:#c2410c"></i>Top Shops</div>
          <div class="text-muted" style="font-size:.8rem">Ranked by earned platform commission</div>
        </div>
      </div>

      @php $topMax = max((float)($topShops->max('commission') ?? 0), 1); @endphp
      @forelse($topShops as $shop)
        <div class="commission-shop-row">
          <div class="commission-shop-main">
            <div class="commission-rank">{{ $loop->iteration }}</div>
            <div style="min-width:0">
              <div class="commission-shop-name">{{ $shop->shop_name }}</div>
              <div class="commission-shop-meta">{{ ucfirst($shop->tier ?? 'basic') }} seller - {{ number_format($shop->commission_rate, 2) }}% rate</div>
              <div class="commission-bar"><span style="width:{{ min(100, ((float)$shop->commission / $topMax) * 100) }}%"></span></div>
            </div>
          </div>
          <div class="commission-shop-metric">
            <div class="commission-shop-metric-label">Commission</div>
            <strong>&#8369;{{ number_format($shop->commission, 2) }}</strong>
          </div>
          <div class="commission-shop-metric">
            <div class="commission-shop-metric-label">Gross Sales</div>
            <strong>&#8369;{{ number_format($shop->gross_sales, 2) }}</strong>
          </div>
          <div class="commission-shop-metric">
            <div class="commission-shop-metric-label">Orders</div>
            <strong>{{ number_format($shop->paid_orders) }}</strong>
          </div>
        </div>
      @empty
        <div class="cs-empty">
          <i class="bi bi-graph-up cs-empty-icon" style="color:#94a3b8"></i>
          <div class="cs-empty-title">No commission yet</div>
          <div class="cs-empty-sub">Paid orders with active seller commission will appear here.</div>
        </div>
      @endforelse
    </div>
  </div>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.8/dist/chart.umd.min.js"></script>
<script>
  document.addEventListener('DOMContentLoaded', function () {
    const chartEl = document.getElementById('commissionTrendChart');
    if (!chartEl || typeof Chart === 'undefined') return;

    const data = @json($chartData);
    const peso = new Intl.NumberFormat('en-PH', {
      style: 'currency',
      currency: 'PHP',
      minimumFractionDigits: 2
    });

    new Chart(chartEl, {
      type: 'bar',
      data: {
        labels: data.labels,
        datasets: [
          {
            type: 'bar',
            label: 'Commission',
            data: data.commission,
            borderRadius: 8,
            borderSkipped: false,
            backgroundColor: 'rgba(22, 163, 74, .78)',
            hoverBackgroundColor: 'rgba(22, 163, 74, .95)'
          },
          {
            type: 'line',
            label: 'Gross Sales',
            data: data.grossSales,
            borderColor: '#2563eb',
            backgroundColor: 'rgba(37, 99, 235, .12)',
            borderWidth: 3,
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: .36,
            fill: true,
            yAxisID: 'gross'
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        interaction: { mode: 'index', intersect: false },
        plugins: {
          legend: {
            position: 'bottom',
            labels: { usePointStyle: true, boxWidth: 8, font: { weight: '700' } }
          },
          tooltip: {
            callbacks: {
              label: function (context) {
                return context.dataset.label + ': ' + peso.format(context.parsed.y || 0);
              },
              afterBody: function (items) {
                const index = items[0].dataIndex;
                return 'Paid orders: ' + (data.orders[index] || 0).toLocaleString();
              }
            }
          }
        },
        scales: {
          x: {
            grid: { display: false },
            ticks: { color: '#64748b', maxRotation: 0, autoSkip: true }
          },
          y: {
            beginAtZero: true,
            ticks: {
              color: '#64748b',
              callback: function (value) { return peso.format(value).replace('.00', ''); }
            },
            grid: { color: 'rgba(148, 163, 184, .22)' }
          },
          gross: {
            beginAtZero: true,
            position: 'right',
            ticks: { display: false },
            grid: { display: false }
          }
        }
      }
    });
  });
</script>
@endpush
