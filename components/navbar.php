<!-- BillMitra Navbar -->
<nav class="bm-navbar">
  <div class="container-fluid d-flex align-items-center justify-content-between">
    <div class="d-flex align-items-center gap-3">
      <!-- sidebar toggle for small screens (keeps compatibility if sidebarToggle exists) -->
      <button id="sidebarToggle" class="bm-toggle d-md-none" aria-label="Open menu"><i class="bi bi-list"></i></button>

      <div class="brand d-flex align-items-center gap-2">
        <div class="brand-icon">
          <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
            <rect x="4" y="8" width="40" height="32" rx="6" fill="#E9F2FF" />
            <path d="M12 30v-8h8v8H12zM28 30v-12h8v12h-8z" fill="#007bff" />
          </svg>
        </div>
        <div class="brand-text">
          <span class="brand-title">BillMitra</span>
          <small class="brand-sub">Billing & Inventory</small>
        </div>
      </div>
    </div>

    <div class="d-flex align-items-center gap-2">
      <a href="/auth/logout.php" class="btn btn-danger btn-sm bm-logout d-flex align-items-center gap-1">
        <i class="bi bi-box-arrow-right"></i>
        <span class="d-none d-md-inline">Logout</span>
      </a>
    </div>
  </div>
</nav>

<!-- subtle page fade (non-interactive) -->
<div class="page-fade" aria-hidden="true"></div>

<style>
/* navbar: glassy, compact, and responsive */
.bm-navbar {
  position: fixed;
  inset: 0 0 auto 0;
  z-index: 1030;
  padding: 0.5rem 1rem;
  background: rgba(255,255,255,0.62);
  backdrop-filter: blur(8px) saturate(1.05);
  border-bottom: 1px solid rgba(15,23,42,0.04);
  transition: box-shadow .22s ease, background .22s ease, transform .22s ease;
  display: block;
}

/* stronger shadow when scrolled */
.bm-navbar.scrolled {
  box-shadow: 0 10px 30px rgba(11,38,69,0.08);
  background: rgba(255,255,255,0.82);
}

/* container adjustments */
.bm-navbar .brand {
  display:flex; align-items:center;
}
.brand-icon svg { width:36px; height:36px; }
.brand-title { font-weight:700; color:#0b2545; display:block; }
.brand-sub { display:block; font-size:11px; color:#6b7280; line-height:1; }

/* search */
.bm-search {
  background: rgba(13,110,253,0.03);
  padding: .25rem .6rem;
  border-radius: 10px;
  border: 1px solid rgba(13,110,253,0.04);
}
.bm-search input { border: 0; background: transparent; outline: none; box-shadow:none; width: 220px; }

/* ghost icon button */
.bm-ghost {
  background: transparent;
  border: 1px solid rgba(15,23,42,0.04);
  padding: .35rem .5rem;
  border-radius: 8px;
  color: #0b2545;
}
.bm-ghost:hover { background: rgba(15,23,42,0.02); transform: translateY(-2px); }

/* logout */
.bm-logout { border-radius: 8px; padding: .35rem .6rem; font-weight:600; }
.bm-logout i { font-size: 1rem; }

/* sidebar toggle (small screens) */
.bm-toggle {
  background: transparent;
  border: 1px solid rgba(15,23,42,0.04);
  padding: .35rem .5rem;
  border-radius: 8px;
  color: #0b2545;
}
.bm-toggle:focus { outline: none; box-shadow: 0 6px 16px rgba(2,6,23,0.06); }

/* page fade overlay to softly fade background */
.page-fade {
  position: fixed;
  inset: 0;
  pointer-events: none;
  z-index: 1020;
  background: linear-gradient(180deg, rgba(2,6,23,0.02) 0%, rgba(2,6,23,0.04) 35%, rgba(2,6,23,0.02) 100%);
  opacity: 1;
  transition: opacity .35s ease;
}

/* responsive sizing */
@media (max-width: 768px) {
  .bm-search { display:none !important; }
  .brand-sub { display:none; }
  .brand-title { font-size:1rem; }
}
</style>

<script>
(function() {
  // scroll effect for navbar shadow
  const nav = document.querySelector('.bm-navbar');
  const fade = document.querySelector('.page-fade');

  function onScroll() {
    if (window.scrollY > 8) nav.classList.add('scrolled');
    else nav.classList.remove('scrolled');
  }
  document.addEventListener('scroll', onScroll, { passive: true });
  onScroll();

  // small sidebar toggle integration if sidebarToggle exists
  document.getElementById('sidebarToggle')?.addEventListener('click', function() {
    const sb = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sb) return;
    const open = sb.classList.toggle('open');
    overlay?.classList.toggle('show', open);
  });

  // optional: fade subtle pulsate on page load then settle
  fade.style.opacity = '0.95';
  setTimeout(() => { fade.style.opacity = '0.7'; }, 400);
  setTimeout(() => { fade.style.opacity = '0.48'; }, 1400);
})();
</script>
