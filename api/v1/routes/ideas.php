<?php
// API маршруты для идей
require_once 'controllers/IdeasController.php';

// GET /ideas - список идей
$router->addRoute('GET', '/ideas', function() {
    $controller = new IdeasController();
    $controller->getIdeas();
});

// GET /ideas/{id} - конкретная идея
$router->addRoute('GET', '/ideas/{id}', function($id) {
    $controller = new IdeasController();
    $controller->getIdea($id);
});

// POST /ideas - создание идеи
$router->addRoute('POST', '/ideas', function() {
    $controller = new IdeasController();
    $controller->createIdea();
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

// GET /ideas/top - топ идеи
$router->addRoute('GET', '/ideas/top', function() {
    $controller = new IdeasController();
    $controller->getTopIdeas();
});

// GET /ideas/my - мои идеи
$router->addRoute('GET', '/ideas/my', function() {
    $controller = new IdeasController();
    $controller->getMyIdeas();
});
?>