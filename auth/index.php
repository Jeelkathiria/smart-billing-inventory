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
        $mail->Password = 'oryx mnhr zjnw fjwj';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port = 587;

        $mail->setFrom('testing992017@gmail.com', 'BillMitra Verification');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "BillMitra OTP Verification";
        $mail->Body = "
            <h3>Your BillMitra OTP is <b>$otp</b></h3>
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
    $role = $_POST['role'];
    $username = trim($_POST['username']);

    // Determine email for OTP based on role
    if ($role === 'admin') {
        $email = trim($_POST['store_email']); // Admin OTP goes to store_email
    } else {
        $email = trim($_POST['email']);       // Manager/Cashier OTP goes to their email
    }

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

    if (!empty($_POST['personal_contact_number'])) {
        $contact = trim($_POST['personal_contact_number']);
        $check2 = $conn->prepare("SELECT personal_contact_number FROM users WHERE personal_contact_number = ?");
        $check2->bind_param("s", $contact);
        $check2->execute();
        $check2->store_result();

        if ($check2->num_rows > 0) {
            echo json_encode(['status' => 'contact_exists']);
            exit;
        }
    }

    // Store Email
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

    // Personal Contact Number
    if ($role === 'admin') {
        $personal_contact_number = trim($_POST['personal_contact_number']);
        $check3 = $conn->prepare("SELECT personal_contact_number FROM users WHERE personal_contact_number = ?");
        $check3->bind_param("s", $personal_contact_number);
        $check3->execute();
        $check3->store_result();
        if ($check3->num_rows > 0) {
            echo json_encode(['status' => 'contact_exists']);
            exit;
        }
    }

    // Generate OTP and store in session
    $otp = rand(100000, 999999);
    $_SESSION['register_data'] = $_POST;
    $_SESSION['otp'] = $otp;
    $_SESSION['otp_expiry'] = time() + 180;

    // Send OTP to correct email
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

/* ---------------------- EMAIL CHECK (AJAX) ---------------------- */
if (isset($_POST['check_email'])) {
    $email = trim($_POST['email']);
    $response = ['exists' => false];

    // check users table
    $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $response['exists'] = true;
        echo json_encode($response);
        exit;
    }

    // check stores table
    $stmt2 = $conn->prepare("SELECT store_email FROM stores WHERE store_email = ?");
    $stmt2->bind_param("s", $email);
    $stmt2->execute();
    $stmt2->store_result();
    if ($stmt2->num_rows > 0) {
        $response['exists'] = true;
    }

    echo json_encode($response);
    exit;
}

/* ---------------------- CONTACT CHECK (AJAX) ---------------------- */
if (isset($_POST['check_contact'])) {
    $contact = trim($_POST['contact']);
    $response = ['exists' => false];

    // check users.personal_contact_number
    $stmt = $conn->prepare("SELECT personal_contact_number FROM users WHERE personal_contact_number = ?");
    $stmt->bind_param("s", $contact);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $response['exists'] = true;
        echo json_encode($response);
        exit;
    }

    // check stores.contact_number (if your stores table uses contact_number)
    $stmt2 = $conn->prepare("SELECT contact_number FROM stores WHERE contact_number = ?");
    $stmt2->bind_param("s", $contact);
    $stmt2->execute();
    $stmt2->store_result();
    if ($stmt2->num_rows > 0) {
        $response['exists'] = true;
    }

    echo json_encode($response);
    exit;
}

/* ---------------------- USER EMAIL CHECK (AJAX) - users table only ---------------------- */
if (isset($_POST['check_user_email'])) {
    $email = trim($_POST['email']);
    $response = ['exists' => false];

    $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $response['exists'] = true;
    }

    echo json_encode($response);
    exit;
}


?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <title>BillMitra – Billing & Inventory Management</title>
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
  <style>
  /* ===== ROOT THEME ===== */
  :root {
    --primary: #0d6efd;
    --bg-soft: #f7f9fc;
    --card-bg: #ffffff;
    --text-dark: #212529;
    --text-muted: #6c757d;
    --radius: 14px;
    --shadow-sm: 0 8px 20px rgba(0, 0, 0, 0.06);
    --shadow-lg: 0 14px 40px rgba(13, 110, 253, 0.15);
  }

  /* ===== SECTIONS ===== */
  .features {
    padding: 70px 0;
    /*padding: 40px 0 70px; */
    background: var(--bg-soft);
  }

  .features.bg-white {
    background: #ffffff;
  }

  /* ===== HEADINGS ===== */
  .features h3 {
    font-weight: 700;
    color: var(--text-dark);
    letter-spacing: -0.5px;
    margin-bottom: 45px;
  }

  /* ===== FEATURE CARDS ===== */
  .feature-card {
    background: var(--card-bg);
    border-radius: var(--radius);
    padding: 30px 22px;
    height: 100%;
    box-shadow: var(--shadow-sm);
    transition: all 0.35s ease;
    border: 1px solid rgba(0, 0, 0, 0.03);
  }

  .feature-card:hover {
    transform: translateY(-8px);
    box-shadow: var(--shadow-lg);
    border-color: rgba(13, 110, 253, 0.25);
  }

  /* ===== ICONS ===== */
  .feature-card i {
    font-size: 40px;
    color: var(--primary);
  }

  /* ===== TITLES ===== */
  .feature-card h6 {
    font-weight: 600;
    font-size: 16px;
    margin-top: 16px;
    color: var(--text-dark);
  }

  /* ===== TEXT ===== */
  .help-text {
    font-size: 14px;
    line-height: 1.6;
    color: var(--text-muted);
    margin-top: 8px;
  }

  /* ===== STEPS PILL ===== */
  .pill {
    display: inline-block;
    padding: 6px 16px;
    font-size: 13px;
    font-weight: 600;
    color: var(--primary);
    background: rgba(13, 110, 253, 0.12);
    border-radius: 50px;
  }

  /* ===== FADE UP ANIMATION ===== */
  .fade-up {
    opacity: 0;
    transform: translateY(25px);
    animation: fadeUp 0.8s ease forwards;
  }

  @keyframes fadeUp {
    to {
      opacity: 1;
      transform: translateY(0);
    }
  }

  /* ===== FOOTER ===== */
  footer {
    background: linear-gradient(135deg, #0d6efd, #084298);
    color: #ffffff;
    padding: 22px 0;
    font-size: 14px;
  }

  footer .help-text {
    color: rgba(255, 255, 255, 0.85);
  }

  html,
  body {
    height: 100%;
  }

  body {
    background: linear-gradient(180deg, var(--bg), #ffffff 40%);
    font-family: Inter, system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial;
    color: #1f2937;
    -webkit-font-smoothing: antialiased;
    -moz-osx-font-smoothing: grayscale;
  }

  /* NAV */
  .navbar {
    background: transparent;
    padding: 0.75rem 0;
    transition: all .25s ease;
  }

  .navbar.scrolled {
    background: #fff;
    box-shadow: 0 6px 24px rgba(18, 38, 63, 0.06);
  }

  .brand-logo {
    display: flex;
    align-items: center;
    gap: .6rem;
    font-weight: 700;
    color: var(--accent);
    text-decoration: none
  }

  .brand-logo svg {
    height: 36px;
    width: 36px
  }

  /* HERO */
  .hero {
    padding: 110px 0 40px;
  }

  .hero-card {
    background: linear-gradient(180deg, rgba(255, 255, 255, 0.9), rgba(255, 255, 255, 0.85));
    border-radius: var(--radius);
    box-shadow: 0 10px 30px rgba(18, 38, 63, 0.06);
    padding: 28px;
  }

  .hero-title {
    font-size: 2.1rem;
    font-weight: 700;
    margin-bottom: 0.25rem;
  }

  .hero-sub {
    color: var(--muted);
    margin-bottom: 18px;
  }

  .cta-btn {
    border-radius: 10px;
    padding: .65rem 1.0rem;
    font-weight: 600;
  }



  /* .feature-card {
    background: var(--card);
    border-radius: 12px;
    padding: 20px;
    box-shadow: 0 8px 24px rgba(18, 38, 63, 0.04);
    transition: transform .28s ease, box-shadow .28s ease;
  }

  .feature-card:hover {
    transform: translateY(-8px);
    box-shadow: 0 18px 45px rgba(18, 38, 63, 0.06);
  } */

  /* Dashboard mock */
  .mock {
    width: 100%;
    height: 320px;
    border-radius: 10px;
    background: linear-gradient(180deg, #ffffff, #f3f6fb);
    border: 1px solid rgba(15, 23, 42, 0.04);
    box-shadow: 0 10px 30px rgba(18, 38, 63, 0.04);
    overflow: hidden;
    position: relative;
  }

  .mock .panel {
    position: absolute;
    background: rgba(255, 255, 255, 0.9);
    border-radius: 8px;
    padding: 12px;
    box-shadow: 0 6px 18px rgba(2, 6, 23, 0.04);
  }

  .mock .left {
    left: 16px;
    top: 18px;
    width: 45%;
    height: 75%;
  }

  .mock .right {
    right: 16px;
    top: 18px;
    width: 40%;
    height: 75%;
    display: flex;
    flex-direction: column;
    gap: 12px;
  }

  .mock .bar {
    height: 10px;
    border-radius: 6px;
    background: linear-gradient(90deg, var(--accent), #4aa3ff);
    width: 60%;
    margin: 6px 0;
  }

  .mock .row {
    height: 42px;
    background: rgba(10, 20, 40, 0.02);
    border-radius: 8px;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    padding: 8px 12px;
    justify-content: space-between;
    font-size: 13px;
    color: var(--muted);
  }

  /* Auth modal (custom) */
  .auth-section {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(10, 20, 40, 0.45);
    z-index: 9998;
    justify-content: center;
    align-items: center;
    padding: 18px;
  }

  .auth-box {
    width: 100%;
    max-width: 520px;
    border-radius: 12px;
    background: var(--glass);
    backdrop-filter: blur(6px);
    padding: 22px;
    box-shadow: 0 18px 60px rgba(2, 6, 23, 0.08);
  }

  .auth-logo {
    display: flex;
    align-items: center;
    gap: .6rem;
    margin-bottom: 10px;
  }

  .auth-logo svg {
    height: 36px;
    width: 36px
  }

  /* Small screens */
  @media (max-width:991px) {
    .mock {
      height: 240px
    }

    .hero-title {
      font-size: 1.6rem;
    }
  }


  footer {
    padding: 28px 0;
    color: var(--muted);
    font-size: 14px;
  }


  /* Modal/backdrop stacking
     - Keep auth overlay lower than modals
     - Ensure backdrop sits below modals so modal content remains clickable */
  .auth-section {
    z-index: 24000;
  }

  /* keep auth overlay under modals */
  /* Backdrop should be below modals but above page content */
  .modal-backdrop.show {
    z-index: 24500 !important;
  }

  /* Important modals must be above the backdrop */
  #otpModal.modal,
  #forgotModal.modal,
  #successModal.modal {
    z-index: 25000 !important;
  }

  /* Global loader used during registration / OTP wait */
  #globalLoader {
    display: none;
    position: fixed;
    inset: 0;
    align-items: center;
    justify-content: center;
    background: rgba(255, 255, 255, 0.7);
    z-index: 31000;
  }

  /* ================= PAGE LOAD LOADER ================= */
  #pageLoader {
    position: fixed;
    inset: 0;
    background: linear-gradient(180deg, #f7f9fc, #ffffff);
    z-index: 36000;
    /* above everything */
    display: flex;
    align-items: center;
    justify-content: center;
  }

  .bill-loader {
    width: 260px;
    background: #fff;
    border-radius: 12px;
    box-shadow: 0 18px 45px rgba(18, 38, 63, 0.08);
    padding: 18px;
    text-align: center;
  }

  .bill-loader .receipt {
    height: 120px;
    border-radius: 8px;
    background: linear-gradient(180deg, #ffffff, #f1f5ff);
    border: 1px dashed rgba(0, 0, 0, .1);
    padding: 10px;
    margin-bottom: 12px;
  }

  .bill-loader .line {
    height: 8px;
    background: rgba(0, 0, 0, .1);
    border-radius: 6px;
    margin-bottom: 8px;
  }

  .line.w80 {
    width: 80%;
  }

  .line.w60 {
    width: 60%;
  }

  .line.w40 {
    width: 40%;
  }


  
  </style>
</head>

<body>

  <!-- PAGE LOAD LOADER -->
  <div id="pageLoader">
    <div class="bill-loader">
      <div class="receipt">
        <div class="line w80"></div>
        <div class="line w60"></div>
        <div class="line w40"></div>
        <div class="line w80"></div>
      </div>
      <strong>BillMitra</strong>
      <div class="help-text">Preparing your billing dashboard…</div>
      <div class="progress mt-2">
        <div class="progress-bar progress-bar-striped progress-bar-animated" style="width:100%"></div>
      </div>
    </div>
  </div>


  <!-- Global loader (hidden by default) -->
  <div id="globalLoader"
    style="display:none; position:fixed; inset:0; align-items:center; justify-content:center; background:rgba(255,255,255,0.85); z-index:35000;">
    <div class="text-center">
      <div class="spinner-border text-primary" role="status" aria-hidden="true"></div>
      <div class="mt-2">Processing... please wait</div>
    </div>
  </div>

  <!-- NAVBAR -->
  <nav class="navbar fixed-top">
    <div class="container d-flex align-items-center justify-content-between">
      <a href="#" class="brand-logo">
        <!-- Simple SVG logo placeholder: replace with your SVG file if you have one -->
        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
          <rect x="4" y="8" width="40" height="32" rx="6" fill="#E9F2FF" />
          <path d="M12 30v-8h8v8H12zM28 30v-12h8v12h-8z" fill="#007bff" />
        </svg>
        BillMitra
      </a>

      <div>
        <button class="btn btn-outline-primary btn-sm me-2" onclick="scrollToFeatures()">Explore Features</button>
        <button class="btn btn-primary btn-sm cta-btn" onclick="openAuth('login')">Login / Register</button>
      </div>
    </div>
  </nav>

  <!-- HERO -->
  <section class="hero">
    <div class="container">
      <div class="row align-items-center g-4">
        <div>
          <div class="hero-card fade-up" data-uid="1">
            <div class="d-flex align-items-center justify-content-between">
              <div>
                <div class="pill">Billing & Inventory</div>
                <h2 class="hero-title mt-3">Simplify your billing and inventory management</h2>
                <p class="hero-sub">Fast invoices, real-time stock, and clear profit reports — designed for small and
                  medium stores in India.</p>
                <div class="d-flex gap-2">
                  <button class="btn btn-primary cta-btn" onclick="openAuth('register')">Get Started</button>
                  <a class="btn btn-outline-secondary cta-btn" href="#features">See Features</a>
                </div>
              </div>
              <div class="text-end help-text d-none d-lg-block" style="min-width:120px;">
                <div style="font-weight:700; font-size:18px;">Trusted by stores</div>
                <div style="margin-top:8px;">Easy setup • Multi-role access • Secure</div>
              </div>
            </div>
            <div style="margin-top:18px;" class="help-text">Sign up quickly and verify via email OTP. No credit card
              required to try.</div>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- FEATURES -->
  <section id="features" class="features">
    <div class="container">
      <h3 class="mb-4 text-center">What BillMitra offers</h3>
      <div class="row g-4">
        <div class="col-md-4">
          <div class="feature-card p-4 text-center fade-up" data-uid="3">
            <i class="bi bi-receipt" style="font-size:28px;color:var(--accent)"></i>
            <h5 class="mt-3">Smart Billing</h5>
            <p class="help-text">Live invoice creation, barcode scanning, tax & discount calculations, and printable
              invoices.</p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="feature-card p-4 text-center fade-up" data-uid="4">
            <i class="bi bi-box-seam" style="font-size:28px;color:var(--accent)"></i>
            <h5 class="mt-3">Inventory Management</h5>
            <p class="help-text">Category-based stock, auto deduction on sale and low-stock alerts.</p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="feature-card p-4 text-center fade-up" data-uid="5">
            <i class="bi bi-graph-up" style="font-size:28px;color:var(--accent)"></i>
            <h5 class="mt-3">Profit & Reports</h5>
            <p class="help-text">Daily/monthly/yearly summaries, tax breakdowns, and profit analysis charts.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- WHY BILLMITRA -->
  <section class="features bg-white">
    <div class="container">
      <h3 class="text-center mb-4">Why choose BillMitra?</h3>
      <div class="row g-4">
        <div class="col-md-4">
          <div class="feature-card fade-up text-center">
            <i class="bi bi-shield-check fs-3 text-primary"></i>
            <h6 class="mt-3">Secure & Reliable</h6>
            <p class="help-text">
              OTP verification, hashed passwords, role-based access,
              and secure database relations.
            </p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="feature-card fade-up text-center">
            <i class="bi bi-lightning-charge fs-3 text-primary"></i>
            <h6 class="mt-3">Fast Billing</h6>
            <p class="help-text">
              Create invoices in seconds with live totals,
              auto tax calculation and instant stock update.
            </p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="feature-card fade-up text-center">
            <i class="bi bi-bar-chart-line fs-3 text-primary"></i>
            <h6 class="mt-3">Business Insights</h6>
            <p class="help-text">
              Clear sales, tax and profit reports to help you
              take better business decisions.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- HOW IT WORKS -->
  <section class="features">
    <div class="container">
      <h3 class="text-center mb-4">How BillMitra works</h3>
      <div class="row g-4">
        <div class="col-md-3">
          <div class="feature-card text-center fade-up">
            <div class="pill">Step 1</div>
            <h6 class="mt-3">Register</h6>
            <p class="help-text">Create your account and verify email via OTP.</p>
          </div>
        </div>

        <div class="col-md-3">
          <div class="feature-card text-center fade-up">
            <div class="pill">Step 2</div>
            <h6 class="mt-3">Setup Store</h6>
            <p class="help-text">Add products, categories and tax settings.</p>
          </div>
        </div>

        <div class="col-md-3">
          <div class="feature-card text-center fade-up">
            <div class="pill">Step 3</div>
            <h6 class="mt-3">Start Billing</h6>
            <p class="help-text">Generate invoices and manage inventory live.</p>
          </div>
        </div>

        <div class="col-md-3">
          <div class="feature-card text-center fade-up">
            <div class="pill">Step 4</div>
            <h6 class="mt-3">Track Growth</h6>
            <p class="help-text">View reports, profit and business performance.</p>
          </div>
        </div>
      </div>
    </div>
  </section>

  <!-- ROLE BASED ACCESS -->
  <section class="features bg-white">
    <div class="container">
      <h3 class="text-center mb-4">Role-based access</h3>
      <div class="row g-4">
        <div class="col-md-4">
          <div class="feature-card fade-up">
            <h6>Admin</h6>
            <p class="help-text">
              Full control of store, inventory, users, reports
              and system settings.
            </p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="feature-card fade-up">
            <h6>Manager</h6>
            <p class="help-text">
              Handle billing, inventory updates and sales
              monitoring without admin access.
            </p>
          </div>
        </div>

        <div class="col-md-4">
          <div class="feature-card fade-up">
            <h6>Cashier</h6>
            <p class="help-text">
              Fast billing interface with limited access,
              perfect for counters.
            </p>
          </div>
        </div>
      </div>
    </div>
  </section>


  <!-- FOOTER -->
  <footer>
    <div class="container d-flex flex-column flex-md-row justify-content-between align-items-center">
      <div>&copy; <?= date('Y') ?> BillMitra. All rights reserved.</div>
      <div class="mt-2 mt-md-0 help-text">Built for Indian stores — simple, secure, and fast.</div>
    </div>
  </footer>

  <!-- AUTH SECTION (modal-style) -->
  <section class="auth-section" id="auth">
    <div class="auth-box">
      <div class="auth-logo">
        <svg viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden>
          <rect x="4" y="8" width="40" height="32" rx="6" fill="#E9F2FF" />
          <path d="M12 30v-8h8v8H12zM28 30v-12h8v12h-8z" fill="#007bff" />
        </svg>
        <div>
          <div style="font-weight:700">BillMitra</div>
          <div class="help-text">Billing & Inventory</div>
        </div>
      </div>

      <button type="button" class="btn-close float-end" aria-label="Close" onclick="closeAuth()"></button>

      <ul class="nav nav-tabs mt-2 mb-3" role="tablist">
        <li class="nav-item">
          <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#loginTab" type="button">Login</button>
        </li>
        <li class="nav-item">
          <button class="nav-link" data-bs-toggle="tab" data-bs-target="#registerTab" type="button">Register</button>
        </li>
      </ul>

      <div class="tab-content">
        <!-- LOGIN -->
        <div class="tab-pane fade show active" id="loginTab">
          <form method="POST" style="margin-top:6px;">
            <div class="mb-2">
              <label class="form-label small">Username</label>
              <input type="text" name="username" class="form-control" placeholder="Enter username">
            </div>
            <div class="mb-3">
              <label class="form-label small">Password</label>
              <input type="password" name="password" class="form-control" placeholder="Enter password">
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <div class="help-text small">Not registered? Switch to Register tab.</div>
              <a href="#" id="forgotLink" class="help-text" style="text-decoration:none"
                onclick="openForgot(event)">Forgot?</a>
            </div>
            <button type="submit" name="login" class="btn btn-primary w-100">Login</button>
          </form>
        </div>

        <!-- REGISTER -->
        <div class="tab-pane fade" id="registerTab">
          <form method="POST" id="registerForm" style="margin-top:6px;">
            <input type="hidden" name="register_ajax" value="1">
            <div class="mb-2">
              <label class="form-label small">Role</label>
              <select name="role" id="roleSelect" class="form-select" required>
                <option value="" disabled selected>Choose role</option>
                <option value="admin">Admin (Create Store)</option>
                <option value="manager">Manager</option>
                <option value="cashier">Cashier</option>
              </select>
            </div>

            <div id="adminFields" style="display:none;">
              <div class="mb-2"><input type="text" name="store_name" class="form-control" placeholder="Store Name">
              </div>
              <div class="mb-2">
                <input type="email" name="store_email" id="storeEmailInput" class="form-control" placeholder="Email">
                <small id="storeEmailMsg" class="text-danger"></small>
                <small id="storeEmailInfo" class="help-text d-block">This email cannot be changed later.</small>
              </div>
              <div class="mb-2">
                <input type="text" name="personal_contact_number" id="pcnInput" class="form-control"
                  placeholder="Contact Number">
                <small id="pcnMsg" class="text-danger"></small>
                <small id="pcnInfo" class="help-text d-block">Mobile number will be used for account verification only.
                  Store contact can be added later in Settings.</small>
              </div>

            </div>

            <div class="mb-2">
              <input type="text" name="username" id="usernameInput" class="form-control" placeholder="Username"
                required>
              <small id="usernameMsg" class="text-danger"></small>
            </div>
            <div class="mb-3"><input type="password" name="password" class="form-control" placeholder="Password"
                required>
              <small id="passwordError" class="text-danger"></small>
            </div>

            <div id="employeeFields" style="display:none;">
              <div class="mb-2">
                <input type="text" name="store_code" id="store_code" class="form-control" placeholder="Store Code">
                <small id="storeCodeMsg" class="text-danger"></small>
              </div>

              <div class="mb-2">
                <input type="email" name="email" id="emailInput" class="form-control" placeholder="Email">
                <small id="emailMsg" class="text-danger"></small>
                <small id="emailInfo" class="help-text d-block">This email cannot be changed later.</small>
              </div>
            </div>

            <button type="submit" id="registerBtn" class="btn btn-success w-100">Register</button>
          </form>
        </div>
      </div>
    </div>
  </section>

  <!-- OTP Modal -->
  <div class="modal fade" id="otpModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-4 text-center">
        <h5 class="fw-bold mb-2">Verify your email</h5>
        <p class="help-text">Enter the 6-digit OTP sent to your email.</p>
        <input type="text" id="otpInput" maxlength="6" class="form-control text-center mb-3" placeholder="Enter OTP">
        <div id="otpMessage" class="text-danger mb-2"></div>
        <button id="verifyOtpBtn" class="btn btn-primary w-100 mb-2">Verify</button>
        <small id="timerText" class="help-text">Resend in 40s</small><br>
        <button id="resendBtn" class="btn btn-link p-0 mt-2" disabled>Resend OTP</button>
      </div>
    </div>
  </div>

  <!-- Success Modal -->
  <div class="modal fade" id="successModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-4 text-center">
        <h5 class="fw-bold mb-2 text-success">Registration Successful</h5>
        <div id="userDetails" class="mb-3 text-start"></div>
        <button class="btn btn-success w-100" id="okSuccessBtn" data-bs-dismiss="modal">OK</button>
      </div>
    </div>
  </div>

  <!-- Forgot Password Modal -->
  <div class="modal fade" id="forgotModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content p-4 text-center">
        <h5 class="fw-bold mb-2">Reset your password</h5>
        <input type="email" id="forgotEmail" class="form-control mb-2" placeholder="Your registered email">
        <div id="forgotMessage" class="text-danger mb-2"></div>
        <button id="sendResetOtpBtn" class="btn btn-primary w-100 mb-2">Send OTP</button>

        <div id="resetSection" style="display:none;">
          <input type="text" id="resetOtpInput" maxlength="6" class="form-control text-center mb-2"
            placeholder="Enter OTP">
          <input type="password" id="newPassword" class="form-control mb-2" placeholder="New password">
          <button id="resetPasswordBtn" class="btn btn-success w-100">Reset Password</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Toast for store exists -->
  <div style="position:fixed; top:1rem; right:1rem; z-index:22000;">
    <div id="storeToast" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive"
      aria-atomic="true" style="display:none;">
      <div class="d-flex">
        <div class="toast-body">Store already exists! with this email</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="$('#storeToast').hide()"></button>
      </div>
    </div>
  </div>

  <div style="position:fixed; top:1rem; right:1rem; z-index:22000;">
    <div id="pcnToast" class="toast align-items-center text-bg-danger border-0" role="alert" aria-live="assertive"
      aria-atomic="true" style="display:none;">
      <div class="d-flex">
        <div class="toast-body">Contact number already exists!</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="$('#pcnToast').hide()"></button>
      </div>
    </div>
  </div>

  <div style="position:fixed; top:1rem; right:1rem; z-index:22000;">
    <div id="invalidStoreCodeToast" class="toast align-items-center text-bg-danger border-0" role="alert"
      aria-live="assertive" aria-atomic="true" style="display:none;">
      <div class="d-flex">
        <div class="toast-body">Invalid Store Code!</div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto"
          onclick="$('#invalidStoreCodeToast').hide()"></button>
      </div>
    </div>
  </div>

  <!-- Toast Area for Login Error -->
  <div class="position-fixed top-0 end-0 p-3" style="z-index: 3000;">
    <div id="loginErrorToast" class="toast align-items-center text-white bg-danger border-0" role="alert">
      <div class="d-flex">
        <div class="toast-body" id="loginErrorMsg">
          <!-- message comes from PHP -->
        </div>
        <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
      </div>
    </div>
  </div>

  <script>
  window.addEventListener("load", () => {
    setTimeout(() => {
      const loader = document.getElementById("pageLoader");
      if (loader) loader.style.display = "none";

      document.querySelectorAll(".fade-up").forEach(el => {
        el.classList.add("visible");
      });
    }, 2000);
  });
  </script>


  <?php if (isset($_GET['login_error'])): ?>

  
  <script>
  document.addEventListener('DOMContentLoaded', function() {
    // Show toast
    document.getElementById('loginErrorMsg').textContent = "<?= htmlspecialchars($_GET['login_error']) ?>";
    const toast = new bootstrap.Toast(document.getElementById('loginErrorToast'));
    toast.show();

    // Remove the login_error parameter from URL so toast doesn't repeat
    const url = new URL(window.location);
    url.searchParams.delete('login_error');
    window.history.replaceState({}, document.title, url);
  });
  </script>
  <?php endif; ?>


  <!-- Login Fields mandatory -->
   <script>
  document.addEventListener("DOMContentLoaded", () => {
    const loginForm = document.querySelector('form[method="POST"]'); // Select the login form
    const usernameInput = loginForm.querySelector('input[name="username"]');
    const passwordInput = loginForm.querySelector('input[name="password"]');
  
    // Create inline error elements for username and password
    const usernameError = document.createElement("small");
    usernameError.className = "text-danger d-block mt-1";
    usernameError.style.display = "none";
    usernameInput.parentNode.appendChild(usernameError);

    const passwordError = document.createElement("small");
    passwordError.className = "text-danger d-block mt-1";
    passwordError.style.display = "none";
    passwordInput.parentNode.appendChild(passwordError);

    // Add event listener on form submit
    loginForm.addEventListener("submit", (event) => {
      // Reset all error messages
      usernameError.textContent = "";
      usernameError.style.display = "none";
      passwordError.textContent = "";
      passwordError.style.display = "none";

      let isValid = true;

      // Check if the username is empty
      if (!usernameInput.value.trim()) {
        usernameError.textContent = "Username is required.";
        usernameError.style.display = "block";
        isValid = false;
      }

      // Check if the password is empty
      if (!passwordInput.value.trim()) {
        passwordError.textContent = "Password is required.";
        passwordError.style.display = "block";
        isValid = false;
      }

      // If any validation fails, prevent form submission
      if (!isValid) {
        event.preventDefault();
      }
    });
  });
</script>

  <!-- SCRIPTS -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
  <script>
  // simple scroll-into features
  function scrollToFeatures() {
    document.getElementById('features').scrollIntoView({
      behavior: 'smooth'
    });
  }

  // show/hide auth
  function openAuth(tab) {
    $('#auth').css('display', 'flex');
    if (tab === 'register') {
      const tabEl = document.querySelector('[data-bs-target="#registerTab"]');
      new bootstrap.Tab(tabEl).show();
    } else {
      const tabEl = document.querySelector('[data-bs-target="#loginTab"]');
      new bootstrap.Tab(tabEl).show();
    }
    // animate visible
    setTimeout(runFadeUps, 40);
  }

  function closeAuth() {
    $('#auth').hide();
  }

  function openForgot(e) {
    if (e) e.preventDefault();
    closeAuth();
    // remove any stray backdrops before opening the forgot modal
    cleanupModalBackdrops();
    const fm = new bootstrap.Modal(document.getElementById('forgotModal'));
    fm.show();
  }

  // helper: disable register button if any inline errors are present or required fields empty
  function checkErrorsAndToggleButton() {
    let disable = false;
    // if any inline error text exists, disable
    $('small.text-danger').each(function() {
      if ($(this).text().trim() !== '') disable = true;
    });
    // basic required checks: role and username must be present
    if ($('#roleSelect').length && ($('#roleSelect').val() === null || $('#roleSelect').val() === '')) disable = true;
    if ($('#usernameInput').length && $('#usernameInput').val().trim() === '') disable = true;
    $('#registerBtn').prop('disabled', disable);
    return !disable;
  }

  // helper: show/hide loader with flex display
  function showLoader() {
    $('#globalLoader').css('display', 'flex');
  }

  function hideLoader() {
    $('#globalLoader').hide();
  }

  // ensure initial state
  $(function() {
    checkErrorsAndToggleButton();
  });

  // role select toggle (keep clearing of inline messages)
  $('#roleSelect').on('change', function() {
    if (this.value === 'admin') {
      $('#adminFields').show();
      $('#employeeFields').hide();
    } else {
      $('#adminFields').hide();
      $('#employeeFields').show();
    }
    // clear inline messages when role toggles
    $('#storeEmailMsg, #pcnMsg, #emailMsg, #usernameMsg, #storeCodeMsg').text('');
    checkErrorsAndToggleButton();
  });

  // username live check
  $('#usernameInput').on('input', function() {
    const username = $(this).val();
    if (username.length < 3) {
      $('#usernameMsg').text('');
      checkErrorsAndToggleButton();
      return;
    }
    $.post('index.php', {
      check_username: 1,
      username
    }, res => {
      const data = JSON.parse(res);
      if (data.exists) {
        $('#usernameMsg').text('Username already exists');
      } else {
        $('#usernameMsg').text('');
      }
      checkErrorsAndToggleButton();
    });
  });

  // admin store email live check (checks users.email and stores.store_email)
  $('#storeEmailInput').on('input', function() {
    const email = $(this).val().trim();
    $('#storeEmailMsg').text('');
    if (email.length < 5) {
      checkErrorsAndToggleButton();
      return;
    }
    $.post('index.php', {
      check_email: 1,
      email
    }, function(res) {
      try {
        const data = typeof res === 'object' ? res : JSON.parse(res);
        if (data.exists) {
          $('#storeEmailMsg').text('Email already registered');
        } else {
          $('#storeEmailMsg').text('');
        }
        checkErrorsAndToggleButton();
      } catch (e) {
        console.error('Invalid JSON from check_email:', res);
      }
    });
  });

  // admin personal contact live check (users.personal_contact_number OR stores.contact_number)
  $('#pcnInput').on('input', function() {
    const contact = $(this).val().trim();
    $('#pcnMsg').text('');
    if (contact.length < 3) {
      checkErrorsAndToggleButton();
      return;
    }
    $.post('index.php', {
      check_contact: 1,
      contact
    }, function(res) {
      try {
        const data = typeof res === 'object' ? res : JSON.parse(res);
        if (data.exists) {
          $('#pcnMsg').text('Contact number already exists');
        } else {
          $('#pcnMsg').text('');
        }
        checkErrorsAndToggleButton();
      } catch (e) {
        console.error('Invalid JSON from check_contact:', res);
      }
    });
  });

  // employee email live check (users table only)
  $('#emailInput').on('input', function() {
    const email = $(this).val().trim();
    $('#emailMsg').text('');
    if (email.length < 5) {
      checkErrorsAndToggleButton();
      return;
    }
    $.post('index.php', {
      check_user_email: 1,
      email
    }, function(res) {
      try {
        const data = typeof res === 'object' ? res : JSON.parse(res);
        if (data.exists) {
          $('#emailMsg').text('Email already registered');
        } else {
          $('#emailMsg').text('');
        }
        checkErrorsAndToggleButton();
      } catch (e) {
        console.error('Invalid JSON from check_user_email:', res);
      }
    });
  });

  // store code live check (calls existing check_store_code.php)
  $('#store_code').on('input', function() {
    const code = $(this).val().trim();
    $('#storeCodeMsg').text('');
    if (code.length < 2) {
      checkErrorsAndToggleButton();
      return;
    }
    $.post('check_store_code.php', {
      store_code: code
    }, function(response) {
      try {
        const res = typeof response === 'object' ? response : JSON.parse(response);
        if (res.status === 'invalid') {
          $('#storeCodeMsg').text('Invalid Store Code');
        } else {
          $('#storeCodeMsg').text('');
        }
        checkErrorsAndToggleButton();
      } catch (e) {
        console.error('Invalid JSON from check_store_code.php:', response);
      }
    });
  });

  // Register Submit (AJAX to existing index.php)
  $('#registerForm').on('submit', function(e) {
    e.preventDefault();
    // prevent submit when register button is disabled
    if ($('#registerBtn').prop('disabled')) return;
    $('small.text-danger').text('');

    const role = $('#roleSelect').val();
    const storeCode = $('#store_code').val().trim();

    // Function to submit the form via AJAX
    function submitRegistration() {
      // show global loader (use flex so spinner centers correctly)
      showLoader();
      $.ajax({
        url: 'index.php',
        method: 'POST',
        data: $('#registerForm').serialize(),
        success: function(response) {
          // hide loader on response
          hideLoader();
          try {
            const res = JSON.parse(response);
            if (res.status === 'otp_sent') {
              // ensure loader hidden and remove stray backdrops before showing OTP modal
              hideLoader();
              cleanupModalBackdrops();
              const otpModal = new bootstrap.Modal(document.getElementById('otpModal'));
              otpModal.show();

              let timer = 40;
              const resendBtn = $('#resendBtn');
              const timerText = $('#timerText');
              resendBtn.prop('disabled', true);
              const countdown = setInterval(() => {
                timer--;
                timerText.text('Resend in ' + timer + 's');
                if (timer <= 0) {
                  clearInterval(countdown);
                  resendBtn.prop('disabled', false);
                  timerText.text('');
                }
              }, 1000);
            } else if (res.status === 'username_exists') {
              $('#usernameMsg').text('Username already exists');
            } else if (res.status === 'email_exists') {
              // for employee role this will map to #emailMsg
              if (role === 'admin') $('#storeEmailMsg').text('Email already exists');
              else $('#emailMsg').text('Email already exists');
            } else if (res.status === 'store_exists') {
              // store email already exists
              $('#storeEmailMsg').text('Store email already registered');
            } else if (res.status === 'contact_exists') {
              // personal contact already exists
              $('#pcnMsg').text('Contact number already exists');
            } else {
              alert('Something went wrong: ' + res.status);
            }
            // update disabled state after server-side messages
            checkErrorsAndToggleButton();
          } catch {
            console.error('Invalid JSON from index.php:', response);
          }
        },
        error: function() {
          hideLoader();
          alert('Server error. Please try again.');
        }
      });
    }

    // If Manager/Cashier, validate store code first
    if ((role === 'manager' || role === 'cashier') && storeCode !== '') {
      $.ajax({
        url: 'check_store_code.php', // same folder as index.php
        type: 'POST',
        data: {
          store_code: storeCode
        },
        success: function(response) {
          try {
            // If PHP returns proper JSON, parse it
            const res = typeof response === 'object' ? response : JSON.parse(response);
            if (res.status === 'invalid') {
              $('#storeCodeMsg').text('Invalid Store Code'); // inline message
              $('#store_code').focus();
              checkErrorsAndToggleButton();
              return false; // Stop registration
            } else {
              // Valid store code → submit registration
              submitRegistration();
            }
          } catch (err) {
            console.error('Invalid JSON from check_store_code.php:', response, err);
          }
        },
        error: function() {
          alert('Server error while checking store code.');
        }
      });
    } else {
      // Admin or no store code entered → proceed directly
      submitRegistration();
    }
  });


  // Verify OTP (calls your verify_otp.php)
  $('#verifyOtpBtn').click(() => {
    const otp = $('#otpInput').val().trim();
    if (!otp) return $('#otpMessage').text('Please enter OTP');

    $('#otpMessage').text('Verifying...');
    // show loader during verify
    showLoader();
    $.post('verify_otp.php', {
        otp
      })
      .done(res => {
        hideLoader();
        let data;
        try {
          data = typeof res === 'object' ? res : JSON.parse(res);
        } catch {
          $('#otpMessage').text('Server error');
          hideLoader();
          return;
        }
        if (data.status === 'success') {
          $('#otpMessage').removeClass('text-danger').addClass('text-success').text('OTP Verified');
          setTimeout(() => {
            // hide OTP modal
            const otpModalEl = document.getElementById('otpModal');
            const otpModal = bootstrap.Modal.getOrCreateInstance(otpModalEl);
            otpModal.hide();
            // hide the auth overlay if you used it
            $('#auth').hide();
            // let the hidden.bs.modal listener run cleanup; call once more defensively
            setTimeout(cleanupModalBackdrops, 20);
            // ensure no stray backdrop before showing success modal
            cleanupModalBackdrops();
            $('#userDetails').html(
              `<p><b>Username:</b> ${data.username}</p><p><b>Email:</b> ${data.role === 'admin' ? (data.store_email || data.email) : data.email}</p>`
            );
            const successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();
          }, 700);
        } else if (data.status === 'invalid_otp') {
          $('#otpMessage').text('Invalid OTP');
        } else if (data.status === 'session_expired') {
          $('#otpMessage').text('Session expired, register again');
        } else {
          $('#otpMessage').text('Something went wrong');
        }
      })
      .fail(() => {
        hideLoader();
        $('#otpMessage').text('Network error');
      });
  });

  // Forgot password flow
  $('#sendResetOtpBtn').click(function() {
    const email = $('#forgotEmail').val().trim();
    if (!email) return $('#forgotMessage').text('Enter your email');
    $('#forgotMessage').text('Sending OTP...');
    $.post('forgot_password.php', {
      action: 'send_otp',
      email
    }, res => {
      try {
        const data = JSON.parse(res);
        if (data.status === 'otp_sent') {
          $('#forgotMessage').text('OTP sent to your email');
          $('#resetSection').slideDown();
        } else if (data.status === 'email_not_found') {
          $('#forgotMessage').text('Email not registered');
        } else {
          $('#forgotMessage').text('Error sending OTP');
        }
      } catch {
        $('#forgotMessage').text('Server error');
      }
    });
  });

  $('#resetPasswordBtn').click(function() {
    const otp = $('#resetOtpInput').val().trim();
    const newPassword = $('#newPassword').val().trim();
    if (!otp || !newPassword) return $('#forgotMessage').text('Please fill all fields');
    $('#forgotMessage').text('Verifying...');
    $.post('forgot_password.php', {
      action: 'verify_otp',
      otp,
      newPassword
    }, res => {
      try {
        const data = JSON.parse(res);
        if (data.status === 'success') {
          $('#forgotMessage').removeClass('text-danger').addClass('text-success').text(
            'Password reset successfully');
          setTimeout(() => {
            const m = bootstrap.Modal.getInstance(document.getElementById('forgotModal'));
            if (m) m.hide();
          }, 900);
        } else if (data.status === 'invalid_otp') {
          $('#forgotMessage').text('Invalid OTP');
        } else if (data.status === 'expired') {
          $('#forgotMessage').text('OTP expired');
        } else {
          $('#forgotMessage').text('Something went wrong');
        }
      } catch {
        $('#forgotMessage').text('Server error');
      }
    });
  });

  // small UI: animate elements on scroll / appear
  function runFadeUps() {
    document.querySelectorAll('.fade-up').forEach((el, i) => {
      setTimeout(() => el.classList.add('visible'), 80 * (i + 1));
    });
  }
  // run on page load
  document.addEventListener('DOMContentLoaded', function() {
    runFadeUps();
    // navbar background on scroll
    window.addEventListener('scroll', function() {
      document.querySelector('.navbar').classList.toggle('scrolled', window.scrollY > 10);
    });
  });

  // allow resend click (calls server resend - this requires endpoint if needed)
  $('#resendBtn').click(function() {
    // If you have an endpoint to resend stored OTP in session, call it here.
    // Currently, we just show text and disable button to avoid spam.
    $(this).prop('disabled', true).text('Resent');
    setTimeout(() => $(this).prop('disabled', false).text('Resend OTP'), 4000);
  });

  // after success OK, show login tab and clear fields
  $('#okSuccessBtn').click(function() {
    openAuth('login');
    document.querySelectorAll('#registerForm input').forEach(i => i.value = '');
    $('#otpInput').val('');
  });

  // Helper: remove stray backdrops and restore body only when no modal is open
  function cleanupModalBackdrops() {
    // if any modal is still shown, don't remove the backdrop
    if (document.querySelectorAll('.modal.show').length > 0) return;
    // remove backdrops and restore body classes/padding
    $('.modal-backdrop').remove();
    $('body').removeClass('modal-open').css('padding-right', '');
  }

  // Run cleanup on page load in case a stray backdrop already exists
  $(function() {
    cleanupModalBackdrops();
  });

  // Listen for any bootstrap modal hidden event and cleanup after a short delay
  document.addEventListener('hidden.bs.modal', function() {
    // small timeout for bootstrap internal cleanup race conditions
    setTimeout(cleanupModalBackdrops, 10);
  });
  </script>
</body>

</html>