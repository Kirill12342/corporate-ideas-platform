<?php
// Проверка структуры таблицы users
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

try {
    // Проверяем структуру таблицы users
    $stmt = $pdo->query("DESCRIBE users");
    $userColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Проверяем какие колонки есть
    $existingColumns = array_column($userColumns, 'Field');
    
    echo json_encode([
        'success' => true,
        'message' => 'Структура таблицы users',
        'columns' => $userColumns,
        'existing_columns' => $existingColumns,
        'missing_columns' => [
            'department' => !in_array('department', $existingColumns),
            'position' => !in_array('position', $existingColumns),
            'last_login' => !in_array('last_login', $existingColumns),
            'is_active' => !in_array('is_active', $existingColumns)
        ]
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    
} catch (Exception $e) {
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
