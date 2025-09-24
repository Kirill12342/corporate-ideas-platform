<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html?error=auth_required");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];
    
    if (empty($title) || empty($description) || empty($category)) {
        $error = "Все поля должны быть заполнены";
    } else if (strlen($title) > 255) {
        $error = "Заголовок слишком длинный";
    } else if (strlen($description) > 1000) {
        $error = "Описание слишком длинное";
    } else {
        try {
            $sql = "INSERT INTO ideas (user_id, title, description, category, status) VALUES (?, ?, ?, ?, 'На рассмотрении')";
            $stmt = $pdo->prepare($sql);
            
            if ($stmt->execute([$user_id, $title, $description, $category])) {
                $success = "Идея успешно отправлена!";
                
                $user_sql = "SELECT fullname, email FROM users WHERE id = ?";
                $user_stmt = $pdo->prepare($user_sql);
                $user_stmt->execute([$user_id]);
                $user = $user_stmt->fetch();
                
                header("Location: user.php?success=" . urlencode($success));
                exit();
            } else {
                $error = "Ошибка при сохранении идеи";
            }
        } catch (PDOException $e) {
            $error = "Ошибка базы данных: " . $e->getMessage();
        }
    }
    
    if (isset($error)) {
        header("Location: idea.html?error=" . urlencode($error));
        exit();
    }
} else {
    header("Location: idea.html");
    exit();
}
?>