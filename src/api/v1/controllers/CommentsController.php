<?php
// Контроллер для системы комментариев к идеям

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../middleware/auth.php';
require_once __DIR__ . '/../utils/response.php';
require_once __DIR__ . '/NotificationsController.php';

class CommentsControllerp
// Контроллер для системы комментариев API

class CommentsController
{
    private $db;

    public function __construct()
    {
        $this->db = APIDatabase::getConnection();
    }

    // Получить комментарии к идее
    public function getComments()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $idea_id = $_GET['idea_id'] ?? '';
            if (!$idea_id) {
                Response::error('VALIDATION_ERROR', 'ID идеи обязателен');
                return;
            }

            // Параметры пагинации
            $page = max(1, intval($_GET['page'] ?? 1));
            $limit = min(50, max(1, intval($_GET['limit'] ?? 20)));
            $offset = ($page - 1) * $limit;

            // Получаем комментарии с информацией о пользователях и лайках
            $sql = "
                WITH RECURSIVE comment_tree AS (
                    -- Корневые комментарии
                    SELECT 
                        c.id, c.idea_id, c.user_id, c.parent_id, c.content, 
                        c.is_edited, c.edited_at, c.created_at, c.updated_at,
                        u.username, u.email, u.avatar_url, u.department,
                        ur.level, ur.total_points,
                        0 as depth,
                        CAST(c.id AS CHAR(1000)) as path,
                        c.created_at as sort_date
                    FROM comments c
                    JOIN users u ON c.user_id = u.id
                    LEFT JOIN user_reputation ur ON c.user_id = ur.user_id
                    WHERE c.idea_id = ? AND c.parent_id IS NULL
                    
                    UNION ALL
                    
                    -- Дочерние комментарии
                    SELECT 
                        c.id, c.idea_id, c.user_id, c.parent_id, c.content,
                        c.is_edited, c.edited_at, c.created_at, c.updated_at,
                        u.username, u.email, u.avatar_url, u.department,
                        ur.level, ur.total_points,
                        ct.depth + 1,
                        CONCAT(ct.path, '-', c.id),
                        ct.sort_date
                    FROM comments c
                    JOIN users u ON c.user_id = u.id
                    LEFT JOIN user_reputation ur ON c.user_id = ur.user_id
                    JOIN comment_tree ct ON c.parent_id = ct.id
                    WHERE ct.depth < 5  -- Ограничиваем глубину вложенности
                )
                SELECT 
                    ct.*,
                    COALESCE(likes_count.count, 0) as likes_count,
                    CASE WHEN user_like.comment_id IS NOT NULL THEN 1 ELSE 0 END as user_liked
                FROM comment_tree ct
                LEFT JOIN (
                    SELECT comment_id, COUNT(*) as count 
                    FROM comment_likes 
                    GROUP BY comment_id
                ) likes_count ON ct.id = likes_count.comment_id
                LEFT JOIN comment_likes user_like ON ct.id = user_like.comment_id 
                    AND user_like.user_id = ?
                ORDER BY ct.sort_date DESC, ct.path
                LIMIT ? OFFSET ?
            ";

            $stmt = $this->db->prepare($sql);
            $userId = $user['user_id'] ?? $user['id'];
            $stmt->execute([$idea_id, $userId, $limit, $offset]);
            $comments = $stmt->fetchAll();

            // Подсчитываем общее количество комментариев
            $countSql = "SELECT COUNT(*) FROM comments WHERE idea_id = ?";
            $countStmt = $this->db->prepare($countSql);
            $countStmt->execute([$idea_id]);
            $total = $countStmt->fetchColumn();

            // Форматируем данные
            foreach ($comments as &$comment) {
                $comment['id'] = (int)$comment['id'];
                $comment['idea_id'] = (int)$comment['idea_id'];
                $comment['user_id'] = (int)$comment['user_id'];
                $comment['parent_id'] = $comment['parent_id'] ? (int)$comment['parent_id'] : null;
                $comment['depth'] = (int)$comment['depth'];
                $comment['likes_count'] = (int)$comment['likes_count'];
                $comment['user_liked'] = (bool)$comment['user_liked'];
                $comment['level'] = (int)($comment['level'] ?? 1);
                $comment['total_points'] = (int)($comment['total_points'] ?? 0);
                $comment['is_edited'] = (bool)$comment['is_edited'];
                
                // Скрываем email для приватности
                unset($comment['email']);
            }

            Response::success([
                'comments' => $comments,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limit,
                    'total' => (int)$total,
                    'pages' => ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Get Comments API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении комментариев', [], 500);
        }
    }

    // Создать комментарий
    public function createComment()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $input = json_decode(file_get_contents('php://input'), true);
            
            // Валидация
            $required_fields = ['idea_id', 'content'];
            foreach ($required_fields as $field) {
                if (!isset($input[$field]) || empty(trim($input[$field]))) {
                    Response::error('VALIDATION_ERROR', "Поле $field обязательно для заполнения");
                    return;
                }
            }

            $idea_id = (int)$input['idea_id'];
            $content = trim($input['content']);
            $parent_id = isset($input['parent_id']) ? (int)$input['parent_id'] : null;

            // Проверяем длину комментария
            if (strlen($content) < 3) {
                Response::error('VALIDATION_ERROR', 'Комментарий слишком короткий (минимум 3 символа)');
                return;
            }
            if (strlen($content) > 2000) {
                Response::error('VALIDATION_ERROR', 'Комментарий слишком длинный (максимум 2000 символов)');
                return;
            }

            // Проверяем существование идеи
            $ideaCheck = $this->db->prepare("SELECT id FROM ideas WHERE id = ?");
            $ideaCheck->execute([$idea_id]);
            if (!$ideaCheck->fetch()) {
                Response::error('NOT_FOUND', 'Идея не найдена');
                return;
            }

            // Проверяем родительский комментарий если указан
            if ($parent_id) {
                $parentCheck = $this->db->prepare("SELECT id, parent_id FROM comments WHERE id = ? AND idea_id = ?");
                $parentCheck->execute([$parent_id, $idea_id]);
                $parent = $parentCheck->fetch();
                if (!$parent) {
                    Response::error('NOT_FOUND', 'Родительский комментарий не найден');
                    return;
                }
                
                // Ограничиваем глубину вложенности до 3 уровней
                if ($parent['parent_id'] !== null) {
                    $grandParentCheck = $this->db->prepare("SELECT parent_id FROM comments WHERE id = ?");
                    $grandParentCheck->execute([$parent['parent_id']]);
                    $grandParent = $grandParentCheck->fetch();
                    if ($grandParent && $grandParent['parent_id'] !== null) {
                        Response::error('VALIDATION_ERROR', 'Превышена максимальная глубина вложенности комментариев');
                        return;
                    }
                }
            }

            $this->db->beginTransaction();

            // Создаем комментарий
            $userId = $user['user_id'] ?? $user['id'];
            $stmt = $this->db->prepare("
                INSERT INTO comments (idea_id, user_id, parent_id, content) 
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([$idea_id, $userId, $parent_id, $content]);
            
            $comment_id = $this->db->lastInsertId();

            // Обновляем репутацию пользователя
            $this->updateUserReputation($userId, 'comment_created', 5, 'comment', $comment_id);

            // Создаем активность
            $this->createActivity($userId, 'comment_created', [
                'comment_id' => $comment_id,
                'idea_id' => $idea_id,
                'parent_id' => $parent_id
            ], 'comment', $comment_id);

            // Если это ответ на комментарий, уведомляем автора родительского комментария
            if ($parent_id) {
                $parentAuthor = $this->db->prepare("SELECT user_id FROM comments WHERE id = ?");
                $parentAuthor->execute([$parent_id]);
                $parentUserId = $parentAuthor->fetchColumn();
                
                if ($parentUserId != $userId) {
                    $this->createNotification($parentUserId, 'comment_reply', 
                        'Ответ на ваш комментарий', 
                        'Пользователь ответил на ваш комментарий',
                        'comment', $comment_id, $userId);
                }
            }

            // Уведомляем автора идеи о новом комментарии
            $ideaAuthor = $this->db->prepare("SELECT user_id FROM ideas WHERE id = ?");
            $ideaAuthor->execute([$idea_id]);
            $ideaUserId = $ideaAuthor->fetchColumn();
            
            if ($ideaUserId != $userId) {
                $this->createNotification($ideaUserId, 'idea_comment', 
                    'Новый комментарий к вашей идее', 
                    'Пользователь прокомментировал вашу идею',
                    'idea', $idea_id, $userId);
            }

            $this->db->commit();

            // Получаем созданный комментарий с дополнительной информацией
            $createdComment = $this->getCommentById($comment_id);

            Response::success($createdComment, 'Комментарий успешно создан', 201);

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Create Comment API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при создании комментария', [], 500);
        }
    }

    // Лайк/анлайк комментария
    public function toggleCommentLike()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $input = json_decode(file_get_contents('php://input'), true);
            $comment_id = (int)($input['comment_id'] ?? 0);

            if (!$comment_id) {
                Response::error('VALIDATION_ERROR', 'ID комментария обязателен');
                return;
            }

            // Проверяем существование комментария
            $commentCheck = $this->db->prepare("SELECT id, user_id FROM comments WHERE id = ?");
            $commentCheck->execute([$comment_id]);
            $comment = $commentCheck->fetch();
            
            if (!$comment) {
                Response::error('NOT_FOUND', 'Комментарий не найден');
                return;
            }

            $userId = $user['user_id'] ?? $user['id'];

            // Проверяем есть ли уже лайк
            $likeCheck = $this->db->prepare("SELECT id FROM comment_likes WHERE comment_id = ? AND user_id = ?");
            $likeCheck->execute([$comment_id, $userId]);
            $existingLike = $likeCheck->fetch();

            $this->db->beginTransaction();

            if ($existingLike) {
                // Удаляем лайк
                $stmt = $this->db->prepare("DELETE FROM comment_likes WHERE comment_id = ? AND user_id = ?");
                $stmt->execute([$comment_id, $userId]);
                $action = 'unliked';
            } else {
                // Добавляем лайк
                $stmt = $this->db->prepare("INSERT INTO comment_likes (comment_id, user_id) VALUES (?, ?)");
                $stmt->execute([$comment_id, $userId]);
                $action = 'liked';

                // Начисляем баллы автору комментария (но не себе)
                if ($comment['user_id'] != $userId) {
                    $this->updateUserReputation($comment['user_id'], 'like_received', 2, 'comment', $comment_id);
                    
                    // Уведомляем автора комментария
                    $this->createNotification($comment['user_id'], 'comment_liked', 
                        'Ваш комментарий понравился', 
                        'Пользователь поставил лайк вашему комментарию',
                        'comment', $comment_id, $userId);
                }

                // Создаем активность
                $this->createActivity($userId, 'comment_liked', [
                    'comment_id' => $comment_id
                ], 'comment', $comment_id);
            }

            // Получаем обновленное количество лайков
            $likesCount = $this->db->prepare("SELECT COUNT(*) FROM comment_likes WHERE comment_id = ?");
            $likesCount->execute([$comment_id]);
            $count = $likesCount->fetchColumn();

            $this->db->commit();

            Response::success([
                'action' => $action,
                'likes_count' => (int)$count,
                'user_liked' => $action === 'liked'
            ], $action === 'liked' ? 'Лайк добавлен' : 'Лайк удален');

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Toggle Comment Like API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при обработке лайка', [], 500);
        }
    }

    // Редактировать комментарий
    public function updateComment()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $comment_id = $_GET['id'] ?? '';
            if (!$comment_id) {
                Response::error('VALIDATION_ERROR', 'ID комментария обязателен');
                return;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $content = trim($input['content'] ?? '');

            if (empty($content)) {
                Response::error('VALIDATION_ERROR', 'Содержимое комментария обязательно');
                return;
            }

            if (strlen($content) < 3 || strlen($content) > 2000) {
                Response::error('VALIDATION_ERROR', 'Комментарий должен содержать от 3 до 2000 символов');
                return;
            }

            $userId = $user['user_id'] ?? $user['id'];

            // Проверяем существование и права на редактирование
            $commentCheck = $this->db->prepare("SELECT user_id, created_at FROM comments WHERE id = ?");
            $commentCheck->execute([$comment_id]);
            $comment = $commentCheck->fetch();

            if (!$comment) {
                Response::error('NOT_FOUND', 'Комментарий не найден');
                return;
            }

            if ($comment['user_id'] != $userId) {
                Response::error('FORBIDDEN', 'Нет прав на редактирование этого комментария');
                return;
            }

            // Ограничиваем время редактирования (15 минут)
            $createdTime = new DateTime($comment['created_at']);
            $currentTime = new DateTime();
            $diffMinutes = $currentTime->getTimestamp() - $createdTime->getTimestamp();
            
            if ($diffMinutes > 900) { // 15 минут
                Response::error('FORBIDDEN', 'Время редактирования истекло (максимум 15 минут после создания)');
                return;
            }

            // Обновляем комментарий
            $stmt = $this->db->prepare("
                UPDATE comments 
                SET content = ?, is_edited = TRUE, edited_at = CURRENT_TIMESTAMP 
                WHERE id = ?
            ");
            $stmt->execute([$content, $comment_id]);

            Response::success([
                'id' => (int)$comment_id,
                'content' => $content,
                'is_edited' => true,
                'edited_at' => date('Y-m-d H:i:s')
            ], 'Комментарий успешно обновлен');

        } catch (PDOException $e) {
            error_log("Update Comment API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при обновлении комментария', [], 500);
        }
    }

    // Удалить комментарий
    public function deleteComment()
    {
        try {
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            $comment_id = $_GET['id'] ?? '';
            if (!$comment_id) {
                Response::error('VALIDATION_ERROR', 'ID комментария обязателен');
                return;
            }

            $userId = $user['user_id'] ?? $user['id'];
            $userRole = $user['role'] ?? 'user';

            // Проверяем существование комментария
            $commentCheck = $this->db->prepare("SELECT user_id FROM comments WHERE id = ?");
            $commentCheck->execute([$comment_id]);
            $comment = $commentCheck->fetch();

            if (!$comment) {
                Response::error('NOT_FOUND', 'Комментарий не найден');
                return;
            }

            // Проверяем права (автор или администратор)
            if ($comment['user_id'] != $userId && $userRole !== 'admin') {
                Response::error('FORBIDDEN', 'Нет прав на удаление этого комментария');
                return;
            }

            $this->db->beginTransaction();

            // Удаляем комментарий (каскадно удалятся лайки и дочерние комментарии)
            $stmt = $this->db->prepare("DELETE FROM comments WHERE id = ?");
            $stmt->execute([$comment_id]);

            $this->db->commit();

            Response::success(['deleted_id' => (int)$comment_id], 'Комментарий успешно удален');

        } catch (PDOException $e) {
            $this->db->rollBack();
            error_log("Delete Comment API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при удалении комментария', [], 500);
        }
    }

    // Вспомогательные методы

    private function getCommentById($comment_id)
    {
        $sql = "
            SELECT 
                c.*, u.username, u.avatar_url, u.department,
                ur.level, ur.total_points,
                COALESCE(likes_count.count, 0) as likes_count
            FROM comments c
            JOIN users u ON c.user_id = u.id
            LEFT JOIN user_reputation ur ON c.user_id = ur.user_id
            LEFT JOIN (
                SELECT comment_id, COUNT(*) as count 
                FROM comment_likes 
                GROUP BY comment_id
            ) likes_count ON c.id = likes_count.comment_id
            WHERE c.id = ?
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute([$comment_id]);
        $comment = $stmt->fetch();
        
        if ($comment) {
            $comment['id'] = (int)$comment['id'];
            $comment['idea_id'] = (int)$comment['idea_id'];
            $comment['user_id'] = (int)$comment['user_id'];
            $comment['parent_id'] = $comment['parent_id'] ? (int)$comment['parent_id'] : null;
            $comment['likes_count'] = (int)$comment['likes_count'];
            $comment['level'] = (int)($comment['level'] ?? 1);
            $comment['total_points'] = (int)($comment['total_points'] ?? 0);
            $comment['is_edited'] = (bool)$comment['is_edited'];
        }
        
        return $comment;
    }

    private function updateUserReputation($user_id, $action_type, $points, $related_type = null, $related_id = null)
    {
        // Добавляем запись в историю репутации
        $stmt = $this->db->prepare("
            INSERT INTO reputation_history (user_id, action_type, points, related_type, related_id) 
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([$user_id, $action_type, $points, $related_type, $related_id]);

        // Обновляем общую репутацию
        $updateStmt = $this->db->prepare("
            INSERT INTO user_reputation (user_id, total_points, comments_count, likes_received) 
            VALUES (?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE 
                total_points = total_points + ?,
                comments_count = comments_count + ?,
                likes_received = likes_received + ?,
                level = GREATEST(1, FLOOR(total_points / 100))
        ");
        
        $commentsIncrement = ($action_type === 'comment_created') ? 1 : 0;
        $likesIncrement = ($action_type === 'like_received') ? 1 : 0;
        
        $updateStmt->execute([
            $user_id, $points, $commentsIncrement, $likesIncrement,
            $points, $commentsIncrement, $likesIncrement
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