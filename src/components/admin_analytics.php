<?php
require_once 'admin_auth.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'get_dashboard_stats':
                $period = $input['period'] ?? '30'; // дни
                $dateFrom = date('Y-m-d', strtotime("-$period days"));

                // Основная статистика
                $stats = [];

                // Общие показатели
                $stmt = $pdo->query("SELECT COUNT(*) FROM ideas");
                $stats['total_ideas'] = $stmt->fetchColumn();

                $stmt = $pdo->query("SELECT COUNT(*) FROM ideas WHERE status = 'В работе'");
                $stats['ideas_in_progress'] = $stmt->fetchColumn();

                $stmt = $pdo->query("SELECT COUNT(*) FROM ideas WHERE status = 'Принято'");
                $stats['ideas_approved'] = $stmt->fetchColumn();

                $stmt = $pdo->query("SELECT COUNT(DISTINCT user_id) FROM ideas WHERE created_at >= '$dateFrom'");
                $stats['active_users'] = $stmt->fetchColumn();

                $stmt = $pdo->query("SELECT COUNT(*) FROM users");
                $stats['total_users'] = $stmt->fetchColumn();

                // Динамика за период
                $stmt = $pdo->query("SELECT COUNT(*) FROM ideas WHERE created_at >= '$dateFrom'");
                $stats['new_ideas_period'] = $stmt->fetchColumn();

                $stmt = $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= '$dateFrom'");
                $stats['new_users_period'] = $stmt->fetchColumn();

                // Средние показатели
                $stmt = $pdo->query("SELECT AVG(likes_count) FROM ideas");
                $stats['avg_likes'] = round($stmt->fetchColumn(), 2);

                $stmt = $pdo->query("SELECT AVG(total_score) FROM ideas");
                $stats['avg_score'] = round($stmt->fetchColumn(), 2);

                echo json_encode(['success' => true, 'data' => $stats]);
                break;

            case 'get_ideas_timeline':
                $period = $input['period'] ?? '30';
                $groupBy = $input['group_by'] ?? 'day'; // day, week, month

                $dateFormat = $groupBy === 'day' ? '%Y-%m-%d' :
                             ($groupBy === 'week' ? '%Y-%u' : '%Y-%m');

                $stmt = $pdo->prepare("
                    SELECT 
                        DATE_FORMAT(created_at, '$dateFormat') as period,
                        COUNT(*) as count,
                        status
                    FROM ideas 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL $period DAY)
                    GROUP BY period, status
                    ORDER BY period ASC
                ");
                $stmt->execute();
                $timeline = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $timeline]);
                break;

            case 'get_categories_stats':
                $stmt = $pdo->query("
                    SELECT 
                        COALESCE(category, 'Без категории') as category,
                        COUNT(*) as count,
                        AVG(likes_count) as avg_likes,
                        AVG(total_score) as avg_score,
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
                        AVG(i.likes_count) as avg_likes,
                        COUNT(CASE WHEN i.status = 'Принято' THEN 1 END) as approved_count
                    FROM users u
                    LEFT JOIN ideas i ON u.id = i.user_id
                    GROUP BY u.department
                    HAVING ideas_count > 0
                    ORDER BY ideas_count DESC
                ");
                $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $departments]);
                break;

            case 'get_top_contributors':
                $limit = intval($input['limit'] ?? 10);

                $stmt = $pdo->prepare("
                    SELECT 
                        u.fullname,
                        u.department,
                        COUNT(i.id) as ideas_count,
                        SUM(i.likes_count) as total_likes,
                        SUM(i.total_score) as total_score,
                        COUNT(CASE WHEN i.status = 'Принято' THEN 1 END) as approved_ideas
                    FROM users u
                    JOIN ideas i ON u.id = i.user_id
                    GROUP BY u.id
                    ORDER BY total_score DESC, ideas_count DESC
                    LIMIT :limit
                ");
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->execute();
                $contributors = $stmt->fetchAll(PDO::FETCH_ASSOC);

                echo json_encode(['success' => true, 'data' => $contributors]);
                break;

            case 'get_engagement_metrics':
                // Метрики вовлеченности
                $stmt = $pdo->query("
                    SELECT 
                        AVG(CASE WHEN likes_count > 0 THEN 1 ELSE 0 END) * 100 as ideas_with_likes_percent,
                        AVG(likes_count) as avg_likes_per_idea,
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
                        AVG(DATEDIFF(updated_at, created_at)) as avg_response_days
                    FROM ideas 
                    WHERE status != 'На рассмотрении' 
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
                        AVG(likes_count) as avg_likes
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
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}
?>
