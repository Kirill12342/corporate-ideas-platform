-- Миграция для добавления социальных функций
-- Создаем новые таблицы для комментариев, репутации, челленджей и геймификации

-- Таблица комментариев к идеям
CREATE TABLE IF NOT EXISTS comments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idea_id INT NOT NULL,
    user_id INT NOT NULL,
    parent_id INT NULL, -- для вложенных ответов
    content TEXT NOT NULL,
    is_edited BOOLEAN DEFAULT FALSE,
    edited_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_idea_id (idea_id),
    INDEX idx_user_id (user_id),
    INDEX idx_parent_id (parent_id),
    FOREIGN KEY (idea_id) REFERENCES ideas(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES comments(id) ON DELETE CASCADE
);

-- Лайки комментариев
CREATE TABLE IF NOT EXISTS comment_likes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    comment_id INT NOT NULL,
    user_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_comment_like (comment_id, user_id),
    FOREIGN KEY (comment_id) REFERENCES comments(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Система репутации пользователей
CREATE TABLE IF NOT EXISTS user_reputation (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL UNIQUE,
    total_points INT DEFAULT 0,
    level INT DEFAULT 1,
    ideas_count INT DEFAULT 0,
    approved_ideas INT DEFAULT 0,
    comments_count INT DEFAULT 0,
    likes_received INT DEFAULT 0,
    challenges_completed INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Логи начисления/списания баллов репутации
CREATE TABLE IF NOT EXISTS reputation_history (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    action_type ENUM('idea_created', 'idea_approved', 'idea_rejected', 'comment_created', 
                    'like_received', 'like_given', 'challenge_completed', 'achievement_unlocked', 
                    'daily_login', 'admin_adjustment') NOT NULL,
    points INT NOT NULL, -- может быть отрицательным
    description VARCHAR(255),
    related_type ENUM('idea', 'comment', 'challenge', 'achievement') NULL,
    related_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_action_type (action_type),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Командные челленджи
CREATE TABLE IF NOT EXISTS challenges (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    challenge_type ENUM('individual', 'team', 'department') NOT NULL,
    status ENUM('draft', 'active', 'completed', 'archived') DEFAULT 'draft',
    start_date DATETIME NOT NULL,
    end_date DATETIME NOT NULL,
    target_metric ENUM('ideas_count', 'likes_count', 'comments_count', 'approval_rate') NOT NULL,
    target_value INT NOT NULL,
    reward_points INT DEFAULT 0,
    reward_description TEXT,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_status (status),
    INDEX idx_dates (start_date, end_date),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Участие пользователей в челленджах
CREATE TABLE IF NOT EXISTS challenge_participants (
    id INT AUTO_INCREMENT PRIMARY KEY,
    challenge_id INT NOT NULL,
    user_id INT NOT NULL,
    team_name VARCHAR(100) NULL,
    current_progress INT DEFAULT 0,
    is_completed BOOLEAN DEFAULT FALSE,
    completed_at TIMESTAMP NULL,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_participation (challenge_id, user_id),
    INDEX idx_challenge_id (challenge_id),
    INDEX idx_user_id (user_id),
    FOREIGN KEY (challenge_id) REFERENCES challenges(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Система достижений (badges/achievements)
CREATE TABLE IF NOT EXISTS achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    icon_class VARCHAR(100), -- для CSS иконок
    icon_color VARCHAR(7) DEFAULT '#FFD700',
    badge_type ENUM('bronze', 'silver', 'gold', 'platinum', 'special') DEFAULT 'bronze',
    unlock_condition JSON NOT NULL, -- условия разблокировки в JSON формате
    points_reward INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    rarity ENUM('common', 'rare', 'epic', 'legendary') DEFAULT 'common',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Разблокированные достижения пользователей
CREATE TABLE IF NOT EXISTS user_achievements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    achievement_id INT NOT NULL,
    unlocked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    progress_data JSON NULL, -- данные о прогрессе к достижению
    UNIQUE KEY unique_user_achievement (user_id, achievement_id),
    INDEX idx_user_id (user_id),
    INDEX idx_achievement_id (achievement_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (achievement_id) REFERENCES achievements(id) ON DELETE CASCADE
);

-- Таблица активностей пользователей (для фида активности)
CREATE TABLE IF NOT EXISTS user_activities (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    activity_type ENUM('idea_created', 'idea_liked', 'comment_created', 'comment_liked', 
                      'challenge_joined', 'challenge_completed', 'achievement_unlocked', 
                      'level_up') NOT NULL,
    activity_data JSON NULL, -- дополнительные данные активности
    visibility ENUM('public', 'friends', 'private') DEFAULT 'public',
    related_type ENUM('idea', 'comment', 'challenge', 'achievement') NULL,
    related_id INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_activity_type (activity_type),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Подписки пользователей друг на друга
CREATE TABLE IF NOT EXISTS user_follows (
    id INT AUTO_INCREMENT PRIMARY KEY,
    follower_id INT NOT NULL,
    following_id INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_follow (follower_id, following_id),
    INDEX idx_follower (follower_id),
    INDEX idx_following (following_id),
    FOREIGN KEY (follower_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (following_id) REFERENCES users(id) ON DELETE CASCADE,
    CHECK (follower_id != following_id)
);

-- Обновляем таблицу users, добавляем поля для социальных функций
-- Проверяем и добавляем поля только если их нет
SET @sql = '';
SELECT COUNT(*) INTO @exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'avatar_url';
SET @sql = IF(@exists = 0, 'ALTER TABLE users ADD COLUMN avatar_url VARCHAR(500) NULL;', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'bio';
SET @sql = IF(@exists = 0, 'ALTER TABLE users ADD COLUMN bio TEXT NULL;', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'department';
SET @sql = IF(@exists = 0, 'ALTER TABLE users ADD COLUMN department VARCHAR(100) NULL;', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'position';
SET @sql = IF(@exists = 0, 'ALTER TABLE users ADD COLUMN position VARCHAR(100) NULL;', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'social_privacy';
SET @sql = IF(@exists = 0, 'ALTER TABLE users ADD COLUMN social_privacy ENUM(''public'', ''colleagues'', ''private'') DEFAULT ''public'';', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'last_active';
SET @sql = IF(@exists = 0, 'ALTER TABLE users ADD COLUMN last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP;', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @exists FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND COLUMN_NAME = 'is_online';
SET @sql = IF(@exists = 0, 'ALTER TABLE users ADD COLUMN is_online BOOLEAN DEFAULT FALSE;', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

-- Добавляем индексы для производительности
SET @sql = '';
SELECT COUNT(*) INTO @exists FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_department';
SET @sql = IF(@exists = 0, 'CREATE INDEX idx_users_department ON users(department);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @exists FROM INFORMATION_SCHEMA.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'users' AND INDEX_NAME = 'idx_users_last_active';
SET @sql = IF(@exists = 0, 'CREATE INDEX idx_users_last_active ON users(last_active);', '');
PREPARE stmt FROM @sql; EXECUTE stmt; DEALLOCATE PREPARE stmt;

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
    FROM votes v
    JOIN ideas i ON v.idea_id = i.id
    WHERE v.vote_type = 'like'
    GROUP BY i.user_id
) vote_stats ON u.id = vote_stats.user_id;

-- Создаем тестовый челлендж
INSERT IGNORE INTO challenges (title, description, challenge_type, status, start_date, end_date, target_metric, target_value, reward_points, reward_description, created_by) VALUES
('Месяц инноваций', 'Создайте как можно больше идей за месяц! Победитель получает особый значок и дополнительные баллы.', 'individual', 'active', DATE_ADD(NOW(), INTERVAL -7 DAY), DATE_ADD(NOW(), INTERVAL 23 DAY), 'ideas_count', 5, 500, 'Значок "Инноватор месяца" и 500 бонусных баллов', 1);

-- Настройки уведомлений пользователей
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