<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id']) || $_SESSION['user_role'] !== 'admin') {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Доступ запрещен']);
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $input = json_decode(file_get_contents('php://input'), true);

    $idea_id = $input['idea_id'] ?? null;
    $status = $input['status'] ?? null;
    $admin_notes = $input['admin_notes'] ?? '';

    if (!$idea_id || !$status) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Недостаточно данных']);
        exit();
    }

    $valid_statuses = ['На рассмотрении', 'Принято', 'В работе', 'Отклонено'];
    if (!in_array($status, $valid_statuses)) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Некорректный статус']);
        exit();
    }

    try {
        $sql = "UPDATE ideas SET status = ?, admin_notes = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt = $pdo->prepare($sql);

        if ($stmt->execute([$status, $admin_notes, $idea_id])) {
            $select_sql = "SELECT i.*, u.fullname, u.email FROM ideas i JOIN users u ON i.user_id = u.id WHERE i.id = ?";
            $select_stmt = $pdo->prepare($select_sql);
            $select_stmt->execute([$idea_id]);
            $updated_idea = $select_stmt->fetch();

            echo json_encode([
                'success' => true,
                'message' => 'Статус успешно обновлен',
                'idea' => $updated_idea
            ]);
        } else {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Ошибка при обновлении']);
        }
    } catch (PDOException $e) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Ошибка базы данных: ' . $e->getMessage()]);
    }
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}
?>