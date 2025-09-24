<?php
require 'config.php'; 

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $fullname = trim($_POST['fullname']);
    $email = trim(strtolower($_POST['email']));
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($fullname) || empty($email) || empty($password)) {
        echo "Все поля должны быть заполнены.";
        exit;
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo "Некорректный формат email.";
        exit;
    }

    if ($password !== $confirm_password) {
        echo "Пароли не совпадают.";
        exit;
    }

    if (strlen($password) < 6) {
        echo "Пароль должен содержать минимум 6 символов.";
        exit;
    }

    $check_sql = "SELECT COUNT(*) FROM users WHERE email = ?";
    $check_stmt = $pdo->prepare($check_sql);
    $check_stmt->execute([$email]);
    $user_exists = $check_stmt->fetchColumn();

    if ($user_exists > 0) {
        echo "Ошибка: Пользователь с таким email уже зарегистрирован.";
        echo "<br><a href='signup.html'>Попробовать другой email</a>";
        exit;
    }

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    try {
        $username = explode('@', $email)[0];
        $base_username = preg_replace('/[^a-zA-Z0-9_]/', '', $username);
        $username = $base_username ?: 'user'; 
        $counter = 1;
        
        while (true) {
            $check_username_sql = "SELECT COUNT(*) FROM users WHERE username = ?";
            $check_username_stmt = $pdo->prepare($check_username_sql);
            $check_username_stmt->execute([$username]);
            
            if ($check_username_stmt->fetchColumn() == 0) {
                break;
            }
            
            $username = $base_username . $counter;
            $counter++;
        }
        
        $sql = "INSERT INTO users (fullname, email, username, password, role) VALUES (?, ?, ?, ?, 'user')";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$fullname, $email, $username, $hashed_password]);
        
    } catch (PDOException $e) {
        try {
            $sql = "INSERT INTO users (fullname, email, password) VALUES (?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$fullname, $email, $hashed_password]);
        } catch (PDOException $e2) {
            echo "Ошибка регистрации: " . $e2->getMessage();
            exit;
        }
    }

    session_start();
    $_SESSION['user_id'] = $pdo->lastInsertId();
    $_SESSION['user_role'] = 'user';
    $_SESSION['user_email'] = $email;
    
    header("Location: user.php?success=" . urlencode("Регистрация прошла успешно! Добро пожаловать!"));
    exit();
}
?>
