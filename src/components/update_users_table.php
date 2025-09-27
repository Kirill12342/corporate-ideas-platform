<?php
// Скрипт для добавления недостающих колонок в таблицу users
require_once 'config.php';

header('Content-Type: text/html; charset=utf-8');

try {
    echo "<h2>Обновление структуры таблицы users</h2>";

    // Проверяем существующие колонки
    $stmt = $pdo->query("DESCRIBE users");
    $existingColumns = array_column($stmt->fetchAll(PDO::FETCH_ASSOC), 'Field');

    echo "<h3>Существующие колонки:</h3>";
    echo "<ul>";
    foreach ($existingColumns as $column) {
        echo "<li>$column</li>";
    }
    echo "</ul>";

    // Добавляем недостающие колонки
    $columnsToAdd = [
        'department' => "ALTER TABLE users ADD COLUMN department VARCHAR(100) DEFAULT NULL AFTER email",
        'position' => "ALTER TABLE users ADD COLUMN position VARCHAR(100) DEFAULT NULL AFTER department",
        'last_login' => "ALTER TABLE users ADD COLUMN last_login DATETIME DEFAULT NULL AFTER position",
        'is_active' => "ALTER TABLE users ADD COLUMN is_active BOOLEAN DEFAULT TRUE AFTER last_login"
    ];

    echo "<h3>Добавление недостающих колонок:</h3>";

    foreach ($columnsToAdd as $columnName => $sql) {
        if (!in_array($columnName, $existingColumns)) {
            try {
                $pdo->exec($sql);
                echo "<p style='color: green;'>✅ Колонка '$columnName' успешно добавлена</p>";
            } catch (PDOException $e) {
                if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
                    echo "<p style='color: orange;'>⚠️ Колонка '$columnName' уже существует</p>";
                } else {
                    echo "<p style='color: red;'>❌ Ошибка при добавлении колонки '$columnName': " . $e->getMessage() . "</p>";
                }
            }
        } else {
            echo "<p style='color: blue;'>ℹ️ Колонка '$columnName' уже существует</p>";
        }
    }

    // Проверяем финальную структуру
    echo "<h3>Финальная структура таблицы users:</h3>";
    $stmt = $pdo->query("DESCRIBE users");
    $finalColumns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse; width: 100%;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($finalColumns as $column) {
        echo "<tr>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<p style='color: green; font-weight: bold;'>✅ Обновление завершено!</p>";

} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Ошибка: " . $e->getMessage() . "</p>";
}
?>
