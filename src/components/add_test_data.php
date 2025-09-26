<?php
require_once 'config.php';

try {
    // Проверим, есть ли пользователи
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $userCount = $stmt->fetch()['count'];

    if ($userCount == 0) {
        echo "Добавляем тестового пользователя...<br>";
        $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
        $stmt->execute(['admin', 'admin@test.com', password_hash('admin', PASSWORD_DEFAULT)]);
        echo "✅ Пользователь admin добавлен<br>";
    }

    // Получим ID пользователя
    $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
    $userId = $stmt->fetch()['id'];

    // Проверим, есть ли идеи
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ideas");
    $ideasCount = $stmt->fetch()['count'];

    if ($ideasCount == 0) {
        echo "Добавляем тестовые идеи...<br>";

        $testIdeas = [
            ['Улучшение системы отчетности', 'Добавить возможность экспорта отчетов в PDF', 'Автоматизация', 'На рассмотрении'],
            ['Внедрение чат-бота', 'Создать чат-бот для службы поддержки', 'Технологии', 'В работе'],
            ['Оптимизация процессов', 'Автоматизировать рутинные задачи', 'Процессы', 'Принято'],
            ['Мобильное приложение', 'Разработать мобильную версию системы', 'Технологии', 'На рассмотрении'],
            ['Обучение сотрудников', 'Провести семинары по новым технологиям', 'Образование', 'Отклонено']
        ];

        $stmt = $pdo->prepare("INSERT INTO ideas (user_id, title, description, category, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");

        foreach ($testIdeas as $idea) {
            $stmt->execute([$userId, $idea[0], $idea[1], $idea[2], $idea[3]]);
        }

        echo "✅ Добавлено " . count($testIdeas) . " тестовых идей<br>";
    }

    echo "<h3>Текущее состояние базы:</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    echo "Пользователей: " . $stmt->fetch()['count'] . "<br>";

    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ideas");
    echo "Идей: " . $stmt->fetch()['count'] . "<br>";

    echo "<br><a href='admin.html'>← Вернуться к дашборду</a>";

} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}
?>