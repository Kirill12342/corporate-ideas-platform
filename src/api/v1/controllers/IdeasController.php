<?php
// Контроллер для работы с идеями через API

class IdeasController
{
    private $db;

    public function __construct()
    {
        $this->db = APIDatabase::getConnection();
    }

    public function getIdeas()
    {
        $user = JWTAuth::requireAuth();

        // Параметры запроса
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        // Фильтры
        $status = $_GET['status'] ?? '';
        $category = $_GET['category'] ?? '';
        $author_id = $_GET['author_id'] ?? '';
        $search = $_GET['search'] ?? '';
        $sort_by = $_GET['sort_by'] ?? 'created_at';
        $sort_order = $_GET['sort_order'] ?? 'desc';

        // Построение WHERE условий
        $where_conditions = [];
        $params = [];

        if ($status && in_array($status, ['pending', 'approved', 'rejected'])) {
            $where_conditions[] = "status = :status";
            $params['status'] = $status;
        }

        if ($category) {
            $where_conditions[] = "category = :category";
            $params['category'] = $category;
        }

        if ($author_id) {
            $where_conditions[] = "user_id = :author_id";
            $params['author_id'] = $author_id;
        }

        if ($search) {
            $where_conditions[] = "(title LIKE :search OR description LIKE :search)";
            $params['search'] = "%$search%";
        }

        $where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

        // Валидация сортировки
        $allowed_sorts = ['created_at', 'likes_count', 'popularity_rank', 'title'];
        if (!in_array($sort_by, $allowed_sorts)) {
            $sort_by = 'created_at';
        }

        $sort_order = strtoupper($sort_order) === 'ASC' ? 'ASC' : 'DESC';

        try {
            // Подсчет общего количества
            $count_sql = "SELECT COUNT(*) as total FROM ideas i $where_clause";
            $count_stmt = $this->db->prepare($count_sql);
            $count_stmt->execute($params);
            $total = $count_stmt->fetch()['total'];

            // Получение данных
            $sql = "
                SELECT 
                    i.id,
                    i.title,
                    i.description,
                    i.category,
                    i.status,
                    i.created_at,
                    i.updated_at,
                    i.likes_count,
                    i.dislikes_count,
                    i.popularity_rank,
                    u.username as author_name,
                    u.id as author_id
                FROM ideas i
                LEFT JOIN users u ON i.user_id = u.id
                $where_clause
                ORDER BY i.$sort_by $sort_order
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($sql);

            // Привязка параметров
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

            $stmt->execute();
            $ideas = $stmt->fetchAll();

            // Форматирование дат
            foreach ($ideas as &$idea) {
                $idea['created_at'] = date('Y-m-d H:i:s', strtotime($idea['created_at']));
                $idea['updated_at'] = date('Y-m-d H:i:s', strtotime($idea['updated_at']));
                $idea['likes_count'] = (int)$idea['likes_count'];
                $idea['dislikes_count'] = (int)$idea['dislikes_count'];
                $idea['popularity_rank'] = (float)$idea['popularity_rank'];
            }

            Response::paginated($ideas, $total, $page, $limit, 'Список идей получен успешно');

        } catch (PDOException $e) {
            error_log("Ideas API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении идей', [], 500);
        }
    }

    public function getIdea($id)
    {
        $user = JWTAuth::requireAuth();

        if (!is_numeric($id)) {
            Response::error('VALIDATION_ERROR', 'Некорректный ID идеи');
            return;
        }

        try {
            $sql = "
                SELECT 
                    i.id,
                    i.title,
                    i.description,
                    i.category,
                    i.status,
                    i.created_at,
                    i.updated_at,
                    i.likes_count,
                    i.dislikes_count,
                    i.popularity_rank,
                    u.username as author_name,
                    u.id as author_id,
                    CASE 
                        WHEN v.vote_type = 'like' THEN 1
                        WHEN v.vote_type = 'dislike' THEN -1
                        ELSE 0
                    END as user_vote
                FROM ideas i
                LEFT JOIN users u ON i.user_id = u.id
                LEFT JOIN idea_votes v ON i.id = v.idea_id AND v.user_id = :user_id
                WHERE i.id = :id
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'id' => $id,
                'user_id' => $user['user_id']
            ]);

            $idea = $stmt->fetch();

            if (!$idea) {
                Response::error('NOT_FOUND', 'Идея не найдена', [], 404);
                return;
            }

            // Форматирование
            $idea['created_at'] = date('Y-m-d H:i:s', strtotime($idea['created_at']));
            $idea['updated_at'] = date('Y-m-d H:i:s', strtotime($idea['updated_at']));
            $idea['likes_count'] = (int)$idea['likes_count'];
            $idea['dislikes_count'] = (int)$idea['dislikes_count'];
            $idea['popularity_rank'] = (float)$idea['popularity_rank'];
            $idea['user_vote'] = (int)$idea['user_vote'];

            Response::success($idea, 'Идея получена успешно');

        } catch (PDOException $e) {
            error_log("Get Idea API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении идеи', [], 500);
        }
    }

    public function createIdea()
    {
        $user = JWTAuth::requireAuth();

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('VALIDATION_ERROR', 'Некорректные JSON данные');
            return;
        }

        // Валидация
        $errors = Validator::validate($input, [
            'title' => ['required' => true, 'min_length' => 5, 'max_length' => 200],
            'description' => ['required' => true, 'min_length' => 10, 'max_length' => 5000],
            'category' => ['required' => true, 'in' => ['Технологии', 'Процессы', 'Продукты', 'HR', 'Другое']]
        ]);

        if (!empty($errors)) {
            Response::error('VALIDATION_ERROR', 'Ошибка валидации данных', $errors);
            return;
        }

        try {
            $sql = "
                INSERT INTO ideas (user_id, title, description, category, status, created_at, updated_at) 
                VALUES (:user_id, :title, :description, :category, 'pending', NOW(), NOW())
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                'user_id' => $user['user_id'],
                'title' => trim($input['title']),
                'description' => trim($input['description']),
                'category' => $input['category']
            ]);

            $idea_id = $this->db->lastInsertId();

            Response::success([
                'id' => (int)$idea_id,
                'title' => trim($input['title']),
                'description' => trim($input['description']),
                'category' => $input['category'],
                'status' => 'pending'
            ], 'Идея создана успешно', 201);

        } catch (PDOException $e) {
            error_log("Create Idea API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при создании идеи', [], 500);
        }
    }

    public function updateIdea($id)
    {
        $user = JWTAuth::requireAuth();

        if (!is_numeric($id)) {
            Response::error('VALIDATION_ERROR', 'Некорректный ID идеи');
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            Response::error('VALIDATION_ERROR', 'Некорректные JSON данные');
            return;
        }

        // Проверка прав доступа
        try {
            $check_sql = "SELECT user_id FROM ideas WHERE id = :id";
            $check_stmt = $this->db->prepare($check_sql);
            $check_stmt->execute(['id' => $id]);
            $idea = $check_stmt->fetch();

            if (!$idea) {
                Response::error('NOT_FOUND', 'Идея не найдена', [], 404);
                return;
            }

            if ($idea['user_id'] != $user['user_id'] && ($user['role'] ?? 'user') !== 'admin') {
                Response::error('ACCESS_DENIED', 'Нет прав для редактирования этой идеи', [], 403);
                return;
            }

            // Валидация (только переданные поля)
            $allowed_fields = ['title', 'description', 'category'];
            $update_fields = [];
            $params = ['id' => $id];

            foreach ($allowed_fields as $field) {
                if (isset($input[$field])) {
                    switch ($field) {
                        case 'title':
                            if (strlen(trim($input[$field])) < 5 || strlen(trim($input[$field])) > 200) {
                                Response::error('VALIDATION_ERROR', 'Заголовок должен содержать от 5 до 200 символов');
                                return;
                            }
                            break;
                        case 'description':
                            if (strlen(trim($input[$field])) < 10 || strlen(trim($input[$field])) > 5000) {
                                Response::error('VALIDATION_ERROR', 'Описание должно содержать от 10 до 5000 символов');
                                return;
                            }
                            break;
                        case 'category':
                            $categories = ['Технологии', 'Процессы', 'Продукты', 'HR', 'Другое'];
                            if (!in_array($input[$field], $categories)) {
                                Response::error('VALIDATION_ERROR', 'Некорректная категория');
                                return;
                            }
                            break;
                    }

                    $update_fields[] = "$field = :$field";
                    $params[$field] = trim($input[$field]);
                }
            }

            if (empty($update_fields)) {
                Response::error('VALIDATION_ERROR', 'Нет полей для обновления');
                return;
            }

            $update_fields[] = "updated_at = NOW()";

            $sql = "UPDATE ideas SET " . implode(', ', $update_fields) . " WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            Response::success(null, 'Идея обновлена успешно');

        } catch (PDOException $e) {
            error_log("Update Idea API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при обновлении идеи', [], 500);
        }
    }

    public function deleteIdea($id)
    {
        $user = JWTAuth::requireAuth();

        if (!is_numeric($id)) {
            Response::error('VALIDATION_ERROR', 'Некорректный ID идеи');
            return;
        }

        try {
            // Проверка прав доступа
            $check_sql = "SELECT user_id FROM ideas WHERE id = :id";
            $check_stmt = $this->db->prepare($check_sql);
            $check_stmt->execute(['id' => $id]);
            $idea = $check_stmt->fetch();

            if (!$idea) {
                Response::error('NOT_FOUND', 'Идея не найдена', [], 404);
                return;
            }

            if ($idea['user_id'] != $user['user_id'] && ($user['role'] ?? 'user') !== 'admin') {
                Response::error('ACCESS_DENIED', 'Нет прав для удаления этой идеи', [], 403);
                return;
            }

            // Удаление идеи (каскадное удаление голосов настроено в БД)
            $sql = "DELETE FROM ideas WHERE id = :id";
            $stmt = $this->db->prepare($sql);
            $stmt->execute(['id' => $id]);

            Response::success(null, 'Идея удалена успешно');

        } catch (PDOException $e) {
            error_log("Delete Idea API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при удалении идеи', [], 500);
        }
    }

    public function getTopIdeas()
    {
        $user = JWTAuth::requireAuth();

        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;
        $period = $_GET['period'] ?? 'month';

        // Определение периода
        $date_condition = '';
        switch ($period) {
            case 'week':
                $date_condition = "AND i.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
                break;
            case 'month':
                $date_condition = "AND i.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
                break;
            case 'year':
                $date_condition = "AND i.created_at >= DATE_SUB(NOW(), INTERVAL 1 YEAR)";
                break;
            case 'all':
            default:
                $date_condition = '';
                break;
        }

        try {
            // Подсчет общего количества
            $count_sql = "
                SELECT COUNT(*) as total 
                FROM ideas i 
                WHERE i.status = 'approved' $date_condition
            ";
            $count_stmt = $this->db->prepare($count_sql);
            $count_stmt->execute();
            $total = $count_stmt->fetch()['total'];

            // Получение топ идей
            $sql = "
                SELECT 
                    i.id,
                    i.title,
                    i.description,
                    i.category,
                    i.created_at,
                    i.likes_count,
                    i.dislikes_count,
                    i.popularity_rank,
                    u.username as author_name
                FROM ideas i
                LEFT JOIN users u ON i.user_id = u.id
                WHERE i.status = 'approved' $date_condition
                ORDER BY i.popularity_rank DESC, i.likes_count DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $ideas = $stmt->fetchAll();

            foreach ($ideas as &$idea) {
                $idea['created_at'] = date('Y-m-d H:i:s', strtotime($idea['created_at']));
                $idea['likes_count'] = (int)$idea['likes_count'];
                $idea['dislikes_count'] = (int)$idea['dislikes_count'];
                $idea['popularity_rank'] = (float)$idea['popularity_rank'];
            }

            Response::paginated($ideas, $total, $page, $limit, 'Топ идеи получены успешно');

        } catch (PDOException $e) {
            error_log("Top Ideas API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении топ идей', [], 500);
        }
    }

    public function getMyIdeas()
    {
        $user = JWTAuth::requireAuth();

        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = min(100, max(1, intval($_GET['limit'] ?? 20)));
        $offset = ($page - 1) * $limit;

        try {
            // Подсчет моих идей
            $count_sql = "SELECT COUNT(*) as total FROM ideas WHERE user_id = :user_id";
            $count_stmt = $this->db->prepare($count_sql);
            $count_stmt->execute(['user_id' => $user['user_id']]);
            $total = $count_stmt->fetch()['total'];

            // Получение моих идей
            $sql = "
                SELECT 
                    id,
                    title,
                    description,
                    category,
                    status,
                    created_at,
                    updated_at,
                    likes_count,
                    dislikes_count,
                    popularity_rank
                FROM ideas
                WHERE user_id = :user_id
                ORDER BY created_at DESC
                LIMIT :limit OFFSET :offset
            ";

            $stmt = $this->db->prepare($sql);
            $stmt->bindValue(':user_id', $user['user_id'], PDO::PARAM_INT);
            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
            $stmt->execute();

            $ideas = $stmt->fetchAll();

            foreach ($ideas as &$idea) {
                $idea['created_at'] = date('Y-m-d H:i:s', strtotime($idea['created_at']));
                $idea['updated_at'] = date('Y-m-d H:i:s', strtotime($idea['updated_at']));
                $idea['likes_count'] = (int)$idea['likes_count'];
                $idea['dislikes_count'] = (int)$idea['dislikes_count'];
                $idea['popularity_rank'] = (float)$idea['popularity_rank'];
            }

            Response::paginated($ideas, $total, $page, $limit, 'Ваши идеи получены успешно');

        } catch (PDOException $e) {
            error_log("My Ideas API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении ваших идей', [], 500);
        }
    }
}

?>