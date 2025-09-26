<?php
// Главный роутер API
require_once 'config/cors.php';
require_once 'config/database.php';
require_once 'middleware/auth.php';
require_once 'utils/response.php';
require_once 'utils/validator.php';

// Логирование для отладки
error_log("API Request - Method: " . $_SERVER['REQUEST_METHOD'] . ", URI: " . $_SERVER['REQUEST_URI']);

class APIRouter {
    public $routes = [];
    private $method;
    private $uri;
    
    public function __construct() {
        $this->method = $_SERVER['REQUEST_METHOD'];
        $this->uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
        
        // Удаляем базовый путь /api/v1
        $original_uri = $this->uri;
        $this->uri = str_replace('/praktica_popov/api/v1', '', $this->uri);
        $this->uri = rtrim($this->uri, '/') ?: '/';
        
        // Отладочная информация
        error_log("Router - Original URI: $original_uri, Processed URI: {$this->uri}, Method: {$this->method}");
    }
    
    public function addRoute($method, $pattern, $handler) {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler
        ];
    }
    
    public function route() {
        error_log("Router - Total routes registered: " . count($this->routes));
        error_log("Router - Looking for: {$this->method} {$this->uri}");
        
        foreach ($this->routes as $route) {
            error_log("Router - Checking route: {$route['method']} {$route['pattern']}");
            
            if ($route['method'] !== $this->method) {
                error_log("Router - Method mismatch: {$route['method']} !== {$this->method}");
                continue;
            }
            
            // Простое сравнение паттернов с поддержкой параметров {id}
            $pattern = $route['pattern'];
            $pattern = preg_replace('/\{([^}]+)\}/', '([^/]+)', $pattern);
            $pattern = '/^' . str_replace('/', '\/', $pattern) . '$/';
            
            error_log("Router - Regex pattern: $pattern");
            
            if (preg_match($pattern, $this->uri, $matches)) {
                error_log("Router - Route matched!");
                array_shift($matches); // Удаляем полное совпадение
                
                try {
                    call_user_func_array($route['handler'], $matches);
                    return;
                } catch (Exception $e) {
                    error_log("Router - Handler error: " . $e->getMessage());
                    Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
                    return;
                }
            } else {
                error_log("Router - No match for pattern: $pattern against URI: {$this->uri}");
            }
        }
        
        Response::error('NOT_FOUND', 'Endpoint не найден', [], 404);
    }
}

// Создание роутера
$router = new APIRouter();

// Тестовый маршрут
$router->addRoute('GET', '/', function() {
    Response::success(['message' => 'API работает!', 'timestamp' => date('Y-m-d H:i:s')]);
});

$router->addRoute('GET', '/test', function() {
    Response::success(['message' => 'Тест успешен!', 'uri' => $_SERVER['REQUEST_URI']]);
});

// Тест загрузки контроллера
$router->addRoute('GET', '/test/auth', function() {
    try {
        require_once 'controllers/AuthController.php';
        Response::success(['message' => 'AuthController загружен успешно!']);
    } catch (Exception $e) {
        Response::error('CONTROLLER_ERROR', 'Ошибка загрузки контроллера: ' . $e->getMessage(), [], 500);
    }
});

// Тест базы данных - получение пользователей для отладки
$router->addRoute('GET', '/test/users', function() {
    try {
        $db = APIDatabase::getConnection();
        $sql = "SELECT id, username, role FROM users LIMIT 5";
        $stmt = $db->prepare($sql);
        $stmt->execute();
        $users = $stmt->fetchAll();
        Response::success(['users' => $users, 'count' => count($users)]);
    } catch (Exception $e) {
        Response::error('DATABASE_ERROR', 'Ошибка получения пользователей: ' . $e->getMessage(), [], 500);
    }
});

// Создание тестового пользователя
$router->addRoute('POST', '/test/create-user', function() {
    try {
        $db = APIDatabase::getConnection();
        $hashedPassword = password_hash('testpass123', PASSWORD_DEFAULT);
        
        $sql = "INSERT INTO users (username, password, role) VALUES ('testuser', ?, 'user')";
        $stmt = $db->prepare($sql);
        $stmt->execute([$hashedPassword]);
        
        Response::success(['message' => 'Тестовый пользователь создан', 'username' => 'testuser', 'password' => 'testpass123']);
    } catch (Exception $e) {
        Response::error('DATABASE_ERROR', 'Ошибка создания пользователя: ' . $e->getMessage(), [], 500);
    }
});

// Отладочный маршрут для просмотра всех маршрутов
$router->addRoute('GET', '/debug/routes', function() use ($router) {
    $routes = [];
    foreach ($router->routes as $route) {
        $routes[] = $route['method'] . ' ' . $route['pattern'];
    }
    Response::success(['routes' => $routes, 'total' => count($routes)]);
});

// Регистрация маршрутов для аутентификации
require_once 'controllers/AuthController.php';

// GET /auth/login - информация о методе
$router->addRoute('GET', '/auth/login', function() {
    Response::error('METHOD_NOT_ALLOWED', 'Для входа в систему используйте POST запрос с JSON данными: {"username": "...", "password": "..."}', [], 405);
});

// POST /auth/login - вход в систему
$router->addRoute('POST', '/auth/login', function() {
    $controller = new AuthController();
    $controller->login();
});

// POST /auth/register - регистрация
$router->addRoute('POST', '/auth/register', function() {
    $controller = new AuthController();
    $controller->register();
});

// POST /auth/refresh - обновление токена
$router->addRoute('POST', '/auth/refresh', function() {
    $controller = new AuthController();
    $controller->refreshToken();
});

// POST /auth/logout - выход из системы
$router->addRoute('POST', '/auth/logout', function() {
    $controller = new AuthController();
    $controller->logout();
});

// Регистрация маршрутов для идей
require_once 'controllers/IdeasController.php';

// GET /ideas - получение списка идей
$router->addRoute('GET', '/ideas', function() {
    $controller = new IdeasController();
    $controller->getIdeas();
});

// POST /ideas - создание новой идеи
$router->addRoute('POST', '/ideas', function() {
    $controller = new IdeasController();
    $controller->createIdea();
});

// GET /ideas/{id} - получение идеи по ID
$router->addRoute('GET', '/ideas/{id}', function($id) {
    $controller = new IdeasController();
    $controller->getIdea($id);
});

// PUT /ideas/{id} - обновление идеи
$router->addRoute('PUT', '/ideas/{id}', function($id) {
    $controller = new IdeasController();
    $controller->updateIdea($id);
});

// DELETE /ideas/{id} - удаление идеи
$router->addRoute('DELETE', '/ideas/{id}', function($id) {
    $controller = new IdeasController();
    $controller->deleteIdea($id);
});

// Регистрация маршрутов для пользователей
require_once 'controllers/UsersController.php';

// GET /users - получение списка пользователей (админ)
$router->addRoute('GET', '/users', function() {
    $controller = new UsersController();
    $controller->getUsers();
});

// GET /users/profile - получение профиля текущего пользователя
$router->addRoute('GET', '/users/profile', function() {
    $controller = new UsersController();
    $controller->getProfile();
});

// PUT /users/profile - обновление профиля
$router->addRoute('PUT', '/users/profile', function() {
    $controller = new UsersController();
    $controller->updateProfile();
});

// GET /users/{id} - получение пользователя по ID (админ)
$router->addRoute('GET', '/users/{id}', function($id) {
    $controller = new UsersController();
    $controller->getUser($id);
});

// Регистрация маршрутов для голосования
require_once 'controllers/VotesController.php';

// POST /ideas/{id}/vote - голосование за идею
$router->addRoute('POST', '/ideas/{id}/vote', function($id) {
    $controller = new VotesController();
    $controller->vote($id);
});

// DELETE /ideas/{id}/vote - отмена голоса
$router->addRoute('DELETE', '/ideas/{id}/vote', function($id) {
    $controller = new VotesController();
    $controller->removeVote($id);
});

// GET /ideas/{id}/votes - получение голосов за идею
$router->addRoute('GET', '/ideas/{id}/votes', function($id) {
    $controller = new VotesController();
    $controller->getVotes($id);
});

// Обработка запросов
$router->route();
?>