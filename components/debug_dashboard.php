<?php
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

echo "<h2>Диагностика дашборда</h2>";

try {
    // 1. Проверим структуру таблицы ideas
    echo "<h3>1. Структура таблицы ideas:</h3>";
    $stmt = $pdo->query("SHOW COLUMNS FROM ideas");
    $columns = $stmt->fetchAll();
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li><strong>" . $col['Field'] . "</strong> (" . $col['Type'] . ")</li>";
    }
    echo "</ul>";
    
    // 2. Проверим количество записей
    echo "<h3>2. Количество записей:</h3>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM ideas");
    $count = $stmt->fetch()['count'];
    echo "В таблице ideas: <strong>$count</strong> записей<br>";
    
    // 3. Если есть записи, покажем первые 3
    if ($count > 0) {
        echo "<h3>3. Примеры записей:</h3>";
        $stmt = $pdo->query("SELECT i.*, u.username FROM ideas i LEFT JOIN users u ON i.user_id = u.id LIMIT 3");
        $ideas = $stmt->fetchAll();
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr>";
        foreach (array_keys($ideas[0]) as $key) {
            if (!is_numeric($key)) echo "<th>$key</th>";
        }
        echo "</tr>";
        foreach ($ideas as $idea) {
            echo "<tr>";
            foreach ($idea as $key => $value) {
                if (!is_numeric($key)) echo "<td>" . htmlspecialchars($value) . "</td>";
            }
            echo "</tr>";
        }
        echo "</table>";
    }
    
    // 4. Проверим API напрямую
    echo "<h3>4. Тест API analytics.php:</h3>";
    
    // Симулируем POST запрос
    $_POST = [
        'action' => 'ideas_table',
        'page' => 1,
        'limit' => 5
    ];
    $_SERVER['REQUEST_METHOD'] = 'POST';
    
    // Включаем аналитику
    ob_start();
    include 'analytics.php';
    $apiResponse = ob_get_clean();
    
    echo "Ответ API: <br>";
    echo "<pre style='background: #f5f5f5; padding: 10px; border: 1px solid #ddd;'>";
    echo htmlspecialchars($apiResponse);
    echo "</pre>";
    
    // Проверим валидность JSON
    $decoded = json_decode($apiResponse, true);
    if ($decoded) {
        echo "<p>✅ JSON валиден</p>";
        if (isset($decoded['success'])) {
            echo "<p>Success: " . ($decoded['success'] ? '✅ true' : '❌ false') . "</p>";
        }
        if (isset($decoded['html'])) {
            echo "<p>HTML контент длина: " . strlen($decoded['html']) . " символов</p>";
        }
    } else {
        echo "<p>❌ JSON невалиден: " . json_last_error_msg() . "</p>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Ошибка: " . $e->getMessage() . "</p>";
}

echo "<br><a href='admin.html'>← Вернуться к дашборду</a>";
?>