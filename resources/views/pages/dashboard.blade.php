@extends('layouts.app')

@section('title', 'Nexora ERP — Dashboard')

@section('content')
<section id="page-dashboard">
      <div class="page-head">
        <h1>Procurement</h1>
        <p>Manage purchase orders, suppliers, and requisitions.</p>
      </div>

      <div class="stat-grid">
        <div class="stat-card">
          <div class="stat-label">ACTIVE POS</div>
          <div class="stat-value" id="dash-stat-po">0</div>
          <div class="stat-sub">No purchase orders yet</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">SUPPLIERS</div>
          <div class="stat-value" id="dash-stat-sup">0</div>
          <div class="stat-sub">No supplier data yet</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">REQUISITIONS</div>
          <div class="stat-value" id="dash-stat-req">0</div>
          <div class="stat-sub">No requisitions yet</div>
        </div>
        <div class="stat-card">
          <div class="stat-label">DELIVERIES</div>
          <div class="stat-value" id="dash-stat-inv">0</div>
          <div class="stat-sub">No deliveries yet</div>
        </div>
      </div>
      
      <div class="dash-grid-3">
        <div class="panel">
          <h2>Spend by Brand</h2>
          <div class="panel-sub">No purchase order spend data yet</div>
          <div style="padding:32px 12px; text-align:center; color:var(--text-muted);">
            No spend data available.
          </div>
        </div>

        <div class="panel">
          <h2>PO Status Split</h2>
          <div class="panel-sub">No purchase orders to summarize yet</div>
          <div style="padding:32px 12px; text-align:center; color:var(--text-muted);">
            No PO status data available.
          </div>
        </div>

        <div class="panel">
          <h2>Top Suppliers</h2>
          <div class="panel-sub">No supplier data yet</div>
          <div style="padding:32px 12px; text-align:center; color:var(--text-muted);">
            No top suppliers to display.
          </div>
        </div>
      </div>

      <div class="dash-po-grid">
      <div class="panel">
        <div class="filter-tabs" id="dash-po-tabs">
          <div class="tab active" data-filter="recent">Recent Purchase Orders</div>
          <div class="tab" data-filter="cancelled">Cancelled</div>
          <div class="tab" data-filter="pending">Pending</div>
          <a href="#" onclick="event.preventDefault(); showPage('purchase-orders', document.querySelectorAll('.nav-item')[1])" style="margin-left:auto; color:var(--blue); font-weight:600; font-size:13px;">View all purchase orders →</a>
        </div>
        <table class="data-table" id="dash-po-table">
          <thead>
            <tr>
              <th class="sortable" data-key="po">PO#</th>
              <th class="sortable" data-key="supplier">SUPPLIER</th>
              <th class="sortable" data-key="amount">AMOUNT</th>
              <th class="sortable" data-key="priority">PRIORITY</th>
              <th class="sortable" data-key="status">STATUS</th>
              <th class="sortable sort-desc" data-key="date">DATE</th>
            </tr>
          </thead>
          <tbody>
            <tr>
              <td colspan="6" style="text-align:center; padding:32px 16px; color:var(--text-muted);">
                No purchase orders yet.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
        <div class="panel dash-del-panel">
          <h2><span class="live-pulse"></span>Delivery Status</h2>
          <div class="panel-sub">No shipment data yet</div>
          <div class="dash-del-list" id="dash-del-list">
            <div style="padding:32px 12px; text-align:center; color:var(--text-muted);">
              No deliveries to display.
            </div>
          </div>
        </div>
      </div>
    </section>
@endsection
