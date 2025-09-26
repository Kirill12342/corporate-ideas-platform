<?php
// API маршруты для голосования
require_once 'controllers/VotesController.php';

// POST /ideas/{id}/vote - голосование за идею
$router->addRoute('POST', '/ideas/{id}/vote', function($id) {
    $controller = new VotesController();
    $controller->voteForIdea($id);
});

// DELETE /ideas/{id}/vote - отмена голоса
$router->addRoute('DELETE', '/ideas/{id}/vote', function($id) {
    $controller = new VotesController();
    $controller->removeVote($id);
});

// GET /ideas/{id}/votes - статистика голосов по идее
$router->addRoute('GET', '/ideas/{id}/votes', function($id) {
    $controller = new VotesController();
    $controller->getVoteStats($id);
});
?>