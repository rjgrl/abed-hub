<?php
session_start();
require_once 'config/database.php';

// Log logout activity
if (isset($_SESSION['user_id'])) {
    $user_id = $_SESSION['user_id'];
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("
        INSERT INTO audit_log (user_id, action, ip_address)
        VALUES (?, 'LOGOUT', ?)
    ");
    $stmt->bind_param("is", $user_id, $ip_address);
    $stmt->execute();
    $conn->close();
}

// Destroy session
session_destroy();

// Redirect to login
header('Location: login.php');
exit;
?>

