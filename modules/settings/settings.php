<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../auth/auth_check.php';

$store_id = $_SESSION['store_id'];

$query = $conn->prepare("SELECT store_name, store_code, created_at FROM stores WHERE store_id = ?");
$query->bind_param("i", $store_id);
$query->execute();
$result = $query->get_result();
$store = $result->fetch_assoc();
?>

<?php include __DIR__ . '/../../components/navbar.php'; ?>
<?php include __DIR__ . '/../../components/sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Settings</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.5/font/bootstrap-icons.css" rel="stylesheet">

  <style>
    body {
      background-color: #f4f6f9;
      margin: 0;
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

    /* Enhanced Settings Card */
    .settings-card {
      max-width: 650px;
      margin: 0 auto;
      background: #fff;
      border-radius: 15px;
      overflow: hidden;
      box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08);
    }

    .settings-header {
      background: linear-gradient(135deg, #0d6efd, #6610f2);
      color: white;
      padding: 15px 20px;
      font-size: 1.2rem;
      font-weight: 600;
      display: flex;
      align-items: center;
      gap: 10px;
    }

    .settings-body {
      padding: 20px 25px;
    }

    .info-row {
      display: flex;
      align-items: center;
      justify-content: space-between;
      padding: 12px 0;
      border-bottom: 1px solid #eee;
      transition: background 0.2s;
    }

    .info-row:last-child {
      border-bottom: none;
    }

    .info-row:hover {
      background-color: #f9fafb;
      border-radius: 6px;
    }

    .info-label {
      display: flex;
      align-items: center;
      gap: 10px;
      font-weight: 500;
      color: #495057;
    }

    .info-label i {
      color: #0d6efd;
      font-size: 1.2rem;
    }

    .info-value {
      font-weight: 600;
      color: #212529;
    }

    .copy-btn {
      margin-left: 10px;
    }

    /* Toast */
    .toast-container {
      position: fixed;
      top: 1rem;
      right: 1rem;
      z-index: 1080;
    }

    /* Responsive */
    @media (max-width: 768px) {
      .main-content {
        margin-left: 0;
        padding: 70px 15px;
      }

      .settings-card {
        width: 100%;
      }
    }
  </style>
</head>

<body>
  <div class="main-content">
    <div class="settings-card">
      <div class="settings-header">
        <i class="bi bi-gear-fill"></i> Store Settings
      </div>
      <div class="settings-body">

        <div class="info-row">
          <div class="info-label"><i class="bi bi-shop"></i> Store Name</div>
          <div class="info-value"><?= htmlspecialchars($store['store_name']) ?></div>
        </div>

        <div class="info-row">
          <div class="info-label"><i class="bi bi-upc-scan"></i> Store Code</div>
          <div class="info-value text-success">
            <?= htmlspecialchars($store['store_code']) ?>
            <button class="btn btn-sm btn-outline-primary copy-btn" onclick="copyCode()">
              <i class="bi bi-clipboard"></i> Copy
            </button>
          </div>
        </div>

        <div class="info-row">
          <div class="info-label"><i class="bi bi-calendar-event"></i> Created On</div>
          <div class="info-value"><?= date('d M Y', strtotime($store['created_at'])) ?></div>
        </div>

      </div>
    </div>
  </div>

  <!-- Toast -->
  <div class="toast-container">
    <div id="copyToast" class="toast align-items-center text-white bg-success border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body">
          Store code copied to clipboard!
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    function copyCode() {
      const storeCode = <?= json_encode($store['store_code']) ?>;
      navigator.clipboard.writeText(storeCode).then(() => {
        const toastEl = document.getElementById('copyToast');
        const toast = new bootstrap.Toast(toastEl, { delay: 2000, autohide: true });
        toast.show();
      }).catch(err => {
        console.error("Failed to copy: ", err);
      });
    }
  </script>
</body>
</html>
