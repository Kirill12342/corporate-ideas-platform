<?php
// Контроллер для системы голосования через API
class VotesController {
    private $db;
    
    public function __construct() {
        $this->db = APIDatabase::getConnection();
    }
    
    public function voteForIdea($idea_id) {
        $user = JWTAuth::requireAuth();
        
        if (!is_numeric($idea_id)) {
            Response::error('VALIDATION_ERROR', 'Некорректный ID идеи');
            return;
        }
        
        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!$input) {
            Response::error('VALIDATION_ERROR', 'Некорректные JSON данные');
            return;
        }
        
        // Валидация типа голоса
        $vote_type = $input['vote_type'] ?? '';
        if (!in_array($vote_type, ['like', 'dislike'])) {
            Response::error('VALIDATION_ERROR', 'Тип голоса должен быть "like" или "dislike"');
            return;
        }
        
        try {
            // Проверка существования идеи
            $idea_check_sql = "SELECT id, user_id FROM ideas WHERE id = :idea_id";
            $idea_check_stmt = $this->db->prepare($idea_check_sql);
            $idea_check_stmt->execute(['idea_id' => $idea_id]);
            $idea = $idea_check_stmt->fetch();
            
            if (!$idea) {
                Response::error('NOT_FOUND', 'Идея не найдена', [], 404);
                return;
            }
            
            // Проверка - пользователь не может голосовать за свои идеи
            if ($idea['user_id'] == $user['user_id']) {
                Response::error('VALIDATION_ERROR', 'Нельзя голосовать за собственные идеи');
                return;
            }
            
            // Проверка существующего голоса
            $existing_vote_sql = "SELECT vote_type FROM idea_votes WHERE idea_id = :idea_id AND user_id = :user_id";
            $existing_vote_stmt = $this->db->prepare($existing_vote_sql);
            $existing_vote_stmt->execute([
                'idea_id' => $idea_id,
                'user_id' => $user['user_id']
            ]);
            $existing_vote = $existing_vote_stmt->fetch();
            
            if ($existing_vote) {
                if ($existing_vote['vote_type'] === $vote_type) {
                    Response::error('VALIDATION_ERROR', 'Вы уже проголосовали таким образом за эту идею');
                    return;
                }
                
                // Обновление существующего голоса
                $update_sql = "UPDATE idea_votes SET vote_type = :vote_type, created_at = NOW() WHERE idea_id = :idea_id AND user_id = :user_id";
                $update_stmt = $this->db->prepare($update_sql);
                $update_stmt->execute([
                    'vote_type' => $vote_type,
                    'idea_id' => $idea_id,
                    'user_id' => $user['user_id']
                ]);
                
                $message = 'Ваш голос изменен успешно';
            } else {
                // Создание нового голоса
                $insert_sql = "INSERT INTO idea_votes (idea_id, user_id, vote_type, created_at) VALUES (:idea_id, :user_id, :vote_type, NOW())";
                $insert_stmt = $this->db->prepare($insert_sql);
                $insert_stmt->execute([
                    'idea_id' => $idea_id,
                    'user_id' => $user['user_id'],
                    'vote_type' => $vote_type
                ]);
                
                $message = 'Голос добавлен успешно';
            }
            
            // Получение обновленной статистики
            $stats = $this->getVoteStatistics($idea_id);
            
            Response::success([
                'vote_type' => $vote_type,
                'statistics' => $stats
            ], $message);
            
        } catch (PDOException $e) {
            error_log("Vote API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при голосовании', [], 500);
        }
    }
    
    public function removeVote($idea_id) {
        $user = JWTAuth::requireAuth();
        
        if (!is_numeric($idea_id)) {
            Response::error('VALIDATION_ERROR', 'Некорректный ID идеи');
            return;
        }
        
        try {
            // Проверка существования голоса
            $check_sql = "SELECT id FROM idea_votes WHERE idea_id = :idea_id AND user_id = :user_id";
            $check_stmt = $this->db->prepare($check_sql);
            $check_stmt->execute([
                'idea_id' => $idea_id,
                'user_id' => $user['user_id']
            ]);
            
            if (!$check_stmt->fetch()) {
                Response::error('NOT_FOUND', 'Голос не найден', [], 404);
                return;
            }
            
            // Удаление голоса
            $delete_sql = "DELETE FROM idea_votes WHERE idea_id = :idea_id AND user_id = :user_id";
            $delete_stmt = $this->db->prepare($delete_sql);
            $delete_stmt->execute([
                'idea_id' => $idea_id,
                'user_id' => $user['user_id']
            ]);
            
            // Получение обновленной статистики
            $stats = $this->getVoteStatistics($idea_id);
            
            Response::success([
                'statistics' => $stats
            ], 'Голос отменен успешно');
            
        } catch (PDOException $e) {
            error_log("Remove Vote API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при отмене голоса', [], 500);
        }
    }
    
    public function getVoteStats($idea_id) {
        $user = JWTAuth::requireAuth();
        
        if (!is_numeric($idea_id)) {
            Response::error('VALIDATION_ERROR', 'Некорректный ID идеи');
            return;
        }
        
        try {
            // Проверка существования идеи
            $idea_check_sql = "SELECT id FROM ideas WHERE id = :idea_id";
            $idea_check_stmt = $this->db->prepare($idea_check_sql);
            $idea_check_stmt->execute(['idea_id' => $idea_id]);
            
            if (!$idea_check_stmt->fetch()) {
                Response::error('NOT_FOUND', 'Идея не найдена', [], 404);
                return;
            }
            
            $stats = $this->getVoteStatistics($idea_id);
            
            // Получение голоса текущего пользователя
            $user_vote_sql = "SELECT vote_type FROM idea_votes WHERE idea_id = :idea_id AND user_id = :user_id";
            $user_vote_stmt = $this->db->prepare($user_vote_sql);
            $user_vote_stmt->execute([
                'idea_id' => $idea_id,
                'user_id' => $user['user_id']
            ]);
            $user_vote = $user_vote_stmt->fetch();
            
            $stats['user_vote'] = $user_vote ? $user_vote['vote_type'] : null;
            
            Response::success($stats, 'Статистика голосов получена успешно');
            
        } catch (PDOException $e) {
            error_log("Vote Stats API Error: " . $e->getMessage());
            Response::error('DATABASE_ERROR', 'Ошибка при получении статистики голосов', [], 500);
        }
    }
    
    private function getVoteStatistics($idea_id) {
        // Получение актуальной статистики из таблицы ideas (обновляется триггерами)
        $stats_sql = "SELECT likes_count, dislikes_count, popularity_rank FROM ideas WHERE id = :idea_id";
        $stats_stmt = $this->db->prepare($stats_sql);
        $stats_stmt->execute(['idea_id' => $idea_id]);
        $stats = $stats_stmt->fetch();
        
        if (!$stats) {
            return [
                'likes_count' => 0,
                'dislikes_count' => 0,
                'total_votes' => 0,
                'popularity_rank' => 0.0
            ];
        }
        
        $likes_count = (int)$stats['likes_count'];
        $dislikes_count = (int)$stats['dislikes_count'];
        
        return [
            'likes_count' => $likes_count,
            'dislikes_count' => $dislikes_count,
            'total_votes' => $likes_count + $dislikes_count,
            'popularity_rank' => (float)$stats['popularity_rank'],
            'like_percentage' => $likes_count + $dislikes_count > 0 
                ? round(($likes_count / ($likes_count + $dislikes_count)) * 100, 2) 
                : 0
        ];
    }
}
?>