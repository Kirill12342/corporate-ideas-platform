<?php
// Контроллер для аутентификации через API

class AuthController
{
    private $db;

    public function __construct()
    {
        $this->db = APIDatabase::getConnection();
    }

    public function login()
    {
        try {
            error_log("AuthController::login() called");

            $input = json_decode(file_get_contents('php://input'), true);
            error_log("Input data: " . print_r($input, true));

            if (!$input) {
                Response::error('VALIDATION_ERROR', 'Некорректные JSON данные');
                return;
            }

            // Валидация
            $errors = Validator::validate($input, [
                'username' => ['required' => true],
                'password' => ['required' => true]
            ]);

            if (!empty($errors)) {
                Response::error('VALIDATION_ERROR', 'Ошибка валидации данных', $errors);
                return;
            }

            // Поиск пользователя
            $sql = "SELECT id, username, password, role FROM users WHERE username = :username";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['username' => $input['username']]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($input['password'], $user['password'])) {
                Response::error('AUTH_FAILED', 'Неверное имя пользователя или пароль', [], 401);
                return;
            }

            // Генерация JWT токена
            $token = JWTAuth::generateToken([
                'id' => $user['id'],
                'username' => $user['username'],
                'role' => $user['role']
            ]);

            Response::success([
                'token' => $token,
                'user' => [
                    'id' => (int)$user['id'],
                    'username' => $user['username'],
                    'role' => $user['role']
                ]
            ], 'Авторизация успешна');

        } catch (Exception $e) {
            error_log("Login API Error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера: ' . $e->getMessage(), [], 500);
        }
    }

    public function register()
    {
        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('VALIDATION_ERROR', 'Некорректные JSON данные');
            return;
        }

        // Валидация
        $errors = Validator::validate($input, [
            'username' => ['required' => true, 'min_length' => 3, 'max_length' => 50],
            'password' => ['required' => true, 'min_length' => 6],
            'confirm_password' => ['required' => true]
        ]);

        if (!empty($errors)) {
            Response::error('VALIDATION_ERROR', 'Ошибка валидации данных', $errors);
            return;
        }

        // Проверка совпадения паролей
        if ($input['password'] !== $input['confirm_password']) {
            Response::error('VALIDATION_ERROR', 'Пароли не совпадают');
            return;
        }

        try {
            // Проверка уникальности имени пользователя
            $check_sql = "SELECT COUNT(*) as count FROM users WHERE username = :username";
            $check_stmt = $this->db->prepare($check_sql);
            $check_stmt->execute(['username' => $input['username']]);

            if ($check_stmt->fetch()['count'] > 0) {
                Response::error('VALIDATION_ERROR', 'Пользователь с таким именем уже существует');
                return;
            }

            // Создание пользователя
            $hashed_password = password_hash($input['password'], PASSWORD_DEFAULT);

            $sql = "INSERT INTO users (username, password, role, created_at) VALUES (:username, :password, 'user', NOW())";
            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'username' => trim($input['username']),
                'password' => $hashed_password
            ]);

            $user_id = $this->db->lastInsertId();

            // Генерация токена для нового пользователя
            $token = JWTAuth::generateToken([
                'id' => $user_id,
                'username' => trim($input['username']),
                'role' => 'user'
            ]);

            Response::success([
                'token' => $token,
                'user' => [
                    'id' => (int)$user_id,
                    'username' => trim($input['username']),
                    'role' => 'user'
                ]
            ], 'Регистрация успешна', 201);

        } catch (PDOException $e) {
            error_log("Register API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при регистрации', [], 500);
        }
    }

    public function refreshToken()
    {
        $user = JWTAuth::requireAuth();

        // Генерация нового токена
        $token = JWTAuth::generateToken([
            'id' => $user['user_id'],
            'username' => $user['username'],
            'role' => $user['role']
        ]);

        Response::success([
            'token' => $token
        ], 'Токен обновлен успешно');
    }

    public function logout()
    {
        // В реальном приложении можно добавить blacklist токенов
        Response::success(null, 'Выход выполнен успешно');
    }
}

?>