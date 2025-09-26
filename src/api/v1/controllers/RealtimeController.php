<?php

// Контроллер для real-time функций через Server-Sent Events
class RealtimeController
{
    private $db;

    public function __construct()
    {
        $this->db = APIDatabase::getConnection();
    }

    public function streamNotifications()
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Настройка SSE headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('Access-Control-Allow-Origin: *');
            header('Access-Control-Allow-Headers: Cache-Control');

            // Отключаем буферизацию
            if (ob_get_level()) ob_end_clean();

            $lastNotificationId = $_GET['last_id'] ?? 0;
            $checkInterval = 2; // Проверяем каждые 2 секунды

            // Основной цикл SSE
            while (true) {
                // Проверяем новые уведомления
                $stmt = $this->db->prepare("
                    SELECT n.*, u.username as sender_username
                    FROM notifications n
                    LEFT JOIN users u ON n.sender_id = u.id
                    WHERE n.user_id = ? AND n.id > ?
                    ORDER BY n.created_at DESC
                    LIMIT 10
                ");
                $stmt->execute([$user['id'], $lastNotificationId]);
                $newNotifications = $stmt->fetchAll();

                if (!empty($newNotifications)) {
                    foreach ($newNotifications as $notification) {
                        echo "data: " . json_encode([
                                'type' => 'notification',
                                'data' => $notification
                            ]) . "\n\n";

                        $lastNotificationId = max($lastNotificationId, $notification['id']);
                    }

                    // Отправляем обновленный счетчик непрочитанных
                    $stmt = $this->db->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
                    $stmt->execute([$user['id']]);
                    $unreadCount = $stmt->fetch()['count'];

                    echo "data: " . json_encode([
                            'type' => 'unread_count',
                            'data' => ['count' => (int)$unreadCount]
                        ]) . "\n\n";
                }

                // Heartbeat для поддержания соединения
                echo "data: " . json_encode([
                        'type' => 'heartbeat',
                        'data' => ['timestamp' => time()]
                    ]) . "\n\n";

                flush();

                // Проверяем, закрыто ли соединение
                if (connection_aborted()) {
                    break;
                }

                sleep($checkInterval);
            }

        } catch (Exception $e) {
            error_log("SSE Notifications error: " . $e->getMessage());
            echo "data: " . json_encode([
                    'type' => 'error',
                    'data' => ['message' => 'Внутренняя ошибка сервера']
                ]) . "\n\n";
            flush();
        }
    }

    public function streamIdeasUpdates()
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            // Настройка SSE headers
            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('Access-Control-Allow-Origin: *');

            if (ob_get_level()) ob_end_clean();

            $lastUpdateTime = $_GET['last_update'] ?? date('Y-m-d H:i:s', strtotime('-1 hour'));
            $checkInterval = 5; // Проверяем каждые 5 секунд

            while (true) {
                // Проверяем обновленные идеи
                $stmt = $this->db->prepare("
                    SELECT i.*, u.username, 
                           COUNT(DISTINCT iv.id) as votes_count,
                           COUNT(DISTINCT a.id) as attachments_count
                    FROM ideas i
                    JOIN users u ON i.user_id = u.id
                    LEFT JOIN idea_votes iv ON i.id = iv.idea_id
                    LEFT JOIN attachments a ON i.id = a.idea_id
                    WHERE i.updated_at > ?
                    GROUP BY i.id
                    ORDER BY i.updated_at DESC
                    LIMIT 10
                ");
                $stmt->execute([$lastUpdateTime]);
                $updatedIdeas = $stmt->fetchAll();

                if (!empty($updatedIdeas)) {
                    echo "data: " . json_encode([
                            'type' => 'ideas_update',
                            'data' => $updatedIdeas
                        ]) . "\n\n";

                    // Обновляем временную метку
                    $lastUpdateTime = date('Y-m-d H:i:s');
                }

                // Heartbeat
                echo "data: " . json_encode([
                        'type' => 'heartbeat',
                        'data' => ['timestamp' => time()]
                    ]) . "\n\n";

                flush();

                if (connection_aborted()) {
                    break;
                }

                sleep($checkInterval);
            }

        } catch (Exception $e) {
            error_log("SSE Ideas Updates error: " . $e->getMessage());
            echo "data: " . json_encode([
                    'type' => 'error',
                    'data' => ['message' => 'Внутренняя ошибка сервера']
                ]) . "\n\n";
            flush();
        }
    }

    public function streamVotesUpdates()
    {
        try {
            // Проверяем авторизацию
            $user = JWTAuth::requireAuth();
            if (!$user) return;

            header('Content-Type: text/event-stream');
            header('Cache-Control: no-cache');
            header('Connection: keep-alive');
            header('Access-Control-Allow-Origin: *');

            if (ob_get_level()) ob_end_clean();

            $lastVoteId = $_GET['last_vote_id'] ?? 0;
            $checkInterval = 3;

            while (true) {
                // Проверяем новые голоса
                $stmt = $this->db->prepare("
                    SELECT iv.*, i.title as idea_title, u.username
                    FROM idea_votes iv
                    JOIN ideas i ON iv.idea_id = i.id
                    JOIN users u ON iv.user_id = u.id
                    WHERE iv.id > ?
                    ORDER BY iv.created_at DESC
                    LIMIT 20
                ");
                $stmt->execute([$lastVoteId]);
                $newVotes = $stmt->fetchAll();

                if (!empty($newVotes)) {
                    // Группируем голоса по идеям для отправки статистики
                    $ideaStats = [];
                    foreach ($newVotes as $vote) {
                        $ideaId = $vote['idea_id'];
                        if (!isset($ideaStats[$ideaId])) {
                            // Получаем актуальную статистику голосов для идеи
                            $statsStmt = $this->db->prepare("
                                SELECT 
                                    COUNT(*) as total_votes,
                                    SUM(CASE WHEN vote_type = 'like' THEN 1 ELSE 0 END) as likes,
                                    SUM(CASE WHEN vote_type = 'dislike' THEN 1 ELSE 0 END) as dislikes
                                FROM idea_votes 
                                WHERE idea_id = ?
                            ");
                            $statsStmt->execute([$ideaId]);
                            $stats = $statsStmt->fetch();

                            $ideaStats[$ideaId] = [
                                'idea_id' => $ideaId,
                                'idea_title' => $vote['idea_title'],
                                'total_votes' => (int)$stats['total_votes'],
                                'likes' => (int)$stats['likes'],
                                'dislikes' => (int)$stats['dislikes']
                            ];
                        }

                        $lastVoteId = max($lastVoteId, $vote['id']);
                    }

                    // Отправляем обновленную статистику
                    echo "data: " . json_encode([
                            'type' => 'votes_update',
                            'data' => array_values($ideaStats)
                        ]) . "\n\n";
                }

                // Heartbeat
                echo "data: " . json_encode([
                        'type' => 'heartbeat',
                        'data' => ['timestamp' => time()]
                    ]) . "\n\n";

                flush();

                if (connection_aborted()) {
                    break;
                }

                sleep($checkInterval);
            }

        } catch (Exception $e) {
            error_log("SSE Votes Updates error: " . $e->getMessage());
            echo "data: " . json_encode([
                    'type' => 'error',
                    'data' => ['message' => 'Внутренняя ошибка сервера']
                ]) . "\n\n";
            flush();
        }
    }

    // Метод для отправки события всем подключенным клиентам (будет использоваться из других контроллеров)
    public static function broadcastEvent($eventType, $data)
    {
        try {
            // В реальной производственной среде здесь может быть интеграция с Redis Pub/Sub,
            // RabbitMQ или другим message broker для отправки событий между серверами

            // Для текущей реализации сохраним событие в БД для последующей обработки SSE потоками
            $db = APIDatabase::getConnection();

            $stmt = $db->prepare("
                INSERT INTO realtime_events (event_type, event_data, created_at)
                VALUES (?, ?, NOW())
            ");

            $stmt->execute([$eventType, json_encode($data)]);

            // Очищаем старые события (старше 1 часа)
            $db->exec("DELETE FROM realtime_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)");

            return true;

        } catch (Exception $e) {
            error_log("Broadcast event error: " . $e->getMessage());
            return false;
        }
    }
}

?>