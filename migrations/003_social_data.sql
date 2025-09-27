-- Простая миграция для заполнения базовых данных социальных функций

-- Добавляем недостающие поля в таблицу users
ALTER TABLE users ADD COLUMN social_privacy ENUM('public', 'colleagues', 'private') DEFAULT 'public';
ALTER TABLE users ADD COLUMN last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP;
ALTER TABLE users ADD COLUMN is_online BOOLEAN DEFAULT FALSE;

-- Добавляем индексы
CREATE INDEX idx_users_department ON users(department);
CREATE INDEX idx_users_last_active ON users(last_active);

-- Создаем таблицу настроек уведомлений если не существует
CREATE TABLE IF NOT EXISTS user_notification_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    comments BOOLEAN DEFAULT TRUE,
    comment_likes BOOLEAN DEFAULT TRUE,
    achievements BOOLEAN DEFAULT TRUE,
    reputation BOOLEAN DEFAULT TRUE,
    challenges BOOLEAN DEFAULT TRUE,
    team_activities BOOLEAN DEFAULT TRUE,
    email_notifications BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Вставляем базовые достижения
INSERT IGNORE INTO achievements (name, title, description, icon_class, badge_type, unlock_condition, points_reward, rarity) VALUES
('first_idea', 'Первая идея', 'Создал свою первую идею на платформе', 'fas fa-lightbulb', 'bronze', '{"type": "ideas_count", "value": 1}', 10, 'common'),
('idea_master', 'Мастер идей', 'Создал 10 идей', 'fas fa-brain', 'silver', '{"type": "ideas_count", "value": 10}', 50, 'rare'),
('innovator', 'Инноватор', 'Создал 50 идей', 'fas fa-rocket', 'gold', '{"type": "ideas_count", "value": 50}', 200, 'epic'),
('first_comment', 'Первый комментарий', 'Оставил первый комментарий', 'fas fa-comment', 'bronze', '{"type": "comments_count", "value": 1}', 5, 'common'),
('commentator', 'Комментатор', 'Оставил 50 комментариев', 'fas fa-comments', 'silver', '{"type": "comments_count", "value": 50}', 75, 'rare'),
('social_butterfly', 'Социальная бабочка', 'Получил 100 лайков', 'fas fa-heart', 'gold', '{"type": "likes_received", "value": 100}', 100, 'epic'),
('approved_idea', 'Одобренная идея', 'Первая идея была одобрена', 'fas fa-check-circle', 'silver', '{"type": "approved_ideas", "value": 1}', 25, 'rare'),
('champion', 'Чемпион', 'Завершил первый челлендж', 'fas fa-trophy', 'gold', '{"type": "challenges_completed", "value": 1}', 150, 'epic'),
('early_bird', 'Ранняя пташка', 'Заходил в систему 7 дней подряд', 'fas fa-sun', 'bronze', '{"type": "daily_login_streak", "value": 7}', 30, 'common'),
('night_owl', 'Сова', 'Активен поздним вечером (после 22:00)', 'fas fa-moon', 'bronze', '{"type": "late_night_activity", "value": 1}', 15, 'common');

-- Инициализируем репутацию для существующих пользователей
INSERT IGNORE INTO user_reputation (user_id, total_points, level, ideas_count, approved_ideas, comments_count)
SELECT 
    u.id,
    COALESCE(idea_stats.ideas * 10, 0) + COALESCE(vote_stats.likes * 2, 0) as total_points,
    GREATEST(1, FLOOR((COALESCE(idea_stats.ideas * 10, 0) + COALESCE(vote_stats.likes * 2, 0)) / 100)) as level,
    COALESCE(idea_stats.ideas, 0) as ideas_count,
    COALESCE(idea_stats.approved, 0) as approved_ideas,
    0 as comments_count
FROM users u
LEFT JOIN (
    SELECT user_id, COUNT(*) as ideas, SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved
    FROM ideas 
    GROUP BY user_id
) idea_stats ON u.id = idea_stats.user_id
LEFT JOIN (
    SELECT i.user_id, COUNT(*) as likes
    FROM idea_votes v
    JOIN ideas i ON v.idea_id = i.id
    WHERE v.vote_type = 'like'
    GROUP BY i.user_id
) vote_stats ON u.id = vote_stats.user_id;

-- Создаем тестовый челлендж
INSERT IGNORE INTO challenges (title, description, challenge_type, status, start_date, end_date, target_metric, target_value, reward_points, reward_description, created_by) VALUES
('Месяц инноваций', 'Создайте как можно больше идей за месяц! Победитель получает особый значок и дополнительные баллы.', 'individual', 'active', DATE_ADD(NOW(), INTERVAL -7 DAY), DATE_ADD(NOW(), INTERVAL 23 DAY), 'ideas_count', 5, 500, 'Значок "Инноватор месяца" и 500 бонусных баллов', 1);