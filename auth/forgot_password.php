<?php
session_start();
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

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

        $mail->setFrom('testing992017@gmail.com', 'SmartBiz Password Reset');
        $mail->addAddress($email);
        $mail->isHTML(true);
        $mail->Subject = "SmartBiz Password Reset OTP";
        $mail->Body = "<h3>Your OTP for password reset is <b>$otp</b></h3><p>Valid for 3 minutes.</p>";
        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
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
