<?php
// Контроллер для системы репутации и рейтингов

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/response.php';

class ReputationController
{
    private $db;

    public function __construct()
    {
        $this->db = APIDatabase::getConnection();
    }

    // Получить информацию о репутации пользователя
    public function getUserReputation()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $target_user_id = $_GET['user_id'] ?? ($user['user_id'] ?? $user['id']);
            
            // Получаем данные репутации
            $sql = "
                SELECT 
                    ur.*,
                    u.username,
                    u.email,
                    u.avatar_url,
                    u.department,
                    u.position,
                    u.created_at as user_since,
                    -- Рассчитываем текущий уровень и прогресс
                    FLOOR(ur.total_points / 100) + 1 as current_level,
                    ur.total_points % 100 as level_progress,
                    100 as points_to_next_level,
                    -- Статистика за последние 30 дней
                    COALESCE(recent_stats.points_last_30d, 0) as points_last_30d,
                    COALESCE(recent_stats.ideas_last_30d, 0) as ideas_last_30d,
                    COALESCE(recent_stats.comments_last_30d, 0) as comments_last_30d
                FROM user_reputation ur
                JOIN users u ON ur.user_id = u.id
                LEFT JOIN (
                    SELECT 
                        user_id,
                        SUM(points) as points_last_30d,
                        SUM(CASE WHEN action_type = 'idea_created' THEN 1 ELSE 0 END) as ideas_last_30d,
                        SUM(CASE WHEN action_type = 'comment_created' THEN 1 ELSE 0 END) as comments_last_30d
                    FROM reputation_history 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY user_id
                ) recent_stats ON ur.user_id = recent_stats.user_id
                WHERE ur.user_id = ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$target_user_id]);
            $reputation = $stmt->fetch();

            if (!$reputation) {
                // Создаем запись репутации если не существует
                $this->initializeUserReputation($target_user_id);
                $stmt->execute([$target_user_id]);
                $reputation = $stmt->fetch();
            }

            // Получаем достижения пользователя
            $achievementsStmt = $this->db->prepare("
                SELECT a.*, ua.unlocked_at 
                FROM user_achievements ua
                JOIN achievements a ON ua.achievement_id = a.id
                WHERE ua.user_id = ?
                ORDER BY ua.unlocked_at DESC
            ");
            $achievementsStmt->execute([$target_user_id]);
            $achievements = $achievementsStmt->fetchAll();

            // Получаем рейтинг пользователя среди всех
            $rankStmt = $this->db->prepare("
                SELECT COUNT(*) + 1 as rank
                FROM user_reputation 
                WHERE total_points > (SELECT total_points FROM user_reputation WHERE user_id = ?)
            ");
            $rankStmt->execute([$target_user_id]);
            $rank = $rankStmt->fetchColumn();

            // Получаем историю репутации за последние 30 дней
            $historyStmt = $this->db->prepare("
                SELECT 
                    action_type,
                    points,
                    description,
                    created_at,
                    related_type,
                    related_id
                FROM reputation_history 
                WHERE user_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                ORDER BY created_at DESC
                LIMIT 20
            ");
            $historyStmt->execute([$target_user_id]);
            $history = $historyStmt->fetchAll();

            // Форматируем данные
            $reputation['user_id'] = (int)$reputation['user_id'];
            $reputation['total_points'] = (int)$reputation['total_points'];
            $reputation['level'] = (int)$reputation['level'];
            $reputation['ideas_count'] = (int)$reputation['ideas_count'];
            $reputation['approved_ideas'] = (int)$reputation['approved_ideas'];
            $reputation['comments_count'] = (int)$reputation['comments_count'];
            $reputation['likes_received'] = (int)$reputation['likes_received'];
            $reputation['challenges_completed'] = (int)$reputation['challenges_completed'];
            $reputation['current_level'] = (int)$reputation['current_level'];
            $reputation['level_progress'] = (int)$reputation['level_progress'];
            $reputation['points_to_next_level'] = (int)$reputation['points_to_next_level'];
            $reputation['points_last_30d'] = (int)$reputation['points_last_30d'];
            $reputation['ideas_last_30d'] = (int)$reputation['ideas_last_30d'];
            $reputation['comments_last_30d'] = (int)$reputation['comments_last_30d'];
            $reputation['rank'] = (int)$rank;

            // Скрываем email для приватности
            unset($reputation['email']);

            Response::success([
                'reputation' => $reputation,
                'achievements' => $achievements,
                'recent_history' => $history
            ]);

        } catch (PDOException $e) {
            error_log("Get User Reputation API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении данных репутации', [], 500);
        }
    }

    // Получить топ пользователей по репутации
    public function getLeaderboard()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Параметры
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $period = $_GET['period'] ?? 'all'; // all, month, week
            $department = $_GET['department'] ?? '';

            $whereClause = "WHERE 1=1";
            $params = [];

            if ($department) {
                $whereClause .= " AND u.department = ?";
                $params[] = $department;
            }

            $orderBy = "ORDER BY ur.total_points DESC, ur.updated_at ASC";
            
            if ($period === 'month') {
                $orderBy = "ORDER BY recent_points DESC, ur.total_points DESC";
            } elseif ($period === 'week') {
                $orderBy = "ORDER BY week_points DESC, ur.total_points DESC";
            }

            $sql = "
                SELECT 
                    ur.user_id,
                    u.username,
                    u.avatar_url,
                    u.department,
                    u.position,
                    ur.total_points,
                    ur.level,
                    ur.ideas_count,
                    ur.approved_ideas,
                    ur.comments_count,
                    ur.likes_received,
                    ur.challenges_completed,
                    COALESCE(month_stats.points, 0) as recent_points,
                    COALESCE(week_stats.points, 0) as week_points,
                    -- Подсчитываем рейтинг
                    ROW_NUMBER() OVER ($orderBy) as rank
                FROM user_reputation ur
                JOIN users u ON ur.user_id = u.id
                LEFT JOIN (
                    SELECT user_id, SUM(points) as points
                    FROM reputation_history 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    GROUP BY user_id
                ) month_stats ON ur.user_id = month_stats.user_id
                LEFT JOIN (
                    SELECT user_id, SUM(points) as points
                    FROM reputation_history 
                    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                    GROUP BY user_id
                ) week_stats ON ur.user_id = week_stats.user_id
                $whereClause
                $orderBy
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $leaders = $stmt->fetchAll();

            // Подсчитываем общее количество
            $countSql = "SELECT COUNT(*) FROM user_reputation ur JOIN users u ON ur.user_id = u.id $whereClause";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetchColumn();

            // Форматируем данные
            foreach ($leaders as &$leader) {
                $leader['user_id'] = (int)$leader['user_id'];
                $leader['total_points'] = (int)$leader['total_points'];
                $leader['level'] = (int)$leader['level'];
                $leader['ideas_count'] = (int)$leader['ideas_count'];
                $leader['approved_ideas'] = (int)$leader['approved_ideas'];
                $leader['comments_count'] = (int)$leader['comments_count'];
                $leader['likes_received'] = (int)$leader['likes_received'];
                $leader['challenges_completed'] = (int)$leader['challenges_completed'];
                $leader['recent_points'] = (int)$leader['recent_points'];
                $leader['week_points'] = (int)$leader['week_points'];
                $leader['rank'] = (int)$leader['rank'];
            }

            Response::success([
                'leaders' => $leaders,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ],
                'period' => $period
            ]);

        } catch (PDOException $e) {
            error_log("Get Leaderboard API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении рейтинга', [], 500);
        }
    }

    // Получить статистику по репутации
    public function getReputationStats()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Общая статистика
            $generalStats = $this->db->query("
                SELECT 
                    COUNT(*) as total_users,
                    AVG(total_points) as avg_points,
                    MAX(total_points) as max_points,
                    SUM(total_points) as total_points_distributed
                FROM user_reputation
            ")->fetch();

            // Распределение по уровням
            $levelDistribution = $this->db->query("
                SELECT 
                    level,
                    COUNT(*) as users_count,
                    MIN(total_points) as min_points,
                    MAX(total_points) as max_points
                FROM user_reputation 
                GROUP BY level 
                ORDER BY level
            ")->fetchAll();

            // Статистика активности за последние 30 дней
            $activityStats = $this->db->query("
                SELECT 
                    action_type,
                    COUNT(*) as count,
                    SUM(points) as total_points
                FROM reputation_history 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY action_type
                ORDER BY total_points DESC
            ")->fetchAll();

            // Топ департаментов
            $departmentStats = $this->db->query("
                SELECT 
                    u.department,
                    COUNT(*) as users_count,
                    AVG(ur.total_points) as avg_points,
                    SUM(ur.total_points) as total_points
                FROM user_reputation ur
                JOIN users u ON ur.user_id = u.id
                WHERE u.department IS NOT NULL
                GROUP BY u.department
                ORDER BY avg_points DESC
                LIMIT 10
            ")->fetchAll();

            Response::success([
                'general' => $generalStats,
                'level_distribution' => $levelDistribution,
                'activity_stats' => $activityStats,
                'department_stats' => $departmentStats
            ]);

        } catch (PDOException $e) {
            error_log("Get Reputation Stats API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении статистики', [], 500);
        }
    }

    // Награды за активность (для администраторов)
    public function awardPoints()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user || ($user['role'] ?? 'user') !== 'admin') {
                Response::error('FORBIDDEN', 'Нет прав для награждения баллов');
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            $required_fields = ['user_id', 'points', 'description'];
            foreach ($required_fields as $field) {
                if (!isset($input[$field])) {
                    Response::error('VALIDATION_ERROR', "Поле $field обязательно");
                    return;
                }
            }

            $target_user_id = (int)$input['user_id'];
            $points = (int)$input['points'];
            $description = trim($input['description']);

            if ($points < -1000 || $points > 1000) {
                Response::error('VALIDATION_ERROR', 'Количество баллов должно быть от -1000 до 1000');
                return;
            }

            if (strlen($description) < 5) {
                Response::error('VALIDATION_ERROR', 'Описание должно содержать минимум 5 символов');
                return;
            }

            // Проверяем существование пользователя
            $userCheck = $this->db->prepare("SELECT id, username FROM users WHERE id = ?");
            $userCheck->execute([$target_user_id]);
            $targetUser = $userCheck->fetch();

            if (!$targetUser) {
                Response::error('NOT_FOUND', 'Пользователь не найден');
                return;
            }

            $this->db->beginTransaction();

            // Обновляем репутацию
            $this->updateUserReputation($target_user_id, 'admin_adjustment', $points, null, null, $description);

            // Создаем уведомление
            $adminId = $user['user_id'] ?? $user['id'];
            $notificationTitle = $points > 0 ? 'Награждение баллами' : 'Списание баллов';
            $notificationMessage = "Администратор " . ($points > 0 ? 'наградил' : 'списал') . " вам $points баллов. Причина: $description";
            
            $this->createNotification($target_user_id, 'reputation_change', $notificationTitle, $notificationMessage, null, null, $adminId);

            $this->db->commit();

            Response::success([
                'user_id' => $target_user_id,
                'username' => $targetUser['username'],
                'points_awarded' => $points,
                'description' => $description
            ], 'Баллы успешно ' . ($points > 0 ? 'начислены' : 'списаны'));

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Award Points API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при начислении баллов', [], 500);
        }
    }

    // Получить историю репутации пользователя
    public function getReputationHistory()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $target_user_id = $_GET['user_id'] ?? ($user['user_id'] ?? $user['id']);
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            // Проверяем права доступа
            $currentUserId = $user['user_id'] ?? $user['id'];
            $userRole = $user['role'] ?? 'user';
            
            if ($target_user_id != $currentUserId && $userRole !== 'admin') {
                Response::error('FORBIDDEN', 'Нет прав для просмотра истории репутации другого пользователя');
                return;
            }

            $sql = "
                SELECT 
                    rh.*,
                    -- Дополнительная информация в зависимости от типа связанного объекта
                    CASE 
                        WHEN rh.related_type = 'idea' THEN i.title
                        WHEN rh.related_type = 'comment' THEN SUBSTR(c.content, 1, 100)
                        WHEN rh.related_type = 'challenge' THEN ch.title
                        WHEN rh.related_type = 'achievement' THEN a.title
                        ELSE NULL
                    END as related_title
                FROM reputation_history rh
                LEFT JOIN ideas i ON rh.related_type = 'idea' AND rh.related_id = i.id
                LEFT JOIN comments c ON rh.related_type = 'comment' AND rh.related_id = c.id
                LEFT JOIN challenges ch ON rh.related_type = 'challenge' AND rh.related_id = ch.id
                LEFT JOIN achievements a ON rh.related_type = 'achievement' AND rh.related_id = a.id
                WHERE rh.user_id = ?
                ORDER BY rh.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$target_user_id, $limit, $offset]);
            $history = $stmt->fetchAll();

            // Подсчитываем общее количество записей
            $countStmt = $this->db->prepare("SELECT COUNT(*) FROM reputation_history WHERE user_id = ?");
            $countStmt->execute([$target_user_id]);
            $total = $countStmt->fetchColumn();

            // Форматируем данные
            foreach ($history as &$record) {
                $record['id'] = (int)$record['id'];
                $record['user_id'] = (int)$record['user_id'];
                $record['points'] = (int)$record['points'];
                $record['related_id'] = $record['related_id'] ? (int)$record['related_id'] : null;
            }

            Response::success([
                'history' => $history,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Get Reputation History API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении истории репутации', [], 500);
        }
    }

    // Вспомогательные методы

    private function initializeUserReputation($user_id)
    {
        $stmt = $this->db->prepare("
            INSERT IGNORE INTO user_reputation (user_id, total_points, level) 
            VALUES (?, 0, 1)
        ");
        $stmt->execute([$user_id]);
    }

    private function updateUserReputation($user_id, $action_type, $points, $related_type = null, $related_id = null, $description = null)
    {
        // Добавляем запись в историю репутации
        $stmt = $this->db->prepare("
            INSERT INTO reputation_history (user_id, action_type, points, description, related_type, related_id) 
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $action_type, $points, $description, $related_type, $related_id]);

        // Обновляем общую репутацию
        $updateStmt = $this->db->prepare("
            INSERT INTO user_reputation (user_id, total_points) 
            VALUES (?, ?)
            ON DUPLICATE KEY UPDATE 
                total_points = total_points + ?,
                level = GREATEST(1, FLOOR((total_points + ?) / 100) + 1),
                updated_at = CURRENT_TIMESTAMP
        ");
        
        $updateStmt->execute([$user_id, $points, $points, $points]);
    }

    private function createNotification($user_id, $type, $title, $message, $related_type = null, $related_id = null, $sender_id = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO notifications (user_id, type, title, message, related_type, related_id, sender_id)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $type, $title, $message, $related_type, $related_id, $sender_id]);
    }
}
?>