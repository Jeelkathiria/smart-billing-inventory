<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php'; // PHPMailer

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$login_error = "";

/* ---------------------- SEND OTP FUNCTION ---------------------- */
function sendOTP($email, $otp)
{
    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host = 'smtp.gmail.com';
        $mail->SMTPAuth = true;
        $mail->Username = 'testing992017@gmail.com';
        $mail->Password = 'ewtg cscr mycx cabc';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('testing992017@gmail.com', 'SmartBiz Verification');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "SmartBiz OTP Verification";
        $mail->Body = "
            <h3>Your SmartBiz OTP is <b>$otp</b></h3>
            <p>Valid for 3 minutes. Please do not share it.</p>
        ";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

/* ---------------------- LOGIN ---------------------- */
if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        $error = "Please fill all fields.";
    } else {
        $stmt = $conn->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result && $result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['store_id'] = $user['store_id'];
                $_SESSION['login_time'] = time();
                header("Location: /modules/dashboard.php");
                exit();
            } else $error = "Invalid password.";
        } else $error = "User not found.";
    }

    if (isset($error)) {
        header("Location: index.php?login_error=" . urlencode($error));
        exit();
    }
}

/* ---------------------- REGISTER AJAX ---------------------- */
if (isset($_POST['register_ajax'])) {
    $email = trim($_POST['email']);
    $role = $_POST['role'];
    $username = trim($_POST['username']);

    // duplicate checks
    $checkUser = $conn->prepare("SELECT username FROM users WHERE username = ?");
    $checkUser->bind_param("s", $username);
    $checkUser->execute();
    $checkUser->store_result();
    if ($checkUser->num_rows > 0) {
        echo json_encode(['status' => 'username_exists']);
        exit;
    }

    $check = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $check->bind_param("s", $email);
    $check->execute();
    $check->store_result();
    if ($check->num_rows > 0) {
        echo json_encode(['status' => 'email_exists']);
        exit;
    }

    if ($role === 'admin') {
        $store_email = trim($_POST['store_email']);
        $check2 = $conn->prepare("SELECT store_email FROM stores WHERE store_email = ?");
        $check2->bind_param("s", $store_email);
        $check2->execute();
        $check2->store_result();
        if ($check2->num_rows > 0) {
            echo json_encode(['status' => 'store_exists']);
            exit;
        }
    }

    $otp = rand(100000, 999999);
    $_SESSION['register_data'] = $_POST;
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_expiry'] = time() + 180;

    if (sendOTP($email, $otp)) {
        echo json_encode(['status' => 'otp_sent']);
    } else {
        echo json_encode(['status' => 'mail_failed']);
    }
    exit;
}

/* ---------------------- LIVE USERNAME CHECK ---------------------- */
if (isset($_POST['check_username'])) {
    $username = trim($_POST['username']);
    $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    echo json_encode(['exists' => $stmt->num_rows > 0]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <title>SmartBiz – Smart Billing & Inventory</title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <style>
  body {
    background: #f8f9fa;
    scroll-behavior: smooth;
  }

  .auth-section {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0, 0, 0, 0.6);
    z-index: 9998;
    justify-content: center;
    align-items: center;
  }

  .auth-box {
    background: #fff;
    padding: 30px;
    border-radius: 12px;
    max-width: 420px;
    width: 100%;
    box-shadow: 0 0 20px rgba(0, 0, 0, 0.15);
  }

  small.text-danger {
    display: block;
    margin-top: 3px;
    font-size: 13px;
  }

  #otpModal {
    z-index: 10000 !important;
  }

  #successModal {
    z-index: 10550 !important;
  }

  /* Toast positioning */
  .toast-container {
    position: fixed;
    top: 1rem;
    right: 1rem;
    z-index: 20000;
  }

    #forgotLink:hover {
    color: #82a8e1ff !important;
    text-decoration: none;
  }
  </style>
</head>

<body>
  <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
    <div class="container">
      <a class="navbar-brand fw-bold text-primary" href="#">SmartBiz</a>
      <button class="navbar-toggler" data-bs-toggle="collapse" data-bs-target="#nav"><span
          class="navbar-toggler-icon"></span></button>
      <div class="collapse navbar-collapse" id="nav">
        <ul class="navbar-nav ms-auto">
          <li class="nav-item"><button class="btn btn-primary ms-2" onclick="openAuth()">Login / Register</button></li>
        </ul>
      </div>
    </div>
  </nav>

  <section class="hero text-center text-white"
    style="background:linear-gradient(135deg,#6f42c1,#6610f2);padding:120px 0;">
    <div class="container">
      <h1>Smart Billing & Inventory Management</h1>
      <p class="lead mt-3">Automate your store operations efficiently — for free.</p>
      <button class="btn btn-light btn-lg mt-4" onclick="openAuth()">Get Started</button>
    </div>
  </section>

  <!-- Authentication Modal -->
  <section class="auth-section" id="auth">
    <div class="auth-box">
      <button class="btn-close float-end mb-2" onclick="closeAuth()"></button>
      <ul class="nav nav-tabs mb-4 justify-content-center">
        <li class="nav-item"><button class="nav-link active" data-bs-toggle="tab"
            data-bs-target="#loginTab">Login</button></li>
        <li class="nav-item"><button class="nav-link" data-bs-toggle="tab"
            data-bs-target="#registerTab">Register</button></li>
      </ul>

      <div class="tab-content">
        <!-- LOGIN -->
        <div class="tab-pane fade show active" id="loginTab">
          <?php if(isset($_GET['login_error'])): ?>
          <div class="alert alert-danger"><?= htmlspecialchars($_GET['login_error']) ?></div>
          <?php endif; ?>
          <form method="POST">
            <input type="text" name="username" class="form-control mb-3" placeholder="Username" required>
            <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
            <div class="text-center mt-2">
            <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;">
            <a href="#" id="forgotLink" class="text-primary" 
              style="font-size:14px; text-decoration:none; color:#0d6efd;">
              Forgot Password?
            </a>
          </div>
          </div>

            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
          </form>
        </div>

        <!-- REGISTER -->
        <div class="tab-pane fade" id="registerTab">
          <form method="POST" id="registerForm">
            <input type="hidden" name="register_ajax" value="1">

            <div class="mb-3">
              <label class="fw-semibold">Select Role</label>
              <select name="role" id="roleSelect" class="form-select" required>
                <option value="" disabled selected>Choose Role</option>
                <option value="admin">Admin (Create Store)</option>
                <option value="manager">Manager</option>
                <option value="cashier">Cashier</option>
              </select>
              <small id="roleError" class="text-danger"></small>
            </div>

            <div id="adminFields">
              <input type="text" name="store_name" class="form-control mb-2" placeholder="Store Name">
              <input type="email" name="store_email" class="form-control mb-2" placeholder="Store Email">
              <input type="text" name="contact_number" class="form-control mb-2" placeholder="Contact Number">
            </div>

            <div id="employeeFields" style="display:none;">
              <input type="text" name="store_code" class="form-control mb-2" placeholder="Store Code">
            </div>

            <input type="text" name="username" id="usernameInput" class="form-control mb-2" placeholder="Username"
              required>
            <small id="usernameMsg" class="text-danger"></small>

            <input type="email" name="email" id="emailInput" class="form-control mb-2" placeholder="Personal Email"
              required>
            <small id="emailMsg" class="text-danger"></small>

            <input type="password" name="password" class="form-control mb-3" placeholder="Password" required>
            <small id="passwordError" class="text-danger"></small>

            <button type="submit" id="registerBtn" class="btn btn-success w-100">Register</button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <!-- OTP Modal -->
  <div class="modal fade" id="otpModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-4 text-center">
        <h5 class="fw-bold mb-3">Verify Your Email</h5>
        <p>Enter the 6-digit OTP sent to your email.</p>
        <input type="text" id="otpInput" maxlength="6" class="form-control text-center mb-3" placeholder="Enter OTP">
        <div id="otpMessage" class="text-danger mb-2"></div>
        <button id="verifyOtpBtn" class="btn btn-primary w-100 mb-2">Verify OTP</button>
        <small id="timerText">Resend in 40s</small><br>
        <button id="resendBtn" class="btn btn-link p-0 mt-2" disabled>Resend OTP</button>
      </div>
    </div>
  </div>

  <!-- ✅ Success Modal -->
  <div class="modal fade" id="successModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-4 text-center">
        <h5 class="fw-bold mb-3 text-success">✅ Registration Successful!</h5>
        <div id="userDetails" class="mb-3 text-start"></div>
        <button class="btn btn-success w-100" id="okSuccessBtn" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>

  <!-- Forgot Password Modal -->
<div class="modal fade" id="forgotModal" tabindex="-1">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content p-4 text-center">
      <h5 class="fw-bold mb-3">Reset Your Password</h5>
      <input type="email" id="forgotEmail" class="form-control mb-2" placeholder="Enter your registered email">
      <div id="forgotMessage" class="text-danger mb-2"></div>
      <button id="sendResetOtpBtn" class="btn btn-primary w-100 mb-2">Send OTP</button>

      <div id="resetSection" style="display:none;">
        <input type="text" id="resetOtpInput" maxlength="6" class="form-control text-center mb-2" placeholder="Enter OTP">
        <input type="password" id="newPassword" class="form-control mb-2" placeholder="New Password">
        <button id="resetPasswordBtn" class="btn btn-success w-100">Reset Password</button>
      </div>
    </div>
  </div>
</div>


  <!-- Toast for Store Exists -->
  <div class="toast-container">
    <div id="storeToast" class="toast align-items-center text-bg-danger border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body">Store already exists!</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
  function openAuth() {
    $('#auth').css('display', 'flex');
  }

  function closeAuth() {
    $('#auth').hide();
  }

  $('#roleSelect').on('change', function() {
    if (this.value === 'admin') {
      $('#adminFields').show();
      $('#employeeFields').hide();
    } else {
      $('#adminFields').hide();
      $('#employeeFields').show();
    }
  });

  // username live check
  $('#usernameInput').on('input', function() {
    const username = $(this).val();
    if (username.length < 3) return $('#usernameMsg').text('');
    $.post('index.php', {
      check_username: 1,
      username
    }, res => {
      const data = JSON.parse(res);
      if (data.exists) {
        $('#usernameMsg').text('Username already exists ❌');
        $('#registerBtn').prop('disabled', true);
      } else {
        $('#usernameMsg').text('');
        $('#registerBtn').prop('disabled', false);
      }
    });
  });

  // Verify OTP
  $('#verifyOtpBtn').click(() => {
    const otp = $('#otpInput').val().trim();
    if (!otp) return $('#otpMessage').text('Please enter OTP');

    $('#otpMessage').text('Verifying...');

    $.post('verify_otp.php', {
        otp
      })
      .done(res => {
        let data;
        try {
          data = typeof res === 'object' ? res : JSON.parse(res);
        } catch {
          $('#otpMessage').text('❌ Server error');
          return;
        }

        if (data.status === 'success') {
          $('#otpMessage').removeClass('text-danger').addClass('text-success').text('✅ OTP Verified!');
          setTimeout(() => {
            const otpModal = bootstrap.Modal.getInstance(document.getElementById('otpModal'));
            otpModal.hide();
            $('#userDetails').html(`
              <p><b>Username:</b> ${data.username}</p>
              <p><b>Email:</b> ${data.email}</p>
              <p><b>Role:</b> ${data.role}</p>
              ${data.store_code ? `<p><b>Store Code:</b> ${data.store_code}</p>` : ''}
            `);
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
          }, 1000);
        } else if (data.status === 'invalid_otp') {
          $('#otpMessage').text('❌ OTP is not valid');
        } else if (data.status === 'session_expired') {
          $('#otpMessage').text('⚠️ Session expired, please register again.');
        } else {
          $('#otpMessage').text('❌ Something went wrong');
        }
      })
      .fail(() => $('#otpMessage').text('❌ Network error'));
  });

  // Register Submit
  $('#registerForm').on('submit', function(e) {
    e.preventDefault();

    $('small.text-danger').text('');

    $.ajax({
      url: 'index.php',
      method: 'POST',
      data: $(this).serialize(),
      success: function(response) {
        try {
          const res = JSON.parse(response);

          if (res.status === 'otp_sent') {
            const otpModal = new bootstrap.Modal(document.getElementById('otpModal'));
            otpModal.show();
            let timer = 40;
            const resendBtn = $('#resendBtn');
            const timerText = $('#timerText');
            resendBtn.prop('disabled', true);
            const countdown = setInterval(() => {
              timer--;
              timerText.text(`Resend in ${timer}s`);
              if (timer <= 0) {
                clearInterval(countdown);
                resendBtn.prop('disabled', false);
                timerText.text('');
              }
            }, 1000);
          } else if (res.status === 'username_exists') {
            $('#usernameMsg').text('Username already exists ❌');
          } else if (res.status === 'email_exists') {
            $('#emailMsg').text('Email already exists ❌');
          } else if (res.status === 'store_exists') {
            const toast = new bootstrap.Toast(document.getElementById('storeToast'));
            toast.show();
          } else {
            alert('Something went wrong: ' + res.status);
          }

        } catch {
          console.error('Invalid JSON:', response);
        }
      },
      error: function() {
        alert('Server error. Please try again.');
      }
    });
  });

  // Redirect to login on success OK
  $('#okSuccessBtn').click(function() {
    $('#auth').show();
    const authTabs = new bootstrap.Tab(document.querySelector('[data-bs-target="#loginTab"]'));
    authTabs.show();
     // Clear registration fields
      document.querySelectorAll('#registerForm input').forEach(input => input.value = '');
  });

  // Open forgot password modal
$('#forgotLink').click(function(e) {
  e.preventDefault();
  const modal = new bootstrap.Modal(document.getElementById('forgotModal'));
  modal.show();
  $('#forgotMessage').text('');
  $('#resetSection').hide();
  $('#forgotEmail').val('');
});

// Send reset OTP
$('#sendResetOtpBtn').click(function() {
  const email = $('#forgotEmail').val().trim();
  if (!email) return $('#forgotMessage').text('Enter your email');

  $('#forgotMessage').text('Sending OTP...');
  $.post('forgot_password.php', { action: 'send_otp', email }, res => {
    try {
      const data = JSON.parse(res);
      if (data.status === 'otp_sent') {
        $('#forgotMessage').text('OTP sent to your email ✔');
        $('#resetSection').slideDown();
      } else if (data.status === 'email_not_found') {
        $('#forgotMessage').text('Email not registered ❌');
      } else {
        $('#forgotMessage').text('Error sending OTP');
      }
    } catch {
      $('#forgotMessage').text('Server error');
    }
  });
});

// Reset password
$('#resetPasswordBtn').click(function() {
  const otp = $('#resetOtpInput').val().trim();
  const newPassword = $('#newPassword').val().trim();
  if (!otp || !newPassword) return $('#forgotMessage').text('Please fill all fields');

  $('#forgotMessage').text('Verifying...');
  $.post('forgot_password.php', { action: 'verify_otp', otp, newPassword }, res => {
    try {
      const data = JSON.parse(res);
      if (data.status === 'success') {
        $('#forgotMessage').removeClass('text-danger').addClass('text-success').text('Password reset successfully ✔');
        setTimeout(() => {
          const modal = bootstrap.Modal.getInstance(document.getElementById('forgotModal'));
          modal.hide();
        }, 1200);
      } else if (data.status === 'invalid_otp') {
        $('#forgotMessage').text('Invalid OTP ❌');
      } else if (data.status === 'expired') {
        $('#forgotMessage').text('OTP expired ⏰');
      } else {
        $('#forgotMessage').text('Something went wrong');
      }
    } catch {
      $('#forgotMessage').text('Server error');
    }
  });
});

  </script>
</body>

</html>
