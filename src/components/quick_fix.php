<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Быстрая диагностика</h2>";

// 1. Проверим, работает ли подключение к БД
echo "<h3>1. Тест подключения к БД</h3>";
try {
    require_once 'config.php';
    echo "✅ config.php загружен<br>";
    
    $stmt = $pdo->query("SELECT 1");
    echo "✅ Подключение к БД работает<br>";
    
    // Проверим таблицы
    $tables = ['users', 'ideas'];
    foreach ($tables as $table) {
        try {
            $stmt = $pdo->query("SELECT COUNT(*) FROM $table");
            $count = $stmt->fetchColumn();
            echo "📊 Таблица $table: $count записей<br>";
        } catch (Exception $e) {
            echo "❌ Таблица $table: " . $e->getMessage() . "<br>";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка: " . $e->getMessage() . "<br>";
    echo "Убедитесь что XAMPP запущен и БД 'stuffVoice' создана.<br>";
}

// 2. Проверим структуру таблицы ideas
echo "<h3>2. Структура таблицы ideas</h3>";
try {
    $stmt = $pdo->query("SHOW COLUMNS FROM ideas");
    $columns = $stmt->fetchAll();
    echo "<ul>";
    foreach ($columns as $col) {
        echo "<li><strong>" . $col['Field'] . "</strong> (" . $col['Type'] . ")</li>";
    }
    echo "</ul>";
} catch (Exception $e) {
    echo "❌ Ошибка получения структуры: " . $e->getMessage() . "<br>";
}

// 3. Если нет данных, создадим их
$stmt = $pdo->query("SELECT COUNT(*) FROM ideas");
$ideasCount = $stmt->fetchColumn();

if ($ideasCount == 0) {
    echo "<h3>3. Создание тестовых данных</h3>";
    try {
        // Создаем пользователя если его нет
        $stmt = $pdo->query("SELECT COUNT(*) FROM users");
        $userCount = $stmt->fetchColumn();
        
        if ($userCount == 0) {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password) VALUES (?, ?, ?)");
            $stmt->execute(['testuser', 'test@example.com', password_hash('test123', PASSWORD_DEFAULT)]);
            echo "✅ Создан тестовый пользователь<br>";
        }
        
        // Получаем ID пользователя
        $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
        $userId = $stmt->fetchColumn();
        
        // Создаем тестовые идеи
        $ideas = [
            ['Улучшение UI', 'Сделать интерфейс более современным', 'Дизайн', 'На рассмотрении'],
            ['Автоматизация процессов', 'Внедрить автоматизацию рутинных задач', 'Процессы', 'В работе'],
            ['Мобильное приложение', 'Создать мобильную версию системы', 'Технологии', 'Принято']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO ideas (user_id, title, description, category, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        
        foreach ($ideas as $idea) {
            $stmt->execute([$userId, $idea[0], $idea[1], $idea[2], $idea[3]]);
        }
        
        echo "✅ Создано " . count($ideas) . " тестовых идей<br>";
        
    } catch (Exception $e) {
        echo "❌ Ошибка создания данных: " . $e->getMessage() . "<br>";
    }
}

// 4. Простой тест выборки
echo "<h3>4. Тест выборки идей</h3>";
try {
    $stmt = $pdo->query("SELECT i.*, u.username FROM ideas i LEFT JOIN users u ON i.user_id = u.id LIMIT 3");
    $ideas = $stmt->fetchAll();
    
    if (empty($ideas)) {
        echo "❌ Нет идей в БД<br>";
    } else {
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
        echo "<tr><th>ID</th><th>Заголовок</th><th>Категория</th><th>Статус</th><th>Пользователь</th></tr>";
        foreach ($ideas as $idea) {
            $title = $idea['title'] ?? $idea['idea'] ?? 'Без названия';
            echo "<tr>";
            echo "<td>" . ($idea['id'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($title) . "</td>";
            echo "<td>" . htmlspecialchars($idea['category'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($idea['status'] ?? 'N/A') . "</td>";
            echo "<td>" . htmlspecialchars($idea['username'] ?? 'N/A') . "</td>";
            echo "</tr>";
        }
        echo "</table>";
        echo "✅ Данные найдены и отображены корректно<br>";
    }
} catch (Exception $e) {
    echo "❌ Ошибка выборки: " . $e->getMessage() . "<br>";
}

echo "<hr>";
echo "<p><strong>Если все тесты выше прошли успешно, попробуйте:</strong></p>";
echo "<ul>";
echo "<li><a href='admin.html' target='_blank'>Открыть дашборд</a> (откроется в новой вкладке)</li>";
echo "<li><a href='fix_dashboard.html'>Вернуться к диагностике</a></li>";
echo "</ul>";
?>