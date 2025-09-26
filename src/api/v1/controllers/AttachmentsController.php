<?php

// Контроллер для работы с файловыми вложениями API
class AttachmentsController
{
    private $db;
    private $uploadPath;
    private $allowedTypes;
    private $maxFileSize;

    public function __construct()
    {
        $this->db = APIDatabase::getConnection();
        $this->uploadPath = '../../uploads/attachments/';
        $this->allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'text/csv'
        ];
        $this->maxFileSize = 10 * 1024 * 1024; // 10MB

        // Создаем директорию если не существует
        if (!is_dir($this->uploadPath)) {
            mkdir($this->uploadPath, 0755, true);
        }
    }

    public function uploadAttachment()
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Проверяем наличие файла
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                Response::error('VALIDATION_ERROR', 'Файл не выбран или произошла ошибка при загрузке');
                return;
            }

            $file = $_FILES['file'];
            $ideaId = $_POST['idea_id'] ?? null;

            // Валидация
            $errors = [];

            if (!$ideaId || !is_numeric($ideaId)) {
                $errors['idea_id'] = 'ID идеи обязателен';
            }

            if ($file['size'] > $this->maxFileSize) {
                $errors['file'] = 'Размер файла не должен превышать 10MB';
            }

            if (!in_array($file['type'], $this->allowedTypes)) {
                $errors['file'] = 'Недопустимый тип файла';
            }

            // Проверяем существование идеи
            if ($ideaId) {
                $stmt = $this->db->prepare("SELECT id, user_id FROM ideas WHERE id = ?");
                $stmt->execute([$ideaId]);
                $idea = $stmt->fetch();

                if (!$idea) {
                    $errors['idea_id'] = 'Идея не найдена';
                } elseif ($idea['user_id'] != $user['id'] && $user['role'] !== 'admin') {
                    Response::error('ACCESS_DENIED', 'Недостаточно прав для добавления вложений к этой идее', [], 403);
                    return;
                }
            }

            if (!empty($errors)) {
                Response::error('VALIDATION_ERROR', 'Ошибка валидации данных', $errors);
                return;
            }

            // Генерируем уникальное имя файла
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid('attachment_') . '.' . $extension;
            $filepath = $this->uploadPath . $filename;

            // Перемещаем файл
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                Response::error('UPLOAD_ERROR', 'Не удалось сохранить файл');
                return;
            }

            // Сохраняем информацию в БД
            $sql = "INSERT INTO attachments (idea_id, user_id, original_name, filename, file_path, file_size, mime_type, created_at) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $ideaId,
                $user['id'],
                $file['name'],
                $filename,
                $filepath,
                $file['size'],
                $file['type']
            ]);

            $attachmentId = $this->db->lastInsertId();

            // Получаем полную информацию о вложении
            $stmt = $this->db->prepare("
                SELECT a.*, u.username 
                FROM attachments a 
                JOIN users u ON a.user_id = u.id 
                WHERE a.id = ?
            ");
            $stmt->execute([$attachmentId]);
            $attachment = $stmt->fetch();

            Response::success($attachment, 'Файл успешно загружен');

        } catch (Exception $e) {
            error_log("Upload attachment error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }

    public function getAttachment($id)
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Получаем вложение
            $stmt = $this->db->prepare("
                SELECT a.*, u.username, i.title as idea_title
                FROM attachments a 
                JOIN users u ON a.user_id = u.id 
                JOIN ideas i ON a.idea_id = i.id
                WHERE a.id = ?
            ");
            $stmt->execute([$id]);
            $attachment = $stmt->fetch();

            if (!$attachment) {
                Response::error('NOT_FOUND', 'Вложение не найдено', [], 404);
                return;
            }

            Response::success($attachment);

        } catch (Exception $e) {
            error_log("Get attachment error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }

    public function downloadAttachment($id)
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Получаем вложение
            $stmt = $this->db->prepare("SELECT * FROM attachments WHERE id = ?");
            $stmt->execute([$id]);
            $attachment = $stmt->fetch();

            if (!$attachment) {
                Response::error('NOT_FOUND', 'Вложение не найдено', [], 404);
                return;
            }

            $filePath = $attachment['file_path'];

            if (!file_exists($filePath)) {
                Response::error('NOT_FOUND', 'Файл не найден на сервере', [], 404);
                return;
            }

            // Отдаем файл для скачивания
            header('Content-Type: ' . $attachment['mime_type']);
            header('Content-Length: ' . $attachment['file_size']);
            header('Content-Disposition: attachment; filename="' . $attachment['original_name'] . '"');
            header('Cache-Control: must-revalidate');
            header('Pragma: public');

            readfile($filePath);
            exit;

        } catch (Exception $e) {
            error_log("Download attachment error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }

    public function deleteAttachment($id)
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Получаем вложение
            $stmt = $this->db->prepare("SELECT * FROM attachments WHERE id = ?");
            $stmt->execute([$id]);
            $attachment = $stmt->fetch();

            if (!$attachment) {
                Response::error('NOT_FOUND', 'Вложение не найдено', [], 404);
                return;
            }

            // Проверяем права доступа
            if ($attachment['user_id'] != $user['id'] && $user['role'] !== 'admin') {
                Response::error('ACCESS_DENIED', 'Недостаточно прав для удаления вложения', [], 403);
                return;
            }

            // Удаляем файл с диска
            if (file_exists($attachment['file_path'])) {
                unlink($attachment['file_path']);
            }

            // Удаляем запись из БД
            $stmt = $this->db->prepare("DELETE FROM attachments WHERE id = ?");
            $stmt->execute([$id]);

            Response::success(['message' => 'Вложение успешно удалено']);

        } catch (Exception $e) {
            error_log("Delete attachment error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }

    public function getIdeaAttachments($ideaId)
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Получаем все вложения для идеи
            $stmt = $this->db->prepare("
                SELECT a.id, a.original_name, a.file_size, a.mime_type, 
                       a.created_at, u.username, a.user_id
                FROM attachments a 
                JOIN users u ON a.user_id = u.id 
                WHERE a.idea_id = ?
                ORDER BY a.created_at DESC
            ");
            $stmt->execute([$ideaId]);
            $attachments = $stmt->fetchAll();

            Response::success(['attachments' => $attachments, 'count' => count($attachments)]);

        } catch (Exception $e) {
            error_log("Get idea attachments error: " . $e->getMessage());
            Response::error('INTERNAL_ERROR', 'Внутренняя ошибка сервера', [], 500);
        }
    }
}

?>