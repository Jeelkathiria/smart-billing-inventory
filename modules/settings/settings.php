<?php
// modules/settings/settings.php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php'; // session started

$user_id  = $_SESSION['user_id'] ?? null;
$store_id = $_SESSION['store_id'] ?? null;

if (!$user_id || !$store_id) {
    header("Location: /auth/index.php");
    exit;
}

// Fetch current user info
$stmt = $conn->prepare("SELECT username, role, password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$role = $user['role'] ?? '';

// Fetch current store info
$stmt = $conn->prepare("SELECT store_name, store_email, contact_number, store_code, gstin, billing_fields 
                        FROM stores WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();
$billing_fields = $store['billing_fields'] ? json_decode($store['billing_fields'], true) : [];

// ================= AJAX HANDLER =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];
    $current_password = $_POST['current_password'] ?? '';

    // Verify password
    if (!$user || !password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'msg' => 'Error: Password incorrect.']);
        exit;
    }

    // ---------- Update Profile ----------
    if ($action === 'update_profile') {
        $newName = trim($_POST['username'] ?? '');
        $newPass = $_POST['password'] ?? '';
        $changes = [];

        if ($newName && $newName !== $user['username']) {
            $stmt = $conn->prepare("UPDATE users SET username=? WHERE user_id=?");
            $stmt->bind_param("si", $newName, $user_id);
            $stmt->execute();
            $changes[] = 'Name changed';
        }

        if (!empty($newPass)) {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
            $stmt->bind_param("si", $hashed, $user_id);
            $stmt->execute();
            $changes[] = 'Password changed';
        }

        echo json_encode($changes
            ? ['success' => true, 'msg' => 'Profile updated: ' . implode(' & ', $changes)]
            : ['success' => false, 'msg' => 'No changes detected.']
        );
        exit;
    }

    // ---------- Update Store ----------
    if ($action === 'update_store' && $role === 'admin') {
        $newName    = trim($_POST['store_name'] ?? '');
        $newEmail   = trim($_POST['store_email'] ?? '');
        $newContact = trim($_POST['contact_number'] ?? '');
        $newGSTIN   = trim($_POST['gstin'] ?? '');
        $fields     = $_POST['fields'] ?? [];
        $billing    = json_encode($fields);

        $changes = [];

        if ($newName !== $store['store_name']) {
            $stmt = $conn->prepare("UPDATE stores SET store_name=? WHERE store_id=?");
            $stmt->bind_param("si", $newName, $store_id);
            $stmt->execute();
            $changes[] = 'Name changed';
        }
        if ($newEmail !== $store['store_email']) {
            $stmt = $conn->prepare("UPDATE stores SET store_email=? WHERE store_id=?");
            $stmt->bind_param("si", $newEmail, $store_id);
            $stmt->execute();
            $changes[] = 'Email changed';
        }
        if ($newContact !== $store['contact_number']) {
            $stmt = $conn->prepare("UPDATE stores SET contact_number=? WHERE store_id=?");
            $stmt->bind_param("si", $newContact, $store_id);
            $stmt->execute();
            $changes[] = 'Contact changed';
        }
        if ($newGSTIN !== $store['gstin']) {
            $stmt = $conn->prepare("UPDATE stores SET gstin=? WHERE store_id=?");
            $stmt->bind_param("si", $newGSTIN, $store_id);
            $stmt->execute();
            $changes[] = 'GSTIN updated';
        }
        if ($billing !== $store['billing_fields']) {
            $stmt = $conn->prepare("UPDATE stores SET billing_fields=? WHERE store_id=?");
            $stmt->bind_param("si", $billing, $store_id);
            $stmt->execute();
            $changes[] = 'Billing fields updated';
        }

        echo json_encode($changes
            ? ['success' => true, 'msg' => 'Store updated: ' . implode(' & ', $changes)]
            : ['success' => false, 'msg' => 'No changes detected.']
        );
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
  <style>
  /* ===== Layout ===== */
  .navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    z-index: 1030;
    background: #fff;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    padding: 0 20px;
  }

  .sidebar {
    width: 220px;
    position: fixed;
    top: 0;
    bottom: 0;
    background: #fff;
    border-right: 1px solid #dee2e6;
    padding-top: 60px;
    display: flex;
    flex-direction: column;
  }

  .sidebar a {
    padding: 12px 20px;
    color: #333;
    text-decoration: none;
    display: flex;
    align-items: center;
    gap: 10px;
    transition: background 0.2s;
  }

  .sidebar a:hover {
    background: #f0f0f0;
    border-left: 4px solid #007bff;
  }

  .container {
    margin-left: 220px;
    padding: 20px;
    padding-top: 80px;
  }

  /* ===== Settings Page ===== */
  .settings-wrapper {
    max-width: 850px;
    margin: 0 auto;
  }

  .settings-header {
    margin-bottom: 25px;
  }

  .nav-tabs {
    border-bottom: 2px solid #dee2e6;
  }

  .nav-tabs .nav-link {
    font-weight: 500;
    border-radius: 0;
  }

  .tab-content {
    margin-top: 20px;
  }

  /* ===== Card Styling ===== */
  .settings-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  }

  .settings-card h5 {
    margin-bottom: 20px;
    font-size: 1.1rem;
    font-weight: 600;
    color: #495057;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
  }

  .form-label {
    font-weight: 500;
  }

  /* ===== Checkbox Group ===== */
  #billing-fields label {
    display: block;
    margin-bottom: 8px;
    cursor: pointer;
  }

  #billing-fields input[type="checkbox"] {
    margin-right: 6px;
  }

  /* ===== Buttons ===== */
  .btn-primary {
    border-radius: 8px;
    padding: 8px 16px;
  }

  .input-group .btn {
    border-radius: 0 8px 8px 0 !important;
  }

  .container {
    margin-left: 220px;
    padding: 20px;
    padding-top: 80px;
  }

  .settings-wrapper {
    max-width: 850px;
    margin: 0 auto;
  }

  .settings-card {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 25px;
    box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05);
  }

  .settings-card h5 {
    margin-bottom: 20px;
    font-size: 1.1rem;
    font-weight: 600;
    color: #495057;
    border-bottom: 1px solid #eee;
    padding-bottom: 10px;
  }

  .form-label {
    font-weight: 500;
  }

  #billing-fields label {
    display: block;
    margin-bottom: 8px;
    cursor: pointer;
  }

  #billing-fields input[type="checkbox"] {
    margin-right: 6px;
  }

  .btn-primary {
    border-radius: 8px;
    padding: 8px 16px;
  }
  </style>



</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <div class="container mt-4">
    <div class="settings-wrapper">
      <div class="settings-header">
        <h2 class="fw-bold">⚙️ Settings</h2>
        <p class="text-muted">Manage your profile and store configurations</p>
      </div>

      <!-- Tabs -->
      <ul class="nav nav-tabs" id="settingsTab" role="tablist">
        <li class="nav-item">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile" type="button">Profile
            Settings</button>
        </li>
        <?php if ($role === 'admin'): ?>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#store" type="button">Store Settings</button>
        </li>
        <?php endif; ?>
      </ul>

      <!-- Tab Content -->
      <div class="tab-content">

        <!-- Profile Settings -->
        <div class="tab-pane fade show active" id="profile">
          <div class="settings-card">
            <h5>👤 Profile Information</h5>
            <form class="profileForm">
              <div class="mb-3">
                <label class="form-label">Full Name</label>
                <input type="text" name="username" class="form-control"
                  value="<?= htmlspecialchars($user['username']) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Role</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($user['role']) ?>" readonly>
              </div>
              <button type="submit" class="btn btn-primary">Update Profile</button>
            </form>
          </div>

          <div class="settings-card">
            <h5>🔒 Security</h5>
            <form class="profileForm">
              <div class="mb-3">
                <label class="form-label">New Password</label>
                <input type="password" name="password" class="form-control">
              </div>
              <div class="mb-3">
                <label class="form-label">Confirm Password</label>
                <input type="password" name="confirm_password" class="form-control">
              </div>
              <button type="submit" class="btn btn-primary">Change Password</button>
            </form>
          </div>
        </div>

        <!-- Store Settings -->
        <?php if ($role === 'admin'): ?>
        <div class="tab-pane fade" id="store">
          <div class="settings-card">
            <h5>🏪 Store Information</h5>
            <form class="storeForm">
              <div class="mb-3">
                <label class="form-label">Store Name</label>
                <input type="text" name="store_name" class="form-control"
                  value="<?= htmlspecialchars($store['store_name']) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Store Code</label>
                <input type="text" class="form-control" value="<?= htmlspecialchars($store['store_code']) ?>" readonly>
              </div>
              <div class="mb-3">
                <label class="form-label">GSTIN Number</label>
                <input type="text" name="gstin" class="form-control"
                  value="<?= htmlspecialchars($store['gstin'] ?? '') ?>" placeholder="Enter GSTIN">
              </div>
              <div class="mb-3">
                <label class="form-label">Store Email</label>
                <input type="email" name="store_email" class="form-control"
                  value="<?= htmlspecialchars($store['store_email']) ?>">
              </div>
              <div class="mb-3">
                <label class="form-label">Contact Number</label>
                <input type="text" name="contact_number" class="form-control"
                  value="<?= htmlspecialchars($store['contact_number']) ?>">
              </div>
              <button type="submit" class="btn btn-primary">Update Store Info</button>
            </form>
          </div>

          <div class="settings-card">
            <h5>🧾 Billing Fields</h5>
            <form class="storeForm">
              <div id="billing-fields" class="mb-3">
                <label><input type="checkbox" name="fields[customer_name]"
                    <?= !empty($billing_fields['customer_name'])?'checked':'' ?>> Customer Name</label>
                <label><input type="checkbox" name="fields[customer_mobile]"
                    <?= !empty($billing_fields['customer_mobile'])?'checked':'' ?>> Customer Mobile</label>
                <label><input type="checkbox" name="fields[address]"
                    <?= !empty($billing_fields['address'])?'checked':'' ?>> Address</label>
              </div>
              <button type="submit" class="btn btn-primary">Update Billing Fields</button>
            </form>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- Password Modal -->
  <div class="modal fade" id="confirmPasswordModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title">Confirm Password</h5>
        </div>
        <div class="modal-body"><input type="password" id="confirmPasswordInput" class="form-control"
            placeholder="Enter your password"></div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="confirmPasswordBtn" class="btn btn-primary">Confirm</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  function showToast(msg) {
    const html = `<div class="position-fixed top-0 end-0 p-3" style="z-index:9999">
      <div class="toast align-items-center text-bg-${msg.startsWith('Error')?'danger':'success'} border-0 show">
        <div class="d-flex"><div class="toast-body">${msg}</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div></div></div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    setTimeout(() => document.querySelector('.toast')?.remove(), 2000);
  }

  document.addEventListener('DOMContentLoaded', () => {
    const copyBtn = document.getElementById('copyStoreCodeBtn');
    const copyIcon = document.getElementById('copyIcon');
    const storeCodeField = document.getElementById('storeCodeField');
    if (copyBtn) {
      copyBtn.addEventListener('click', async () => {
        try {
          await navigator.clipboard.writeText(storeCodeField.value);
          copyIcon.classList.replace('bi-clipboard', 'bi-check2');
          copyBtn.classList.replace('btn-primary', 'btn-success');
          setTimeout(() => {
            copyIcon.classList.replace('bi-check2', 'bi-clipboard');
            copyBtn.classList.replace('btn-success', 'btn-primary');
          }, 1500);
        } catch (err) {
          console.error('Copy failed', err);
        }
      });
    }

    let pendingData = null;
    let pendingAction = '';

    function handleFormSubmit(e, action) {
      e.preventDefault();
      const formData = new FormData(e.target);
      pendingData = formData;
      pendingAction = action;
      new bootstrap.Modal(document.getElementById('confirmPasswordModal')).show();
    }
      document.querySelectorAll('.profileForm').forEach(form =>
        form.addEventListener('submit', e => handleFormSubmit(e, 'update_profile'))
      );
      document.querySelectorAll('.storeForm').forEach(form =>
        form.addEventListener('submit', e => handleFormSubmit(e, 'update_store'))
      );


    document.getElementById('confirmPasswordBtn').addEventListener('click', () => {
      const pwd = document.getElementById('confirmPasswordInput').value;
      if (!pwd) return;
      pendingData.append('current_password', pwd);
      pendingData.append('action', pendingAction);

      fetch('', {
          method: 'POST',
          body: pendingData
        })
        .then(r => r.json())
        .then(res => {
          showToast(res.msg);
        })
        .catch(err => console.error(err));

      bootstrap.Modal.getInstance(document.getElementById('confirmPasswordModal')).hide();
      document.getElementById('confirmPasswordInput').value = '';
    });
  });
  </script>
</body>

</html>