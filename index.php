<?php
/**
 * File: index.php
 * Purpose: Application entry point for Smart Billing & Inventory
 * Project: Smart Billing & Inventory
 * Last Modified: 2025-02-18
 */

session_start();

// Check if user is logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['store_id'])) {
    // User is logged in - redirect to dashboard
    header('Location: /modules/dashboard.php');
    exit;
} else {
    // User is not logged in - redirect to login page
    header('Location: /auth/index.php');
    exit;
}
?>
