<?php
// Простой тест подключения к базе данных
header('Content-Type: application/json; charset=utf-8');

try {
    require_once 'config.php';

    // Проверяем, что переменные определены
    if (!isset($host) || !isset($dbname) || !isset($user) || !isset($pass)) {
        throw new Exception('Переменные подключения к БД не определены');
    }

    // Пытаемся подключиться
    $testPdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $pass);
    $testPdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Проверяем существование таблиц
    $tables = $testPdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

    echo json_encode([
        'success' => true,
        'message' => 'Подключение к БД успешно',
        'host' => $host,
        'database' => $dbname,
        'tables' => $tables
    ]);

} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => __FILE__,
        'line' => __LINE__
    ]);
}
?>
