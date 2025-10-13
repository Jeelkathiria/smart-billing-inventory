<?php
// ================= INIT =================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

$user_id  = $_SESSION['user_id'] ?? null;
$store_id = $_SESSION['store_id'] ?? null;

if (!$user_id || !$store_id) {
    header("Location: /auth/index.php");
    exit;
}

// ================= FETCH USER =================
$stmt = $conn->prepare("SELECT username, role, password FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$role = $user['role'] ?? '';

// ================= FETCH STORE =================
$stmt = $conn->prepare("SELECT store_name, store_email, contact_number, store_code, gstin, billing_fields 
                        FROM stores WHERE store_id = ?");
$stmt->bind_param("i", $store_id);
$stmt->execute();
$store = $stmt->get_result()->fetch_assoc();

$billing_fields = json_decode($store['billing_fields'] ?? '[]', true);
if (!is_array($billing_fields)) $billing_fields = [];

// ================= AJAX HANDLER =================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    $current_password = trim($_POST['current_password'] ?? '');

    if (!$user || !password_verify($current_password, $user['password'])) {
        echo json_encode(['success' => false, 'msg' => 'Error: Incorrect password.']);
        exit;
    }

    // ---------- Update Profile ----------
    if ($action === 'update_profile') {
        $newName = trim($_POST['username'] ?? '');
        $newPass = trim($_POST['password'] ?? '');
        $changes = [];

        if ($newName && $newName !== $user['username']) {
            $stmt = $conn->prepare("UPDATE users SET username=? WHERE user_id=?");
            $stmt->bind_param("si", $newName, $user_id);
            $stmt->execute();
            $changes[] = 'Name updated';
        }

        if (!empty($newPass)) {
            $hashed = password_hash($newPass, PASSWORD_BCRYPT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE user_id=?");
            $stmt->bind_param("si", $hashed, $user_id);
            $stmt->execute();
            $changes[] = 'Password changed';
        }

        echo json_encode($changes
            ? ['success' => true, 'msg' => implode(' & ', $changes)]
            : ['success' => false, 'msg' => 'No changes detected.']
        );
        exit;
    }

    // ---------- Update Store Info ----------
    if ($action === 'update_store_info' && $role === 'admin') {
        $newData = [
            'store_name' => trim($_POST['store_name'] ?? ''),
            'store_email' => trim($_POST['store_email'] ?? ''),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            'gstin' => trim($_POST['gstin'] ?? '')
        ];

        $changes = [];
        foreach ($newData as $key => $value) {
            if ($value !== ($store[$key] ?? '')) {
                $stmt = $conn->prepare("UPDATE stores SET {$key}=? WHERE store_id=?");
                $stmt->bind_param("si", $value, $store_id);
                $stmt->execute();
                $changes[] = ucfirst(str_replace('_', ' ', $key)) . ' updated';
            }
        }

        echo json_encode($changes
            ? ['success' => true, 'msg' => implode(' & ', $changes)]
            : ['success' => false, 'msg' => 'No changes detected.']
        );
        exit;
    }

    // ---------- Update Billing Fields ----------
    if ($action === 'update_billing_fields' && $role === 'admin') {
        $fields = $_POST['fields'] ?? [];
        $encoded = json_encode($fields);

        if ($fields != $billing_fields) {
            $stmt = $conn->prepare("UPDATE stores SET billing_fields=? WHERE store_id=?");
            $stmt->bind_param("si", $encoded, $store_id);
            $stmt->execute();
            echo json_encode(['success' => true, 'msg' => 'Billing fields updated']);
        } else {
            echo json_encode(['success' => false, 'msg' => 'No changes detected']);
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
  <link rel="stylesheet" href="/assets/css/common.css">
  <style>
  /* Profile Card */
  .profile-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 30px;
    max-width: 30vw;
  }

  .profile-header {
    display: flex;
    align-items: center;
    padding: 20px;
    background: linear-gradient(135deg, #6a11cb, #2575fc);
    color: #fff;
    position: relative;
  }

  .profile-img {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    border: 3px solid #fff;
    margin-right: 15px;
    object-fit: cover;
  }

  .profile-info h4 {
    margin: 0;
    font-size: 1.2rem;
  }

  .user-role {
    position: absolute;
    top: 15px;
    right: 20px;
    background: rgba(255, 255, 255, 0.2);
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 0.8rem;
    font-weight: 600;
  }

  /* Sections */
  .profile-section {
    padding: 15px 20px;
    border-top: 1px solid #f0f0f0;
  }

  .profile-section h5 {
    margin-bottom: 12px;
    font-size: 1rem;
    color: #333;
  }

  /* Form Inputs */
  .form-input {
    width: 100%;
    padding: 10px 12px;
    margin-bottom: 10px;
    border: 1px solid #ccc;
    border-radius: 8px;
    font-size: 0.95rem;
    transition: all 0.2s ease;
  }

  .form-input:focus {
    border-color: #2575fc;
    outline: none;
    box-shadow: 0 0 5px rgba(37, 117, 252, 0.4);
  }

  /* Buttons */
  .btn-submit {
    width: 100%;
    padding: 10px 0;
    border: none;
    border-radius: 8px;
    background: #2575fc;
    color: #fff;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s ease;
  }

  .btn-submit:hover {
    background: #1a5ed8;
  }

  /* Billing fields */
  #billing-fields label {
    display: block;
    margin-bottom: 8px;
    cursor: pointer;
  }

  #billing-fields input[type="checkbox"] {
    margin-right: 6px;
  }

  /* Store Card Wider */
  .store-card {
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.1);
    overflow: hidden;
    margin-bottom: 30px;
    max-width: 30vw;
  }
  </style>
</head>

<body>
  <?php include '../../components/navbar.php'; ?>
  <?php include '../../components/sidebar.php'; ?>

  <main class="content mt-4">
    <div class="settings-wrapper">

      <!-- Tabs -->
      <ul class="nav nav-tabs mb-3" id="settingsTab">
        <li class="nav-item">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#profile-tab">Profile</button>
        </li>
        <?php if($role==='admin'): ?>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#store-tab">Store</button>
        </li>
        <?php endif; ?>
      </ul>

      <div class="tab-content">
        <!-- Profile Tab -->
        <div class="tab-pane fade show active" id="profile-tab">
          <div class="profile-card">
            <div class="profile-header">
              <img src="https://via.placeholder.com/80" alt="Profile Picture" class="profile-img">
              <div class="profile-info">
                <h4><?= htmlspecialchars($user['username']) ?></h4>
              </div>
              <span class="user-role"><?= htmlspecialchars($role) ?></span>
            </div>

            <div class="profile-section">
              <h5>👤 Profile Information</h5>
              <form class="profileForm">
                <input type="text" name="username" class="form-input" value="<?= htmlspecialchars($user['username']) ?>"
                  placeholder="Full Name">
                <button type="submit" class="btn-submit">Update Profile</button>
              </form>
            </div>

            <div class="profile-section">
              <h5>🔒 Security</h5>
              <form class="profileForm">
                <input type="password" name="password" class="form-input" placeholder="New Password">
                <input type="password" name="confirm_password" class="form-input" placeholder="Confirm Password">
                <button type="submit" class="btn-submit">Change Password</button>
              </form>
            </div>
          </div>
        </div>

        <!-- Store Tab -->
        <?php if($role==='admin'): ?>
        <div class="tab-pane fade" id="store-tab">
          <div class="store-card">
            <div class="profile-section">
              <h5>🏪 Store Information</h5>
              <form class="storeForm" data-action="update_store_info">
                <input type="text" name="store_name" class="form-input"
                  value="<?= htmlspecialchars($store['store_name'] ?? '') ?>" placeholder="Store Name">
                <input type="text" class="form-input" value="<?= htmlspecialchars($store['store_code'] ?? '') ?>"
                  placeholder="Store Code" readonly>
                <input type="text" name="gstin" class="form-input"
                  value="<?= htmlspecialchars($store['gstin'] ?? '') ?>" placeholder="GSTIN">
                <input type="email" name="store_email" class="form-input"
                  value="<?= htmlspecialchars($store['store_email'] ?? '') ?>" placeholder="Store Email">
                <input type="text" name="contact_number" class="form-input"
                  value="<?= htmlspecialchars($store['contact_number'] ?? '') ?>" placeholder="Contact Number">
                <button type="submit" class="btn-submit">Update Store Info</button>
              </form>
            </div>

            <div class="profile-section">
              <h5>🧾 Billing Fields</h5>
              <form class="storeForm" data-action="update_billing_fields">
                <div id="billing-fields" class="mb-3">
                  <?php
                $fields = [
                  'customer_name' => 'Customer Name',
                  'customer_mobile' => 'Customer Mobile',
                  'customer_email' => 'Customer Email',
                  'customer_address' => 'Address'
                ];
                foreach($fields as $key=>$label): ?>
                  <label><input type="checkbox" name="fields[<?= $key ?>]"
                      <?= !empty($billing_fields[$key])?'checked':'' ?>> <?= $label ?></label>
                  <?php endforeach; ?>
                </div>
                <button type="submit" class="btn-submit">Update Billing Fields</button>
              </form>
            </div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </main>

  <!-- Password Modal -->
  <div class="modal fade" id="confirmPasswordModal" tabindex="-1">
    <div class="modal-dialog">
      <div class="modal-content border-0">
        <div class="modal-header bg-primary text-white">
          <h5 class="modal-title">Confirm Password</h5>
        </div>
        <div class="modal-body">
          <input type="password" id="confirmPasswordInput" class="form-control" placeholder="Enter your password">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
          <button type="button" id="confirmPasswordBtn" class="btn btn-primary">Confirm</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
  function showToast(msg, type = 'info') {
    const html = `
    <div class="position-fixed top-0 end-0 p-3" style="z-index:1050">
      <div class="toast align-items-center text-bg-${type} border-0 show">
        <div class="d-flex">
          <div class="toast-body">${msg}</div>
          <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
        </div>
      </div>
    </div>`;
    document.body.insertAdjacentHTML('beforeend', html);
    setTimeout(() => document.querySelector('.toast')?.remove(), 2500);
  }

  document.addEventListener('DOMContentLoaded', () => {
    let pendingData = null,
      pendingAction = '';
    const passwordModal = new bootstrap.Modal(document.getElementById('confirmPasswordModal'));

    function handleFormSubmit(e, action) {
      e.preventDefault();
      pendingData = new FormData(e.target);
      pendingAction = action;
      passwordModal.show();
    }

    document.querySelectorAll('.profileForm').forEach(f => f.addEventListener('submit', e => handleFormSubmit(e,
      'update_profile')));
    document.querySelectorAll('.storeForm').forEach(f => f.addEventListener('submit', e => handleFormSubmit(e, f
      .dataset.action)));

    document.getElementById('confirmPasswordBtn').addEventListener('click', () => {
      const pwd = document.getElementById('confirmPasswordInput').value.trim();
      if (!pwd) return showToast('Please enter your password', 'warning');
      pendingData.append('current_password', pwd);
      pendingData.append('action', pendingAction);

      fetch('', {
          method: 'POST',
          body: pendingData
        })
        .then(r => r.json())
        .then(res => {
          showToast(res.msg, res.success ? 'success' : 'danger');
        })
        .catch(() => showToast('Error: Network or server issue.', 'danger'));

      passwordModal.hide();
      document.getElementById('confirmPasswordInput').value = '';
    });
  });
  </script>
</body>

</html>