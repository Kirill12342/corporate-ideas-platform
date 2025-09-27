<?php
require_once 'admin_auth.php';
require_once 'config.php';

// Проверяем, что подключение к БД установлено
if (!isset($pdo) || !$pdo) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Нет подключения к базе данных']);
    exit();
}

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'get_dashboard_stats':
                $period = intval($input['period'] ?? 30); // дни

                // Основная статистика
                $stats = [];

                // Общие показатели
                $stmt = $pdo->query("SELECT COUNT(*) FROM ideas");
                $stats['total_ideas'] = intval($stmt->fetchColumn());

                $stmt = $pdo->query("SELECT COUNT(*) FROM ideas WHERE status = 'В работе'");
                $stats['ideas_in_progress'] = intval($stmt->fetchColumn());

                $stmt = $pdo->query("SELECT COUNT(*) FROM ideas WHERE status = 'Принято'");
                $stats['ideas_approved'] = intval($stmt->fetchColumn());

                $stmt = $pdo->prepare("SELECT COUNT(DISTINCT user_id) FROM ideas WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$period]);
                $stats['active_users'] = intval($stmt->fetchColumn());

                $stmt = $pdo->query("SELECT COUNT(*) FROM users");
                $stats['total_users'] = intval($stmt->fetchColumn());

                // Динамика за период
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM ideas WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$period]);
                $stats['new_ideas_period'] = intval($stmt->fetchColumn());

                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)");
                $stmt->execute([$period]);
                $stats['new_users_period'] = intval($stmt->fetchColumn());

                // Средние показатели
                $stmt = $pdo->query("SELECT AVG(likes_count) FROM ideas WHERE likes_count IS NOT NULL");
                $avgLikes = $stmt->fetchColumn();
                if ($avgLikes !== false && $avgLikes !== null && is_numeric($avgLikes)) {
                    $stats['avg_likes'] = number_format((float)$avgLikes, 2, '.', '');
                } else {
                    $stats['avg_likes'] = '0.00';
                }

                $stmt = $pdo->query("SELECT AVG(total_score) FROM ideas WHERE total_score IS NOT NULL");
                $avgScore = $stmt->fetchColumn();
                if ($avgScore !== false && $avgScore !== null && is_numeric($avgScore)) {
                    $stats['avg_score'] = number_format((float)$avgScore, 2, '.', '');
                } else {
                    $stats['avg_score'] = '0.00';
                }

                echo json_encode(['success' => true, 'data' => $stats]);
                break;

            case 'get_ideas_timeline':
                $period = intval($input['period'] ?? 30);
                $groupBy = in_array($input['group_by'] ?? 'day', ['day', 'week', 'month']) ? $input['group_by'] : 'day';

                $dateFormat = $groupBy === 'day' ? '%Y-%m-%d' :
                             ($groupBy === 'week' ? '%Y-%u' : '%Y-%m');

                $stmt = $pdo->prepare("
                    SELECT 
                        DATE_FORMAT(created_at, ?) as period,
                        COUNT(*) as count,
                        status
                    FROM ideas 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL ? DAY)
                    GROUP BY period, status
                    ORDER BY period ASC
                ");
                $stmt->execute([$dateFormat, $period]);
                $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $timeline]);
                break;

            case 'get_categories_stats':
                $stmt = $pdo->query("
                    SELECT 
                        COALESCE(category, 'Без категории') as category,
                        COUNT(*) as count,
                        COALESCE(AVG(likes_count), 0) as avg_likes,
                        COALESCE(AVG(total_score), 0) as avg_score,
                        COUNT(CASE WHEN status = 'Принято' THEN 1 END) as approved_count
                    FROM ideas 
                    GROUP BY category
                    ORDER BY count DESC
                ");
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $categories]);
                break;

            case 'get_departments_stats':
                $stmt = $pdo->query("
                    SELECT 
                        COALESCE(u.department, 'Не указан') as department,
                        COUNT(i.id) as ideas_count,
                        COUNT(DISTINCT i.user_id) as active_users,
                        COALESCE(AVG(i.likes_count), 0) as avg_likes,
                        COUNT(CASE WHEN i.status = 'Принято' THEN 1 END) as approved_count
                    FROM users u
                    LEFT JOIN ideas i ON u.id = i.user_id
                    GROUP BY COALESCE(u.department, 'Не указан')
                    HAVING ideas_count > 0
                    ORDER BY ideas_count DESC
                ");
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $departments]);
                break;

            case 'get_top_contributors':
                $limit = max(1, min(100, intval($input['limit'] ?? 10))); // Ограничиваем от 1 до 100

                $stmt = $pdo->prepare("
                    SELECT 
                        u.fullname,
                        u.department,
                        COUNT(i.id) as ideas_count,
                        COALESCE(SUM(i.likes_count), 0) as total_likes,
                        COALESCE(SUM(i.total_score), 0) as total_score,
                        COUNT(CASE WHEN i.status = 'Принято' THEN 1 END) as approved_ideas
                    FROM users u
                    INNER JOIN ideas i ON u.id = i.user_id
                    GROUP BY u.id, u.fullname, u.department
                    ORDER BY total_score DESC, ideas_count DESC
                    LIMIT ?
                ");
                $stmt->execute([$limit]);
                $contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $contributors]);
                break;

            case 'get_engagement_metrics':
                // Метрики вовлеченности
                $stmt = $pdo->query("
                    SELECT 
                        COALESCE(AVG(CASE WHEN likes_count > 0 THEN 1 ELSE 0 END) * 100, 0) as ideas_with_likes_percent,
                        COALESCE(AVG(likes_count), 0) as avg_likes_per_idea,
                        COUNT(DISTINCT user_id) as unique_contributors,
                        COUNT(*) as total_ideas,
                        COUNT(DISTINCT DATE(created_at)) as active_days_last_30
                    FROM ideas 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ");
                $engagement = $stmt->fetch(PDO::FETCH_ASSOC);

                // Время отклика на идеи
                $stmt = $pdo->query("
                    SELECT 
                        COALESCE(AVG(DATEDIFF(updated_at, created_at)), 0) as avg_response_days
                    FROM ideas 
                    WHERE status != 'На рассмотрении' 
                    AND updated_at IS NOT NULL
                    AND updated_at > created_at
                    AND created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
                ");
                $responseTime = $stmt->fetch(PDO::FETCH_ASSOC);

                $engagement['avg_response_days'] = $responseTime['avg_response_days'] ?? 0;

                echo json_encode(['success' => true, 'data' => $engagement]);
                break;

            case 'get_status_distribution':
                $stmt = $pdo->query("
                    SELECT 
                        status,
                        COUNT(*) as count,
                        ROUND(COUNT(*) * 100.0 / (SELECT COUNT(*) FROM ideas), 2) as percentage
                    FROM ideas 
                    GROUP BY status
                    ORDER BY count DESC
                ");
                $distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $distribution]);
                break;

            case 'get_ideas_by_rating':
                $stmt = $pdo->query("
                    SELECT 
                        CASE 
                            WHEN total_score >= 10 THEN 'Высокий рейтинг (10+)'
                            WHEN total_score >= 5 THEN 'Средний рейтинг (5-9)'
                            WHEN total_score >= 1 THEN 'Низкий рейтинг (1-4)'
                            ELSE 'Без рейтинга (0)'
                        END as rating_category,
                        COUNT(*) as count
                    FROM ideas
                    GROUP BY rating_category
                    ORDER BY 
                        CASE rating_category
                            WHEN 'Высокий рейтинг (10+)' THEN 1
                            WHEN 'Средний рейтинг (5-9)' THEN 2
                            WHEN 'Низкий рейтинг (1-4)' THEN 3
                            ELSE 4
                        END
                ");
                $rating = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $rating]);
                break;

            case 'get_monthly_trends':
                $stmt = $pdo->query("
                    SELECT 
                        DATE_FORMAT(created_at, '%Y-%m') as month,
                        COUNT(*) as total_ideas,
                        COUNT(CASE WHEN status = 'Принято' THEN 1 END) as approved_ideas,
                        COUNT(DISTINCT user_id) as active_users,
                        COALESCE(AVG(likes_count), 0) as avg_likes
                    FROM ideas 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                    GROUP BY month
                    ORDER BY month ASC
                ");
                $trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $trends]);
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
        }
    } catch (PDOException $e) {
        error_log("Database error in admin_analytics.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Ошибка базы данных']);
    } catch (Exception $e) {
        error_log("Error in admin_analytics.php: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Внутренняя ошибка сервера']);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}
?>
