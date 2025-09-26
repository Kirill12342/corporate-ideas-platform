<?php
// Супер-простая диагностика без JSON
header('Content-Type: text/html; charset=utf-8');
echo "<h2>🔧 Диагностика проблемы дашборда</h2>";

echo "<h3>1. Проверка PHP</h3>";
echo "✅ PHP работает<br>";
echo "Версия PHP: " . phpversion() . "<br>";

echo "<h3>2. Проверка подключения к БД</h3>";
try {
    // Простая проверка файла config.php
    if (!file_exists('config.php')) {
        echo "❌ Файл config.php не найден<br>";
        die();
    }
    echo "✅ Файл config.php найден<br>";
    
    // Пытаемся подключиться
    require_once 'config.php';
    echo "✅ config.php загружен<br>";
    
    // Проверяем переменную $pdo
    if (!isset($pdo)) {
        echo "❌ Переменная \$pdo не создана<br>";
        die();
    }
    echo "✅ Подключение к БД создано<br>";
    
    // Простой запрос
    $result = $pdo->query("SELECT 1 as test");
    $row = $result->fetch();
    if ($row['test'] == 1) {
        echo "✅ БД отвечает на запросы<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка БД: " . $e->getMessage() . "<br>";
    echo "Проверьте что XAMPP запущен и база данных 'stuffVoise' создана<br>";
    die();
}

echo "<h3>3. Проверка таблиц</h3>";
try {
    // Проверяем таблицу users
    $stmt = $pdo->query("SELECT COUNT(*) FROM users");
    $userCount = $stmt->fetchColumn();
    echo "👥 Пользователей: $userCount<br>";
    
    // Проверяем таблицу ideas
    $stmt = $pdo->query("SELECT COUNT(*) FROM ideas");
    $ideaCount = $stmt->fetchColumn();
    echo "💡 Идей: $ideaCount<br>";
    
    if ($ideaCount == 0) {
        echo "<div style='background: #fff3cd; padding: 10px; margin: 10px 0; border: 1px solid #ffeaa7;'>";
        echo "⚠️ <strong>Проблема найдена!</strong> В таблице ideas нет записей.<br>";
        echo "Это объясняет почему дашборд показывает 'Нет данных для отображения'.<br>";
        echo "</div>";
        
        echo "<h4>Автоматическое исправление:</h4>";
        
        // Создаем пользователя если его нет
        if ($userCount == 0) {
            $stmt = $pdo->prepare("INSERT INTO users (username, email, password, created_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute(['admin', 'admin@test.com', password_hash('admin123', PASSWORD_DEFAULT)]);
            echo "✅ Создан пользователь admin<br>";
        }
        
        // Получаем ID пользователя
        $stmt = $pdo->query("SELECT id FROM users LIMIT 1");
        $userId = $stmt->fetchColumn();
        
        // Создаем идеи
        $testIdeas = [
            ['Улучшение интерфейса', 'Сделать UI более современным и удобным', 'Дизайн', 'На рассмотрении'],
            ['Мобильная версия', 'Создать мобильное приложение для системы', 'Технологии', 'В работе'],
            ['Автоматизация отчетов', 'Внедрить автоматическое создание отчетов', 'Автоматизация', 'Принято'],
            ['Интеграция с CRM', 'Подключить систему к существующей CRM', 'Интеграция', 'На рассмотрении'],
            ['Улучшение поиска', 'Добавить фильтры и умный поиск', 'Функциональность', 'Отклонено']
        ];
        
        $stmt = $pdo->prepare("INSERT INTO ideas (user_id, title, description, category, status, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        
        foreach ($testIdeas as $idea) {
            $stmt->execute([$userId, $idea[0], $idea[1], $idea[2], $idea[3]]);
        }
        
        echo "✅ Добавлено " . count($testIdeas) . " тестовых идей<br>";
        
        // Проверяем результат
        $stmt = $pdo->query("SELECT COUNT(*) FROM ideas");
        $newCount = $stmt->fetchColumn();
        echo "📊 Теперь идей в БД: $newCount<br>";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка при работе с таблицами: " . $e->getMessage() . "<br>";
}

echo "<h3>4. Тест выборки данных</h3>";
try {
    $stmt = $pdo->query("SELECT i.id, i.title, i.category, i.status, u.username, i.created_at 
                         FROM ideas i 
                         LEFT JOIN users u ON i.user_id = u.id 
                         ORDER BY i.created_at DESC 
                         LIMIT 3");
    $ideas = $stmt->fetchAll();
    
    if (empty($ideas)) {
        echo "❌ Запрос не вернул данных<br>";
    } else {
        echo "✅ Запрос вернул " . count($ideas) . " записей<br>";
        echo "<table border='1' style='border-collapse: collapse; margin: 10px 0; width: 100%;'>";
        echo "<tr style='background: #f8f9fa;'><th>ID</th><th>Название</th><th>Категория</th><th>Статус</th><th>Автор</th><th>Дата</th></tr>";
        
        foreach ($ideas as $idea) {
            echo "<tr>";
            echo "<td>" . $idea['id'] . "</td>";
            echo "<td>" . htmlspecialchars($idea['title']) . "</td>";
            echo "<td>" . htmlspecialchars($idea['category']) . "</td>";
            echo "<td>" . htmlspecialchars($idea['status']) . "</td>";
            echo "<td>" . htmlspecialchars($idea['username']) . "</td>";
            echo "<td>" . date('d.m.Y H:i', strtotime($idea['created_at'])) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
    
} catch (Exception $e) {
    echo "❌ Ошибка выборки: " . $e->getMessage() . "<br>";
}

echo "<h3>5. Результат диагностики</h3>";
$stmt = $pdo->query("SELECT COUNT(*) FROM ideas");
$finalCount = $stmt->fetchColumn();

if ($finalCount > 0) {
    echo "<div style='background: #d4edda; padding: 15px; margin: 10px 0; border: 1px solid #c3e6cb; border-radius: 5px;'>";
    echo "🎉 <strong>Проблема исправлена!</strong><br>";
    echo "В базе данных теперь есть $finalCount идей.<br>";
    echo "Дашборд должен теперь работать корректно.<br>";
    echo "</div>";
    
    echo "<h4>Следующие шаги:</h4>";
    echo "<ol>";
    echo "<li><a href='admin.html' target='_blank' style='color: #007bff; text-decoration: none;'>🚀 Открыть дашборд</a> (в новой вкладке)</li>";
    echo "<li>Проверить что таблица идей заполнилась</li>";
    echo "<li>Если проблемы остались - проверить консоль браузера (F12)</li>";
    echo "</ol>";
} else {
    echo "<div style='background: #f8d7da; padding: 15px; margin: 10px 0; border: 1px solid #f5c6cb; border-radius: 5px;'>";
    echo "❌ <strong>Проблема не решена</strong><br>";
    echo "В БД по-прежнему нет идей. Возможно проблема с правами доступа к БД.<br>";
    echo "</div>";
}

echo "<hr>";
echo "<p><a href='fix_dashboard.html'>← Вернуться к диагностике</a></p>";
?>