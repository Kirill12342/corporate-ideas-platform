<?php
// Минимальная версия analytics API для тестирования
header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'config.php';
    
    // Простой тест - получим идеи из БД
    $stmt = $pdo->query("SELECT i.*, u.username FROM ideas i LEFT JOIN users u ON i.user_id = u.id ORDER BY i.created_at DESC LIMIT 5");
    $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    $html = '';
    if (empty($ideas)) {
        $html = '<tr><td colspan="7" style="text-align: center;">Нет идей в базе данных</td></tr>';
    } else {
        foreach ($ideas as $idea) {
            $title = htmlspecialchars($idea['title'] ?? $idea['idea'] ?? 'Без названия');
            $category = htmlspecialchars($idea['category'] ?? 'Без категории');
            $status = htmlspecialchars($idea['status'] ?? 'Без статуса');
            $username = htmlspecialchars($idea['username'] ?? 'Неизвестный');
            $date = date('d.m.Y', strtotime($idea['created_at'] ?? 'now'));
            
            $html .= "<tr>
                <td>{$idea['id']}</td>
                <td>{$title}</td>
                <td>{$category}</td>
                <td>{$status}</td>
                <td>{$username}</td>
                <td>{$date}</td>
                <td>Действия</td>
            </tr>";
        }
    }
    
    $response = [
        'success' => true,
        'html' => $html,
        'count' => count($ideas),
        'pagination' => [
            'currentPage' => 1,
            'totalPages' => 1,
            'total' => count($ideas)
        ]
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => $e->getMessage(),
        'file' => __FILE__,
        'line' => $e->getLine()
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>