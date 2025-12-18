// Global utilities: showToast and enforceNonNegativeInputs
(function() {
  // inject minimal styles for toasts
  const css = `
  .global-toast-container { position: fixed; top: 1rem; right: 1rem; z-index: 1060; display: flex; flex-direction: column; gap: .5rem; }
  .global-toast { min-width: 220px; max-width: 360px; padding: .6rem .9rem; color: #fff; border-radius: .45rem; box-shadow: 0 6px 20px rgba(2,6,23,0.08); opacity: 0; transform: translateY(-6px); transition: opacity .18s ease, transform .18s ease; font-size: .9rem; }
  .global-toast.show { opacity: 1; transform: translateY(0); }
  .global-toast.success { background: linear-gradient(90deg,#16a34a,#059669); }
  .global-toast.danger { background: linear-gradient(90deg,#dc2626,#b91c1c); }
  .global-toast.warning { background: linear-gradient(90deg,#f59e0b,#d97706); }
  .global-toast.info { background: linear-gradient(90deg,#0ea5e9,#0284c7); }
  `;
  const s = document.createElement('style'); s.textContent = css; document.head.appendChild(s);

  // ensure container exists
  function ensureContainer(){
    let c = document.getElementById('globalToasts');
    if (!c) {
      c = document.createElement('div');
      c.id = 'globalToasts';
      c.className = 'global-toast-container';
      document.body.appendChild(c);
    }
    return c;
  }

  function showToast(message, type='info', duration=2000) {
    try {
      const c = ensureContainer();
      const t = document.createElement('div');
      t.className = `global-toast ${type}`;
      t.setAttribute('role', 'status');
      t.innerHTML = message;
      c.appendChild(t);
      // trigger show
      requestAnimationFrame(() => t.classList.add('show'));
      // remove after duration
      setTimeout(() => {
        t.classList.remove('show');
        setTimeout(() => t.remove(), 200);
      }, duration);
    } catch (e) {
      // fallback
      console.error('showToast error', e);
      alert(message);
    }
  }

  // Expose globally
  window.showGlobalToast = showToast;
  // Optional compatibility aliases
  window.showToast = (message, type='info', duration=2000) => showToast(message, type, duration);

  // Make showPopup behave as non-blocking toast for quick notices (keeps existing callers working)
  window.showPopup = (message, title = 'Notice') => showToast((title ? (title + ': ') : '') + message, 'info', 2000);

  // Enforce non-negative number inputs across the page
  function enforceNonNegativeInputs() {
    const inputs = document.querySelectorAll('input[type=number]');
    inputs.forEach(inp => {
      // If no min attribute set, default to 0
      if (!inp.hasAttribute('min')) inp.setAttribute('min', '0');

      // clamp on input/change
      function clamp() {
        const min = parseFloat(inp.getAttribute('min'));
        const max = inp.hasAttribute('max') ? parseFloat(inp.getAttribute('max')) : null;
        let val = inp.value;
        if (val === '' || val === null) return; // allow empty (forms handle required)
        // allow decimals and negatives handling
        const num = Number(val);
        if (!isFinite(num)) return;
        if (min !== null && num < min) {
          inp.value = String(min);
          // show brief toast
          showToast('Value cannot be negative', 'warning', 2000);
        } else if (max !== null && num > max) {
          inp.value = String(max);
          showToast('Value higher than allowed', 'warning', 2000);
        }
      }
      inp.addEventListener('input', clamp);
      inp.addEventListener('change', clamp);
      // also prevent using the down arrow to go negative in browsers without min enforcement
      inp.addEventListener('keydown', (e) => {
        if ((e.key === 'ArrowDown' || e.key === 'Down') && inp.step && inp.value) {
          const min = parseFloat(inp.getAttribute('min'));
          const step = parseFloat(inp.getAttribute('step')) || 1;
          const next = Number(inp.value) - step;
          if (min !== null && !isNaN(next) && next < min) {
            e.preventDefault();
            inp.value = String(min);
            showToast('Minimum value reached', 'warning', 1500);
          }
        }
      });
    });
  }

  document.addEventListener('DOMContentLoaded', enforceNonNegativeInputs);
  window.enforceNonNegativeInputs = enforceNonNegativeInputs;
})();