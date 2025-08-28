<?php
// modules/settings/settings.php

require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php'; // session already started

// ---- Guard ----
$store_id = $_SESSION['store_id'] ?? null;
$user_id  = $_SESSION['user_id'] ?? null;

if (!$store_id) {
  header('Location: /auth/index.php');
  exit;
}

// ---- Fetch store safely ----
$store = [];
if ($stmt = $conn->prepare("SELECT * FROM stores WHERE store_id = ?")) {
  $stmt->bind_param("i", $store_id);
  $stmt->execute();
  $res = $stmt->get_result();
  $store = $res ? ($res->fetch_assoc() ?: []) : [];
  $stmt->close();
}

// ---- Fetch user safely ----
$user = [];
if ($user_id) {
  if ($stmt = $conn->prepare("SELECT * FROM users WHERE user_id  = ?")) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result();
    $user = $res ? ($res->fetch_assoc() ?: []) : [];
    $stmt->close();
  }
}

// ---- Safe fallbacks ----
$storeName = $store['store_name'] ?? '—';
$storeCode = $store['store_code'] ?? '—';
$createdOn = isset($store['created_at']) ? date('d M Y', strtotime($store['created_at'])) : '—';
$username  = $user['username'] ?? 'User';
$email     = $user['email'] ?? 'user@example.com';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet" />
  <style>
    body {
      background-color: #f4f6f9;
    }

    .main-content {
      margin-left: 220px;
      padding: 80px 20px;
    }
  
.navbar {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    height: 60px;
    z-index: 1030;
    background-color: #ffffff;
    border-bottom: 1px solid #dee2e6;
    display: flex;
    align-items: center;
    padding: 0 20px;
  }

  .sidebar .nav-links {
    flex-grow: 1;
  }

  .sidebar {
    width: 220px;
    position: fixed;
    top: 0;
    bottom: 0;
    background: #ffffff;
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
    background-color: #f0f0f0;
    border-left: 4px solid #007bff;
  }

  .sidebar-footer {
    padding: 12px 20px;
    margin-top: auto;
  }

    .settings-card {
      max-width: 800px;
      margin: 0 auto 2rem auto;
      background: #fff;
      border-radius: 12px;
      overflow: hidden;
      box-shadow: 0 4px 16px rgba(0, 0, 0, .08);
    }

    .settings-header {
      background: linear-gradient(135deg, #0d6efd, #6610f2);
      color: #fff;
      padding: 16px 20px;
      font-size: 1.25rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .settings-body {
      padding: 20px 25px;
    }

    .avatar {
      width: 100px;
      height: 100px;
      border-radius: 50%;
      object-fit: cover;
    }

    .toast-container {
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 1080;
    }

    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        padding: 70px 15px;
      }
    }
  </style>
</head>

<body>

  <!-- Navbar -->
  <?php include __DIR__ . '/../../components/navbar.php'; ?>

  <!-- Sidebar -->
  <?php include __DIR__ . '/../../components/sidebar.php'; ?>

  <!-- Main -->
  <div class="main-content">
    <h2 class="mb-4">⚙️ Settings</h2>

    <!-- Profile Settings -->
    <div class="card settings-card">
      <div class="settings-header bg-primary">
        <i class="bi bi-person-circle"></i> Profile Settings
      </div>
      <div class="settings-body">
        <div class="d-flex align-items-center mb-3">
          <img src="https://via.placeholder.com/100" alt="Profile" class="avatar me-3">
          <div>
            <h5><?= htmlspecialchars($username) ?></h5>
            <small><?= htmlspecialchars($email) ?></small>
          </div>
        </div>
        <form>
          <div class="mb-3">
            <label class="form-label">Full Name</label>
            <input type="text" class="form-control" value="<?= htmlspecialchars($username) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Email</label>
            <input type="email" class="form-control" value="<?= htmlspecialchars($email) ?>">
          </div>
          <div class="mb-3">
            <label class="form-label">Password</label>
            <input type="password" class="form-control" placeholder="••••••••">
          </div>
          <button class="btn btn-primary">Update Profile</button>
        </form>
      </div>
    </div>

    <!-- Store Settings -->
    <div class="card settings-card">
      <div class="settings-header bg-success">
        <i class="bi bi-shop"></i> Store Settings
      </div>
      <div class="settings-body">
        <div class="mb-3">
          <label class="form-label">Store Name</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($storeName) ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Store Code</label>
          <div class="input-group">
            <input type="text" class="form-control" value="<?= htmlspecialchars($storeCode) ?>" readonly>
            <?php if ($storeCode !== '—'): ?>
            <button class="btn btn-outline-primary" type="button" onclick="copyCode()">
              <i class="bi bi-clipboard"></i> Copy
            </button>
            <?php endif; ?>
          </div>
        </div>
        <div class="mb-3">
          <label class="form-label">Created On</label>
          <input type="text" class="form-control" value="<?= $createdOn ?>" readonly>
        </div>
        <div class="mb-3">
          <label class="form-label">Store Address</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($store['address'] ?? '') ?>">
        </div>
        <div class="mb-3">
          <label class="form-label">Contact</label>
          <input type="text" class="form-control" value="<?= htmlspecialchars($store['contact'] ?? '') ?>">
        </div>
        <button class="btn btn-success">Update Store</button>
      </div>
    </div>

    <!-- Preferences -->
    <div class="card settings-card">
      <div class="settings-header bg-warning text-dark">
        <i class="bi bi-sliders"></i> Preferences
      </div>
      <div class="settings-body">
        <div class="mb-3">
          <label class="form-label">Theme</label>
          <select class="form-select">
            <option selected>Light</option>
            <option>Dark</option>
            <option>System Default</option>
          </select>
        </div>
        <div class="mb-3">
          <label class="form-label">Language</label>
          <select class="form-select">
            <option selected>English</option>
            <option>Hindi</option>
            <option>Gujarati</option>
          </select>
        </div>
        <button class="btn btn-warning">Save Preferences</button>
      </div>
    </div>

    <!-- Security -->
    <div class="card settings-card">
      <div class="settings-header bg-danger">
        <i class="bi bi-shield-lock"></i> Security
      </div>
      <div class="settings-body">
        <div class="mb-3">
          <label class="form-label">Two-Factor Authentication</label>
          <select class="form-select">
            <option selected>Disabled</option>
            <option>Enabled</option>
          </select>
        </div>
        <button class="btn btn-danger">Update Security</button>
      </div>
    </div>

  </div>

  <!-- Toast -->
  <div class="toast-container">
    <div id="copyToast" class="toast align-items-center text-white bg-success border-0" role="alert"
      aria-live="assertive" aria-atomic="true">
      <div class="d-flex">
        <div class="toast-body">
          Store code copied to clipboard!
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"
          aria-label="Close"></button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function copyCode() {
      const code = <?= json_encode($storeCode) ?>;
      if (!code) return;
      navigator.clipboard.writeText(code).then(() => {
        const toastEl = document.getElementById('copyToast');
        const toast = new bootstrap.Toast(toastEl, {
          delay: 2000,
          autohide: true
        });
        toast.show();
      }).catch(err => console.error('Clipboard error:', err));
    }
  </script>
</body>

</html>
