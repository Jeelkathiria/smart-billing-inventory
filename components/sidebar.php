<?php
// We assume auth_check.php was already included on this page,
// so $_SESSION['role'] is set and validated.
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'];
?>

<style>
  .sidebar {
    width: 220px;
    background: #ffffff;
    border-right: 1px solid #e0e0e0;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    justify-content: space-between;
  }
  .sidebar .nav-links a,
  .sidebar-footer a {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    color: #333;
    text-decoration: none;
    transition: all 0.2s ease;
    font-weight: 500;
    position: relative;
  }
  .sidebar .nav-links a i,
  .sidebar-footer a i {
    font-size: 1.2rem;
    min-width: 20px;
    text-align: center;
  }
  .sidebar .nav-links a.active,
  .sidebar-footer a.active {
    background-color: #d8d8d8;
    border-left: 4px solid #0056b3;
    font-weight: 600;
    transform: translateY(-2px);
  }
  .sidebar .nav-links a:hover:not(.active),
  .sidebar-footer a:hover:not(.active) {
    background-color: #f1f1f1;
    color: #000;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
  }
  .sidebar-footer {
    border-top: 1px solid #e0e0e0;
  }
</style>

<div class="sidebar">
  <div class="nav-links">
    <a href="/modules/dashboard.php" class="<?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
      <i class="bi bi-house-door"></i> Dashboard
    </a>
    <a href="/modules/billing/billing.php" class="<?= ($currentPage === 'billing.php') ? 'active' : '' ?>">
      <i class="bi bi-receipt"></i> Billing
    </a>
    <a href="/modules/sales/sales.php" class="<?= ($currentPage === 'sales.php') ? 'active' : '' ?>">
      <i class="bi bi-currency-dollar"></i> Sales
    </a>

    <?php if ($role === 'admin' || $role === 'manager' || $role === 'manager'): ?>
      <a href="/modules/products/products.php" class="<?= ($currentPage === 'products.php') ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i> Inventory
      </a>
      <a href="/modules/categories.php" class="<?= ($currentPage === 'categories.php') ? 'active' : '' ?>">
        <i class="bi bi-tags"></i> Categories
      </a>
      <a href="/modules/customers.php" class="<?= ($currentPage === 'customers.php') ? 'active' : '' ?>">
        <i class="bi bi-people"></i> Customers
      </a>
      <a href="/modules/reports/report.php" class="<?= ($currentPage === 'report.php') ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i> Reports
      </a>
      <a href="/modules/users/users.php" class="<?= ($currentPage === 'users.php') ? 'active' : '' ?>">
        <i class="bi bi-person-badge"></i> Employee
      </a>
    <?php endif; ?>
  </div>

  <div class="sidebar-footer">
    <a href="/modules/settings/settings.php" class="<?= ($currentPage === 'settings.php') ? 'active' : '' ?>">
      <i class="bi bi-gear"></i> Settings
    </a>
  </div>
</div>
