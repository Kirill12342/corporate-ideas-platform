<?php
// API маршруты для аутентификации
require_once 'controllers/AuthController.php';

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
?>