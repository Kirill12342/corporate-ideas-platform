<?php
session_start();
require_once 'config.php';

header('Content-Type: application/json');

// Проверка авторизации
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Требуется авторизация']);
    exit();
}

// Получение параметров
$period = isset($_GET['period']) ? $_GET['period'] : 'month'; // month, week, all_time
$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 50) : 10; // максимум 50
$category = isset($_GET['category']) ? $_GET['category'] : '';

try {
    // Формируем WHERE условие для периода
    $whereClause = "WHERE 1=1";
    $params = [];
    
    // Фильтр по периоду
    if ($period === 'month') {
        $whereClause .= " AND i.created_at >= DATE_SUB(NOW(), INTERVAL 1 MONTH)";
    } elseif ($period === 'week') {
        $whereClause .= " AND i.created_at >= DATE_SUB(NOW(), INTERVAL 1 WEEK)";
    }
    
    // Фильтр по категории
    if ($category && $category !== 'all') {
        $whereClause .= " AND i.category = ?";
        $params[] = $category;
    }
    
    // Основной запрос для топ идей
    $sql = "SELECT 
                i.id,
                i.title,
                i.description,
                i.category,
                i.status,
                i.likes_count,
                i.dislikes_count,
                i.total_score,
                i.popularity_rank,
                i.created_at,
                u.fullname as author_name,
                u.email as author_email,
                -- Проверяем голос текущего пользователя
                (SELECT vote_type FROM idea_votes WHERE idea_id = i.id AND user_id = ?) as user_vote
            FROM ideas i
            JOIN users u ON i.user_id = u.id
            {$whereClause}
            AND (i.likes_count > 0 OR i.dislikes_count > 0) -- только идеи с голосами
            ORDER BY 
                -- Сортировка по популярности: сначала по рейтингу, затем по общему счету, затем по количеству лайков
                i.popularity_rank DESC,
                i.total_score DESC,
                i.likes_count DESC,
                i.created_at DESC
            LIMIT ?";
    
    array_unshift($params, $_SESSION['user_id']); // Добавляем user_id в начало
    $params[] = $limit; // Добавляем limit в конец
    
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $topIdeas = $stmt->fetchAll();
    
    // Получаем статистику по периодам
    $statsSql = "SELECT 
        COUNT(*) as total_ideas,
        SUM(likes_count) as total_likes,
        SUM(dislikes_count) as total_dislikes,
        AVG(popularity_rank) as avg_popularity
        FROM ideas i
        {$whereClause}";
    
    // Убираем user_id из параметров для статистики
    $statsParams = array_slice($params, 1, -1);
    $statsStmt = $pdo->prepare($statsSql);
    $statsStmt->execute($statsParams);
    $stats = $statsStmt->fetch();
    
    // Получаем категории для фильтрации
    $categoriesSql = "SELECT DISTINCT category FROM ideas WHERE category IS NOT NULL AND category != '' ORDER BY category";
    $categoriesStmt = $pdo->prepare($categoriesSql);
    $categoriesStmt->execute();
    $categories = $categoriesStmt->fetchAll(PDO::FETCH_COLUMN);
    
    // Форматируем даты и добавляем дополнительную информацию
    foreach ($topIdeas as &$idea) {
        $idea['formatted_date'] = date('d.m.Y H:i', strtotime($idea['created_at']));
        $idea['engagement_rate'] = $idea['likes_count'] + $idea['dislikes_count'];
        
        // Определяем "горячесть" идеи (недавние + популярные)
        $daysSinceCreated = (time() - strtotime($idea['created_at'])) / (60 * 60 * 24);
        $idea['hot_score'] = $idea['popularity_rank'] * (1 / max($daysSinceCreated, 0.1));
    }
    
    echo json_encode([
        'success' => true,
        'data' => [
            'ideas' => $topIdeas,
            'stats' => [
                'total_ideas' => (int)$stats['total_ideas'],
                'total_likes' => (int)$stats['total_likes'],
                'total_dislikes' => (int)$stats['total_dislikes'],
                'avg_popularity' => round((float)$stats['avg_popularity'], 2)
            ],
            'categories' => $categories,
            'period' => $period,
            'limit' => $limit
        ]
    ]);
    
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Ошибка базы данных: ' . $e->getMessage()
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false, 
        'message' => 'Ошибка сервера: ' . $e->getMessage()
    ]);
}
?>