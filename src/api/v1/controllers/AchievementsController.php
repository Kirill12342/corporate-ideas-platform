<?php
// Контроллер для системы достижений и геймификации

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/response.php';

class AchievementsController
{
    private $db;

    public function __construct()
    {
        $this->db = APIDatabase::getConnection();
    }

    // Получить все достижения с информацией о разблокировке для пользователя
    public function getAchievements()
    {
        try {
            // Проверяем авторизацию, но не прерываем выполнение если пользователь не авторизован
            $user = JWTAuth::getOptionalUser();
            $userId = null;
            if ($user) {
                $userId = $user['user_id'] ?? $user['id'];
            }
            
            // Параметры фильтрации
            $category = $_GET['category'] ?? ''; // unlocked, locked, all
            $rarity = $_GET['rarity'] ?? '';
            $badge_type = $_GET['badge_type'] ?? '';

            $whereConditions = ['a.is_active = TRUE'];
            $params = [];

            if ($rarity && in_array($rarity, ['common', 'rare', 'epic', 'legendary'])) {
                $whereConditions[] = "a.rarity = ?";
                $params[] = $rarity;
            }

            if ($badge_type && in_array($badge_type, ['bronze', 'silver', 'gold', 'platinum', 'special'])) {
                $whereConditions[] = "a.badge_type = ?";
                $params[] = $badge_type;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            if ($userId) {
                // Для авторизованных пользователей показываем статус разблокировки
                $userJoin = "LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?";
                $params[] = $userId;
                
                if ($category === 'unlocked') {
                    $whereClause .= ' AND ua.user_id IS NOT NULL';
                } elseif ($category === 'locked') {
                    $whereClause .= ' AND ua.user_id IS NULL';
                }
                
                $sql = "
                    SELECT 
                        a.*,
                        ua.unlocked_at,
                        ua.progress_data,
                        CASE WHEN ua.user_id IS NOT NULL THEN TRUE ELSE FALSE END as is_unlocked
                    FROM achievements a
                    $userJoin
                    $whereClause
                    ORDER BY 
                        CASE WHEN ua.user_id IS NOT NULL THEN 0 ELSE 1 END,
                        CASE a.rarity 
                            WHEN 'legendary' THEN 1 
                            WHEN 'epic' THEN 2 
                            WHEN 'rare' THEN 3 
                            WHEN 'common' THEN 4 
                        END,
                        a.points_reward DESC,
                        a.created_at ASC
                ";
            } else {
                // Для неавторизованных пользователей просто показываем все достижения
                $sql = "
                    SELECT 
                        a.*,
                        NULL as unlocked_at,
                        NULL as progress_data,
                        FALSE as is_unlocked
                    FROM achievements a
                    $whereClause
                    ORDER BY 
                        CASE a.rarity 
                            WHEN 'legendary' THEN 1 
                            WHEN 'epic' THEN 2 
                            WHEN 'rare' THEN 3 
                            WHEN 'common' THEN 4 
                        END,
                        a.points_reward DESC,
                        a.created_at ASC
                ";
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $achievements = $stmt->fetchAll();

            // Рассчитываем прогресс для незаблокированных достижений
            foreach ($achievements as &$achievement) {
                $achievement['id'] = (int)$achievement['id'];
                $achievement['points_reward'] = (int)$achievement['points_reward'];
                $achievement['is_active'] = (bool)$achievement['is_active'];
                $achievement['is_unlocked'] = (bool)$achievement['is_unlocked'];
                
                // Декодируем условия разблокировки
                $achievement['unlock_condition'] = json_decode($achievement['unlock_condition'], true);
                
                if (!$achievement['is_unlocked']) {
                    // Рассчитываем текущий прогресс
                    $progress = $this->calculateAchievementProgress($userId, $achievement);
                    $achievement['current_progress'] = $progress['current'];
                    $achievement['progress_percentage'] = $progress['percentage'];
                    $achievement['progress_description'] = $progress['description'];
                } else {
                    $achievement['current_progress'] = $achievement['unlock_condition']['value'] ?? 0;
                    $achievement['progress_percentage'] = 100;
                    $achievement['progress_description'] = 'Разблокировано';
                }
            }

            // Статистика достижений пользователя
            $statsStmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_achievements,
                    SUM(CASE WHEN ua.user_id IS NOT NULL THEN 1 ELSE 0 END) as unlocked_count,
                    SUM(CASE WHEN ua.user_id IS NOT NULL THEN a.points_reward ELSE 0 END) as total_points_earned
                FROM achievements a
                LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
                WHERE a.is_active = TRUE
            ");
            $statsStmt->execute([$userId]);
            $stats = $statsStmt->fetch();

            Response::success([
                'achievements' => $achievements,
                'stats' => [
                    'total_achievements' => (int)$stats['total_achievements'],
                    'unlocked_count' => (int)$stats['unlocked_count'],
                    'locked_count' => (int)$stats['total_achievements'] - (int)$stats['unlocked_count'],
                    'completion_percentage' => $stats['total_achievements'] > 0 ? 
                        round(($stats['unlocked_count'] / $stats['total_achievements']) * 100, 1) : 0,
                    'total_points_earned' => (int)$stats['total_points_earned']
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Get Achievements API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении достижений', [], 500);
        }
    }

    // Проверить и разблокировать достижения для пользователя
    public function checkAchievements()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $userId = $user['user_id'] ?? $user['id'];
            
            // Получаем все активные достижения, которые еще не разблокированы
            $sql = "
                SELECT a.* 
                FROM achievements a
                LEFT JOIN user_achievements ua ON a.id = ua.achievement_id AND ua.user_id = ?
                WHERE a.is_active = TRUE AND ua.user_id IS NULL
            ";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute([$userId]);
            $lockedAchievements = $stmt->fetchAll();

            $unlockedAchievements = [];

            foreach ($lockedAchievements as $achievement) {
                $progress = $this->calculateAchievementProgress($userId, $achievement);
                
                // Если достижение выполнено, разблокируем его
                if ($progress['completed']) {
                    $this->unlockAchievement($userId, $achievement['id']);
                    $unlockedAchievements[] = [
                        'id' => (int)$achievement['id'],
                        'name' => $achievement['name'],
                        'title' => $achievement['title'],
                        'description' => $achievement['description'],
                        'badge_type' => $achievement['badge_type'],
                        'rarity' => $achievement['rarity'],
                        'points_reward' => (int)$achievement['points_reward'],
                        'unlocked_at' => date('Y-m-d H:i:s')
                    ];
                }
            }

            Response::success([
                'newly_unlocked' => $unlockedAchievements,
                'count' => count($unlockedAchievements)
            ], count($unlockedAchievements) > 0 ? 
                'Поздравляем! Разблокировано новых достижений: ' . count($unlockedAchievements) :
                'Новых достижений нет'
            );

        } catch (PDOException $e) {
            error_log("Check Achievements API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при проверке достижений', [], 500);
        }
    }

    // Получить топ пользователей по количеству достижений
    public function getAchievementLeaderboard()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            $sql = "
                SELECT 
                    u.id as user_id,
                    u.username,
                    u.avatar_url,
                    u.department,
                    ur.level,
                    ur.total_points,
                    COUNT(ua.id) as achievements_count,
                    SUM(a.points_reward) as achievement_points,
                    GROUP_CONCAT(
                        CASE WHEN a.rarity = 'legendary' THEN a.name END
                        ORDER BY a.points_reward DESC 
                        SEPARATOR ','
                    ) as legendary_achievements,
                    ROW_NUMBER() OVER (ORDER BY COUNT(ua.id) DESC, SUM(a.points_reward) DESC) as rank
                FROM users u
                LEFT JOIN user_achievements ua ON u.id = ua.user_id
                LEFT JOIN achievements a ON ua.achievement_id = a.id
                LEFT JOIN user_reputation ur ON u.id = ur.user_id
                GROUP BY u.id, u.username, u.avatar_url, u.department, ur.level, ur.total_points
                ORDER BY achievements_count DESC, achievement_points DESC
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([$limit, $offset]);
            $leaderboard = $stmt->fetchAll();

            // Подсчитываем общее количество
            $countStmt = $this->db->query("SELECT COUNT(DISTINCT u.id) FROM users u");
            $total = $countStmt->fetchColumn();

            // Форматируем данные
            foreach ($leaderboard as &$entry) {
                $entry['user_id'] = (int)$entry['user_id'];
                $entry['level'] = (int)($entry['level'] ?? 1);
                $entry['total_points'] = (int)($entry['total_points'] ?? 0);
                $entry['achievements_count'] = (int)$entry['achievements_count'];
                $entry['achievement_points'] = (int)($entry['achievement_points'] ?? 0);
                $entry['rank'] = (int)$entry['rank'];
                
                // Обрабатываем легендарные достижения
                $entry['legendary_achievements'] = $entry['legendary_achievements'] ? 
                    explode(',', $entry['legendary_achievements']) : [];
            }

            Response::success([
                'leaderboard' => $leaderboard,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Get Achievement Leaderboard API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении рейтинга достижений', [], 500);
        }
    }

    // Создать новое достижение (только для администраторов)
    public function createAchievement()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user || ($user['role'] ?? 'user') !== 'admin') {
                Response::error('FORBIDDEN', 'Недостаточно прав для создания достижений');
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            $required_fields = ['name', 'title', 'description', 'unlock_condition'];
            foreach ($required_fields as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    Response::error('VALIDATION_ERROR', "Поле $field обязательно для заполнения");
                    return;
                }
            }

            $name = trim($input['name']);
            $title = trim($input['title']);
            $description = trim($input['description']);
            $icon_class = trim($input['icon_class'] ?? 'fas fa-trophy');
            $icon_color = trim($input['icon_color'] ?? '#FFD700');
            $badge_type = $input['badge_type'] ?? 'bronze';
            $unlock_condition = $input['unlock_condition'];
            $points_reward = (int)($input['points_reward'] ?? 0);
            $rarity = $input['rarity'] ?? 'common';

            // Валидация
            if (strlen($name) < 3 || strlen($name) > 100) {
                Response::error('VALIDATION_ERROR', 'Имя достижения должно содержать от 3 до 100 символов');
                return;
            }

            if (strlen($title) < 3 || strlen($title) > 255) {
                Response::error('VALIDATION_ERROR', 'Заголовок должен содержать от 3 до 255 символов');
                return;
            }

            if (!in_array($badge_type, ['bronze', 'silver', 'gold', 'platinum', 'special'])) {
                Response::error('VALIDATION_ERROR', 'Неверный тип значка');
                return;
            }

            if (!in_array($rarity, ['common', 'rare', 'epic', 'legendary'])) {
                Response::error('VALIDATION_ERROR', 'Неверная редкость достижения');
                return;
            }

            if (!is_array($unlock_condition) || !isset($unlock_condition['type'], $unlock_condition['value'])) {
                Response::error('VALIDATION_ERROR', 'Условие разблокировки должно содержать type и value');
                return;
            }

            // Проверяем уникальность имени
            $nameCheck = $this->db->prepare("SELECT id FROM achievements WHERE name = ?");
            $nameCheck->execute([$name]);
            if ($nameCheck->fetch()) {
                Response::error('CONFLICT', 'Достижение с таким именем уже существует');
                return;
            }

            // Создаем достижение
            $stmt = $this->db->prepare("
                INSERT INTO achievements 
                (name, title, description, icon_class, icon_color, badge_type, 
                 unlock_condition, points_reward, rarity, is_active)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, TRUE)
            ");
            
            $stmt->execute([
                $name, $title, $description, $icon_class, $icon_color, $badge_type,
                json_encode($unlock_condition), $points_reward, $rarity
            ]);

            $achievementId = $this->db->lastInsertId();

            Response::success([
                'id' => (int)$achievementId,
                'name' => $name,
                'title' => $title,
                'badge_type' => $badge_type,
                'rarity' => $rarity,
                'points_reward' => $points_reward
            ], 'Достижение успешно создано', 201);

        } catch (PDOException $e) {
            error_log("Create Achievement API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при создании достижения', [], 500);
        }
    }

    // Получить активности пользователей (лента активности)
    public function getActivityFeed()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $activity_type = $_GET['type'] ?? '';

            $whereConditions = ['ua.visibility = "public"'];
            $params = [];

            if ($activity_type) {
                $whereConditions[] = 'ua.activity_type = ?';
                $params[] = $activity_type;
            }

            $whereClause = 'WHERE ' . implode(' AND ', $whereConditions);

            $sql = "
                SELECT 
                    ua.*,
                    u.username,
                    u.avatar_url,
                    u.department,
                    ur.level,
                    -- Дополнительная информация в зависимости от типа активности
                    CASE 
                        WHEN ua.related_type = 'idea' THEN i.title
                        WHEN ua.related_type = 'comment' THEN SUBSTR(c.content, 1, 100)
                        WHEN ua.related_type = 'challenge' THEN ch.title
                        WHEN ua.related_type = 'achievement' THEN a.title
                        ELSE NULL
                    END as related_title
                FROM user_activities ua
                JOIN users u ON ua.user_id = u.id
                LEFT JOIN user_reputation ur ON ua.user_id = ur.user_id
                LEFT JOIN ideas i ON ua.related_type = 'idea' AND ua.related_id = i.id
                LEFT JOIN comments c ON ua.related_type = 'comment' AND ua.related_id = c.id
                LEFT JOIN challenges ch ON ua.related_type = 'challenge' AND ua.related_id = ch.id
                LEFT JOIN achievements a ON ua.related_type = 'achievement' AND ua.related_id = a.id
                $whereClause
                ORDER BY ua.created_at DESC
                LIMIT ? OFFSET ?
            ";

            $params[] = $limit;
            $params[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $activities = $stmt->fetchAll();

            // Подсчитываем общее количество
            $countSql = "SELECT COUNT(*) FROM user_activities ua $whereClause";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute(array_slice($params, 0, -2));
            $total = $countStmt->fetchColumn();

            // Форматируем данные
            foreach ($activities as &$activity) {
                $activity['id'] = (int)$activity['id'];
                $activity['user_id'] = (int)$activity['user_id'];
                $activity['level'] = (int)($activity['level'] ?? 1);
                $activity['related_id'] = $activity['related_id'] ? (int)$activity['related_id'] : null;
                $activity['activity_data'] = json_decode($activity['activity_data'], true);
            }

            Response::success([
                'activities' => $activities,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Get Activity Feed API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении ленты активности', [], 500);
        }
    }

    // Вспомогательные методы

    private function calculateAchievementProgress($user_id, $achievement)
    {
        $condition = json_decode($achievement['unlock_condition'], true);
        $type = $condition['type'] ?? '';
        $targetValue = $condition['value'] ?? 0;
        $currentValue = 0;

        switch ($type) {
            case 'ideas_count':
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM ideas WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $currentValue = $stmt->fetchColumn();
                break;

            case 'approved_ideas':
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM ideas WHERE user_id = ? AND status = 'approved'");
                $stmt->execute([$user_id]);
                $currentValue = $stmt->fetchColumn();
                break;

            case 'comments_count':
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM comments WHERE user_id = ?");
                $stmt->execute([$user_id]);
                $currentValue = $stmt->fetchColumn();
                break;

            case 'likes_received':
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM votes v
                    JOIN ideas i ON v.idea_id = i.id
                    WHERE i.user_id = ? AND v.vote_type = 'like'
                ");
                $stmt->execute([$user_id]);
                $currentValue = $stmt->fetchColumn();
                break;

            case 'challenges_completed':
                $stmt = $this->db->prepare("SELECT COUNT(*) FROM challenge_participants WHERE user_id = ? AND is_completed = TRUE");
                $stmt->execute([$user_id]);
                $currentValue = $stmt->fetchColumn();
                break;

            case 'daily_login_streak':
                // Логика для подсчета серии входов (требует дополнительной таблицы логов)
                $currentValue = 0; // Placeholder
                break;

            case 'late_night_activity':
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM user_activities 
                    WHERE user_id = ? AND HOUR(created_at) >= 22
                ");
                $stmt->execute([$user_id]);
                $currentValue = $stmt->fetchColumn();
                break;
        }

        $completed = $currentValue >= $targetValue;
        $percentage = $targetValue > 0 ? min(100, ($currentValue / $targetValue) * 100) : 0;
        $description = "$currentValue / $targetValue";

        return [
            'current' => $currentValue,
            'target' => $targetValue,
            'completed' => $completed,
            'percentage' => round($percentage, 1),
            'description' => $description
        ];
    }

    private function unlockAchievement($user_id, $achievement_id)
    {
        $this->db->beginTransaction();

        try {
            // Разблокируем достижение
            $stmt = $this->db->prepare("
                INSERT INTO user_achievements (user_id, achievement_id)
                VALUES (?, ?)
            ");
            $stmt->execute([$user_id, $achievement_id]);

            // Получаем информацию о достижении
            $achievementStmt = $this->db->prepare("SELECT * FROM achievements WHERE id = ?");
            $achievementStmt->execute([$achievement_id]);
            $achievement = $achievementStmt->fetch();

            if ($achievement && $achievement['points_reward'] > 0) {
                // Начисляем баллы репутации
                $this->updateUserReputation(
                    $user_id, 
                    'achievement_unlocked', 
                    $achievement['points_reward'],
                    'achievement',
                    $achievement_id,
                    "Получение достижения: {$achievement['title']}"
                );
            }

            // Создаем уведомление
            $this->createNotification(
                $user_id,
                'achievement_unlocked',
                'Новое достижение!',
                "Поздравляем! Вы получили достижение \"{$achievement['title']}\"" . 
                ($achievement['points_reward'] > 0 ? " и {$achievement['points_reward']} баллов репутации." : "."),
                'achievement',
                $achievement_id
            );

            // Создаем активность
            $this->createActivity($user_id, 'achievement_unlocked', [
                'achievement_id' => $achievement_id,
                'achievement_name' => $achievement['name'],
                'achievement_title' => $achievement['title'],
                'points_reward' => $achievement['points_reward'],
                'rarity' => $achievement['rarity']
            ], 'achievement', $achievement_id);

            $this->db->commit();

        } catch (Exception $e) {
            $this->db->rollBack();
            throw $e;
        }
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
                level = GREATEST(1, FLOOR((total_points + ?) / 100) + 1)
        ");
        
        $updateStmt->execute([$user_id, $points, $points, $points]);
    }

    private function createActivity($user_id, $activity_type, $activity_data, $related_type = null, $related_id = null)
    {
        $stmt = $this->db->prepare("
            INSERT INTO user_activities (user_id, activity_type, activity_data, related_type, related_id)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $user_id, $activity_type, json_encode($activity_data), $related_type, $related_id
        ]);
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