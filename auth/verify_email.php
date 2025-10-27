<?php
require_once __DIR__ . '/../config/db.php';

if (isset($_GET['token'])) {
    $token = $_GET['token'];

    $stmt = $conn->prepare("SELECT * FROM users WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows > 0) {
        $user = $result->fetch_assoc();

        $update = $conn->prepare("UPDATE users SET email_verified = 1, verification_token = NULL WHERE user_id = ?");
        $update->bind_param("i", $user['user_id']);
        $update->execute();

        echo "<h2>Email Verified Successfully ✅</h2><p>You can now <a href='/auth/index.php'>Login</a>.</p>";
    } else {
        echo "<h2>Invalid or expired link ❌</h2>";
    }
}
?>
