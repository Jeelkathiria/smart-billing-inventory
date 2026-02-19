<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/mail.php';
require_once __DIR__ . '/../../auth/auth_check.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  echo json_encode(['status' => 'error', 'message' => 'Invalid method']);
  exit;
}

$action = $_POST['action'] ?? '';
$user_id = $_SESSION['user_id'] ?? null;
$store_id = $_SESSION['store_id'] ?? null;
$role = $_SESSION['role'] ?? null;

if (!$user_id || !$store_id) {
  echo json_encode(['status' => 'error', 'message' => 'Not authenticated']);
  exit;
}
if ($role !== 'admin') {
  echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
  exit;
}

function sendOTPMail($email, $otp) {
  $subject = 'Account Deletion OTP';
  $body = "
    <h2>Account Deletion Verification</h2>
    <h3>Your account deletion OTP is: <b>$otp</b></h3>
    <p>Valid for 3 minutes.</p>
    <p>If you did not request account deletion, please ignore this email.</p>
  ";
  
  $result = sendEmail($email, 'Administrator', $subject, $body);
  return $result['success'];
}

// --- ACTIONS ---
if ($action === 'send_otp') {
  $stmt = $conn->prepare("SELECT store_email FROM stores WHERE store_id = ?");
  $stmt->bind_param("i", $store_id);
  $stmt->execute();
  $storeEmail = $stmt->get_result()->fetch_assoc()['store_email'] ?? null;
  $stmt->close();

  if (!$storeEmail) {
    echo json_encode(['status' => 'error', 'message' => 'Store email not found']);
    exit;
  }
  $otp = rand(100000, 999999);
  $_SESSION['delete_account_otp'] = $otp;
  $_SESSION['delete_account_otp_expiry'] = time() + 180; // 3 minutes
  if (sendOTPMail($storeEmail, $otp)) {
    echo json_encode(['status' => 'ok']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Could not send OTP']);
  }
  exit;
}

if ($action === 'verify_otp') {
  $otp = trim($_POST['otp'] ?? '');
  if (empty($otp) || !isset($_SESSION['delete_account_otp'])) {
    echo json_encode(['status' => 'error', 'message' => 'No OTP pending']);
    exit;
  }
  if (time() > ($_SESSION['delete_account_otp_expiry'] ?? 0)) {
    echo json_encode(['status' => 'error', 'message' => 'OTP expired']);
    exit;
  }
  if ((string)($otp) === (string)$_SESSION['delete_account_otp']) {
    unset($_SESSION['delete_account_otp']);
    unset($_SESSION['delete_account_otp_expiry']);
    $_SESSION['delete_account_otp_verified'] = true;
    echo json_encode(['status' => 'ok']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid OTP']);
  }
  exit;
}

if ($action === 'check_password') {
  $password = $_POST['password'] ?? '';
  if (!$password) {
    echo json_encode(['status' => 'error', 'message' => 'Password required']);
    exit;
  }
  $stmt = $conn->prepare("SELECT password, username FROM users WHERE user_id = ? AND store_id = ? LIMIT 1");
  $stmt->bind_param("ii", $user_id, $store_id);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if ($user && password_verify($password, $user['password'])) {
    echo json_encode(['status' => 'ok']);
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
  }
  exit;
}

if ($action === 'delete_account') {
  // required params: password, reason, final_confirm
  $password = $_POST['password'] ?? '';
  $reason = trim($_POST['reason'] ?? '');
  $other_reason = trim($_POST['other_reason'] ?? '');

  // Server-side validation: reason is mandatory. If Other is selected, require the freeform text.
  $allowedReasons = ['Business closed', 'Switching to another software', 'Testing purpose', 'Too complex to use', 'Other'];
  if (empty($reason)) {
    echo json_encode(['status' => 'error', 'message' => 'Please select a reason for deletion']);
    exit;
  }
  if (!in_array($reason, $allowedReasons)) {
    // If it's not an allowed value, reject the input to avoid unexpected values.
    echo json_encode(['status' => 'error', 'message' => 'Invalid reason selected']);
    exit;
  }
  if ($reason === 'Other' && empty($other_reason)) {
    echo json_encode(['status' => 'error', 'message' => 'Please provide details for "Other" reason']);
    exit;
  }
  $final_confirm = trim($_POST['final_confirm'] ?? '');

  if ($final_confirm !== 'DELETE MY ACCOUNT') {
    echo json_encode(['status' => 'error', 'message' => 'Final confirmation text not entered correctly']);
    exit;
  }

  // verify password
  $stmt = $conn->prepare("SELECT password, username FROM users WHERE user_id = ? AND store_id = ? LIMIT 1");
  $stmt->bind_param("ii", $user_id, $store_id);
  $stmt->execute();
  $user = $stmt->get_result()->fetch_assoc();
  $stmt->close();
  if (!$user || !password_verify($password, $user['password'])) {
    echo json_encode(['status' => 'error', 'message' => 'Invalid password']);
    exit;
  }

  // verify OTP was completed
  if (!isset($_SESSION['delete_account_otp_verified']) || $_SESSION['delete_account_otp_verified'] !== true) {
    echo json_encode(['status' => 'error', 'message' => 'OTP not verified']);
    exit;
  }

  // record deletion reason
  if ($reason === 'Other') $reason = substr($other_reason, 0, 255);

  // get store + admin info
  $stmt = $conn->prepare("SELECT s.store_name, s.store_email, s.contact_number, u.username FROM stores s JOIN users u ON u.user_id = ? WHERE s.store_id = ? LIMIT 1");
  $stmt->bind_param("ii", $user_id, $store_id);
  $stmt->execute();
  $row = $stmt->get_result()->fetch_assoc();
  $stmt->close();

  $admin_name = $row['username'] ?? '';
  $store_name = $row['store_name'] ?? '';
  $store_email = $row['store_email'] ?? '';
  $contact_number = $row['contact_number'] ?? '';

  try {
    $conn->begin_transaction();

    // Audit insertion: check table & column existence before inserting to avoid runtime errors
    $canAudit = false;
    $deletedByExists = false;
    $tblRes = $conn->query("SHOW TABLES LIKE 'deleted_stores'");
    if ($tblRes && $tblRes->num_rows) {
      $canAudit = true;
      $colRes = $conn->query("SHOW COLUMNS FROM deleted_stores LIKE 'deleted_by'");
      if ($colRes && $colRes->num_rows) $deletedByExists = true;
    }
    if ($canAudit) {
      if ($deletedByExists) {
        $stmt = $conn->prepare("INSERT INTO deleted_stores (deleted_by, admin_name, store_name, store_email, contact_number, reason) VALUES (?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("isssss", $user_id, $admin_name, $store_name, $store_email, $contact_number, $reason);
      } else {
        $stmt = $conn->prepare("INSERT INTO deleted_stores (admin_name, store_name, store_email, contact_number, reason) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $admin_name, $store_name, $store_email, $contact_number, $reason);
      }
      $stmt->execute();
      $stmt->close();
    } else {
      $warnings[] = 'deleted_stores table not found; deletion was not audited on your server.';
    }

    // Delete sale items then sales
    $stmt = $conn->prepare("DELETE si FROM sale_items si JOIN sales s ON si.sale_id = s.sale_id WHERE s.store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $stmt->close();

    $stmt = $conn->prepare("DELETE FROM sales WHERE store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $stmt->close();

    // Other store-scoped tables
    $tables = ['customers', 'products', 'categories'];
    foreach ($tables as $t) {
      $stmt = $conn->prepare("DELETE FROM $t WHERE store_id = ?");
      $stmt->bind_param("i", $store_id);
      $stmt->execute();
      $stmt->close();
    }

    // Delete users belonging to this store
    $stmt = $conn->prepare("DELETE FROM users WHERE store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $stmt->close();

    // Finally delete the store itself
    $stmt = $conn->prepare("DELETE FROM stores WHERE store_id = ?");
    $stmt->bind_param("i", $store_id);
    $stmt->execute();
    $stmt->close();

    $conn->commit();

    // Clear session and indicate success
    session_unset();
    session_destroy();
    $resp = ['status' => 'ok'];
    if (!empty($warnings)) $resp['warnings'] = $warnings;
    echo json_encode($resp);
    exit;
  } catch (Exception $e) {
    $conn->rollback();
    echo json_encode(['status' => 'error', 'message' => 'Deletion failed: ' . $e->getMessage()]);
    exit;
  }
}

echo json_encode(['status' => 'error', 'message' => 'Unknown action']);
exit;

?>
