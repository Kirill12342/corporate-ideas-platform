<?php
// Подключение к базе данных и проверка авторизации администратора
include 'config.php';
include 'admin_auth.php';

// Установка заголовка для JSON ответа
header('Content-Type: application/json');

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Разрешен только POST запрос']);
    exit;
}

// Получение данных из запроса
$input = json_decode(file_get_contents('php://input'), true);

// Проверка наличия ID идеи
if (!isset($input['idea_id']) || empty($input['idea_id'])) {
    echo json_encode(['success' => false, 'error' => 'ID идеи не предоставлен']);
    exit;
}

$ideaId = intval($input['idea_id']);

// Проверка существования идеи
try {
    $checkSql = "SELECT id, title FROM ideas WHERE id = :idea_id";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->bindParam(':idea_id', $ideaId, PDO::PARAM_INT);
    $checkStmt->execute();
    
    $idea = $checkStmt->fetch();
    
    if (!$idea) {
        echo json_encode(['success' => false, 'error' => 'Идея с указанным ID не найдена']);
        exit;
    }
    
    // Удаление идеи из базы данных
    $deleteSql = "DELETE FROM ideas WHERE id = :idea_id";
    $deleteStmt = $pdo->prepare($deleteSql);
    $deleteStmt->bindParam(':idea_id', $ideaId, PDO::PARAM_INT);
    
    if ($deleteStmt->execute()) {
        // Проверяем, что запись действительно была удалена
        if ($deleteStmt->rowCount() > 0) {
            echo json_encode([
                'success' => true, 
                'message' => 'Идея "' . htmlspecialchars($idea['title']) . '" успешно удалена',
                'deleted_id' => $ideaId
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Не удалось удалить идею']);
        }
    } else {
        echo json_encode(['success' => false, 'error' => 'Ошибка при выполнении запроса удаления']);
    }
    
} catch (PDOException $e) {
    error_log("Database error in delete_idea.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
}
?>