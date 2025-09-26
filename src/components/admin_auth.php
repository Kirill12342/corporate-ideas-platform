<?php
// Включаем буферизацию вывода для предотвращения проблем с заголовками
ob_start();

session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    header("Location: login.html?error=access_denied");
    exit();
}

function secure_logout() {
    $_SESSION = array();
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    session_destroy();
}

if (isset($_SESSION['user_id'])) {
    require 'config.php';
    $sql = "SELECT role FROM users WHERE id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();

    if (!$user || $user['role'] !== 'admin') {
        secure_logout();
        header("Location: login.html?error=session_invalid");
        exit();
    }
}
?>