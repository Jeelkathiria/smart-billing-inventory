<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/mail.php';

function sendOTP($email, $otp)
{
    $subject = "SmartBiz Password Reset OTP";
    $body = "
        <h2>Password Reset Request</h2>
        <h3>Your OTP for password reset is: <b>$otp</b></h3>
        <p>Valid for 3 minutes.</p>
        <p>If you did not request a password reset, please ignore this email and your password will remain unchanged.</p>
    ";
    
    $result = sendEmail($email, 'User', $subject, $body);
    return $result['success'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'];

    /* --- SEND OTP --- */
    if ($action === 'send_otp') {
        $email = trim($_POST['email']);
        $stmt = $conn->prepare("SELECT user_id FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            echo json_encode(['status' => 'email_not_found']);
            exit;
        }

        $otp = rand(100000, 999999);
        $_SESSION['reset_otp'] = $otp;
        $_SESSION['reset_email'] = $email;
        $_SESSION['reset_expiry'] = time() + 180;

        if (sendOTP($email, $otp))
            echo json_encode(['status' => 'otp_sent']);
        else
            echo json_encode(['status' => 'mail_failed']);
        exit;
    }

    /* --- VERIFY OTP & RESET PASSWORD --- */
    if ($action === 'verify_otp') {
        $otp = $_POST['otp'];
        $newPassword = $_POST['newPassword'];

        if (!isset($_SESSION['reset_otp'], $_SESSION['reset_email'])) {
            echo json_encode(['status' => 'expired']);
            exit;
        }

        if (time() > $_SESSION['reset_expiry']) {
            unset($_SESSION['reset_otp']);
            echo json_encode(['status' => 'expired']);
            exit;
        }

        if ($otp != $_SESSION['reset_otp']) {
            echo json_encode(['status' => 'invalid_otp']);
            exit;
        }

        $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password=? WHERE email=?");
        $stmt->bind_param("ss", $hashed, $_SESSION['reset_email']);
        $stmt->execute();

        unset($_SESSION['reset_otp'], $_SESSION['reset_email'], $_SESSION['reset_expiry']);
        echo json_encode(['status' => 'success']);
        exit;
    }
}
?>
