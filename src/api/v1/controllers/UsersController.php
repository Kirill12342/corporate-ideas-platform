<?php
// Контроллер для управления пользователями через API

class UsersController
{
    private $db;

    public function __construct()
    {
        $this->db = APIDatabase::getConnection();
    }

    public function getProfile()
    {
        $user = JWTAuth::requireAuth();

        try {
            $sql = "
                SELECT 
                    id,
                    username,
                    role,
                    created_at,
                    (SELECT COUNT(*) FROM ideas WHERE user_id = users.id) as ideas_count,
                    (SELECT COUNT(*) FROM idea_votes WHERE user_id = users.id) as votes_count
                FROM users 
                WHERE id = :user_id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $user['user_id']]);
            $profile = $stmt->fetch();

            if (!$profile) {
                Response::error('NOT_FOUND', 'Профиль не найден', [], 404);
                return;
            }

            // Форматирование данных
            $profile['id'] = (int)$profile['id'];
            $profile['ideas_count'] = (int)$profile['ideas_count'];
            $profile['votes_count'] = (int)$profile['votes_count'];
            $profile['created_at'] = date('Y-m-d H:i:s', strtotime($profile['created_at']));

            Response::success($profile, 'Профиль получен успешно');

        } catch (PDOException $e) {
            error_log("Get Profile API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении профиля', [], 500);
        }
    }

    public function updateProfile()
    {
        $user = JWTAuth::requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('VALIDATION_ERROR', 'Некорректные JSON данные');
            return;
        }

        try {
            $update_fields = [];
            $params = ['user_id' => $user['user_id']];

            // Обновление имени пользователя
            if (isset($input['username'])) {
                if (strlen(trim($input['username'])) < 3 || strlen(trim($input['username'])) > 50) {
                    Response::error('VALIDATION_ERROR', 'Имя пользователя должно содержать от 3 до 50 символов');
                    return;
                }

                // Проверка уникальности
                $check_sql = "SELECT COUNT(*) as count FROM users WHERE username = :username AND id != :user_id";
                $check_stmt = $this->db->prepare($check_sql);
                $check_stmt->execute([
                    'username' => trim($input['username']),
                    'user_id' => $user['user_id']
                ]);

                if ($check_stmt->fetch()['count'] > 0) {
                    Response::error('VALIDATION_ERROR', 'Пользователь с таким именем уже существует');
                    return;
                }

                $update_fields[] = "username = :username";
                $params['username'] = trim($input['username']);
            }

            // Обновление пароля
            if (isset($input['password']) && !empty($input['password'])) {
                if (strlen($input['password']) < 6) {
                    Response::error('VALIDATION_ERROR', 'Пароль должен содержать минимум 6 символов');
                    return;
                }

                $update_fields[] = "password = :password";
                $params['password'] = password_hash($input['password'], PASSWORD_DEFAULT);
            }

            if (empty($update_fields)) {
                Response::error('VALIDATION_ERROR', 'Нет полей для обновления');
                return;
            }

            $sql = "UPDATE users SET " . implode(', ', $update_fields) . " WHERE id = :user_id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            Response::success(null, 'Профиль обновлен успешно');

        } catch (PDOException $e) {
            error_log("Update Profile API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при обновлении профиля', [], 500);
        }
    }

    public function getUserInfo($id)
    {
        $user = JWTAuth::requireAuth();

        if (!is_numeric($id)) {
            Response::error('VALIDATION_ERROR', 'Некорректный ID пользователя');
            return;
        }

        try {
            $sql = "
                SELECT 
                    u.id,
                    u.username,
                    u.created_at,
                    COUNT(DISTINCT i.id) as ideas_count,
                    COUNT(DISTINCT iv.id) as votes_count,
                    COALESCE(SUM(i.likes_count), 0) as total_likes_received
                FROM users u
                LEFT JOIN ideas i ON u.id = i.user_id
                LEFT JOIN idea_votes iv ON u.id = iv.user_id
                WHERE u.id = :user_id
                GROUP BY u.id, u.username, u.created_at
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute(['user_id' => $id]);
            $userInfo = $stmt->fetch();

            if (!$userInfo) {
                Response::error('NOT_FOUND', 'Пользователь не найден', [], 404);
                return;
            }

            // Получение последних идей пользователя
            $ideas_sql = "
                SELECT 
                    id,
                    title,
                    category,
                    status,
                    created_at,
                    likes_count,
                    dislikes_count
                FROM ideas 
                WHERE user_id = :user_id AND status = 'approved'
                ORDER BY created_at DESC 
                LIMIT 5
            ";

            $ideas_stmt = $this->db->prepare($ideas_sql);
            $ideas_stmt->execute(['user_id' => $id]);
            $recent_ideas = $ideas_stmt->fetchAll();

            // Форматирование данных
            $userInfo['id'] = (int)$userInfo['id'];
            $userInfo['ideas_count'] = (int)$userInfo['ideas_count'];
            $userInfo['votes_count'] = (int)$userInfo['votes_count'];
            $userInfo['total_likes_received'] = (int)$userInfo['total_likes_received'];
            $userInfo['created_at'] = date('Y-m-d H:i:s', strtotime($userInfo['created_at']));

            foreach ($recent_ideas as &$idea) {
                $idea['id'] = (int)$idea['id'];
                $idea['likes_count'] = (int)$idea['likes_count'];
                $idea['dislikes_count'] = (int)$idea['dislikes_count'];
                $idea['created_at'] = date('Y-m-d H:i:s', strtotime($idea['created_at']));
            }

            $userInfo['recent_ideas'] = $recent_ideas;

            Response::success($userInfo, 'Информация о пользователе получена успешно');

        } catch (PDOException $e) {
            error_log("Get User Info API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении информации о пользователе', [], 500);
        }
    }
}

?>