<!-- BillMitra Navbar -->
<nav class="navbar px-4" style="
  background: linear-gradient(90deg, #ffffff 0%, #f6f7f8 100%);
  box-shadow: 0 3px 8px rgba(0, 0, 0, 0.08);
  backdrop-filter: blur(6px);
  z-index: 1030;">
  <div class="container-fluid d-flex justify-content-between align-items-center">

    <!-- Brand Section -->
    <div class="d-flex align-items-center">
      <div class="me-2 d-flex justify-content-center align-items-center rounded"
        style="width: 44px; height: 44px; background: #e9f2ff; box-shadow: 0 2px 6px rgba(13, 110, 253, 0.15);">
        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden
          style="width: 28px; height: 28px;">
          <rect x="4" y="8" width="40" height="32" rx="6" fill="#E9F2FF" />
          <path d="M12 30v-8h8v8H12zM28 30v-12h8v12h-8z" fill="#007bff" />
        </svg>
      </div>
      <span class="navbar-brand mb-0 h5 fw-semibold text-dark">BillMitra</span>
    </div>

    <!-- Right Controls -->
    <div class="d-flex align-items-center gap-2">
      <a href="/auth/logout.php"
        class="btn btn-outline-danger btn-sm d-flex align-items-center gap-1 px-3 py-2"
        style="border-radius: 8px; font-weight: 500;">
        <i class="bi bi-box-arrow-right"></i>
        Logout
      </a>
    </div>
  </div>
</nav>
