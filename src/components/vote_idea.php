<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Разрешен только POST запрос']);
    exit();
}

// Получение данных
$input = json_decode(file_get_contents('php://input'), true);

$idea_id = isset($input['idea_id']) ? (int)$input['idea_id'] : 0;
$vote_type = isset($input['vote_type']) ? $input['vote_type'] : '';
$user_id = $_SESSION['user_id'];

// Валидация
if (!$idea_id) {
    echo json_encode(['success' => false, 'message' => 'ID идеи не указан']);
    exit();
}

if (!in_array($vote_type, ['like', 'dislike', 'remove'])) {
    echo json_encode(['success' => false, 'message' => 'Неверный тип голоса']);
    exit();
}

try {
    // Проверяем, существует ли идея
    $checkSql = "SELECT id, user_id, title FROM ideas WHERE id = ?";
    $checkStmt = $pdo->prepare($checkSql);
    $checkStmt->execute([$idea_id]);
    $idea = $checkStmt->fetch();
    
    if (!$idea) {
        echo json_encode(['success' => false, 'message' => 'Идея не найдена']);
        exit();
    }
    
    // Проверяем, не голосует ли пользователь за свою собственную идею
    if ($idea['user_id'] == $user_id) {
        echo json_encode(['success' => false, 'message' => 'Нельзя голосовать за собственную идею']);
        exit();
    }
    
    // Проверяем существующий голос
    $existingVoteSql = "SELECT vote_type FROM idea_votes WHERE idea_id = ? AND user_id = ?";
    $existingVoteStmt = $pdo->prepare($existingVoteSql);
    $existingVoteStmt->execute([$idea_id, $user_id]);
    $existingVote = $existingVoteStmt->fetch();
    
    // Начинаем транзакцию
    $pdo->beginTransaction();
    
    if ($vote_type === 'remove') {
        // Удаляем голос
        if ($existingVote) {
            $deleteSql = "DELETE FROM idea_votes WHERE idea_id = ? AND user_id = ?";
            $deleteStmt = $pdo->prepare($deleteSql);
            $deleteStmt->execute([$idea_id, $user_id]);
            $message = 'Голос отменен';
        } else {
            $message = 'Голос не найден';
        }
    } else {
        if ($existingVote) {
            if ($existingVote['vote_type'] === $vote_type) {
                // Если пользователь нажал на тот же тип голоса - удаляем голос
                $deleteSql = "DELETE FROM idea_votes WHERE idea_id = ? AND user_id = ?";
                $deleteStmt = $pdo->prepare($deleteSql);
                $deleteStmt->execute([$idea_id, $user_id]);
                $message = 'Голос отменен';
            } else {
                // Изменяем тип голоса
                $updateSql = "UPDATE idea_votes SET vote_type = ?, updated_at = CURRENT_TIMESTAMP WHERE idea_id = ? AND user_id = ?";
                $updateStmt = $pdo->prepare($updateSql);
                $updateStmt->execute([$vote_type, $idea_id, $user_id]);
                $message = $vote_type === 'like' ? 'Поставлен лайк' : 'Поставлен дизлайк';
            }
        } else {
            // Добавляем новый голос
            $insertSql = "INSERT INTO idea_votes (idea_id, user_id, vote_type) VALUES (?, ?, ?)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute([$idea_id, $user_id, $vote_type]);
            $message = $vote_type === 'like' ? 'Поставлен лайк' : 'Поставлен дизлайк';
        }
    }
    
    // Получаем обновленную статистику
    $statsSql = "SELECT likes_count, dislikes_count, total_score, popularity_rank FROM ideas WHERE id = ?";
    $statsStmt = $pdo->prepare($statsSql);
    $statsStmt->execute([$idea_id]);
    $stats = $statsStmt->fetch();
    
    // Получаем текущий голос пользователя
    $currentVoteSql = "SELECT vote_type FROM idea_votes WHERE idea_id = ? AND user_id = ?";
    $currentVoteStmt = $pdo->prepare($currentVoteSql);
    $currentVoteStmt->execute([$idea_id, $user_id]);
    $currentVote = $currentVoteStmt->fetch();
    
    $pdo->commit();
    
    echo json_encode([
        'success' => true,
        'message' => $message,
        'stats' => [
            'likes_count' => (int)$stats['likes_count'],
            'dislikes_count' => (int)$stats['dislikes_count'],
            'total_score' => (int)$stats['total_score'],
            'popularity_rank' => (float)$stats['popularity_rank']
        ],
        'user_vote' => $currentVote ? $currentVote['vote_type'] : null
    ]);
    
} catch (PDOException $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    $pdo->rollBack();
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Ошибка сервера: ' . $e->getMessage()
    ]);
}
?>