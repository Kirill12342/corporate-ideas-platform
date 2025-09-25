-- Создание таблицы для файловых вложений
CREATE TABLE IF NOT EXISTS idea_attachments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    idea_id INT NOT NULL,
    filename VARCHAR(255) NOT NULL,
    original_name VARCHAR(255) NOT NULL,
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(100) NOT NULL,
    file_size INT NOT NULL,
    upload_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (idea_id) REFERENCES ideas(id) ON DELETE CASCADE,
    INDEX idx_idea_id (idea_id)
);

-- Добавляем индекс для быстрого поиска вложений по идее
CREATE INDEX idx_idea_attachments_idea_id ON idea_attachments(idea_id);