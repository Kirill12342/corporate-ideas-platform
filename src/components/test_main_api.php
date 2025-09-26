<?php
// Тест основного analytics.php API
header('Content-Type: text/html; charset=utf-8');

echo "<h2>🔍 Тест основного analytics.php</h2>";

// Симулируем POST запрос как делает JavaScript
$postData = json_encode([
    'action' => 'ideas_table',
    'page' => 1,
    'limit' => 10
]);

echo "<h3>1. Тестируем POST запрос к analytics.php</h3>";
echo "Отправляемые данные: <code>$postData</code><br><br>";

// Используем cURL для имитации AJAX запроса
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost/praktica_popov/components/analytics.php');
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($postData)
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h3>2. Результат запроса</h3>";
echo "HTTP код: <strong>$httpCode</strong><br>";
echo "Длина ответа: <strong>" . strlen($response) . "</strong> символов<br><br>";

echo "<h3>3. Содержимое ответа</h3>";
if (empty($response)) {
    echo "❌ Пустой ответ!<br>";
    echo "Возможные причины:<br>";
    echo "- Ошибка в analytics.php<br>";
    echo "- Проблема с URL или путем<br>";
    echo "- PHP ошибка<br>";
} else {
    echo "Первые 200 символов:<br>";
    echo "<pre>" . htmlspecialchars(substr($response, 0, 200)) . "</pre><br>";

    // Проверяем JSON
    $decoded = json_decode($response, true);
    if ($decoded === null) {
        echo "❌ Ответ не является валидным JSON<br>";
        echo "JSON ошибка: " . json_last_error_msg() . "<br><br>";
        echo "Полный ответ:<br>";
        echo "<pre style='background: #f8f9fa; padding: 10px; max-height: 300px; overflow: auto;'>" . htmlspecialchars($response) . "</pre>";
    } else {
        echo "✅ JSON валиден<br>";
        if (isset($decoded['success'])) {
            echo "Success: " . ($decoded['success'] ? '✅ true' : '❌ false') . "<br>";
        }
        if (isset($decoded['error'])) {
            echo "Ошибка: <strong>" . htmlspecialchars($decoded['error']) . "</strong><br>";
        }
        if (isset($decoded['html'])) {
            echo "HTML длина: " . strlen($decoded['html']) . " символов<br>";
        }

        echo "<details><summary>Полный JSON ответ (кликните для раскрытия)</summary>";
        echo "<pre style='background: #f8f9fa; padding: 10px; max-height: 400px; overflow: auto;'>" . json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "</pre>";
        echo "</details>";
    }
}

echo "<hr>";

echo "<h3>4. Альтернативный тест через включение файла</h3>";

try {
    // Симулируем переменные как при POST запросе
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_POST = [];

    // Мокаем file_get_contents для php://input
    $GLOBALS['mockPhpInput'] = $postData;

    ob_start();

    // Сохраняем текущие переменные
    $originalPost = $_POST;
    $originalServer = $_SERVER['REQUEST_METHOD'];

    // Захватываем вывод analytics.php
    include 'analytics.php';

    $output = ob_get_contents();
    ob_end_clean();

    // Восстанавливаем переменные
    $_POST = $originalPost;
    $_SERVER['REQUEST_METHOD'] = $originalServer;

    echo "Результат прямого включения:<br>";
    if (empty($output)) {
        echo "❌ Пустой вывод при включении файла<br>";
    } else {
        $decoded = json_decode($output, true);
        if ($decoded) {
            echo "✅ Прямое включение работает<br>";
            echo "Success: " . ($decoded['success'] ? '✅ true' : '❌ false') . "<br>";
        } else {
            echo "❌ Прямое включение вернуло невалидный JSON<br>";
            echo "<pre>" . htmlspecialchars($output) . "</pre>";
        }
    }

} catch (Exception $e) {
    echo "❌ Ошибка при прямом включении: " . $e->getMessage() . "<br>";
}

echo "<hr>";

echo "<h3>5. Рекомендации по исправлению</h3>";
if ($httpCode == 200 && !empty($response) && json_decode($response)) {
    echo "✅ <strong>API работает корректно!</strong><br>";
    echo "Проблема может быть в JavaScript дашборда.<br>";
    echo "Проверьте консоль браузера (F12) при открытии дашборда.<br>";
} else {
    echo "❌ <strong>Найдена проблема с API</strong><br>";
    echo "Нужно исправить analytics.php<br>";
}

echo "<br><a href='fix_dashboard.html'>← Вернуться к диагностике</a> | ";
echo "<a href='admin.html' target='_blank'>Открыть дашборд →</a>";
?>