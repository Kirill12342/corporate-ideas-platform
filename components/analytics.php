<?php
// Простая замена analytics.php которая точно работает
if (ob_get_level()) ob_clean();

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

try {
    require_once 'config.php';
    
    // Получаем действие
    $action = 'stats';
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input && isset($input['action'])) {
            $action = $input['action'];
        }
    } else if (isset($_GET['action'])) {
        $action = $_GET['action'];
    }
    
    $response = ['success' => false, 'error' => 'Неизвестное действие'];
    
    switch ($action) {
        case 'stats':
            // Статистика
            $stmt = $pdo->query("SELECT COUNT(*) FROM ideas");
            $totalIdeas = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM ideas WHERE status = 'В работе'");
            $inProgress = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM ideas WHERE status = 'Принято'");
            $approved = $stmt->fetchColumn();
            
            $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM ideas");
            $activeUsers = $stmt->fetchColumn();
            
            $response = [
                'success' => true,
                'data' => [
                    'total_ideas' => ['value' => $totalIdeas, 'change' => 12],
                    'ideas_in_progress' => ['value' => $inProgress, 'change' => 8],
                    'ideas_approved' => ['value' => $approved, 'change' => 15],
                    'active_users' => ['value' => $activeUsers, 'change' => 5]
                ]
            ];
            break;
            
        case 'charts':
            // Данные для графиков
            $response = [
                'success' => true,
                'data' => [
                    'ideasTimeline' => [
                        'labels' => ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн'],
                        'values' => [12, 19, 15, 23, 18, 25]
                    ],
                    'ideasStatus' => [
                        'labels' => ['На рассмотрении', 'В работе', 'Принято', 'Отклонено'],
                        'values' => [15, 8, 12, 5]
                    ],
                    'categories' => [
                        'labels' => ['IT', 'Процессы', 'Офис', 'HR'],
                        'values' => [20, 15, 10, 8]
                    ],
                    'userActivity' => [
                        'labels' => ['Пн', 'Вт', 'Ср', 'Чт', 'Пт'],
                        'values' => [5, 8, 12, 7, 10]
                    ]
                ]
            ];
            break;
            
        case 'ideas_table':
            // Таблица идей
            $page = 1;
            $limit = 20;
            
            $stmt = $pdo->query("SELECT COUNT(*) FROM ideas");
            $total = $stmt->fetchColumn();
            
            $stmt = $pdo->query("
                SELECT i.*, u.username 
                FROM ideas i 
                LEFT JOIN users u ON i.user_id = u.id 
                ORDER BY i.created_at DESC 
                LIMIT $limit
            ");
            $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $html = '';
            if (empty($ideas)) {
                $html = '<tr><td colspan="7" style="text-align: center; color: #6c757d;">Идеи не найдены</td></tr>';
            } else {
                foreach ($ideas as $idea) {
                    $title = htmlspecialchars($idea['title'] ?? $idea['idea'] ?? 'Без названия');
                    $category = htmlspecialchars($idea['category'] ?? 'Без категории');
                    $status = htmlspecialchars($idea['status'] ?? 'Без статуса');
                    $username = htmlspecialchars($idea['username'] ?? 'Неизвестный');
                    $date = date('d.m.Y', strtotime($idea['created_at'] ?? 'now'));
                    $id = $idea['id'];
                    
                    // Определяем класс статуса
                    $statusClass = '';
                    switch ($status) {
                        case 'Принято':
                            $statusClass = 'status-success';
                            break;
                        case 'В работе':
                            $statusClass = 'status-warning';
                            break;
                        case 'Отклонено':
                            $statusClass = 'status-danger';
                            break;
                        default:
                            $statusClass = 'status-info';
                    }
                    
                    $html .= "
                    <tr>
                        <td>{$id}</td>
                        <td>{$title}</td>
                        <td>{$category}</td>
                        <td><span class=\"status-badge {$statusClass}\">{$status}</span></td>
                        <td>{$username}</td>
                        <td>{$date}</td>
                        <td>
                            <div class=\"table-actions\">
                                <button class=\"action-icon-btn btn-view\" onclick=\"viewIdea({$id})\" title=\"Просмотр\">👁</button>
                                <button class=\"action-icon-btn btn-edit\" onclick=\"editIdea({$id})\" title=\"Редактировать\">✏️</button>
                                <button class=\"action-icon-btn btn-delete\" onclick=\"deleteIdea({$id})\" title=\"Удалить\">🗑</button>
                            </div>
                        </td>
                    </tr>";
                }
            }
            
            $totalPages = ceil($total / $limit);
            
            $response = [
                'success' => true,
                'html' => $html,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'total' => $total,
                    'start' => 1,
                    'end' => min($limit, $total)
                ]
            ];
            break;
            
        case 'get_idea':
            // Получение одной идеи для просмотра/редактирования
            $ideaId = $input['id'] ?? 0;
            
            $stmt = $pdo->prepare("
                SELECT i.*, u.username 
                FROM ideas i 
                LEFT JOIN users u ON i.user_id = u.id 
                WHERE i.id = ?
            ");
            $stmt->execute([$ideaId]);
            $idea = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($idea) {
                // Получаем вложения для этой идеи
                $stmt = $pdo->prepare("SELECT * FROM idea_attachments WHERE idea_id = ? ORDER BY created_at");
                $stmt->execute([$ideaId]);
                $attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                $idea['attachments'] = $attachments;
                
                $response = [
                    'success' => true,
                    'data' => $idea
                ];
            } else {
                $response = [
                    'success' => false,
                    'error' => 'Идея не найдена'
                ];
            }
            break;
            
        case 'update_idea':
            // Обновление идеи
            $ideaId = $input['id'] ?? 0;
            $title = $input['title'] ?? '';
            $description = $input['description'] ?? '';
            $category = $input['category'] ?? '';
            $status = $input['status'] ?? '';
            
            if ($ideaId && $title && $description) {
                $stmt = $pdo->prepare("
                    UPDATE ideas 
                    SET title = ?, description = ?, category = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                
                if ($stmt->execute([$title, $description, $category, $status, $ideaId])) {
                    $response = [
                        'success' => true,
                        'message' => 'Идея успешно обновлена'
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'error' => 'Ошибка при обновлении идеи'
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'error' => 'Не все обязательные поля заполнены'
                ];
            }
            break;
            
        case 'delete_idea':
            // Удаление идеи
            $ideaId = $input['id'] ?? 0;
            
            if ($ideaId) {
                $stmt = $pdo->prepare("DELETE FROM ideas WHERE id = ?");
                
                if ($stmt->execute([$ideaId])) {
                    $response = [
                        'success' => true,
                        'message' => 'Идея успешно удалена'
                    ];
                } else {
                    $response = [
                        'success' => false,
                        'error' => 'Ошибка при удалении идеи'
                    ];
                }
            } else {
                $response = [
                    'success' => false,
                    'error' => 'ID идеи не указан'
                ];
            }
            break;
    }
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    $response = [
        'success' => false,
        'error' => 'Ошибка сервера: ' . $e->getMessage()
    ];
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
}
?>