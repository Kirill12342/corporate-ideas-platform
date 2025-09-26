<?php
// Начинаем буферизацию вывода до всех других операций
if (!ob_get_level()) {
    ob_start();
}

// Проверяем, не были ли уже отправлены заголовки
if (!headers_sent()) {
    // Стартуем сессию только если заголовки еще не отправлены
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    if (!isset($_SESSION['user_id'])) {
        header("Location: login.html?error=auth_required");
        exit();
    }
} else {
    // Если заголовки уже отправлены, используем JavaScript редирект
    if (!isset($_SESSION['user_id'])) {
        echo '<script>window.location.href = "login.html?error=auth_required";</script>';
        exit();
    }
}

require 'config.php';
$sql = "SELECT * FROM users WHERE id = ?";
$stmt = $pdo->prepare($sql);
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if (!$user) {
    $_SESSION = array();
    session_destroy();
    if (!headers_sent()) {
        header("Location: login.html?error=session_invalid");
    } else {
        echo '<script>window.location.href = "login.html?error=session_invalid";</script>';
    }
    exit();
}

$_SESSION['user_role'] = $user['role'] ?? 'user';
$_SESSION['user_email'] = $user['email'];
$_SESSION['user_name'] = $user['fullname'];
?>