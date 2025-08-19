<?php
session_set_cookie_params([
    'lifetime' => 3600,  // 1 hour
    'path' => '/',        // important for subfolders
    'secure' => false,    // true if using HTTPS
    'httponly' => true,
    'samesite' => 'Lax'
]);
session_start();

// include DB connection
require_once __DIR__ . "/../config/db.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]);

    if (empty($username) || empty($password)) {
        header("Location: index.php?error=Please%20fill%20all%20fields");
        exit();
    }

    $sql = "SELECT * FROM users WHERE username = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result && $result->num_rows === 1) {
        $row = $result->fetch_assoc();

        if (password_verify($password, $row["password"])) {
            // Store session values
            $_SESSION["user_id"] = $row["user_id"];
            $_SESSION["username"] = $row["username"];
            $_SESSION["role"] = $row["role"];
            $_SESSION["store_id"] = $row["store_id"];
            $_SESSION["login_time"] = time(); // <-- Add this line

            // Redirect to dashboard
            header("Location: ../modules/dashboard.php");
            exit();
        } else {
            header("Location: index.php?error=Invalid%20password");
            exit();
        }
    } else {
        header("Location: index.php?error=User%20not%20found");
        exit();
    }
}
?>
