<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html?error=auth_required");
    exit();
}

require 'config.php';
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION = array();
    session_destroy();
    header("Location: login.html?error=session_invalid");
    exit();
}

$_SESSION['user_role'] = $user['role'] ?? 'user';
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['fullname'];
?>