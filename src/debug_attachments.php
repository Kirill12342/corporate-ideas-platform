<?php
// Скрипт для создания таблицы и проверки файлов

require_once 'components/config.php';

echo "<h2>Создание таблицы idea_attachments</h2>";

try {
    // Создаем таблицу
    $sql = "CREATE TABLE IF NOT EXISTS idea_attachments (
        id INT AUTO_INCREMENT PRIMARY KEY,
        idea_id INT NOT NULL,
        filename VARCHAR(255) NOT NULL,
        original_name VARCHAR(255) NOT NULL,
        file_path VARCHAR(500) NOT NULL,
        file_type VARCHAR(100) NOT NULL,
        file_size INT NOT NULL,
        upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (idea_id) REFERENCES ideas(id) ON DELETE CASCADE,
        INDEX idx_idea_id (idea_id)
    )";

    $pdo->exec($sql);
    echo "<p style='color: green;'>✓ Таблица idea_attachments успешно создана или уже существует</p>";

    // Проверяем структуру таблицы
    $stmt = $pdo->query("DESCRIBE idea_attachments");
    $columns = $stmt->fetchAll();

    echo "<h3>Структура таблицы:</h3>";
    echo "<table border='1'><tr><th>Поле</th><th>Тип</th><th>Null</th><th>Ключ</th></tr>";
    foreach ($columns as $column) {
        echo "<tr><td>{$column['Field']}</td><td>{$column['Type']}</td><td>{$column['Null']}</td><td>{$column['Key']}</td></tr>";
    }
    echo "</table>";

    // Проверяем количество записей
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM idea_attachments");
    $result = $stmt->fetch();
    echo "<p>Количество файловых вложений в базе: <strong>{$result['count']}</strong></p>";

    // Показываем последние 5 вложений
    if ($result['count'] > 0) {
        $stmt = $pdo->query("SELECT * FROM idea_attachments ORDER BY upload_date DESC LIMIT 5");
        $attachments = $stmt->fetchAll();

        echo "<h3>Последние загруженные файлы:</h3>";
        echo "<table border='1'><tr><th>ID</th><th>Idea ID</th><th>Оригинальное имя</th><th>Путь к файлу</th><th>Тип</th><th>Размер</th><th>Дата загрузки</th></tr>";
        foreach ($attachments as $attachment) {
            echo "<tr>";
            echo "<td>{$attachment['id']}</td>";
            echo "<td>{$attachment['idea_id']}</td>";
            echo "<td>{$attachment['original_name']}</td>";
            echo "<td>{$attachment['file_path']}</td>";
            echo "<td>{$attachment['file_type']}</td>";
            echo "<td>{$attachment['file_size']}</td>";
            echo "<td>{$attachment['upload_date']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }

    // Проверяем существование папки uploads
    $uploadDir = 'uploads/';
    if (is_dir($uploadDir)) {
        echo "<p style='color: green;'>✓ Папка uploads существует</p>";

        $files = scandir($uploadDir);
        $fileCount = count($files) - 2; // Убираем . и ..
        echo "<p>Количество файлов в папке uploads: <strong>$fileCount</strong></p>";

        if ($fileCount > 0) {
            echo "<h3>Файлы в папке uploads:</h3>";
            echo "<ul>";
            foreach ($files as $file) {
                if ($file != '.' && $file != '..') {
                    $filePath = $uploadDir . $file;
                    $fileSize = filesize($filePath);
                    $isImage = getimagesize($filePath) !== false;
                    echo "<li>$file (" . number_format($fileSize) . " байт)" . ($isImage ? " [ИЗОБРАЖЕНИЕ]" : "") . "</li>";
                }
            }
            echo "</ul>";
        }
    } else {
        echo "<p style='color: red;'>✗ Папка uploads не существует</p>";
        if (mkdir($uploadDir, 0755, true)) {
            echo "<p style='color: green;'>✓ Папка uploads создана</p>";
        } else {
            echo "<p style='color: red;'>✗ Не удалось создать папку uploads</p>";
        }
    }

} catch (Exception $e) {
    echo "<p style='color: red;'>Ошибка: " . $e->getMessage() . "</p>";
}

echo "<br><p><a href='components/idea.html'>Перейти к форме добавления идеи</a></p>";
echo "<p><a href='components/admin.php'>Перейти в админ-панель</a></p>";
echo "<p><a href='components/user.php'>Перейти в пользовательскую панель</a></p>";
?>