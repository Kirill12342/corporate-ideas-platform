<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Проверка аутентификации
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit();
}

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Неверный метод запроса']);
    exit();
}

// Проверка наличия файлов
if (!isset($_FILES['files'])) {
    echo json_encode(['success' => false, 'message' => 'Файлы не загружены']);
    exit();
}

// Конфигурация загрузки
$max_file_size = 10 * 1024 * 1024; // 10 МБ
$allowed_types = [
    // Изображения
    'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
    // Документы
    'application/pdf', 'application/msword', 
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'application/vnd.ms-excel',
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    'application/vnd.ms-powerpoint',
    'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'text/plain', 'text/csv',
    // Архивы
    'application/zip', 'application/x-rar-compressed'
];

$upload_dir = '../uploads/';
$uploaded_files = [];

// Создание папки если не существует
if (!is_dir($upload_dir)) {
    mkdir($upload_dir, 0755, true);
}

try {
    $files = $_FILES['files'];
    
    // Обработка множественной загрузки
    if (is_array($files['name'])) {
        $file_count = count($files['name']);
        
        for ($i = 0; $i < $file_count; $i++) {
            if ($files['error'][$i] === UPLOAD_ERR_OK) {
                $result = processFile([
                    'name' => $files['name'][$i],
                    'tmp_name' => $files['tmp_name'][$i],
                    'size' => $files['size'][$i],
                    'type' => $files['type'][$i]
                ], $upload_dir, $max_file_size, $allowed_types);
                
                if ($result['success']) {
                    $uploaded_files[] = $result['file_info'];
                } else {
                    echo json_encode(['success' => false, 'message' => $result['message']]);
                    exit();
                }
            }
        }
    } else {
        // Обработка одиночной загрузки
        if ($files['error'] === UPLOAD_ERR_OK) {
            $result = processFile($files, $upload_dir, $max_file_size, $allowed_types);
            
            if ($result['success']) {
                $uploaded_files[] = $result['file_info'];
            } else {
                echo json_encode(['success' => false, 'message' => $result['message']]);
                exit();
            }
        }
    }
    
    echo json_encode([
        'success' => true, 
        'message' => 'Файлы успешно загружены',
        'files' => $uploaded_files
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'message' => 'Ошибка при загрузке: ' . $e->getMessage()]);
}

function processFile($file, $upload_dir, $max_file_size, $allowed_types) {
    // Проверка размера файла
    if ($file['size'] > $max_file_size) {
        return ['success' => false, 'message' => 'Файл ' . $file['name'] . ' слишком большой. Максимальный размер: 10 МБ'];
    }
    
    // Проверка типа файла
    if (!in_array($file['type'], $allowed_types)) {
        return ['success' => false, 'message' => 'Недопустимый тип файла: ' . $file['name']];
    }
    
    // Генерация безопасного имени файла
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $safe_filename = uniqid() . '_' . time() . '.' . $file_extension;
    $file_path = $upload_dir . $safe_filename;
    
    // Перемещение загруженного файла
    if (move_uploaded_file($file['tmp_name'], $file_path)) {
        return [
            'success' => true,
            'file_info' => [
                'filename' => $safe_filename,
                'original_name' => $file['name'],
                'file_path' => 'uploads/' . $safe_filename,
                'file_type' => $file['type'],
                'file_size' => $file['size']
            ]
        ];
    } else {
        return ['success' => false, 'message' => 'Ошибка при сохранении файла: ' . $file['name']];
    }
}

function formatFileSize($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $unit_index = 0;
    
    while ($size >= 1024 && $unit_index < count($units) - 1) {
        $size /= 1024;
        $unit_index++;
    }
    
    return round($size, 2) . ' ' . $units[$unit_index];
}
?>