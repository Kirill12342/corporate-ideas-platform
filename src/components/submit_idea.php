<?php
session_start();
require 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html?error=auth_required");
    exit();
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $category = $_POST['category'];

    // Валидация основных полей
    if (empty($title) || empty($description) || empty($category)) {
        $error = "Все поля должны быть заполнены";
    } else if (strlen($title) > 255) {
        $error = "Заголовок слишком длинный";
    } else if (strlen($description) > 1000) {
        $error = "Описание слишком длинное";
    } else {
        try {
            // Начинаем транзакцию
            $pdo->beginTransaction();

            // Добавляем идею
            $sql = "INSERT INTO ideas (user_id, title, description, category, status) VALUES (?, ?, ?, ?, 'На рассмотрении')";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute([$user_id, $title, $description, $category])) {
                $idea_id = $pdo->lastInsertId();

                // Обрабатываем загруженные файлы
                if (isset($_FILES['files']) && !empty($_FILES['files']['name'][0])) {
                    $upload_result = processFileUploads($_FILES['files'], $idea_id, $pdo);

                    if (!$upload_result['success']) {
                        throw new Exception($upload_result['message']);
                    }
                }

                // Подтверждаем транзакцию
                $pdo->commit();

                $success = "Идея успешно отправлена!";
                header("Location: user.php?success=" . urlencode($success));
                exit();
            } else {
                throw new Exception("Ошибка при сохранении идеи");
            }
        } catch (Exception $e) {
            // Откатываем транзакцию в случае ошибки
            $pdo->rollback();
            $error = $e->getMessage();
        }
    }

    if (isset($error)) {
        header("Location: idea.html?error=" . urlencode($error));
        exit();
    }
} else {
    header("Location: idea.html");
    exit();
}

function processFileUploads($files, $idea_id, $pdo) {
    $max_file_size = 10 * 1024 * 1024; // 10 МБ
    $allowed_types = [
        'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
        'application/pdf', 'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'text/plain', 'text/csv', 'application/zip', 'application/x-rar-compressed'
    ];

    $upload_dir = '../uploads/';

    // Создаем папку если не существует
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }

    $file_count = count($files['name']);

    for ($i = 0; $i < $file_count; $i++) {
        if ($files['error'][$i] === UPLOAD_ERR_OK) {
            $file_name = $files['name'][$i];
            $file_tmp = $files['tmp_name'][$i];
            $file_size = $files['size'][$i];
            $file_type = $files['type'][$i];

            // Валидация файла
            if ($file_size > $max_file_size) {
                return ['success' => false, 'message' => "Файл '$file_name' слишком большой. Максимальный размер: 10 МБ"];
            }

            if (!in_array($file_type, $allowed_types)) {
                return ['success' => false, 'message' => "Недопустимый тип файла: '$file_name'"];
            }

            // Генерируем безопасное имя файла
            $file_extension = pathinfo($file_name, PATHINFO_EXTENSION);
            $safe_filename = uniqid() . '_' . time() . '_' . $i . '.' . $file_extension;
            $file_path = $upload_dir . $safe_filename;

            // Перемещаем файл
            if (move_uploaded_file($file_tmp, $file_path)) {
                // Сохраняем информацию о файле в БД
                $sql = "INSERT INTO idea_attachments (idea_id, filename, original_name, file_path, file_type, file_size) VALUES (?, ?, ?, ?, ?, ?)";
                $stmt = $pdo->prepare($sql);

                if (!$stmt->execute([$idea_id, $safe_filename, $file_name, 'uploads/' . $safe_filename, $file_type, $file_size])) {
                    return ['success' => false, 'message' => "Ошибка при сохранении информации о файле '$file_name'"];
                }
            } else {
                return ['success' => false, 'message' => "Ошибка при загрузке файла '$file_name'"];
            }
        }
    }

    return ['success' => true];
}
?>