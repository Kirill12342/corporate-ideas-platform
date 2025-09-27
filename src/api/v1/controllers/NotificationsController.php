<?php
// Контроллер для системы уведомлений API

class NotificationsController
{
    private $db;

    public function __construct()
    {
        $this->db = APIDatabase::getConnection();
    }

    public function getNotifications()
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Параметры пагинации и фильтрации
            $page = (int)($_GET['page'] ?? 1);
            $limit = min((int)($_GET['limit'] ?? 20), 100);
            $offset = ($page - 1) * $limit;
            $unreadOnly = $_GET['unread_only'] ?? false;

            // Базовый запрос
            $whereClause = "WHERE n.user_id = ?";
            $userId = $user['user_id'] ?? $user['id'];
            $params = [(int)$userId];

            if ($unreadOnly === 'true') {
                $whereClause .= " AND n.is_read = 0";
            }

            // Получаем уведомления с поддержкой социальных функций
            $sql = "
                SELECT n.*,
                       CASE
                           WHEN n.related_type = 'idea' THEN i.title
                           WHEN n.related_type = 'vote' THEN CONCAT('Голос за идею: ', i2.title)
                           WHEN n.related_type = 'comment' THEN CONCAT('Комментарий к идее: ', i3.title)
                           WHEN n.related_type = 'comment_like' THEN CONCAT('Лайк комментария к идее: ', i4.title)
                           WHEN n.related_type = 'achievement' THEN a.title
                           WHEN n.related_type = 'challenge' THEN c.title
                           WHEN n.related_type = 'reputation' THEN 'Изменение репутации'
                           ELSE NULL
                       END as related_title,
                       CASE
                           WHEN n.sender_id IS NOT NULL THEN u.username
                           ELSE NULL
                       END as sender_username,
                       CASE
                           WHEN n.related_type = 'achievement' THEN a.badge_type
                           WHEN n.related_type = 'challenge' THEN c.type
                           ELSE NULL
                       END as extra_data
                FROM notifications n
                LEFT JOIN ideas i ON n.related_type = 'idea' AND n.related_id = i.id
                LEFT JOIN idea_votes iv ON n.related_type = 'vote' AND n.related_id = iv.id
                LEFT JOIN ideas i2 ON iv.idea_id = i2.id
                LEFT JOIN comments com ON n.related_type IN ('comment', 'comment_like') AND n.related_id = com.id
                LEFT JOIN ideas i3 ON com.idea_id = i3.id
                LEFT JOIN comment_likes cl ON n.related_type = 'comment_like' AND n.related_id = cl.id
                LEFT JOIN comments com2 ON cl.comment_id = com2.id
                LEFT JOIN ideas i4 ON com2.idea_id = i4.id
                LEFT JOIN user_achievements ua ON n.related_type = 'achievement' AND n.related_id = ua.id
                LEFT JOIN achievements a ON ua.achievement_id = a.id
                LEFT JOIN challenges c ON n.related_type = 'challenge' AND n.related_id = c.id
                LEFT JOIN users u ON n.sender_id = u.id
                $whereClause
                ORDER BY n.created_at DESC
                LIMIT $limit OFFSET $offset
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $notifications = $stmt->fetchAll();

            // Получаем общее количество
            $countSql = "SELECT COUNT(*) as total FROM notifications n $whereClause";
            $stmt = $this->db->prepare($countSql);
            $stmt->execute($params);
            $total = $stmt->fetch()['total'];

            // Получаем количество непрочитанных
            $unreadSql = "SELECT COUNT(*) as unread FROM notifications WHERE user_id = ? AND is_read = 0";
            $stmt = $this->db->prepare($unreadSql);
            $stmt->execute([$userId]);
            $unreadCount = $stmt->fetch()['unread'];

            Response::success([
                'notifications' => $notifications,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ],
                'unread_count' => (int)$unreadCount
            ]);

        } catch (Exception $e) {
            error_log("Get notifications error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }

    public function markAsRead($notificationId)
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Проверяем существование уведомления и права доступа
            $stmt = $this->db->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $user['id']]);
            $notification = $stmt->fetch();

            if (!$notification) {
                Response::error('NOT_FOUND', 'Уведомление не найдено', [], 404);
                return;
            }

            // Отмечаем как прочитанное
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE id = ?");
            $stmt->execute([$notificationId]);

            Response::success(['message' => 'Уведомление отмечено как прочитанное']);

        } catch (Exception $e) {
            error_log("Mark notification as read error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }

    public function markAllAsRead()
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Отмечаем все уведомления как прочитанные
            $stmt = $this->db->prepare("UPDATE notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user['id']]);

            $affected = $stmt->rowCount();

            Response::success([
                'message' => 'Все уведомления отмечены как прочитанные',
                'marked_count' => $affected
            ]);

        } catch (Exception $e) {
            error_log("Mark all notifications as read error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }

    public function getUnreadCount()
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Получаем количество непрочитанных уведомлений
            $stmt = $this->db->prepare("SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = ? AND is_read = 0");
            $stmt->execute([$user['id']]);
            $result = $stmt->fetch();

            Response::success(['unread_count' => (int)$result['unread_count']]);

        } catch (Exception $e) {
            error_log("Get unread count error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }

    public function deleteNotification($notificationId)
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Проверяем существование уведомления и права доступа
            $stmt = $this->db->prepare("SELECT * FROM notifications WHERE id = ? AND user_id = ?");
            $stmt->execute([$notificationId, $user['id']]);
            $notification = $stmt->fetch();

            if (!$notification) {
                Response::error('NOT_FOUND', 'Уведомление не найдено', [], 404);
                return;
            }

            // Удаляем уведомление
            $stmt = $this->db->prepare("DELETE FROM notifications WHERE id = ?");
            $stmt->execute([$notificationId]);

            Response::success(['message' => 'Уведомление удалено']);

        } catch (Exception $e) {
            error_log("Delete notification error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }

    // Статический метод для создания уведомлений из других контроллеров
    public static function createNotification($userId, $type, $title, $message, $relatedType = null, $relatedId = null, $senderId = null)
    {
        try {
            $db = APIDatabase::getConnection();

            $sql = "INSERT INTO notifications (user_id, type, title, message, related_type, related_id, sender_id, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $db->prepare($sql);
            $stmt->execute([$userId, $type, $title, $message, $relatedType, $relatedId, $senderId]);

            return $db->lastInsertId();

        } catch (Exception $e) {
            error_log("Create notification error: " . $e->getMessage());
            return false;
        }
    }

    // Метод для создания массовых уведомлений (например, для администраторов)
    public static function createBulkNotification($userIds, $type, $title, $message, $relatedType = null, $relatedId = null, $senderId = null)
    {
        try {
            $db = APIDatabase::getConnection();

            $sql = "INSERT INTO notifications (user_id, type, title, message, related_type, related_id, sender_id, created_at) VALUES ";
            $values = [];
            $params = [];

            foreach ($userIds as $userId) {
                $values[] = "(?, ?, ?, ?, ?, ?, ?, NOW())";
                array_push($params, $userId, $type, $title, $message, $relatedType, $relatedId, $senderId);
            }

            $sql .= implode(', ', $values);

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            return $stmt->rowCount();

        } catch (Exception $e) {
            error_log("Create bulk notification error: " . $e->getMessage());
            return false;
        }
    }

    // Специальные методы для социальных уведомлений

    public static function createCommentNotification($ideaAuthorId, $commentAuthorId, $commentId, $ideaTitle)
    {
        if ($ideaAuthorId == $commentAuthorId) return; // Не уведомляем автора о собственном комментарии

        return self::createNotification(
            $ideaAuthorId,
            'comment',
            'Новый комментарий к вашей идее',
            "К вашей идее \"{$ideaTitle}\" добавлен новый комментарий",
            'comment',
            $commentId,
            $commentAuthorId
        );
    }

    public static function createCommentReplyNotification($parentCommentAuthorId, $replyAuthorId, $replyId, $ideaTitle)
    {
        if ($parentCommentAuthorId == $replyAuthorId) return; // Не уведомляем автора о собственном ответе

        return self::createNotification(
            $parentCommentAuthorId,
            'comment_reply',
            'Ответ на ваш комментарий',
            "На ваш комментарий к идее \"{$ideaTitle}\" дан ответ",
            'comment',
            $replyId,
            $replyAuthorId
        );
    }

    public static function createCommentLikeNotification($commentAuthorId, $likerId, $commentId, $ideaTitle)
    {
        if ($commentAuthorId == $likerId) return; // Не уведомляем автора о собственном лайке

        return self::createNotification(
            $commentAuthorId,
            'comment_like',
            'Лайк вашего комментария',
            "Ваш комментарий к идее \"{$ideaTitle}\" понравился пользователю",
            'comment_like',
            $commentId,
            $likerId
        );
    }

    public static function createAchievementNotification($userId, $achievementId, $achievementTitle, $points)
    {
        return self::createNotification(
            $userId,
            'achievement',
            'Новое достижение!',
            "Поздравляем! Вы получили достижение \"{$achievementTitle}\" и {$points} баллов репутации",
            'achievement',
            $achievementId,
            null
        );
    }

    public static function createReputationNotification($userId, $pointsChange, $reason)
    {
        $title = $pointsChange > 0 ? 'Репутация увеличена' : 'Репутация изменена';
        $sign = $pointsChange > 0 ? '+' : '';
        
        return self::createNotification(
            $userId,
            'reputation',
            $title,
            "Ваша репутация изменена на {$sign}{$pointsChange} баллов за {$reason}",
            'reputation',
            null,
            null
        );
    }

    public static function createChallengeNotification($userId, $challengeId, $challengeTitle, $message, $senderId = null)
    {
        return self::createNotification(
            $userId,
            'challenge',
            'Обновление челленджа',
            $message,
            'challenge',
            $challengeId,
            $senderId
        );
    }

    public static function createChallengeInviteNotification($userId, $challengeId, $challengeTitle, $inviterId)
    {
        return self::createNotification(
            $userId,
            'challenge_invite',
            'Приглашение в челлендж',
            "Вы приглашены участвовать в челлендже \"{$challengeTitle}\"",
            'challenge',
            $challengeId,
            $inviterId
        );
    }

    public static function createChallengeCompleteNotification($userId, $challengeId, $challengeTitle, $reward = null)
    {
        $message = "Челлендж \"{$challengeTitle}\" завершен!";
        if ($reward) {
            $message .= " Награда: {$reward} баллов репутации";
        }

        return self::createNotification(
            $userId,
            'challenge_complete',
            'Челлендж завершен',
            $message,
            'challenge',
            $challengeId,
            null
        );
    }

    public static function createLevelUpNotification($userId, $newLevel, $pointsTotal)
    {
        return self::createNotification(
            $userId,
            'level_up',
            'Новый уровень!',
            "Поздравляем! Вы достигли {$newLevel} уровня! Общий счет: {$pointsTotal} баллов",
            'reputation',
            null,
            null
        );
    }

    public static function createTeamJoinNotification($teamMemberIds, $newMemberId, $challengeTitle)
    {
        $db = APIDatabase::getConnection();
        $stmt = $db->prepare("SELECT username FROM users WHERE id = ?");
        $stmt->execute([$newMemberId]);
        $newMemberUsername = $stmt->fetchColumn();

        foreach ($teamMemberIds as $memberId) {
            if ($memberId != $newMemberId) {
                self::createNotification(
                    $memberId,
                    'team_join',
                    'Новый участник команды',
                    "Пользователь {$newMemberUsername} присоединился к вашей команде в челлендже \"{$challengeTitle}\"",
                    'challenge',
                    null,
                    $newMemberId
                );
            }
        }
    }

    // Метод для получения настроек уведомлений пользователя
    public function getNotificationSettings()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Получаем настройки уведомлений или создаем значения по умолчанию
            $stmt = $this->db->prepare("
                SELECT * FROM user_notification_settings 
                WHERE user_id = ?
            ");
            $stmt->execute([$user['id']]);
            $settings = $stmt->fetch();

            if (!$settings) {
                // Создаем настройки по умолчанию
                $defaultSettings = [
                    'comments' => 1,
                    'comment_likes' => 1,
                    'achievements' => 1,
                    'reputation' => 1,
                    'challenges' => 1,
                    'team_activities' => 1,
                    'email_notifications' => 0
                ];

                $stmt = $this->db->prepare("
                    INSERT INTO user_notification_settings 
                    (user_id, comments, comment_likes, achievements, reputation, challenges, team_activities, email_notifications, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
                ");
                $stmt->execute([
                    $user['id'],
                    $defaultSettings['comments'],
                    $defaultSettings['comment_likes'],
                    $defaultSettings['achievements'],
                    $defaultSettings['reputation'],
                    $defaultSettings['challenges'],
                    $defaultSettings['team_activities'],
                    $defaultSettings['email_notifications']
                ]);

                $settings = $defaultSettings;
                $settings['user_id'] = $user['id'];
            }

            Response::success(['settings' => $settings]);

        } catch (Exception $e) {
            error_log("Get notification settings error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }

    // Метод для обновления настроек уведомлений
    public function updateNotificationSettings()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $input = json_decode(file_get_contents('php://input'), true);
            
            $allowedSettings = ['comments', 'comment_likes', 'achievements', 'reputation', 'challenges', 'team_activities', 'email_notifications'];
            $updateData = [];
            
            foreach ($allowedSettings as $setting) {
                if (isset($input[$setting])) {
                    $updateData[$setting] = (bool)$input[$setting] ? 1 : 0;
                }
            }

            if (empty($updateData)) {
                Response::error('VALIDATION_ERROR', 'Не указаны настройки для обновления');
                return;
            }

            // Обновляем настройки
            $setClause = implode(', ', array_map(fn($key) => "$key = ?", array_keys($updateData)));
            $values = array_values($updateData);
            $values[] = $user['id'];

            $stmt = $this->db->prepare("
                UPDATE user_notification_settings 
                SET $setClause, updated_at = NOW()
                WHERE user_id = ?
            ");
            $stmt->execute($values);

            if ($stmt->rowCount() === 0) {
                // Создаем запись если не существует
                $this->getNotificationSettings(); // Это создаст настройки по умолчанию
                
                // Повторяем обновление
                $stmt = $this->db->prepare("
                    UPDATE user_notification_settings 
                    SET $setClause, updated_at = NOW()
                    WHERE user_id = ?
                ");
                $stmt->execute($values);
            }

            Response::success(['message' => 'Настройки уведомлений обновлены']);

        } catch (Exception $e) {
            error_log("Update notification settings error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }
}

?>