<?php
session_start();
require_once 'config.php';

// Проверка аутентификации
if (!isset($_SESSION['user_id'])) {
    http_response_code(403);
    exit('Доступ запрещен');
}

// Получение параметров
$attachment_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$attachment_id) {
    http_response_code(400);
    exit('Неверные параметры');
}

try {
    // Получаем информацию о файле
    $sql = "SELECT a.*, i.user_id FROM idea_attachments a 
            JOIN ideas i ON a.idea_id = i.id 
            WHERE a.id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$attachment_id]);
    $attachment = $stmt->fetch();
    
    if (!$attachment) {
        http_response_code(404);
        exit('Файл не найден');
    }
    
    // Проверяем права доступа
    $user_id = $_SESSION['user_id'];
    $user_role = $_SESSION['user_role'] ?? 'user';
    
    // Админы могут скачивать все файлы, пользователи - только свои
    if ($user_role !== 'admin' && $attachment['user_id'] != $user_id) {
        http_response_code(403);
        exit('Доступ запрещен');
    }
    
    // Проверяем существование файла
    $file_path = '../' . $attachment['file_path'];
    
    if (!file_exists($file_path)) {
        http_response_code(404);
        exit('Файл не найден на сервере');
    }
    
    // Устанавливаем заголовки для скачивания
    header('Content-Type: application/octet-stream');
    header('Content-Disposition: attachment; filename="' . $attachment['original_name'] . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: private');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    // Выводим файл
    readfile($file_path);
    exit();
    
} catch (Exception $e) {
    error_log("Ошибка при скачивании файла: " . $e->getMessage());
    http_response_code(500);
    exit('Внутренняя ошибка сервера');
}
?>