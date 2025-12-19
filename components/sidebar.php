<?php
/**
 * File: components/sidebar.php
 * Purpose: Sidebar UI component — renders navigation links and role-aware menus.
 * Project: Smart Billing & Inventory
 * Author: Project Maintainers
 * Last Modified: 2025-12-18
 * Notes: UI-only change; no logic modified.
 */
$currentPage = basename($_SERVER['PHP_SELF']);
$role = $_SESSION['role'];
?>

<style>
/* ---------------- Sidebar Base ---------------- */
.sidebar {
  width: 230px;
  background: linear-gradient(180deg, #ffffff 0%, #f7f8fa 100%);
  border-right: 1px solid #e5e7eb;
  min-height: 100vh;
  display: flex;
  flex-direction: column;
  justify-content: space-between;
  box-shadow: 2px 0 6px rgba(0, 0, 0, 0.05);
  transition: width 0.3s ease;
  overflow: hidden;
  z-index: 1025; /* Sit below navbar (1030), above content */
}

.sidebar.collapsed {
  width: 70px;
}

/* ---------------- Header (Logo + Toggle) ---------------- */
.sidebar-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 14px 18px;
  border-bottom: 1px solid #e5e7eb;
  background: linear-gradient(180deg, #ffffff 0%, #f6f7f9 100%);
  box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.sidebar-header .logo {
  display: flex;
  align-items: center;
  gap: 10px;
  font-weight: 600;
  font-size: 16px;
  color: #111;
  white-space: nowrap;
}

.sidebar-header img {
  width: 24px;
  height: 24px;
}

.sidebar-header button {
  background: none;
  border: none;
  color: #333;
  font-size: 1.2rem;
  cursor: pointer;
  transition: transform 0.3s ease;
}

/* show/hide brand name beside toggle */
.sidebar-header .brand-name {
  font-weight: 700;
  color: #0b2545;
  margin-left: 8px;
  transition: opacity .18s ease, transform .18s ease;
}
.sidebar.collapsed .sidebar-header .brand-name {
  opacity: 0;
  transform: translateX(-6px);
  pointer-events: none;
}

.sidebar.collapsed .nav-links a span,
.sidebar.collapsed .sidebar-footer a span {
  display: none;
}

/* ---------------- Links ---------------- */
.sidebar .nav-links a,
.sidebar-footer a {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 18px;
  color: #333;
  text-decoration: none;
  transition: all 0.25s ease;
  font-weight: 500;
  position: relative;
  border-left: 3px solid transparent;
}

.sidebar .nav-links a i,
.sidebar-footer a i {
  font-size: 1.1rem;
  min-width: 22px;
  text-align: center;
  transition: all 0.25s ease;
}

/* Active Link (Blue Border Left) */
.sidebar .nav-links a.active,
.sidebar-footer a.active {
  background: linear-gradient(90deg, #e9f2ff 0%, #f2f7ff 100%);
  border-left: 4px solid #007bff;
  font-weight: 600;
  color: #000;
  box-shadow: inset 0 0 8px rgba(13, 110, 253, 0.08);
}

/* Hover Effect */
.sidebar .nav-links a:hover:not(.active),
.sidebar-footer a:hover:not(.active) {
  background: #f4f6fa;
  color: #000;
  border-left: 4px solid #007bff;
  transform: translateX(3px);
  box-shadow: 2px 2px 8px rgba(0, 0, 0, 0.04);
}

/* Footer Separator */
.sidebar-footer {
  border-top: 1px solid #e5e7eb;
  background: #fafbfc;
}

/* Center icons when collapsed */
.sidebar.collapsed .nav-links a,
.sidebar.collapsed .sidebar-footer a {
  justify-content: center;
  padding: 12px 0;
}

.sidebar.collapsed .nav-links a i,
.sidebar.collapsed .sidebar-footer a i {
  font-size: 1.3rem;
}

/* Overlay (fade background) */
.sidebar-overlay {
  position: fixed;
  inset: 0;
  background: linear-gradient(180deg, rgba(2,6,23,0.18), rgba(2,6,23,0.34));
  backdrop-filter: blur(4px);
  z-index: 1040;
  opacity: 0;
  pointer-events: none;
  transition: opacity .18s ease;
}
.sidebar-overlay.show {
  opacity: 1;
  pointer-events: auto;
}

/* ensure chevron rotates smoothly */
.sidebar-header button i {
  transition: transform .22s cubic-bezier(.2,.9,.2,1);
  display: inline-block;
}

/* Rotate when collapsed (desktop) */
.sidebar.collapsed .sidebar-header button i {
  transform: rotate(180deg);
}

/* Reset rotation when sidebar is open (mobile / overlay) or hover-expanded */
.sidebar.open .sidebar-header button i,
.sidebar.hover-expand .sidebar-header button i {
  transform: rotate(0deg);
}
</style>

<!-- Sidebar -->
<div class="sidebar" id="sidebar">
  <div>
    <div class="sidebar-header">
      <div style="display:flex; align-items:center; gap:6px;">
        <button id="toggleSidebar" aria-label="Toggle sidebar"><i class="bi bi-chevron-left"></i></button>
        <span class="brand-name">BillMitra</span>
      </div>
    </div>

    <div class="nav-links">
      <a href="/modules/dashboard.php" class="<?= ($currentPage === 'dashboard.php') ? 'active' : '' ?>">
        <i class="bi bi-house-door"></i> <span>Dashboard</span>
      </a>
      <a href="/modules/billing/billing.php" class="<?= ($currentPage === 'billing.php') ? 'active' : '' ?>">
        <i class="bi bi-receipt"></i> <span>Billing</span>
      </a>
      <a href="/modules/sales/sales.php" class="<?= ($currentPage === 'sales.php') ? 'active' : '' ?>">
        <i class="bi bi-currency-dollar"></i> <span>Sales</span>
      </a>

      <?php if ($role === 'admin' || $role === 'manager'): ?>
      <a href="/modules/products/products.php" class="<?= ($currentPage === 'products.php') ? 'active' : '' ?>">
        <i class="bi bi-box-seam"></i> <span>Inventory</span>
      </a>
      <a href="/modules/categories.php" class="<?= ($currentPage === 'categories.php') ? 'active' : '' ?>">
        <i class="bi bi-tags"></i> <span>Categories</span>
      </a>
      <a href="/modules/customers/customers.php" class="<?= ($currentPage === 'customers.php') ? 'active' : '' ?>">
        <i class="bi bi-people"></i> <span>Customers</span>
      </a>
      <a href="/modules/reports/report.php" class="<?= ($currentPage === 'report.php') ? 'active' : '' ?>">
        <i class="bi bi-graph-up"></i> <span>Reports</span>
      </a>
      <a href="/modules/users/users.php" class="<?= ($currentPage === 'users.php') ? 'active' : '' ?>">
        <i class="bi bi-person-badge"></i> <span>Employee</span>
      </a>
      <?php endif; ?>
    </div>
  </div>

  <div class="sidebar-footer">
    <a href="/modules/settings/settings.php" class="<?= ($currentPage === 'settings.php') ? 'active' : '' ?>">
      <i class="bi bi-gear"></i> <span>Settings</span>
    </a>
  </div>
</div>

<!-- overlay for fade background -->
<div id="sidebarOverlay" class="sidebar-overlay" tabindex="-1" aria-hidden="true"></div>

<!-- Sidebar Toggle Script -->
<script>
const sidebar = document.getElementById('sidebar');
const toggleButton = document.getElementById('toggleSidebar');
const overlay = document.getElementById('sidebarOverlay');

/* ---------- Load Sidebar State ---------- */
document.addEventListener('DOMContentLoaded', function() {
  const collapsed = localStorage.getItem('sidebarCollapsed') === 'true';
  if (collapsed) sidebar.classList.add('collapsed');
});

/* ---------- Toggle Sidebar ---------- */
toggleButton.addEventListener('click', function() {
  // Mobile: open as overlay
  if (window.innerWidth < 992) {
    const isOpen = sidebar.classList.toggle('open');
    overlay.classList.toggle('show', isOpen);
    // do not persist mobile open state as "collapsed"
    return;
  }

  // Desktop: collapse / expand and persist
  sidebar.classList.toggle('collapsed');
  localStorage.setItem('sidebarCollapsed', sidebar.classList.contains('collapsed'));
});

/* ---------- Overlay click / ESC to close ---------- */
overlay.addEventListener('click', function() {
  sidebar.classList.remove('open');
  overlay.classList.remove('show');
});
document.addEventListener('keydown', function(e) {
  if (e.key === 'Escape') {
    if (sidebar.classList.contains('open')) {
      sidebar.classList.remove('open');
      overlay.classList.remove('show');
    }
  }
});

/* Keep layout responsive on resize */
window.addEventListener('resize', function() {
  if (window.innerWidth >= 992) {
    overlay.classList.remove('show');
    sidebar.classList.remove('open');
  }
});
</script>