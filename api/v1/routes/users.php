<?php
// API маршруты для пользователей
require_once 'controllers/UsersController.php';

// GET /users/profile - профиль текущего пользователя
$router->addRoute('GET', '/users/profile', function() {
    $controller = new UsersController();
    $controller->getProfile();
});

// PUT /users/profile - обновление профиля
$router->addRoute('PUT', '/users/profile', function() {
    $controller = new UsersController();
    $controller->updateProfile();
});

// GET /users/{id} - публичная информация пользователя
$router->addRoute('GET', '/users/{id}', function($id) {
    $controller = new UsersController();
    $controller->getUserInfo($id);
});
?>