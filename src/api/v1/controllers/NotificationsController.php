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

            // Получаем уведомления
            $sql = "
                SELECT n.*, 
                       CASE 
                           WHEN n.related_type = 'idea' THEN i.title
                           WHEN n.related_type = 'vote' THEN CONCAT('Голос за идею: ', i2.title)
                           ELSE NULL 
                       END as related_title,
                       CASE 
                           WHEN n.sender_id IS NOT NULL THEN u.username
                           ELSE NULL 
                       END as sender_username
                FROM notifications n
                LEFT JOIN ideas i ON n.related_type = 'idea' AND n.related_id = i.id
                LEFT JOIN idea_votes iv ON n.related_type = 'vote' AND n.related_id = iv.id
                LEFT JOIN ideas i2 ON iv.idea_id = i2.id
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
}

?>