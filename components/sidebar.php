<?php $currentPage = basename($_SERVER['PHP_SELF']); ?>


<style>

  .sidebar .nav-links a {
  position: relative;
  padding-left: 16px;
  transition: background-color 0.3s;
}

.sidebar .nav-links a.active::before {
  content: '';
  position: absolute;
  left: 0;
  top: 10%;
  height: 80%;
  width: 4px;
  background-color: #f0f0f0;
  border-left: 4px solid #007bff;

}


.sidebar .nav-links a,
.sidebar-footer a {
  display: block;
  padding: 12px 16px;
  color: #333;
  text-decoration: none;
  transition: all 0.2s ease;
  box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
  position: relative;
}

/* Active link looks pressed in */
.sidebar .nav-links a.active,
.sidebar-footer a.active {
  background-color: #d8d8d8;
  transform: translateY(-2px);
}

/* Hover effect for others */
.sidebar .nav-links a:hover:not(.active),
.sidebar-footer a:hover:not(.active) {
  background-color: #f1f1f1;
  color: #000;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
}

</style>

<div class="sidebar">
  <div class="nav-links">
    <a href="/modules/dashboard.php" class="<?= ($currentPage == 'dashboard.php') ? 'active' : '' ?>">
      <i class="bi bi-house-door"></i> Dashboard
    </a>
    <a href="/modules/sales/sales.php" class="<?= ($currentPage == 'sales.php') ? 'active' : '' ?>">
      <i class="bi bi-currency-dollar"></i> Sales
    </a>
    <a href="/modules/products/products.php" class="<?= ($currentPage == 'products.php') ? 'active' : '' ?>">
      <i class="bi bi-box-seam"></i> Inventory
    </a>
    <a href="/modules/billing/billing.php" class="<?= ($currentPage == 'billing.php') ? 'active' : '' ?>">
      <i class="bi bi-receipt"></i> Billing
    </a>
    <a href="/modules/categories.php" class="<?= ($currentPage == 'categories.php') ? 'active' : '' ?>">
      <i class="bi bi-tags"></i> Categories
    </a>
    <a href="/modules/customers.php" class="<?= ($currentPage == 'customers.php') ? 'active' : '' ?>">
      <i class="bi bi-people"></i> Customers
    </a>
    <a href="/modules/reports/report.php" class="<?= ($currentPage == 'report.php') ? 'active' : '' ?>">
      <i class="bi bi-graph-up"></i> Reports
    </a>
  </div>
  <div class="sidebar-footer">
    <a href="admin_panel.php" class="<?= ($currentPage == 'admin_panel.php') ? 'active' : '' ?>">
      <i class="bi bi-gear"></i> Settings
    </a>
  </div>
</div>
