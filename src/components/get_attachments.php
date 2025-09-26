<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Проверка аутентификации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit();
}

// Получение параметров
$idea_id = isset($_GET['idea_id']) ? (int)$_GET['idea_id'] : 0;

if (!$idea_id) {
    echo json_encode(['success' => false, 'message' => 'Неверные параметры']);
    exit();
}

try {
    // Проверяем права доступа
    $user_role = $_SESSION['role'] ?? 'user';
    $user_id = $_SESSION['user_id'];

    if ($user_role !== 'admin') {
        // Пользователи могут видеть только свои файлы
        $checkSql = "SELECT user_id FROM ideas WHERE id = ?";
        $checkStmt = $pdo->prepare($checkSql);
        $checkStmt->execute([$idea_id]);
        $idea = $checkStmt->fetch();

        if (!$idea || $idea['user_id'] != $user_id) {
            echo json_encode(['success' => false, 'message' => 'Доступ запрещен']);
            exit();
        }
    }

    // Получаем файлы
    $sql = "SELECT * FROM idea_attachments WHERE idea_id = ? ORDER BY upload_date ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$idea_id]);
    $attachments = $stmt->fetchAll();

    echo json_encode([
        'success' => true,
        'attachments' => $attachments
    ]);

} catch (Exception $e) {
    error_log("Ошибка при получении вложений: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Внутренняя ошибка сервера']);
}
?>