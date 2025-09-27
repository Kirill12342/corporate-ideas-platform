<?php
// Контроллер для системы челленджей и соревнований

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/NotificationsController.php';

class ChallengesController
{
    private $db;

    public function __construct()
    {
        $this->db = APIDatabase::getConnection();
    }

    // Получить список челленджей
    public function getChallenges()
    {
        try {
            $user = JWTAuth::getOptionalUser();

            // Параметры фильтрации
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;
            $status = $_GET['status'] ?? '';
            $type = $_GET['type'] ?? '';
            $my_challenges = $_GET['my_challenges'] ?? false;

            $whereConditions = [];
            $params = [];

            if ($status && in_array($status, ['draft', 'active', 'completed', 'archived'])) {
                $whereConditions[] = "c.status = ?";
                $params[] = $status;
            }

            if ($type && in_array($type, ['individual', 'team', 'department'])) {
                $whereConditions[] = "c.challenge_type = ?";
                $params[] = $type;
            }

            $userId = null;
            if ($user) {
                $userId = $user['user_id'] ?? $user['id'];
            }
            
            if ($my_challenges === 'true' && $userId) {
                $whereConditions[] = "(c.created_by = ? OR cp.user_id = ?)";
                $params[] = $userId;
                $params[] = $userId;
            }

            $whereClause = !empty($whereConditions) ? 'WHERE ' . implode(' AND ', $whereConditions) : '';

            $sql = "
                SELECT DISTINCT
                    c.*,
                    creator.username as creator_name,
                    creator.avatar_url as creator_avatar,
                    participants_count.count as participants_count,
                    completed_count.count as completed_count,
                    CASE WHEN user_participation.user_id IS NOT NULL THEN 1 ELSE 0 END as is_participating,
                    user_participation.current_progress,
                    user_participation.is_completed as user_completed,
                    user_participation.team_name as user_team
                FROM challenges c
                JOIN users creator ON c.created_by = creator.id
                LEFT JOIN challenge_participants cp ON c.id = cp.challenge_id
                LEFT JOIN (
                    SELECT challenge_id, COUNT(*) as count
                    FROM challenge_participants
                    GROUP BY challenge_id
                ) participants_count ON c.id = participants_count.challenge_id
                LEFT JOIN (
                    SELECT challenge_id, COUNT(*) as count
                    FROM challenge_participants
                    WHERE is_completed = TRUE
                    GROUP BY challenge_id
                ) completed_count ON c.id = completed_count.challenge_id
                LEFT JOIN challenge_participants user_participation ON c.id = user_participation.challenge_id 
                    AND user_participation.user_id = ?
                $whereClause
                ORDER BY 
                    CASE c.status 
                        WHEN 'active' THEN 1 
                        WHEN 'draft' THEN 2 
                        WHEN 'completed' THEN 3 
                        WHEN 'archived' THEN 4 
                    END,
                    c.end_date ASC
                LIMIT ? OFFSET ?
            ";

            $finalParams = [$userId];
            $finalParams = array_merge($finalParams, $params);
            $finalParams[] = $limit;
            $finalParams[] = $offset;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($finalParams);
            $challenges = $stmt->fetchAll();

            // Подсчитываем общее количество
            $countSql = "
                SELECT COUNT(DISTINCT c.id)
                FROM challenges c
                LEFT JOIN challenge_participants cp ON c.id = cp.challenge_id
                $whereClause
            ";
            $countParams = array_merge($params);
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute($countParams);
            $total = $countStmt->fetchColumn();

            // Форматируем данные
            foreach ($challenges as &$challenge) {
                $challenge['id'] = (int)$challenge['id'];
                $challenge['created_by'] = (int)$challenge['created_by'];
                $challenge['target_value'] = (int)$challenge['target_value'];
                $challenge['reward_points'] = (int)$challenge['reward_points'];
                $challenge['participants_count'] = (int)($challenge['participants_count'] ?? 0);
                $challenge['completed_count'] = (int)($challenge['completed_count'] ?? 0);
                $challenge['is_participating'] = (bool)$challenge['is_participating'];
                $challenge['current_progress'] = (int)($challenge['current_progress'] ?? 0);
                $challenge['user_completed'] = (bool)($challenge['user_completed'] ?? false);
                
                // Рассчитываем прогресс в процентах
                $challenge['progress_percentage'] = $challenge['target_value'] > 0 ? 
                    min(100, ($challenge['current_progress'] / $challenge['target_value']) * 100) : 0;
                
                // Определяем статус для пользователя
                $challenge['user_status'] = 'not_joined';
                if ($challenge['is_participating']) {
                    if ($challenge['user_completed']) {
                        $challenge['user_status'] = 'completed';
                    } else {
                        $challenge['user_status'] = 'in_progress';
                    }
                }
            }

            Response::success([
                'challenges' => $challenges,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Get Challenges API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении челленджей', [], 500);
        }
    }

    // Создать новый челлендж
    public function createChallenge()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Проверяем права (админы или модераторы могут создавать челленджи)
            $userRole = $user['role'] ?? 'user';
            if (!in_array($userRole, ['admin', 'moderator'])) {
                Response::error('FORBIDDEN', 'Недостаточно прав для создания челленджей');
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            
            // Валидация обязательных полей
            $required_fields = ['title', 'description', 'challenge_type', 'start_date', 'end_date', 'target_metric', 'target_value'];
            foreach ($required_fields as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    Response::error('VALIDATION_ERROR', "Поле $field обязательно для заполнения");
                    return;
                }
            }

            $title = trim($input['title']);
            $description = trim($input['description']);
            $challenge_type = $input['challenge_type'];
            $start_date = $input['start_date'];
            $end_date = $input['end_date'];
            $target_metric = $input['target_metric'];
            $target_value = (int)$input['target_value'];
            $reward_points = (int)($input['reward_points'] ?? 0);
            $reward_description = trim($input['reward_description'] ?? '');
            $status = $input['status'] ?? 'draft';

            // Валидация значений
            if (strlen($title) < 5 || strlen($title) > 255) {
                Response::error('VALIDATION_ERROR', 'Название должно содержать от 5 до 255 символов');
                return;
            }

            if (strlen($description) < 10 || strlen($description) > 2000) {
                Response::error('VALIDATION_ERROR', 'Описание должно содержать от 10 до 2000 символов');
                return;
            }

            if (!in_array($challenge_type, ['individual', 'team', 'department'])) {
                Response::error('VALIDATION_ERROR', 'Неверный тип челленджа');
                return;
            }

            if (!in_array($target_metric, ['ideas_count', 'likes_count', 'comments_count', 'approval_rate'])) {
                Response::error('VALIDATION_ERROR', 'Неверная метрика цели');
                return;
            }

            if (!in_array($status, ['draft', 'active'])) {
                Response::error('VALIDATION_ERROR', 'Неверный статус челленджа');
                return;
            }

            if ($target_value < 1 || $target_value > 10000) {
                Response::error('VALIDATION_ERROR', 'Целевое значение должно быть от 1 до 10000');
                return;
            }

            // Проверяем даты
            $startDateTime = new DateTime($start_date);
            $endDateTime = new DateTime($end_date);
            $currentDateTime = new DateTime();

            if ($startDateTime < $currentDateTime) {
                Response::error('VALIDATION_ERROR', 'Дата начала не может быть в прошлом');
                return;
            }

            if ($endDateTime <= $startDateTime) {
                Response::error('VALIDATION_ERROR', 'Дата окончания должна быть позже даты начала');
                return;
            }

            $duration = $endDateTime->diff($startDateTime);
            if ($duration->days < 1 || $duration->days > 365) {
                Response::error('VALIDATION_ERROR', 'Продолжительность челленджа должна быть от 1 дня до 1 года');
                return;
            }

            // Создаем челлендж
            $userId = $user['user_id'] ?? $user['id'];
            
            $stmt = $this->db->prepare("
                INSERT INTO challenges 
                (title, description, challenge_type, status, start_date, end_date, 
                 target_metric, target_value, reward_points, reward_description, created_by)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                $title, $description, $challenge_type, $status, $start_date, $end_date,
                $target_metric, $target_value, $reward_points, $reward_description, $userId
            ]);

            $challengeId = $this->db->lastInsertId();

            // Создаем активность
            $this->createActivity($userId, 'challenge_created', [
                'challenge_id' => $challengeId,
                'challenge_type' => $challenge_type,
                'title' => $title
            ], 'challenge', $challengeId);

            Response::success([
                'id' => (int)$challengeId,
                'title' => $title,
                'status' => $status,
                'challenge_type' => $challenge_type
            ], 'Челлендж успешно создан', 201);

        } catch (PDOException $e) {
            error_log("Create Challenge API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при создании челленджа', [], 500);
        }
    }

    // Присоединиться к челленджу
    public function joinChallenge()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $challenge_id = $_GET['id'] ?? '';
            if (!$challenge_id) {
                Response::error('VALIDATION_ERROR', 'ID челленджа обязателен');
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $team_name = trim($input['team_name'] ?? '');

            // Проверяем существование и статус челленджа
            $challengeStmt = $this->db->prepare("
                SELECT * FROM challenges 
                WHERE id = ? AND status = 'active' 
                AND start_date <= NOW() AND end_date > NOW()
            ");
            $challengeStmt->execute([$challenge_id]);
            $challenge = $challengeStmt->fetch();

            if (!$challenge) {
                Response::error('NOT_FOUND', 'Активный челлендж не найден или уже завершился');
                return;
            }

            // Для командных челленджей требуется название команды
            if ($challenge['challenge_type'] === 'team' && empty($team_name)) {
                Response::error('VALIDATION_ERROR', 'Для командного челленджа необходимо указать название команды');
                return;
            }

            if ($team_name && (strlen($team_name) < 2 || strlen($team_name) > 50)) {
                Response::error('VALIDATION_ERROR', 'Название команды должно содержать от 2 до 50 символов');
                return;
            }

            $userId = $user['user_id'] ?? $user['id'];

            // Проверяем, не участвует ли уже пользователь
            $participationCheck = $this->db->prepare("
                SELECT id FROM challenge_participants 
                WHERE challenge_id = ? AND user_id = ?
            ");
            $participationCheck->execute([$challenge_id, $userId]);
            
            if ($participationCheck->fetch()) {
                Response::error('CONFLICT', 'Вы уже участвуете в этом челлендже');
                return;
            }

            $this->db->beginTransaction();

            // Добавляем участника
            $stmt = $this->db->prepare("
                INSERT INTO challenge_participants (challenge_id, user_id, team_name)
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$challenge_id, $userId, $team_name]);

            // Обновляем текущий прогресс пользователя
            $this->updateChallengeProgress($challenge_id, $userId);

            // Создаем активность
            $this->createActivity($userId, 'challenge_joined', [
                'challenge_id' => $challenge_id,
                'challenge_title' => $challenge['title'],
                'team_name' => $team_name
            ], 'challenge', $challenge_id);

            $this->db->commit();

            Response::success([
                'challenge_id' => (int)$challenge_id,
                'challenge_title' => $challenge['title'],
                'team_name' => $team_name,
                'joined_at' => date('Y-m-d H:i:s')
            ], 'Вы успешно присоединились к челленджу!');

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Join Challenge API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при присоединении к челленджу', [], 500);
        }
    }

    // Покинуть челлендж
    public function leaveChallenge()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $challenge_id = $_GET['id'] ?? '';
            if (!$challenge_id) {
                Response::error('VALIDATION_ERROR', 'ID челленджа обязателен');
                return;
            }

            $userId = $user['user_id'] ?? $user['id'];

            // Проверяем участие в челлендже
            $participationStmt = $this->db->prepare("
                SELECT cp.*, c.status, c.end_date 
                FROM challenge_participants cp
                JOIN challenges c ON cp.challenge_id = c.id
                WHERE cp.challenge_id = ? AND cp.user_id = ?
            ");
            $participationStmt->execute([$challenge_id, $userId]);
            $participation = $participationStmt->fetch();

            if (!$participation) {
                Response::error('NOT_FOUND', 'Вы не участвуете в этом челлендже');
                return;
            }

            // Нельзя покинуть завершенный челлендж
            if ($participation['status'] === 'completed') {
                Response::error('FORBIDDEN', 'Нельзя покинуть завершенный челлендж');
                return;
            }

            // Нельзя покинуть если уже выполнен
            if ($participation['is_completed']) {
                Response::error('FORBIDDEN', 'Нельзя покинуть челлендж после его выполнения');
                return;
            }

            // Удаляем участие
            $stmt = $this->db->prepare("
                DELETE FROM challenge_participants 
                WHERE challenge_id = ? AND user_id = ?
            ");
            $stmt->execute([$challenge_id, $userId]);

            Response::success([
                'challenge_id' => (int)$challenge_id,
                'left_at' => date('Y-m-d H:i:s')
            ], 'Вы покинули челлендж');

        } catch (PDOException $e) {
            error_log("Leave Challenge API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при выходе из челленджа', [], 500);
        }
    }

    // Получить лидерборд челленджа
    public function getChallengeLeaderboard()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $challenge_id = $_GET['id'] ?? '';
            if (!$challenge_id) {
                Response::error('VALIDATION_ERROR', 'ID челленджа обязателен');
                return;
            }

            // Проверяем существование челленджа
            $challengeStmt = $this->db->prepare("SELECT * FROM challenges WHERE id = ?");
            $challengeStmt->execute([$challenge_id]);
            $challenge = $challengeStmt->fetch();

            if (!$challenge) {
                Response::error('NOT_FOUND', 'Челлендж не найден');
                return;
            }

            // Получаем лидерборд в зависимости от типа челленджа
            if ($challenge['challenge_type'] === 'team') {
                // Групповой лидерборд по командам
                $sql = "
                    SELECT 
                        cp.team_name,
                        COUNT(*) as team_size,
                        SUM(cp.current_progress) as total_progress,
                        AVG(cp.current_progress) as avg_progress,
                        SUM(CASE WHEN cp.is_completed THEN 1 ELSE 0 END) as completed_members,
                        GROUP_CONCAT(u.username ORDER BY cp.current_progress DESC SEPARATOR ', ') as members
                    FROM challenge_participants cp
                    JOIN users u ON cp.user_id = u.id
                    WHERE cp.challenge_id = ?
                    GROUP BY cp.team_name
                    ORDER BY total_progress DESC, avg_progress DESC
                ";
                $params = [$challenge_id];
            } else {
                // Индивидуальный лидерборд
                $sql = "
                    SELECT 
                        cp.user_id,
                        u.username,
                        u.avatar_url,
                        u.department,
                        ur.level,
                        ur.total_points as reputation_points,
                        cp.current_progress,
                        cp.is_completed,
                        cp.completed_at,
                        cp.team_name,
                        ROW_NUMBER() OVER (ORDER BY cp.current_progress DESC, cp.completed_at ASC) as rank
                    FROM challenge_participants cp
                    JOIN users u ON cp.user_id = u.id
                    LEFT JOIN user_reputation ur ON cp.user_id = ur.user_id
                    WHERE cp.challenge_id = ?
                    ORDER BY cp.current_progress DESC, cp.completed_at ASC
                ";
                $params = [$challenge_id];
            }

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $leaderboard = $stmt->fetchAll();

            // Форматируем данные
            foreach ($leaderboard as &$entry) {
                if (isset($entry['user_id'])) {
                    $entry['user_id'] = (int)$entry['user_id'];
                    $entry['level'] = (int)($entry['level'] ?? 1);
                    $entry['reputation_points'] = (int)($entry['reputation_points'] ?? 0);
                    $entry['current_progress'] = (int)$entry['current_progress'];
                    $entry['is_completed'] = (bool)$entry['is_completed'];
                    $entry['rank'] = (int)$entry['rank'];
                }
                
                if (isset($entry['total_progress'])) {
                    $entry['team_size'] = (int)$entry['team_size'];
                    $entry['total_progress'] = (int)$entry['total_progress'];
                    $entry['avg_progress'] = (float)$entry['avg_progress'];
                    $entry['completed_members'] = (int)$entry['completed_members'];
                }
            }

            Response::success([
                'challenge' => [
                    'id' => (int)$challenge['id'],
                    'title' => $challenge['title'],
                    'challenge_type' => $challenge['challenge_type'],
                    'target_value' => (int)$challenge['target_value'],
                    'target_metric' => $challenge['target_metric']
                ],
                'leaderboard' => $leaderboard
            ]);

        } catch (PDOException $e) {
            error_log("Get Challenge Leaderboard API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении лидерборда', [], 500);
        }
    }

    // Обновить прогресс всех активных челленджей (вызывается по cron или при изменениях)
    public function updateAllChallengeProgress()
    {
        try {
            // Получаем все активные челленджи
            $challengesStmt = $this->db->query("
                SELECT id FROM challenges 
                WHERE status = 'active' 
                AND start_date <= NOW() 
                AND end_date > NOW()
            ");
            $challenges = $challengesStmt->fetchAll();

            foreach ($challenges as $challenge) {
                $this->updateChallengeProgressForAll($challenge['id']);
            }

            // Проверяем и завершаем истекшие челленджи
            $this->completeExpiredChallenges();

            Response::success(['updated_challenges' => count($challenges)], 'Прогресс челленджей обновлен');

        } catch (PDOException $e) {
            error_log("Update All Challenge Progress API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при обновлении прогресса', [], 500);
        }
    }

    // Вспомогательные методы

    private function updateChallengeProgress($challenge_id, $user_id)
    {
        // Получаем информацию о челлендже
        $challengeStmt = $this->db->prepare("SELECT target_metric, target_value FROM challenges WHERE id = ?");
        $challengeStmt->execute([$challenge_id]);
        $challenge = $challengeStmt->fetch();

        if (!$challenge) return;

        // Рассчитываем текущий прогресс в зависимости от метрики
        $progress = 0;
        
        switch ($challenge['target_metric']) {
            case 'ideas_count':
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM ideas 
                    WHERE user_id = ? 
                    AND created_at >= (SELECT start_date FROM challenges WHERE id = ?)
                ");
                $stmt->execute([$user_id, $challenge_id]);
                $progress = $stmt->fetchColumn();
                break;
                
            case 'likes_count':
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM votes v
                    JOIN ideas i ON v.idea_id = i.id
                    WHERE i.user_id = ? 
                    AND v.vote_type = 'like'
                    AND v.created_at >= (SELECT start_date FROM challenges WHERE id = ?)
                ");
                $stmt->execute([$user_id, $challenge_id]);
                $progress = $stmt->fetchColumn();
                break;
                
            case 'comments_count':
                $stmt = $this->db->prepare("
                    SELECT COUNT(*) FROM comments 
                    WHERE user_id = ? 
                    AND created_at >= (SELECT start_date FROM challenges WHERE id = ?)
                ");
                $stmt->execute([$user_id, $challenge_id]);
                $progress = $stmt->fetchColumn();
                break;
                
            case 'approval_rate':
                $stmt = $this->db->prepare("
                    SELECT 
                        COUNT(CASE WHEN status = 'approved' THEN 1 END) * 100 / GREATEST(COUNT(*), 1) as rate
                    FROM ideas 
                    WHERE user_id = ? 
                    AND created_at >= (SELECT start_date FROM challenges WHERE id = ?)
                ");
                $stmt->execute([$user_id, $challenge_id]);
                $progress = $stmt->fetchColumn();
                break;
        }

        // Определяем выполнен ли челлендж
        $is_completed = $progress >= $challenge['target_value'];

        // Обновляем прогресс
        $updateStmt = $this->db->prepare("
            UPDATE challenge_participants 
            SET current_progress = ?, 
                is_completed = ?,
                completed_at = CASE WHEN ? AND completed_at IS NULL THEN NOW() ELSE completed_at END
            WHERE challenge_id = ? AND user_id = ?
        ");
        $updateStmt->execute([$progress, $is_completed, $is_completed, $challenge_id, $user_id]);

        // Если челлендж только что выполнен, награждаем пользователя
        if ($is_completed) {
            $this->rewardChallengeCompletion($challenge_id, $user_id);
        }
    }

    private function updateChallengeProgressForAll($challenge_id)
    {
        // Получаем всех участников челленджа
        $participantsStmt = $this->db->prepare("
            SELECT user_id FROM challenge_participants WHERE challenge_id = ?
        ");
        $participantsStmt->execute([$challenge_id]);
        $participants = $participantsStmt->fetchAll();

        foreach ($participants as $participant) {
            $this->updateChallengeProgress($challenge_id, $participant['user_id']);
        }
    }

    private function rewardChallengeCompletion($challenge_id, $user_id)
    {
        // Проверяем, не награждали ли уже
        $rewardCheck = $this->db->prepare("
            SELECT id FROM reputation_history 
            WHERE user_id = ? AND action_type = 'challenge_completed' 
            AND related_type = 'challenge' AND related_id = ?
        ");
        $rewardCheck->execute([$user_id, $challenge_id]);
        
        if ($rewardCheck->fetch()) {
            return; // Уже награждали
        }

        // Получаем информацию о челлендже
        $challengeStmt = $this->db->prepare("SELECT title, reward_points FROM challenges WHERE id = ?");
        $challengeStmt->execute([$challenge_id]);
        $challenge = $challengeStmt->fetch();

        if ($challenge && $challenge['reward_points'] > 0) {
            // Начисляем баллы репутации
            $this->updateUserReputation(
                $user_id, 
                'challenge_completed', 
                $challenge['reward_points'], 
                'challenge', 
                $challenge_id,
                "Выполнение челленджа: {$challenge['title']}"
            );

            // Создаем уведомление
            $this->createNotification(
                $user_id, 
                'challenge_completed',
                'Челлендж выполнен!',
                "Поздравляем! Вы выполнили челлендж \"{$challenge['title']}\" и получили {$challenge['reward_points']} баллов репутации.",
                'challenge',
                $challenge_id
            );

            // Создаем активность
            $this->createActivity($user_id, 'challenge_completed', [
                'challenge_id' => $challenge_id,
                'challenge_title' => $challenge['title'],
                'reward_points' => $challenge['reward_points']
            ], 'challenge', $challenge_id);
        }
    }

    private function completeExpiredChallenges()
    {
        $this->db->prepare("
            UPDATE challenges 
            SET status = 'completed' 
            WHERE status = 'active' AND end_date <= NOW()
        ")->execute();
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
            INSERT INTO user_reputation (user_id, total_points, challenges_completed) 
            VALUES (?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                total_points = total_points + ?,
                challenges_completed = challenges_completed + ?,
                level = GREATEST(1, FLOOR((total_points + ?) / 100) + 1)
        ");
        
        $challengesIncrement = ($action_type === 'challenge_completed') ? 1 : 0;
        
        $updateStmt->execute([
            $user_id, $points, $challengesIncrement,
            $points, $challengesIncrement, $points
        ]);
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