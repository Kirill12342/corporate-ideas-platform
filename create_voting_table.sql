-- Создание таблицы для системы голосования за идеи
CREATE TABLE IF NOT EXISTS idea_votes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    idea_id INT NOT NULL,
    user_id INT NOT NULL,
    vote_type ENUM('like', 'dislike') NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    -- Ограничения
    FOREIGN KEY (idea_id) REFERENCES ideas(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    
    -- Уникальный индекс - один пользователь может проголосовать за идею только один раз
    UNIQUE KEY unique_user_vote (idea_id, user_id),
    
    -- Индексы для производительности
    INDEX idx_idea_votes (idea_id),
    INDEX idx_user_votes (user_id),
    INDEX idx_vote_type (vote_type),
    INDEX idx_created_at (created_at)
);

-- Добавляем поля для кэширования количества голосов в таблицу ideas
ALTER TABLE ideas 
ADD COLUMN likes_count INT DEFAULT 0 AFTER status,
ADD COLUMN dislikes_count INT DEFAULT 0 AFTER likes_count,
ADD COLUMN total_score INT DEFAULT 0 AFTER dislikes_count,
ADD COLUMN popularity_rank DECIMAL(10,2) DEFAULT 0 AFTER total_score;

-- Индексы для сортировки по популярности
ALTER TABLE ideas 
ADD INDEX idx_likes_count (likes_count),
ADD INDEX idx_total_score (total_score),
ADD INDEX idx_popularity_rank (popularity_rank);

-- Триггеры для автоматического пересчета счетчиков голосов
DELIMITER $$

CREATE TRIGGER update_idea_votes_after_insert
AFTER INSERT ON idea_votes
FOR EACH ROW
BEGIN
    UPDATE ideas 
    SET 
        likes_count = (SELECT COUNT(*) FROM idea_votes WHERE idea_id = NEW.idea_id AND vote_type = 'like'),
        dislikes_count = (SELECT COUNT(*) FROM idea_votes WHERE idea_id = NEW.idea_id AND vote_type = 'dislike')
    WHERE id = NEW.idea_id;
    
    -- Обновляем общий счет и рейтинг популярности
    UPDATE ideas 
    SET 
        total_score = likes_count - dislikes_count,
        popularity_rank = CASE 
            WHEN (likes_count + dislikes_count) = 0 THEN 0
            ELSE ROUND((likes_count / (likes_count + dislikes_count)) * 100, 2)
        END
    WHERE id = NEW.idea_id;
END$$

CREATE TRIGGER update_idea_votes_after_update
AFTER UPDATE ON idea_votes
FOR EACH ROW
BEGIN
    UPDATE ideas 
    SET 
        likes_count = (SELECT COUNT(*) FROM idea_votes WHERE idea_id = NEW.idea_id AND vote_type = 'like'),
        dislikes_count = (SELECT COUNT(*) FROM idea_votes WHERE idea_id = NEW.idea_id AND vote_type = 'dislike')
    WHERE id = NEW.idea_id;
    
    -- Обновляем общий счет и рейтинг популярности
    UPDATE ideas 
    SET 
        total_score = likes_count - dislikes_count,
        popularity_rank = CASE 
            WHEN (likes_count + dislikes_count) = 0 THEN 0
            ELSE ROUND((likes_count / (likes_count + dislikes_count)) * 100, 2)
        END
    WHERE id = NEW.idea_id;
END$$

CREATE TRIGGER update_idea_votes_after_delete
AFTER DELETE ON idea_votes
FOR EACH ROW
BEGIN
    UPDATE ideas 
    SET 
        likes_count = (SELECT COUNT(*) FROM idea_votes WHERE idea_id = OLD.idea_id AND vote_type = 'like'),
        dislikes_count = (SELECT COUNT(*) FROM idea_votes WHERE idea_id = OLD.idea_id AND vote_type = 'dislike')
    WHERE id = OLD.idea_id;
    
    -- Обновляем общий счет и рейтинг популярности
    UPDATE ideas 
    SET 
        total_score = likes_count - dislikes_count,
        popularity_rank = CASE 
            WHEN (likes_count + dislikes_count) = 0 THEN 0
            ELSE ROUND((likes_count / (likes_count + dislikes_count)) * 100, 2)
        END
    WHERE id = OLD.idea_id;
END$$

DELIMITER ;

-- Пересчитываем существующие данные (если есть)
UPDATE ideas SET 
    likes_count = 0,
    dislikes_count = 0,
    total_score = 0,
    popularity_rank = 0;