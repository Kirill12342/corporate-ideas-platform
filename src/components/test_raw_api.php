<?php
// Чистый тест API
header('Content-Type: text/plain');

echo "=== Тест Analytics API ===\n\n";

// 1. Проверим подключение к БД
try {
    require_once 'config.php';
    echo "✅ Подключение к БД: OK\n";

    // Проверим количество идей
    $stmt = $pdo->query("SELECT COUNT(*) FROM ideas");
    $count = $stmt->fetchColumn();
    echo "📊 Количество идей в БД: $count\n\n";

    if ($count == 0) {
        echo "❌ В БД нет идей! Добавьте тестовые данные.\n";
        echo "Откройте add_test_data.php для добавления данных.\n\n";
    }

} catch (Exception $e) {
    echo "❌ Ошибка БД: " . $e->getMessage() . "\n\n";
    exit;
}

// 2. Тестируем API через внутренний вызов
echo "=== Тест API через POST запрос ===\n";

// Симулируем POST запрос
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST = json_encode(['action' => 'ideas_table']);

// Захватываем вывод
ob_start();
$originalErrorReporting = error_reporting(E_ALL);

try {
    // Очищаем все заголовки
    if (function_exists('headers_sent') && !headers_sent()) {
        header_remove();
    }

    include 'analytics.php';
    $output = ob_get_contents();

} catch (Exception $e) {
    $output = "EXCEPTION: " . $e->getMessage();
} finally {
    error_reporting($originalErrorReporting);
    ob_end_clean();
}

echo "Длина ответа: " . strlen($output) . " символов\n";
echo "Первые 200 символов:\n";
echo substr($output, 0, 200) . "\n\n";

echo "Последние 50 символов:\n";
echo substr($output, -50) . "\n\n";

// Проверим, валиден ли JSON
$decoded = json_decode($output, true);
if ($decoded !== null) {
    echo "✅ JSON валиден\n";
    echo "Структура ответа:\n";
    print_r(array_keys($decoded));
} else {
    echo "❌ JSON невалиден: " . json_last_error_msg() . "\n";
    echo "Весь ответ:\n";
    echo $output . "\n";
}

echo "\n=== Конец теста ===\n";
?>