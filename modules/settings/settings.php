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

// Fetch current user and store info for page render
$stmt = $conn->prepare("SELECT username, role, password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

$stmt = $conn->prepare("SELECT store_name, store_email, contact_number, store_code FROM stores WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();

// AJAX handler for updates with password check
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    $current_password = $_POST['current_password'] ?? '';
    if (!$user || !password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'msg' => 'Error: Password incorrect.']);
        exit;
    }

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

        if ($changes) {
            echo json_encode(['success' => true, 'msg' => 'Profile updated: ' . implode(' & ', $changes)]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'No changes detected.']);
        }
        exit;
    }

    if ($action === 'update_store') {
        $newName    = trim($_POST['store_name'] ?? '');
        $newEmail   = trim($_POST['store_email'] ?? '');
        $newContact = trim($_POST['contact_number'] ?? '');
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

        if ($changes) {
            echo json_encode(['success' => true, 'msg' => 'Store updated: ' . implode(' & ', $changes)]);
        } else {
            echo json_encode(['success' => false, 'msg' => 'No changes detected.']);
        }
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
    .navbar {
      position: fixed; top: 0; left: 0; right: 0; height: 60px; z-index: 1030;
      background-color: #ffffff; border-bottom: 1px solid #dee2e6; display: flex; align-items: center; padding: 0 20px;
    }
    .sidebar {
      width: 220px; position: fixed; top: 0; bottom: 0; background: #ffffff; border-right: 1px solid #dee2e6;
      padding-top: 60px; display: flex; flex-direction: column;
    }
    .sidebar .nav-links { flex-grow: 1; }
    .sidebar a {
      padding: 12px 20px; color: #333; text-decoration: none; display: flex; align-items: center; gap: 10px; transition: background 0.2s;
    }
    .sidebar a:hover { background-color: #f0f0f0; border-left: 4px solid #007bff; }
    .sidebar-footer { padding: 12px 20px; margin-top: auto; }
    .container { margin-left: 220px; padding: 20px; padding-top: 80px; }
  </style>
</head>
<body>
<?php include '../../components/navbar.php'; ?>
<?php include '../../components/sidebar.php'; ?>

<div class="container mt-4">
  <h2>Settings</h2>
  <ul class="nav nav-tabs" id="settingsTab" role="tablist">
    <li class="nav-item"><button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">Profile Settings</button></li>
    <?php if ($role === 'admin'): ?>
    <li class="nav-item"><button class="nav-link" id="store-tab" data-bs-toggle="tab" data-bs-target="#store" type="button">Store Settings</button></li>
    <?php endif; ?>
  </ul>
  <div class="tab-content p-3 border border-top-0">

    <!-- Profile Settings -->
    <div class="tab-pane fade show active" id="profile">
      <form id="profileForm">
        <div class="mb-3"><label class="form-label">Full Name</label>
          <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($user['username']) ?>" data-original="<?= htmlspecialchars($user['username']) ?>">
        </div>
        <div class="mb-3"><label class="form-label">Role</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($user['role']) ?>" readonly>
        </div>
        <div class="mb-3"><label class="form-label">New Password (optional)</label>
          <input type="password" name="password" class="form-control">
        </div>
        <div class="mb-3"><label class="form-label">Confirm Password</label>
          <input type="password" name="confirm_password" class="form-control">
        </div>
        <button type="submit" class="btn btn-primary">Update Profile</button>
      </form>
    </div>

    <!-- Store Settings -->
     <?php if ($role === 'admin'): ?>
    <div class="tab-pane fade" id="store">
      <form id="storeForm">
        <div class="mb-3"><label class="form-label">Store Name</label>
          <input type="text" name="store_name" class="form-control" value="<?= htmlspecialchars($store['store_name']) ?>">
        </div>
        <div class="mb-3 position-relative"><label class="form-label">Store Code</label>
          <div class="input-group">
            <input type="text" id="storeCodeField" class="form-control" value="<?= htmlspecialchars($store['store_code']) ?>" readonly>
            <button type="button" class="btn btn-primary d-flex align-items-center" id="copyStoreCodeBtn" title="Copy Store Code">
              <span>copy </span><i class="bi bi-clipboard text-white" id="copyIcon"></i>
            </button>
          </div>
        </div>
        <div class="mb-3"><label class="form-label">Store Email</label>
          <input type="email" name="store_email" class="form-control" value="<?= htmlspecialchars($store['store_email']) ?>">
        </div>
        <div class="mb-3"><label class="form-label">Contact Number</label>
          <input type="text" name="contact_number" class="form-control" value="<?= htmlspecialchars($store['contact_number']) ?>">
        </div>
        <button type="submit" class="btn btn-primary">Update Store Info</button>
      </form>
    </div>
    <?php endif; ?>
  </div>
</div>

<!-- Password Modal -->
<div class="modal fade" id="confirmPasswordModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <div class="modal-header"><h5 class="modal-title">Confirm Password</h5></div>
    <div class="modal-body"><input type="password" id="confirmPasswordInput" class="form-control" placeholder="Enter your password"></div>
    <div class="modal-footer">
      <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
      <button type="button" id="confirmPasswordBtn" class="btn btn-primary">Confirm</button>
    </div>
  </div></div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function showToast(msg) {
  const html = `
    <div class="position-fixed top-0 end-0 p-3" style="z-index:9999">
      <div class="toast align-items-center text-bg-${msg.startsWith('Error')?'danger':'success'} border-0 show">
        <div class="d-flex">
          <div class="toast-body">${msg}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    </div>`;
  document.body.insertAdjacentHTML('beforeend', html);
  setTimeout(() => document.querySelector('.toast')?.remove(), 2000);
}

document.addEventListener('DOMContentLoaded', () => {
  const copyBtn = document.getElementById('copyStoreCodeBtn');
  const copyIcon = document.getElementById('copyIcon');
  const storeCodeField = document.getElementById('storeCodeField');
  copyBtn.addEventListener('click', async () => {
    try {
      await navigator.clipboard.writeText(storeCodeField.value);
      copyIcon.classList.replace('bi-clipboard', 'bi-check2');
      copyBtn.classList.replace('btn-primary', 'btn-success');
      setTimeout(() => {
        copyIcon.classList.replace('bi-check2', 'bi-clipboard');
        copyBtn.classList.replace('btn-success', 'btn-primary');
      }, 1500);
    } catch (err) { console.error('Copy failed', err); }
  });

  let pendingData = null;
  let pendingAction = '';

  function handleFormSubmit(e, action) {
    e.preventDefault();
    const formData = new FormData(e.target);
    pendingData = formData;
    pendingAction = action;
    const modal = new bootstrap.Modal(document.getElementById('confirmPasswordModal'));
    modal.show();
  }

  document.getElementById('profileForm').addEventListener('submit', e => handleFormSubmit(e, 'update_profile'));
  document.getElementById('storeForm').addEventListener('submit', e => handleFormSubmit(e, 'update_store'));

  document.getElementById('confirmPasswordBtn').addEventListener('click', () => {
    const pwd = document.getElementById('confirmPasswordInput').value;
    if (!pwd) return;
    pendingData.append('current_password', pwd);
    pendingData.append('action', pendingAction);

    fetch('', { method: 'POST', body: pendingData })
      .then(r => r.json())
      .then(res => { showToast(res.msg); })
      .catch(err => console.error(err));

    bootstrap.Modal.getInstance(document.getElementById('confirmPasswordModal')).hide();
    document.getElementById('confirmPasswordInput').value = '';
  });
});
</script>
</body>
</html>
