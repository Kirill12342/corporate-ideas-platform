<?php
require_once 'admin_auth.php';
require_once 'config.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    $action = $input['action'] ?? '';

    try {
        switch ($action) {
            case 'get_users':
                $page = intval($input['page'] ?? 1);
                $limit = intval($input['limit'] ?? 20);
                $search = $input['search'] ?? '';
                $sort = $input['sort'] ?? 'created_at';
                $order = $input['order'] ?? 'DESC';

                $offset = ($page - 1) * $limit;

                $whereClause = '';
                $params = [];

                if ($search) {
                    $whereClause = "WHERE u.fullname LIKE :search OR u.email LIKE :search OR u.department LIKE :search";
                    $params[':search'] = "%$search%";
                }

                // Получаем пользователей с статистикой
                $sql = "SELECT 
                    u.id,
                    u.fullname,
                    u.email,
                    u.department,
                    u.position,
                    u.created_at,
                    u.last_login,
                    u.is_active,
                    COUNT(i.id) as ideas_count,
                    COALESCE(SUM(i.likes_count), 0) as total_likes,
                    COALESCE(SUM(i.total_score), 0) as total_score,
                    COUNT(CASE WHEN i.status = 'Принято' THEN 1 END) as approved_ideas
                FROM users u
                LEFT JOIN ideas i ON u.id = i.user_id
                $whereClause
                GROUP BY u.id
                ORDER BY $sort $order
                LIMIT :limit OFFSET :offset";

                $stmt = $pdo->prepare($sql);
                $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

                foreach ($params as $key => $value) {
                    $stmt->bindValue($key, $value);
                }

                $stmt->execute();
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Получаем общее количество пользователей
                $countSql = "SELECT COUNT(DISTINCT u.id) FROM users u $whereClause";
                $countStmt = $pdo->prepare($countSql);
                foreach ($params as $key => $value) {
                    $countStmt->bindValue($key, $value);
                }
                $countStmt->execute();
                $total = $countStmt->fetchColumn();

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'users' => $users,
                        'pagination' => [
                            'current_page' => $page,
                            'total_pages' => ceil($total / $limit),
                            'total_records' => $total,
                            'per_page' => $limit
                        ]
                    ]
                ]);
                break;

            case 'update_user_status':
                $userId = intval($input['user_id']);
                $isActive = $input['is_active'] ? 1 : 0;

                $stmt = $pdo->prepare("UPDATE users SET is_active = :is_active WHERE id = :id");
                $stmt->execute([':is_active' => $isActive, ':id' => $userId]);

                echo json_encode(['success' => true, 'message' => 'Статус пользователя обновлен']);
                break;

            case 'delete_user':
                $userId = intval($input['user_id']);

                // Сначала переносим идеи пользователя на системного пользователя или удаляем
                $stmt = $pdo->prepare("UPDATE ideas SET user_id = 1 WHERE user_id = :user_id");
                $stmt->execute([':user_id' => $userId]);

                // Удаляем пользователя
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
                $stmt->execute([':id' => $userId]);

                echo json_encode(['success' => true, 'message' => 'Пользователь удален']);
                break;

            case 'get_user_details':
                $userId = intval($input['user_id']);

                $stmt = $pdo->prepare("
                    SELECT u.*, 
                        COUNT(i.id) as ideas_count,
                        COUNT(CASE WHEN i.status = 'Принято' THEN 1 END) as approved_ideas,
                        COUNT(CASE WHEN i.status = 'В работе' THEN 1 END) as in_progress_ideas,
                        COUNT(CASE WHEN i.status = 'Отклонено' THEN 1 END) as rejected_ideas,
                        COALESCE(SUM(i.likes_count), 0) as total_likes,
                        COALESCE(SUM(i.total_score), 0) as total_score
                    FROM users u
                    LEFT JOIN ideas i ON u.id = i.user_id
                    WHERE u.id = :id
                    GROUP BY u.id
                ");
                $stmt->execute([':id' => $userId]);
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user) {
                    // Получаем последние идеи пользователя
                    $stmt = $pdo->prepare("
                        SELECT id, title, status, likes_count, total_score, created_at
                        FROM ideas 
                        WHERE user_id = :user_id 
                        ORDER BY created_at DESC 
                        LIMIT 10
                    ");
                    $stmt->execute([':user_id' => $userId]);
                    $user['recent_ideas'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

                    echo json_encode(['success' => true, 'data' => $user]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Пользователь не найден']);
                }
                break;

            case 'export_users':
                $format = $input['format'] ?? 'csv';

                $stmt = $pdo->query("
                    SELECT 
                        u.id,
                        u.fullname,
                        u.email,
                        u.department,
                        u.position,
                        u.created_at,
                        u.last_login,
                        u.is_active,
                        COUNT(i.id) as ideas_count,
                        COALESCE(SUM(i.likes_count), 0) as total_likes
                    FROM users u
                    LEFT JOIN ideas i ON u.id = i.user_id
                    GROUP BY u.id
                    ORDER BY u.created_at DESC
                ");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if ($format === 'csv') {
                    $filename = 'users_export_' . date('Y-m-d_H-i-s') . '.csv';
                    $filepath = '../uploads/' . $filename;

                    $file = fopen($filepath, 'w');
                    fputcsv($file, ['ID', 'ФИО', 'Email', 'Отдел', 'Должность', 'Дата регистрации', 'Последний вход', 'Активен', 'Количество идей', 'Всего лайков']);

                    foreach ($users as $user) {
                        fputcsv($file, [
                            $user['id'],
                            $user['fullname'],
                            $user['email'],
                            $user['department'],
                            $user['position'],
                            $user['created_at'],
                            $user['last_login'],
                            $user['is_active'] ? 'Да' : 'Нет',
                            $user['ideas_count'],
                            $user['total_likes']
                        ]);
                    }
                    fclose($file);

                    echo json_encode(['success' => true, 'file_url' => '../uploads/' . $filename]);
                }
                break;

            default:
                echo json_encode(['success' => false, 'error' => 'Неизвестное действие']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Метод не поддерживается']);
}
?>
