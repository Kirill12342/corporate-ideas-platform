<?php
require_once 'config.php';

try {
    // Проверяем наличие таблиц
    $tables = ['ideas', 'users'];
    
    foreach ($tables as $table) {
        echo "<h3>Проверка таблицы: $table</h3>";
        
        // Проверяем существование таблицы
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$table]);
        $exists = $stmt->fetch();
        
        if ($exists) {
            echo "✅ Таблица $table существует<br>";
            
            // Получаем структуру
            $stmt = $pdo->query("DESCRIBE $table");
            $columns = $stmt->fetchAll();
            echo "Поля: ";
            foreach ($columns as $col) {
                echo $col['Field'] . " (" . $col['Type'] . "), ";
            }
            echo "<br>";
            
            // Получаем количество записей
            $stmt = $pdo->query("SELECT COUNT(*) as count FROM $table");
            $count = $stmt->fetch();
            echo "Записей: " . $count['count'] . "<br>";
            
            // Если есть записи, показываем первую
            if ($count['count'] > 0) {
                $stmt = $pdo->query("SELECT * FROM $table LIMIT 1");
                $first = $stmt->fetch();
                echo "Пример записи: <pre>" . print_r($first, true) . "</pre>";
            }
        } else {
            echo "❌ Таблица $table не существует<br>";
        }
        
        echo "<hr>";
    }
    
} catch (Exception $e) {
    echo "Ошибка: " . $e->getMessage();
}
?>