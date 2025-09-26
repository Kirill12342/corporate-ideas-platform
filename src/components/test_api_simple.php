<?php
// Отключаем буферизацию вывода
ob_clean();

require_once 'config.php';

header('Content-Type: application/json');
header('Cache-Control: no-cache');

try {
    // Создаем экземпляр Analytics напрямую
    class Analytics {
        private $pdo;

        public function __construct($pdo) {
            $this->pdo = $pdo;
        }

        public function getIdeasTable($filters = [], $page = 1, $limit = 20) {
            try {
                $offset = ($page - 1) * $limit;

                // Построение WHERE условий
                $whereConditions = ['1=1'];
                $params = [];

                // Получение общего количества
                $countSql = "SELECT COUNT(*) FROM ideas WHERE " . implode(' AND ', $whereConditions);
                $countStmt = $this->pdo->prepare($countSql);
                $countStmt->execute($params);
                $total = $countStmt->fetchColumn();

                // Получение данных
                $sql = "SELECT i.*, u.username 
                        FROM ideas i 
                        LEFT JOIN users u ON i.user_id = u.id 
                        WHERE " . implode(' AND ', $whereConditions) . "
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
                        $date = date('d.m.Y', strtotime($idea['created_at'] ?? 'now'));

                        // Безопасное получение значений с проверкой на null
                        $ideaText = substr($idea['title'] ?? $idea['idea'] ?? 'Нет описания', 0, 50);
                        $category = $idea['category'] ?? 'Без категории';
                        $status = $idea['status'] ?? 'Неизвестно';
                        $username = $idea['username'] ?? 'Неизвестный пользователь';
                        $ideaId = $idea['id'] ?? 0;

                        $html .= "
                        <tr>
                            <td>{$ideaId}</td>
                            <td>{$ideaText}</td>
                            <td>{$category}</td>
                            <td><span class=\"status-badge\">{$status}</span></td>
                            <td>{$username}</td>
                            <td>{$date}</td>
                            <td>
                                <div class=\"table-actions\">
                                    <button class=\"action-icon-btn btn-view\" onclick=\"viewIdea({$ideaId})\">👁</button>
                                    <button class=\"action-icon-btn btn-edit\" onclick=\"editIdea({$ideaId})\">✏️</button>
                                    <button class=\"action-icon-btn btn-delete\" onclick=\"deleteIdea({$ideaId})\">🗑</button>
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
                    ],
                    'debug' => [
                        'sql' => $sql,
                        'ideas_count' => count($ideas),
                        'total_in_db' => $total
                    ]
                ];

            } catch (Exception $e) {
                return ['success' => false, 'error' => 'Ошибка получения данных: ' . $e->getMessage()];
            }
        }
    }

    $analytics = new components\Analytics($pdo);

    // Тестируем получение таблицы идей
    $result = $analytics->getIdeasTable([], 1, 10);

    echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
}
?>