<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['store_id'])) {
  header('Location: ../../auth/index.php?error=Please%20login');
  exit();
}

$store_id = $_SESSION['store_id'];

// ------------------- SUMMARY METRICS -------------------
$total_revenue = $total_profit = $total_tax = 0;
$today_total = $today_profit = 0;
$month_total = $month_profit = 0;
$year_total = $year_profit = 0;

// General summary
$sql_summary = "
  SELECT 
      SUM(si.quantity * si.price) AS total_revenue,
      SUM((p.sell_price - p.purchase_price) * si.quantity) AS total_profit,
      SUM(si.quantity * si.price * (si.gst_percent / 100)) AS total_tax
  FROM sale_items si
  JOIN sales s ON si.sale_id = s.sale_id
  JOIN products p ON si.product_id = p.product_id
  WHERE s.store_id = ?
";
$stmt = $conn->prepare($sql_summary);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($total_revenue, $total_profit, $total_tax);
$stmt->fetch();
$stmt->close();

// Today's summary
$sql_today = "
  SELECT 
      SUM(s.total_amount) AS today_total,
      SUM((p.sell_price - p.purchase_price) * si.quantity) AS today_profit
  FROM sales s
  JOIN sale_items si ON s.sale_id = si.sale_id
  JOIN products p ON si.product_id = p.product_id
  WHERE s.store_id = ? AND DATE(s.sale_date) = CURDATE()
";
$stmt = $conn->prepare($sql_today);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($today_total, $today_profit);
$stmt->fetch();
$stmt->close();

// This Month
$sql_month = "
  SELECT 
      SUM(s.total_amount) AS month_total,
      SUM((p.sell_price - p.purchase_price) * si.quantity) AS month_profit
  FROM sales s
  JOIN sale_items si ON s.sale_id = si.sale_id
  JOIN products p ON si.product_id = p.product_id
  WHERE s.store_id = ? AND MONTH(s.sale_date) = MONTH(CURDATE()) AND YEAR(s.sale_date) = YEAR(CURDATE())
";
$stmt = $conn->prepare($sql_month);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($month_total, $month_profit);
$stmt->fetch();
$stmt->close();

// This Year
$sql_year = "
  SELECT 
      SUM(s.total_amount) AS year_total,
      SUM((p.sell_price - p.purchase_price) * si.quantity) AS year_profit
  FROM sales s
  JOIN sale_items si ON s.sale_id = si.sale_id
  JOIN products p ON si.product_id = p.product_id
  WHERE s.store_id = ? AND YEAR(s.sale_date) = YEAR(CURDATE())
";
$stmt = $conn->prepare($sql_year);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($year_total, $year_profit);
$stmt->fetch();
$stmt->close();

// Top selling products (will be fetched via JS)
$top_products = [];
$sql_top = "
  SELECT p.product_name, SUM(si.quantity) AS total_sold
  FROM sale_items si
  JOIN products p ON si.product_id = p.product_id
  JOIN sales s ON si.sale_id = s.sale_id
  WHERE s.store_id = ?
  GROUP BY si.product_id
  ORDER BY total_sold DESC
  LIMIT 6
";
$stmt = $conn->prepare($sql_top);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
  $top_products[] = $row;
}
$stmt->close();

// Recent transactions
$recent_sales = [];
$sql_recent = "
  SELECT s.sale_id, s.customer_name, s.total_amount, s.sale_date, COUNT(si.id) AS items
  FROM sales s
  LEFT JOIN sale_items si ON s.sale_id = si.sale_id
  WHERE s.store_id = ?
  GROUP BY s.sale_id, s.customer_name, s.total_amount, s.sale_date
  ORDER BY s.sale_date DESC
  LIMIT 5
";
$stmt = $conn->prepare($sql_recent);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
  $recent_sales[] = $row;
}
$stmt->close();

// Average metrics
$avg_order = $total_orders = 0;
$sql_avg = "
  SELECT COUNT(*) AS total_orders, AVG(s.total_amount) AS avg_order_value
  FROM sales s
  WHERE s.store_id = ?
";
$stmt = $conn->prepare($sql_avg);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($total_orders, $avg_order);
$stmt->fetch();
$stmt->close();

// Total products (replace Today's Orders KPI)
$total_products = 0;
$sql_prod = "SELECT COUNT(*) FROM products WHERE store_id = ?";
$stmt = $conn->prepare($sql_prod);
$stmt->bind_param("i", $store_id);
$stmt->execute();
$stmt->bind_result($total_products);
$stmt->fetch();
$stmt->close();

// Profit margin
$profit_margin = 0;
if ($total_revenue > 0) {
  $profit_margin = ($total_profit / $total_revenue) * 100;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Business Dashboard</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
  <link rel="stylesheet" href="/assets/css/common.css">
  <style>
  h2 {
    font-weight: 700;
    color: #1e293b;
    margin-bottom: 2rem;
    font-size: 2rem;
  }

  .card-custom {
    border: none;
    border-radius: 0.8rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    background: linear-gradient(135deg, #ffffff, #f8fafc);
    padding: 1.5rem;
    transition: all 0.2s ease;
  }

  .card-custom:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 16px rgba(0, 0, 0, 0.08);
  }

  .summary-icon {
    font-size: 1.8rem;
    margin-right: 1rem;
  }

  .chart-section {
    display: grid;
    grid-template-columns: 2fr 1fr;
    gap: 2vw;
    margin-top: 2.5vh;
  }

  .chart-card {
    padding: 1.5rem;
    border-radius: 0.8rem;
    background: #fff;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    min-height: 50vh;
  }

  .chart-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
    flex-wrap: wrap;
    gap: 1rem;
  }

  .btn-outline-primary {
    border-radius: 0.5rem;
    font-size: 0.85rem;
    padding: 0.3rem 0.8rem;
  }

  .product-chart-container {
    max-width: 100%;
    height: 300px;
  }

  .product-nav {
    display: flex;
    gap: 0.3rem;
    justify-content: center;
    align-items: center;
    margin-bottom: 1rem;
  }

  .product-nav button {
    background: #f1f5f9;
    border: none;
    padding: 0.3rem 0.6rem;
    border-radius: 0.3rem;
    cursor: pointer;
    font-size: 0.75rem;
    transition: all 0.2s ease;
  }

  .product-nav button:hover {
    background: #e2e8f0;
  }

  .product-nav span {
    font-weight: 600;
    font-size: 0.85rem;
    color: #475569;
    min-width: 110px;
    text-align: center;
  }

  /* Calendar Styles */
  .calendar-container {
    background: #fff;
    border-radius: 0.8rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    padding: 1.2rem;
    margin-top: 2rem;
    max-width: 500px;
  }

  .calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 1rem;
  }

  .calendar-header h5 {
    margin: 0;
    font-weight: 600;
    color: #1e293b;
    font-size: 1.1rem;
  }

  .calendar-nav {
    display: flex;
    gap: 0.3rem;
  }

  .calendar-nav button {
    background: #f1f5f9;
    border: none;
    padding: 0.25rem 0.5rem;
    border-radius: 0.3rem;
    cursor: pointer;
    font-size: 0.75rem;
    transition: all 0.2s ease;
  }

  .calendar-nav button:hover {
    background: #e2e8f0;
  }

  .calendar-grid {
    display: grid;
    grid-template-columns: repeat(7, 1fr);
    gap: 0.3rem;
    margin-bottom: 0.8rem;
  }

  .day-header {
    text-align: center;
    font-weight: 600;
    color: #64748b;
    font-size: 0.7rem;
    padding: 0.3rem;
  }

  .calendar-day {
    aspect-ratio: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 0.3rem;
    cursor: pointer;
    font-size: 0.75rem;
    background: #f8fafc;
    border: 1px solid #e2e8f0;
    transition: all 0.2s ease;
    font-weight: 500;
  }

  .calendar-day:hover {
    transform: scale(1.05);
    border-color: #0ea5e9;
  }

  .calendar-day.other-month {
    color: #cbd5e1;
    cursor: default;
    font-weight: 400;
  }

  .calendar-day.other-month:hover {
    background: #f8fafc;
    border-color: #e2e8f0;
    transform: scale(1);
  }

  .calendar-day.has-sales {
    color: white;
    border: none;
    font-weight: 600;
  }

  .calendar-day.selected {
    box-shadow: 0 0 0 2px #007bff;
  }

  .date-details {
    background: #f8fafc;
    border-radius: 0.6rem;
    padding: 1rem;
    margin-top: 0.8rem;
    border-left: 4px solid #007bff;
  }

  .date-details h6 {
    color: #1e293b;
    margin-bottom: 0.5rem;
    font-size: 0.95rem;
  }

  .date-detail-item {
    display: flex;
    justify-content: space-between;
    padding: 0.3rem 0;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.85rem;
  }

  .date-detail-item:last-child {
    border-bottom: none;
  }

  .date-detail-item .label {
    color: #64748b;
    font-weight: 500;
  }

  .date-detail-item .value {
    color: #1e293b;
    font-weight: 600;
  }

  .sales-table {
    margin-top: 0.8rem;
    font-size: 0.8rem;
  }

  .sales-table table {
    width: 100%;
  }

  .sales-table th {
    background: #e2e8f0;
    padding: 0.4rem;
    font-weight: 600;
    color: #475569;
    font-size: 0.75rem;
  }

  .sales-table td {
    padding: 0.4rem;
    border-bottom: 1px solid #f1f5f9;
    font-size: 0.75rem;
  }

  /* Recent transactions */
  .recent-transactions {
    background: #fff;
    border-radius: 0.8rem;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    padding: 1.5rem;
    margin-top: 2rem;
  }

  .recent-transactions h6 {
    font-weight: 600;
    color: #1e293b;
    margin-bottom: 1rem;
  }

  .transaction-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 0.8rem;
    border-bottom: 1px solid #e2e8f0;
    font-size: 0.9rem;
  }

  .transaction-item:last-child {
    border-bottom: none;
  }

  .transaction-info {
    display: flex;
    align-items: center;
    gap: 0.8rem;
    flex: 1;
  }

  .transaction-icon {
    width: 35px;
    height: 35px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    background: #e0f2fe;
    color: #0284c7;
    font-size: 0.9rem;
  }

  .transaction-details {
    flex: 1;
  }

  .transaction-customer {
    font-weight: 600;
    color: #1e293b;
  }

  .transaction-time {
    font-size: 0.8rem;
    color: #64748b;
  }

  .transaction-amount {
    font-weight: 600;
    color: #28a745;
    text-align: right;
  }

  /* KPI Stats */
  .kpi-section {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1.5rem;
    margin-top: 2rem;
  }

  .kpi-card {
    background: linear-gradient(135deg, #e0f2fe, #f0f9ff);
    border-radius: 0.8rem;
    padding: 1.2rem;
    border-left: 4px solid #0284c7;
    text-align: center;
  }

  .kpi-card.profit {
    background: linear-gradient(135deg, #dcfce7, #f0fdf4);
    border-left-color: #22c55e;
  }

  .kpi-card.margin {
    background: linear-gradient(135deg, #fef3c7, #fffbeb);
    border-left-color: #f59e0b;
  }

  .kpi-label {
    font-size: 0.8rem;
    color: #64748b;
    margin-bottom: 0.4rem;
    font-weight: 500;
  }

  .kpi-value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #1e293b;
  }

  /* responsive to sidebar width */
  :root {
    --sidebar-width: 250px;
  }

  .content {
    margin-left: var(--sidebar-width);
    width: calc(100vw - var(--sidebar-width));
    transition: width 0.25s ease, margin-left 0.25s ease;
    padding: 2vw;
    min-height: 100vh;
    background-color: transparent;
  }

  @media(max-width:1024px) {
    .chart-section {
      grid-template-columns: 1fr;
    }
  }

  @media(max-width:768px) {
    h2 {
      font-size: 1.5rem;
    }

    .card-custom {
      padding: 1rem;
    }

    .chart-header {
      flex-direction: column;
      align-items: flex-start;
    }

    .calendar-container {
      max-width: 100%;
    }

    .kpi-section {
      grid-template-columns: repeat(2, 1fr);
    }
  }

  /* Ensure content stays behind navbar/sidebar */
  .content {
    z-index: 0;
    position: relative;
    background-color: transparent;
  }

  .profit-chart-container {
    height: 350px;
    min-height: 350px;
    max-height: 350px;
  }
  </style>
</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <div class="content">
    <h2 class="mt-4"><i class="bi bi-graph-up me-2"></i>Business Reports</h2>
    
    <div class="row g-3">
      <?php
      $cards = [
        ['Total Revenue', $total_revenue, 'success', 'bi-cash-stack'],
        ['Total Profit', $total_profit, 'info', 'bi-bar-chart-line-fill'],
        ['Total Tax Collected', $total_tax, 'danger', 'bi-receipt'],
        ["Today's Revenue", $today_total, 'primary', 'bi-calendar-day'],
        ["Today's Profit", $today_profit, 'primary', 'bi-calendar-check'],
        ['This Month Revenue', $month_total, 'warning', 'bi-calendar2'],
        ['This Month Profit', $month_profit, 'warning', 'bi-calendar2-check'],
        ['This Year Revenue', $year_total, 'secondary', 'bi-calendar3'],
        ['This Year Profit', $year_profit, 'secondary', 'bi-calendar3-check']
      ];

      foreach ($cards as [$label, $value, $color, $icon]) {
        echo "
        <div class='col-xl-4 col-lg-4 col-md-6 col-sm-12'>
          <div class='card card-custom'>
            <div class='d-flex align-items-center'>
              <i class='bi $icon summary-icon text-$color'></i>
              <div>
                <div class='text-muted small'>{$label}</div>
                <div class='fw-bold fs-5'>₹" . number_format((float)($value ?? 0), 2) . "</div>
              </div>
            </div>
          </div>
        </div>";
      }
      ?>
    </div>

    <!-- KPI Section (replace Today's Orders with Total Products) -->
    <div class="kpi-section">
      <div class="kpi-card">
        <div class="kpi-label">Avg Order Value</div>
        <div class="kpi-value">₹<?= number_format((float)($avg_order ?? 0), 2) ?></div>
      </div>
      <div class="kpi-card">
        <div class="kpi-label">Total Bills Till Date</div>
        <div class="kpi-value"><?= (int)$total_orders ?></div>
      </div>
      <div class="kpi-card profit">
        <div class="kpi-label">Profit Margin</div>
        <div class="kpi-value"><?= number_format($profit_margin, 2) ?>%</div>
      </div>
      <div class="kpi-card margin">
        <div class="kpi-label">Total Products</div>
        <div class="kpi-value"><?= (int)$total_products ?></div>
      </div>
    </div>

    <!-- CHANGED: Chart section now contains ONLY the Profit chart (big) + Top Products (small) -->
    <div class="chart-section">
      <div class="chart-card">
        <div class="chart-header">
          <h6 class="fw-semibold text-secondary mb-0">Profit Trend Analysis</h6>
          <div>
            <button class="btn btn-outline-primary btn-sm me-1" onclick="loadProfitGraph('last7')">7-Day</button>
            <button class="btn btn-outline-primary btn-sm me-1" onclick="loadProfitGraph('daily')">Daily</button>
            <button class="btn btn-outline-primary btn-sm me-1" onclick="loadProfitGraph('monthly')">Monthly</button>
            <button class="btn btn-outline-primary btn-sm" onclick="loadProfitGraph('yearly')">Yearly</button>
          </div>
        </div>
        <div class="chart-container profit-chart-container">
          <canvas id="profitChart"></canvas>
        </div>
      </div>

      <div class="chart-card">
        <div class="chart-header flex-column align-items-stretch">
          <h6 class="mb-0 fw-semibold text-secondary">Top Products (Month)</h6>
        </div>
        <div class="product-nav">
          <button onclick="previousProductMonth()"><i class="bi bi-chevron-left"></i></button>
          <span id="productMonthYear"></span>
          <button onclick="nextProductMonth()"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="product-chart-container">
          <canvas id="topProductsChart"></canvas>
        </div>
      </div>
    </div>

    <!-- Transactions section: remove duplicate Top Products (already moved above) -->
    <div class="transactions-section">
      <div class="recent-transactions">
        <h6><i class="bi bi-clock-history me-2"></i>Recent Transactions</h6>
        <!-- ...existing recent transactions loop ... -->
        <?php if (!empty($recent_sales)): ?>
          <?php foreach ($recent_sales as $sale): ?>
            <div class="transaction-item">
              <div class="transaction-info">
                <div class="transaction-icon"><i class="bi bi-bag-check"></i></div>
                <div class="transaction-details">
                  <div class="transaction-customer"><?= htmlspecialchars($sale['customer_name']) ?></div>
                  <div class="transaction-time"><?= date('M d, Y H:i', strtotime($sale['sale_date'])) ?> • <?= $sale['items'] ?> items</div>
                </div>
              </div>
              <div class="transaction-amount">₹<?= number_format((float)$sale['total_amount'], 2) ?></div>
            </div>
          <?php endforeach; ?>
        <?php else: ?>
          <div class="text-center text-muted py-3">No recent transactions</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Calendar Section -->
    <div class="calendar-container">
      <div class="calendar-header">
        <h5><i class="bi bi-calendar-event me-2"></i>Daily Income</h5>
        <div class="calendar-nav">
          <button onclick="previousMonth()"><i class="bi bi-chevron-left"></i></button>
          <span id="calendarMonthYear" style="min-width: 120px; text-align: center; font-weight: 600; font-size: 0.9rem;"></span>
          <button onclick="nextMonth()"><i class="bi bi-chevron-right"></i></button>
        </div>
      </div>
      <div class="calendar-grid" id="calendarGrid"></div>
      <div id="dateDetails"></div>
    </div>
  </div>

  <script>
  // JS: remove salesChart and loadGraph usage, keep profitChart & topChart
  let profitChart, topChart;
  let currentDate = new Date();
  let productMonthDate = new Date();
  let monthSalesData = {};

  // small helper to pad numbers
  function pad(n) {
    return String(n).padStart(2, '0');
  }

  // Update CSS var for sidebar width so content expands on collapse
  function updateSidebarWidth() {
    const sb = document.querySelector('.sidebar') || document.getElementById('sidebar');
    if (sb) {
      const rect = sb.getBoundingClientRect();
      const w = rect.width || parseFloat(getComputedStyle(sb).width) || 0;
      document.documentElement.style.setProperty('--sidebar-width', (w || 0) + 'px');
    } else {
      // fallback
      document.documentElement.style.setProperty('--sidebar-width', '0px');
    }
  }

  // run on key lifecycle events to keep --sidebar-width in sync
  document.addEventListener('DOMContentLoaded', () => {
    updateSidebarWidth();
    // some sidebars toggle after load; update a bit later too
    setTimeout(updateSidebarWidth, 300);
  });
  window.addEventListener('load', updateSidebarWidth);
  window.addEventListener('resize', updateSidebarWidth);

  // observe sidebar attribute/class changes (if present)
  const sidebarNode = document.querySelector('.sidebar') || document.getElementById('sidebar');
  if (sidebarNode && window.MutationObserver) {
    const mo = new MutationObserver(() => {
      // slight debounce
      setTimeout(updateSidebarWidth, 50);
    });
    mo.observe(sidebarNode, { attributes: true, attributeFilter: ['class', 'style'] });
  }

  // Fetch monthly sales for calendar (unchanged)
  function fetchMonthlySalesData(year, month) {
    const monthStr = String(month + 1).padStart(2, '0');
    
    fetch(`./get_daily_sales.php?year=${year}&month=${monthStr}`)
      .then(res => res.json())
      .then(data => {
        monthSalesData = data.sales || {};
        renderCalendar();
      })
      .catch(err => console.error('Error fetching sales data:', err));
  }

  function getColorIntensity(salesAmount, maxSales) {
    if (!salesAmount || salesAmount <= 0) return null;
    
    const intensity = Math.min(salesAmount / maxSales, 1);
    const hue = 120;
    const saturation = 70;
    const lightness = Math.max(90 - (intensity * 60), 30);
    
    return `hsl(${hue}, ${saturation}%, ${lightness}%)`;
  }

  function getMaxSalesAmount() {
    return Math.max(...Object.values(monthSalesData).map(v => parseFloat(v) || 0), 1);
  }

  function renderCalendar() {
    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    
    document.getElementById('calendarMonthYear').textContent = 
      currentDate.toLocaleString('default', { month: 'short', year: 'numeric' });

    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const daysInPrevMonth = new Date(year, month, 0).getDate();

    const grid = document.getElementById('calendarGrid');
    grid.innerHTML = '';

    const maxSales = getMaxSalesAmount();

    // Day headers
    ['S', 'M', 'T', 'W', 'T', 'F', 'S'].forEach(day => {
      const header = document.createElement('div');
      header.className = 'day-header';
      header.textContent = day;
      grid.appendChild(header);
    });

    // Previous month days
    for (let i = firstDay - 1; i >= 0; i--) {
      const day = document.createElement('div');
      day.className = 'calendar-day other-month';
      day.textContent = daysInPrevMonth - i;
      grid.appendChild(day);
    }

    // Current month days
    for (let i = 1; i <= daysInMonth; i++) {
      const day = document.createElement('div');
      day.className = 'calendar-day';
      day.textContent = i;
      
      const dateKey = String(i).padStart(2, '0');
      const salesAmount = monthSalesData[dateKey];
      
      if (salesAmount && parseFloat(salesAmount) > 0) {
        day.classList.add('has-sales');
        const bgColor = getColorIntensity(parseFloat(salesAmount), maxSales);
        day.style.backgroundColor = bgColor;
      }
      
      day.onclick = () => selectDate(year, month, i);
      grid.appendChild(day);
    }

    // Next month days
    const totalCells = grid.children.length - 7;
    const remainingCells = 42 - totalCells;
    for (let i = 1; i <= remainingCells; i++) {
      const day = document.createElement('div');
      day.className = 'calendar-day other-month';
      day.textContent = i;
      grid.appendChild(day);
    }
  }

  function previousMonth() {
    currentDate.setMonth(currentDate.getMonth() - 1);
    fetchMonthlySalesData(currentDate.getFullYear(), currentDate.getMonth());
  }

  function nextMonth() {
    currentDate.setMonth(currentDate.getMonth() + 1);
    fetchMonthlySalesData(currentDate.getFullYear(), currentDate.getMonth());
  }

  // Fix: build date string manually to avoid timezone shift from toISOString
  function selectDate(year, month, day) {
    const yyyy = String(year);
    const mm = pad(month + 1);
    const dd = pad(day);
    const dateStr = `${yyyy}-${mm}-${dd}`; // safe local date string
    fetchDateDetails(dateStr);
  }

  function fetchDateDetails(dateStr) {
    fetch('./get_sales_by_date.php?date=' + dateStr)
      .then(res => res.json())
      .then(data => {
        if (data.success) {
          displayDateDetails(dateStr, data);
        }
      })
      .catch(err => console.error('Error:', err));
  }

  function displayDateDetails(dateStr, data) {
    const dateObj = new Date(dateStr);
    const formattedDate = dateObj.toLocaleDateString('default', { 
      weekday: 'short', 
      year: 'numeric', 
      month: 'short', 
      day: 'numeric' 
    });

    let html = `
      <div class="date-details">
        <h6>${formattedDate}</h6>
        <div class="date-detail-item">
          <span class="label">Revenue:</span>
          <span class="value">₹${parseFloat(data.revenue || 0).toFixed(2)}</span>
        </div>
        <div class="date-detail-item">
          <span class="label">Profit:</span>
          <span class="value">₹${parseFloat(data.profit || 0).toFixed(2)}</span>
        </div>
        <div class="date-detail-item">
          <span class="label">Tax:</span>
          <span class="value">₹${parseFloat(data.tax || 0).toFixed(2)}</span>
        </div>
    `;

    if (data.items && data.items.length > 0) {
      html += `
        <div class="sales-table">
          <table>
            <thead>
              <tr>
                <th>Product</th>
                <th>Qty</th>
                <th>Total</th>
              </tr>
            </thead>
            <tbody>
      `;
      data.items.forEach(item => {
        html += `
          <tr>
            <td>${item.product_name}</td>
            <td>${item.quantity}</td>
            <td>₹${parseFloat(item.total || 0).toFixed(2)}</td>
          </tr>
        `;
      });
      html += `
            </tbody>
          </table>
        </div>
      `;
    }

    html += `</div>`;
    document.getElementById('dateDetails').innerHTML = html;
  }

  // Product Month Navigation
  function previousProductMonth() {
    productMonthDate.setMonth(productMonthDate.getMonth() - 1);
    loadTopProductsChart();
  }

  function nextProductMonth() {
    productMonthDate.setMonth(productMonthDate.getMonth() + 1);
    loadTopProductsChart();
  }

  // Top Products Chart with Month/Year
  async function loadTopProductsChart() {
    const year = productMonthDate.getFullYear();
    const month = String(productMonthDate.getMonth() + 1).padStart(2, '0');
    
    document.getElementById('productMonthYear').textContent = 
      productMonthDate.toLocaleString('default', { month: 'short', year: 'numeric' });

    try {
      const res = await fetch(`./get_monthly_top_products.php?year=${year}&month=${month}`);
      const data = await res.json();

      const ctx = document.getElementById('topProductsChart').getContext('2d');
      if (topChart) topChart.destroy();

      topChart = new Chart(ctx, {
        type: 'doughnut',
        data: {
          labels: data.labels || [],
          datasets: [{
            data: data.data || [],
            backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#20c997'],
            borderWidth: 1
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: {
              position: 'bottom',
              labels: {
                font: {
                  size: 10
                },
                padding: 10
              }
            }
          }
        }
      });
    } catch (err) {
      console.error('Error loading products chart:', err);
    }
  }

  async function loadProfitGraph(view = 'last7') {
    try {
      const res = await fetch('./get_sales_chart_data.php?view=' + view);
      const data = await res.json();

      const ctx = document.getElementById('profitChart').getContext('2d');
      if (profitChart) profitChart.destroy();

      profitChart = new Chart(ctx, {
        type: 'line',
        data: {
          labels: data.dates,
          datasets: [{
            label: 'Profit',
            data: data.profit || [],
            borderColor: '#22c55e',
            backgroundColor: 'rgba(34,197,94,0.1)',
            fill: true,
            tension: 0.4,
            pointRadius: 5,
            pointBackgroundColor: '#22c55e'
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: { legend: { position: 'top' } },
          scales: { y: { beginAtZero: true } }
        }
      });
    } catch (err) {
      console.error('Error loading profit chart:', err);
    }
  }

  // Initialize
  fetchMonthlySalesData(currentDate.getFullYear(), currentDate.getMonth());
  loadProfitGraph('last7');
  loadTopProductsChart();
  </script>
</body>

</html>