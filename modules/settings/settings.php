<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

$user_id  = $_SESSION['user_id'] ?? null;
$store_id = $_SESSION['store_id'] ?? null;

if (!$user_id || !$store_id) {
  header("Location: /auth/index.php");
  exit;
}

// Fetch user info
$stmt = $conn->prepare("SELECT username, role FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$role = $user['role'] ?? '';

// Fetch store info
$stmt = $conn->prepare("SELECT store_name, store_email, contact_number, store_code, gstin, billing_fields FROM stores WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$billing_fields = json_decode($store['billing_fields'] ?? '{}', true);
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <link rel="stylesheet" href="/assets/css/common.css">
  <style>
  body {
    margin-top: 10vh;
    background: #f5f6fa;
  }

  .settings-container {
    max-width: 900px;
    margin: 40px auto;
  }

  .card {
    border: none;
    border-radius: 12px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08);
    margin-bottom: 25px;
  }

  .card-header {
    background: #fff;
    font-weight: 600;
    font-size: 1.05rem;
    display: flex;
    justify-content: space-between;
    align-items: center;
  }
  </style>
</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <main class="settings-container container mt-4">
    <!-- ===================== TAB NAVIGATION ===================== -->
    <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
      <li class="nav-item">
        <button class="nav-link active" id="personal-tab" data-bs-toggle="tab" data-bs-target="#personal"
          type="button"><i class="bi bi-person-circle"></i> Personal</button>
      </li>
      <?php if ($role === 'admin'): ?>
      <li class="nav-item">
        <button class="nav-link" id="store-tab" data-bs-toggle="tab" data-bs-target="#store" type="button"><i
            class="bi bi-shop"></i>
          Store</button>
      </li>
      <?php endif; ?>
    </ul>

    <!-- ===================== TAB CONTENT ===================== -->
    <div class="tab-content" id="settingsTabsContent">
      <!-- ========== PERSONAL TAB ========== -->
      <div class="tab-pane fade show active" id="personal">
        <!-- PROFILE CARD -->
        <div class="card mb-3">
          <div class="card-header">
            <span><i class="bi bi-person-fill"></i> Profile Information</span>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editProfileModal">
              <i class="bi bi-pencil-square"></i> Edit
            </button>
          </div>
          <div class="card-body">
            <p><strong>Name:</strong> <?= htmlspecialchars($user['username']) ?></p>
            <p><strong>Role:</strong> <?= htmlspecialchars($user['role']) ?></p>
          </div>
        </div>

        <!-- CHANGE PASSWORD CARD -->
        <div class="card">
          <div class="card-header">
            <span><i class="bi bi-shield-lock"></i> Change Password</span>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
              <i class="bi bi-key"></i> Change
            </button>
          </div>
          <div class="card-body">
            <p>Keep your account secure by changing your password regularly.</p>
          </div>
        </div>
      </div>

      <!-- ========== STORE TAB ========== -->
      <?php if ($role === 'admin'): ?>
      <div class="tab-pane fade" id="store">
        <!-- STORE INFO -->
        <div class="card mb-3">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-shop"></i> Store Information</span>
            <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editStoreModal">
              <i class="bi bi-pencil-square"></i> Edit
            </button>
          </div>

          <div class="card-body">
            <p>
              <strong>Store Name:</strong> <?= htmlspecialchars($store['store_name']) ?>
            </p>

            <p><strong>Store Code:</strong>
              <span id="storeCode"><?= htmlspecialchars($store['store_code']) ?></span>
              <i id="copyIcon" class="bi bi-clipboard ms-2 text-primary" style="cursor: pointer;" title="Copy"
                onclick="copyStoreCode()"></i>
            </p>


            <p><strong>Email:</strong> <?= htmlspecialchars($store['store_email']) ?></p>
            <p><strong>Contact:</strong> <?= htmlspecialchars($store['contact_number']) ?></p>

            <p>
              <strong>GSTIN:</strong>
              <?php if (!empty($store['gstin'])): ?>
              <?= htmlspecialchars($store['gstin']) ?>
              <?php else: ?>
              <span class="text-muted">-- Not entered --</span>
              <i class="bi bi-info-circle-fill text-warning ms-2" data-bs-toggle="tooltip"
                title="Enter GSTIN to appear on the bill"></i>
              <?php endif; ?>
            </p>
          </div>
        </div>

        <!-- BILLING FIELDS -->
        <div class="card">
          <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-receipt-cutoff"></i> Billing Fields</span>
            <i class="bi bi-info-circle text-primary" data-bs-toggle="tooltip" data-bs-placement="left"
              title="Don’t forget to save otherwise changes won’t be applied." style="cursor: help;"></i>
          </div>

          <div class="card-body">
            <form id="billingFieldsForm">
              <?php
      $fields = [
        'customer_name' => 'Customer Name',
        'customer_mobile' => 'Customer Mobile',
        'customer_email' => 'Customer Email',
        'customer_address' => 'Customer Address'
      ];
      foreach ($fields as $key => $label):
      ?>
              <div class="form-check form-switch mb-2">
                <input class="form-check-input" type="checkbox" name="fields[<?= $key ?>]"
                  <?= !empty($billing_fields[$key]) ? 'checked' : '' ?>>
                <label class="form-check-label"><?= $label ?></label>
              </div>
              <?php endforeach; ?>
              <button type="submit" class="btn btn-primary mt-3">Save Changes</button>
            </form>
          </div>
        </div>

      </div>
      <?php endif; ?>
    </div>
  </main>

  <!-- ===================== EDIT PROFILE MODAL ===================== -->
  <div class="modal fade" id="editProfileModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="profileForm">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-person-lines-fill"></i> Edit Profile</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Full Name</label>
              <input type="text" class="form-control" name="username" required>
            </div>
            <div class="mb-3">
              <label class="form-label">Email</label>
              <input type="email" class="form-control" name="email" required>
            </div>
            <button type="submit" class="btn btn-primary w-100">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===================== CHANGE PASSWORD MODAL ===================== -->
  <div class="modal fade" id="changePasswordModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="passwordForm">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-shield-lock-fill"></i> Change Password</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <!-- Step 1: Verify current password -->
            <div id="verifyStep">
              <div class="mb-3">
                <label class="form-label">Current Password</label>
                <input type="password" class="form-control" name="current_password" required>
              </div>
              <button type="button" class="btn btn-primary w-100" id="verifyBtn">Verify</button>
            </div>

            <!-- Step 2: New password (hidden) -->
            <div id="updateStep" style="display:none;">
              <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" class="form-control" name="new_password" required>
              </div>
              <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" class="form-control" name="confirm_password" required>
              </div>
              <button type="button" class="btn btn-success w-100" id="updateBtn">Update Password</button>
            </div>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===================== EDIT STORE MODAL ===================== -->
  <div class="modal fade" id="editStoreModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <form id="storeForm">
          <div class="modal-header">
            <h5 class="modal-title"><i class="bi bi-shop"></i> Edit Store</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Store Name</label>
              <input type="text" class="form-control" name="store_name"
                value="<?= htmlspecialchars($store['store_name']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Store Email</label>
              <input type="email" class="form-control" name="store_email"
                value="<?= htmlspecialchars($store['store_email']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">Contact Number</label>
              <input type="text" class="form-control" name="contact_number"
                value="<?= htmlspecialchars($store['contact_number']) ?>">
            </div>
            <div class="mb-3">
              <label class="form-label">GSTIN</label>
              <input type="text" class="form-control" name="gstin" value="<?= htmlspecialchars($store['gstin']) ?>">
            </div>
            <button type="submit" class="btn btn-primary w-100">Save Changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ===================== JS ===================== -->

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  /* ============================================================
   ✅ UNIVERSAL FORM HANDLER SYSTEM (Profile / Store / Billing)
============================================================ */
  function showModalMessage(form, message, type = 'success') {
    let msgBox = form.querySelector('.modal-msg');
    if (!msgBox) {
      msgBox = document.createElement('div');
      msgBox.className = 'modal-msg mt-3';
      form.appendChild(msgBox); // ✅ append directly below Save button
    }

    msgBox.innerHTML = `
    <div class="alert alert-${type} py-2 px-3 mb-2">${message}</div>
  `;
    setTimeout(() => (msgBox.innerHTML = ''), 4000);
  }

  async function handleForm(formId, url, reload = false) {
    const form = document.getElementById(formId);
    if (!form) return;

    form.addEventListener('submit', async e => {
      e.preventDefault();
      const fd = new FormData(form);

      try {
        const res = await fetch(`/modules/settings/${url}`, {
          method: 'POST',
          body: fd,
          headers: {
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'include' // ✅ Keep PHP session alive
        });

        const text = await res.text();
        console.log(`🔍 Raw response from ${url}:`, text);

        // Check if session expired (HTML returned instead of JSON)
        if (text.trim().startsWith('<')) {
          showModalMessage(form, 'Session expired. Please log in again.', 'danger');
          return;
        }

        const data = JSON.parse(text);
        const type = (data.success || data.status === 'success') ? 'success' : 'danger';
        showModalMessage(form, data.msg || data.message || 'Something went wrong.', type);

        // On success → hide modal + optional reload
        if (type === 'success') {
          const modalEl = bootstrap.Modal.getInstance(form.closest('.modal'));
          if (modalEl) setTimeout(() => modalEl.hide(), 800);
          if (reload) setTimeout(() => location.reload(), 1000);
        }

      } catch (err) {
        console.error(`❌ Error submitting ${formId}:`, err);
        showModalMessage(form, 'Network or server error. Please try again.', 'danger');
      }
    });
  }

  /* ============================================================
     ✅ ATTACH ALL FORMS
  ============================================================ */
  handleForm('profileForm', 'update_profile.php', true);
  handleForm('storeForm', 'update_store.php', true);
  handleForm('billingFieldsForm', 'update_billing_fields.php');

  /* ============================================================
     🔐 CHANGE PASSWORD LOGIC
  ============================================================ */
  const verifyBtn = document.getElementById('verifyBtn');
  const updateBtn = document.getElementById('updateBtn');
  const verifyStep = document.getElementById('verifyStep');
  const updateStep = document.getElementById('updateStep');
  const passwordForm = document.getElementById('passwordForm');

  if (verifyBtn && updateBtn && passwordForm) {
    verifyBtn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('stage', 'verify');
      fd.append('current_password', document.querySelector('[name="current_password"]').value);

      try {
        const res = await fetch('update_password.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();

        showModalMessage(passwordForm, data.msg, data.success ? 'success' : 'danger');

        if (data.success) {
          verifyStep.style.display = 'none';
          updateStep.style.display = 'block';
        }
      } catch {
        showModalMessage(passwordForm, 'Server error while verifying password.', 'danger');
      }
    });

    updateBtn.addEventListener('click', async () => {
      const fd = new FormData();
      fd.append('stage', 'update');
      fd.append('new_password', document.querySelector('[name="new_password"]').value);
      fd.append('confirm_password', document.querySelector('[name="confirm_password"]').value);

      try {
        const res = await fetch('update_password.php', {
          method: 'POST',
          body: fd
        });
        const data = await res.json();

        showModalMessage(passwordForm, data.msg, data.success ? 'success' : 'danger');

        if (data.success) {
          const modalEl = bootstrap.Modal.getInstance(document.getElementById('changePasswordModal'));
          if (modalEl) setTimeout(() => modalEl.hide(), 800);
          setTimeout(() => location.reload(), 1000);
        }
      } catch {
        showModalMessage(passwordForm, 'Error updating password.', 'danger');
      }
    });
  }

  // Enable Bootstrap tooltips
  document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });

  /// Copy Store Code to clipboard with tick feedback
  function copyStoreCode() {
    const code = document.getElementById('storeCode').textContent.trim();
    const icon = document.getElementById('copyIcon');

    navigator.clipboard.writeText(code).then(() => {
      // Change icon to tick
      icon.classList.remove('bi-clipboard', 'text-primary');
      icon.classList.add('bi-check-circle-fill', 'text-success');

      // Show toast
      const toast = document.createElement('div');
      toast.textContent = 'Store code copied!';
      toast.style.position = 'fixed';
      toast.style.bottom = '20px';
      toast.style.right = '20px';
      toast.style.backgroundColor = '#198754';
      toast.style.color = 'white';
      toast.style.padding = '10px 16px';
      toast.style.borderRadius = '8px';
      toast.style.boxShadow = '0 2px 10px rgba(0,0,0,0.2)';
      toast.style.fontSize = '14px';
      toast.style.zIndex = '9999';
      document.body.appendChild(toast);

      setTimeout(() => toast.remove(), 1500); // Remove toast
      setTimeout(() => {
        icon.classList.remove('bi-check-circle-fill', 'text-success');
        icon.classList.add('bi-clipboard', 'text-primary');
      }, 1000);
    }).catch(() => {
      alert('Failed to copy store code.');
    });
  }
  document.addEventListener('DOMContentLoaded', function() {
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function(tooltipTriggerEl) {
      return new bootstrap.Tooltip(tooltipTriggerEl);
    });
  });
  </script>

</body>

</html>