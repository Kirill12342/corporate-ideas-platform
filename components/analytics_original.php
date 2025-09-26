<?php
// Очищаем буферы и устанавливаем заголовки
if (ob_get_level()) {
    ob_clean();
}

require_once 'config.php';
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

class Analytics {
    private $pdo;
    
    public function __construct($pdo) {
        $this->pdo = $pdo;
    }
    
    // Получение основной статистики
    public function getStats($filters = []) {
        try {
            $stats = [];
            
            // Общее количество идей
            $stats['total_ideas'] = [
                'value' => $this->getTotalIdeas($filters),
                'change' => $this->getIdeasChange($filters)
            ];
            
            // Идеи в работе
            $stats['ideas_in_progress'] = [
                'value' => $this->getIdeasByStatus('В работе', $filters),
                'change' => $this->getStatusChange('В работе', $filters)
            ];
            
            // Принятые идеи
            $stats['ideas_approved'] = [
                'value' => $this->getIdeasByStatus('Принято', $filters),
                'change' => $this->getStatusChange('Принято', $filters)
            ];
            
            // Активные пользователи
            $stats['active_users'] = [
                'value' => $this->getActiveUsers($filters),
                'change' => $this->getUsersChange($filters)
            ];
            
            return $stats;
            
        } catch (Exception $e) {
            error_log("Ошибка получения статистики: " . $e->getMessage());
            return [];
        }
    }
    
    // Данные для графиков
    public function getChartsData($filters = []) {
        try {
            $data = [];
            
            // График по статусам
            $data['ideasByStatus'] = $this->getIdeasStatusChart($filters);
            
            // Временная динамика
            $data['ideasTimeline'] = $this->getIdeasTimeline($filters);
            
            // График по категориям
            $data['categoriesChart'] = $this->getCategoriesChart($filters);
            
            // Активность пользователей
            $data['userActivity'] = $this->getUserActivityChart($filters);
            
            return $data;
            
        } catch (Exception $e) {
            error_log("Ошибка получения данных для графиков: " . $e->getMessage());
            return [];
        }
    }
    
    // Получение списка идей с пагинацией
    public function getIdeasTable($filters = [], $page = 1, $limit = 20) {
        try {
            $offset = ($page - 1) * $limit;
            
            // Построение WHERE условий
            $whereConditions = ['1=1'];
            $params = [];
            
            if (!empty($filters['status'])) {
                if (is_array($filters['status'])) {
                    $placeholders = str_repeat('?,', count($filters['status']) - 1) . '?';
                    $whereConditions[] = "status IN ($placeholders)";
                    $params = array_merge($params, $filters['status']);
                } else {
                    $whereConditions[] = "status = ?";
                    $params[] = $filters['status'];
                }
            }
            
            if (!empty($filters['category'])) {
                if (is_array($filters['category'])) {
                    $placeholders = str_repeat('?,', count($filters['category']) - 1) . '?';
                    $whereConditions[] = "category IN ($placeholders)";
                    $params = array_merge($params, $filters['category']);
                } else {
                    $whereConditions[] = "category = ?";
                    $params[] = $filters['category'];
                }
            }
            
            if (!empty($filters['date_from'])) {
                $whereConditions[] = "DATE(created_at) >= ?";
                $params[] = $filters['date_from'];
            }
            
            if (!empty($filters['date_to'])) {
                $whereConditions[] = "DATE(created_at) <= ?";
                $params[] = $filters['date_to'];
            }
            
            if (!empty($filters['search'])) {
                $whereConditions[] = "(title LIKE ? OR idea LIKE ? OR description LIKE ?)";
                $params[] = '%' . $filters['search'] . '%';
                $params[] = '%' . $filters['search'] . '%';
                $params[] = '%' . $filters['search'] . '%';
            }
            
            $whereClause = implode(' AND ', $whereConditions);
            
            // Получение общего количества
            $countSql = "SELECT COUNT(*) FROM ideas WHERE $whereClause";
            $countStmt = $this->pdo->prepare($countSql);
            $countStmt->execute($params);
            $total = $countStmt->fetchColumn();
            
            // Получение данных
            $sql = "SELECT i.*, u.username 
                    FROM ideas i 
                    LEFT JOIN users u ON i.user_id = u.id 
                    WHERE $whereClause 
                    ORDER BY i.created_at DESC 
                    LIMIT $limit OFFSET $offset";
            
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            $ideas = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Генерация HTML для таблицы
            $html = '';
            if (empty($ideas)) {
                $html = '<tr><td colspan="7" style="text-align: center; color: #6c757d;">Идеи не найдены</td></tr>';
            } else {
                foreach ($ideas as $idea) {
                    $statusClass = $this->getStatusClass($idea['status'] ?? '');
                    $date = date('d.m.Y', strtotime($idea['created_at'] ?? 'now'));
                    
                    // Безопасное получение значений с проверкой на null
                    $ideaText = $this->truncateText($idea['title'] ?? $idea['idea'] ?? 'Нет описания', 50);
                    $category = $idea['category'] ?? 'Без категории';
                    $status = $idea['status'] ?? 'Неизвестно';
                    $username = $idea['username'] ?? 'Неизвестный пользователь';
                    $ideaId = $idea['id'] ?? 0;
                    
                    $html .= "
                    <tr>
                        <td>{$ideaId}</td>
                        <td>{$ideaText}</td>
                        <td>{$category}</td>
                        <td><span class=\"status-badge {$statusClass}\">{$status}</span></td>
                        <td>{$username}</td>
                        <td>{$date}</td>
                        <td>
                            <div class=\"table-actions\">
                                <button class=\"action-icon-btn btn-view\" onclick=\"viewIdea({$ideaId})\" title=\"Просмотр\">👁</button>
                                <button class=\"action-icon-btn btn-edit\" onclick=\"editIdea({$ideaId})\" title=\"Редактировать\">✏️</button>
                                <button class=\"action-icon-btn btn-delete\" onclick=\"deleteIdea({$ideaId})\" title=\"Удалить\">🗑</button>
                            </div>
                        </td>
                    </tr>";
                }
            }
            
            $totalPages = ceil($total / $limit);
            
            return [
                'success' => true,
                'html' => $html,
                'pagination' => [
                    'currentPage' => $page,
                    'totalPages' => $totalPages,
                    'total' => $total,
                    'start' => $offset + 1,
                    'end' => min($offset + $limit, $total)
                ]
            ];
            
        } catch (Exception $e) {
            error_log("Ошибка получения таблицы идей: " . $e->getMessage());
            return ['success' => false, 'error' => 'Ошибка получения данных'];
        }
    }
    
    // Приватные методы для вычислений
    private function getTotalIdeas($filters = []) {
        $sql = "SELECT COUNT(*) FROM ideas WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    private function getIdeasByStatus($status, $filters = []) {
        $sql = "SELECT COUNT(*) FROM ideas WHERE status = ?";
        $params = [$status];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }
    
    private function getIdeasChange($filters = []) {
        // Сравнение с предыдущим периодом
        $currentPeriod = $this->getTotalIdeas($filters);
        
        // Для примера, возвращаем случайное изменение
        // В реальном проекте здесь должна быть логика сравнения периодов
        return rand(-15, 25);
    }
    
    private function getStatusChange($status, $filters = []) {
        return rand(-10, 20);
    }
    
    private function getActiveUsers($filters = []) {
        $sql = "SELECT COUNT(DISTINCT user_id) FROM ideas WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        
        if (!empty($filters['date_from'])) {
            $sql = "SELECT COUNT(DISTINCT user_id) FROM ideas WHERE DATE(created_at) >= ?";
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute([$filters['date_from']]);
        } else {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute();
        }
        
        return $stmt->fetchColumn();
    }
    
    private function getUsersChange($filters = []) {
        return rand(5, 15);
    }
    
    private function getIdeasStatusChart($filters = []) {
        $sql = "SELECT status, COUNT(*) as count FROM ideas WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " GROUP BY status";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $data = [
            'pending' => 0,
            'approved' => 0,
            'inProgress' => 0,
            'rejected' => 0
        ];
        
        foreach ($results as $row) {
            switch ($row['status']) {
                case 'На рассмотрении':
                    $data['pending'] = (int)$row['count'];
                    break;
                case 'Принято':
                    $data['approved'] = (int)$row['count'];
                    break;
                case 'В работе':
                    $data['inProgress'] = (int)$row['count'];
                    break;
                case 'Отклонено':
                    $data['rejected'] = (int)$row['count'];
                    break;
            }
        }
        
        return $data;
    }
    
    private function getIdeasTimeline($filters = []) {
        $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                FROM ideas 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql = "SELECT DATE(created_at) as date, COUNT(*) as count 
                    FROM ideas 
                    WHERE DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
            
            if (!empty($filters['date_to'])) {
                $sql .= " AND DATE(created_at) <= ?";
                $params[] = $filters['date_to'];
            }
        }
        
        $sql .= " GROUP BY DATE(created_at) ORDER BY date";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $labels = [];
        $values = [];
        
        foreach ($results as $row) {
            $labels[] = date('d.m', strtotime($row['date']));
            $values[] = (int)$row['count'];
        }
        
        return [
            'labels' => $labels,
            'values' => $values
        ];
    }
    
    private function getCategoriesChart($filters = []) {
        $sql = "SELECT category, COUNT(*) as count FROM ideas WHERE 1=1";
        $params = [];
        
        if (!empty($filters['date_from'])) {
            $sql .= " AND DATE(created_at) >= ?";
            $params[] = $filters['date_from'];
        }
        
        if (!empty($filters['date_to'])) {
            $sql .= " AND DATE(created_at) <= ?";
            $params[] = $filters['date_to'];
        }
        
        $sql .= " GROUP BY category ORDER BY count DESC LIMIT 6";
        
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $labels = [];
        $values = [];
        
        foreach ($results as $row) {
            $labels[] = $row['category'];
            $values[] = (int)$row['count'];
        }
        
        return [
            'labels' => $labels,
            'values' => $values
        ];
    }
    
    private function getUserActivityChart($filters = []) {
        // Для демонстрации возвращаем статичные данные
        // В реальном проекте здесь должна быть логика получения активности пользователей
        return [
            'labels' => ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
            'activeUsers' => [45, 52, 48, 61, 58, 35, 28],
            'newRegistrations' => [2, 5, 3, 8, 6, 1, 1]
        ];
    }
    
    private function getStatusClass($status) {
        switch ($status) {
            case 'На рассмотрении':
                return 'status-pending';
            case 'Принято':
                return 'status-approved';
            case 'В работе':
                return 'status-inprogress';
            case 'Отклонено':
                return 'status-rejected';
            default:
                return 'status-pending';
        }
    }
    
    private function truncateText($text, $length) {
        if (mb_strlen($text) > $length) {
            return mb_substr($text, 0, $length) . '...';
        }
        return $text;
    }
}

// Основная логика
try {
    $analytics = new Analytics($pdo);
    
    $action = $_GET['action'] ?? $_POST['action'] ?? 'stats';
    $filters = [];
    
    // Получение фильтров
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $input = json_decode(file_get_contents('php://input'), true);
        if ($input) {
            $filters = $input;
        }
    } else {
        $filters = $_GET;
    }
    
    // Удаляем action из фильтров
    unset($filters['action']);
    
    switch ($action) {
        case 'stats':
            $response = [
                'success' => true,
                'data' => $analytics->getStats($filters)
            ];
            break;
            
        case 'charts':
            $response = [
                'success' => true,
                'data' => $analytics->getChartsData($filters)
            ];
            break;
            
        case 'ideas_table':
            $page = (int)($filters['page'] ?? 1);
            $limit = (int)($filters['limit'] ?? 20);
            
            // Удаляем служебные ключи из фильтров
            unset($filters['action'], $filters['page'], $filters['limit']);
            
            $response = $analytics->getIdeasTable($filters, $page, $limit);
            break;
            
        case 'export_data':
            // Данные для экспорта
            $response = [
                'success' => true,
                'data' => [
                    'stats' => $analytics->getStats($filters),
                    'charts' => $analytics->getChartsData($filters)
                ]
            ];
            break;
            
        default:
            $response = [
                'success' => false,
                'error' => 'Неизвестное действие'
            ];
    }
    
} catch (Exception $e) {
    error_log("Ошибка в analytics.php: " . $e->getMessage());
    $response = [
        'success' => false,
        'error' => 'Внутренняя ошибка сервера'
    ];
}

echo json_encode($response, JSON_UNESCAPED_UNICODE);
?>