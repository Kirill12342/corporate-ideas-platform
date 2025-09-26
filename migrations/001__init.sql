-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Хост: 127.0.0.1
-- Время создания: Сен 26 2025 г., 16:04
-- Версия сервера: 10.4.32-MariaDB
-- Версия PHP: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- База данных: `stuffvoice`
--

DELIMITER $$
--
-- Процедуры
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `CreateNotification` (IN `p_user_id` INT, IN `p_type` VARCHAR(50), IN `p_title` VARCHAR(255), IN `p_message` TEXT, IN `p_related_type` VARCHAR(50), IN `p_related_id` INT, IN `p_sender_id` INT)   BEGIN
  INSERT INTO notifications (user_id, type, title, message, related_type, related_id, sender_id)
  VALUES (p_user_id, p_type, p_title, p_message, p_related_type, p_related_id, p_sender_id);
END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `attachments`
--

CREATE TABLE `attachments` (
  `id` int(11) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `original_name` varchar(255) NOT NULL COMMENT 'Оригинальное имя файла',
  `filename` varchar(255) NOT NULL COMMENT 'Имя файла на сервере',
  `file_path` varchar(500) NOT NULL COMMENT 'Путь к файлу',
  `file_size` int(11) NOT NULL COMMENT 'Размер файла в байтах',
  `mime_type` varchar(100) NOT NULL COMMENT 'MIME тип файла',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Файловые вложения к идеям';

-- --------------------------------------------------------

--
-- Структура таблицы `ideas`
--

CREATE TABLE `ideas` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `category` varchar(100) NOT NULL,
  `status` enum('На рассмотрении','Принято','В работе','Отклонено') DEFAULT 'На рассмотрении',
  `likes_count` int(11) DEFAULT 0,
  `dislikes_count` int(11) DEFAULT 0,
  `total_score` int(11) DEFAULT 0,
  `popularity_rank` decimal(10,2) DEFAULT 0.00,
  `admin_notes` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `ideas`
--

INSERT INTO `ideas` (`id`, `user_id`, `title`, `description`, `category`, `status`, `likes_count`, `dislikes_count`, `total_score`, `popularity_rank`, `admin_notes`, `created_at`, `updated_at`) VALUES
(1, 10, 'Сделать защиту от sql-инъекций', 'Обязательно', 'IT-сервисы', 'В работе', 0, 1, -1, 0.00, 'нормально', '2025-09-24 07:55:01', '2025-09-26 11:46:53'),
(2, 10, 'Тест-2', 'Тест-2', 'Рабочие процессы', 'Принято', 0, 0, 0, 0.00, 'норм', '2025-09-24 08:24:45', '2025-09-24 08:25:12'),
(3, 11, '123', '123', 'IT-сервисы', 'На рассмотрении', 1, 0, 1, 100.00, NULL, '2025-09-24 14:19:41', '2025-09-26 11:01:19'),
(7, 11, '1231', '32131', 'IT-сервисы', 'На рассмотрении', 1, 0, 1, 100.00, NULL, '2025-09-24 14:19:55', '2025-09-25 16:18:19'),
(8, 11, '123', '31231', 'Офис', 'На рассмотрении', 1, 0, 1, 100.00, NULL, '2025-09-24 14:19:59', '2025-09-25 16:18:18'),
(9, 10, '123', '321', 'Финансы', 'На рассмотрении', 0, 0, 0, 0.00, NULL, '2025-09-25 12:28:46', '2025-09-25 12:28:46'),
(10, 10, '544', '556', 'IT-сервисы', 'На рассмотрении', 0, 0, 0, 0.00, NULL, '2025-09-25 13:08:13', '2025-09-25 13:08:13'),
(11, 10, '434', '4324', 'HR', 'На рассмотрении', 0, 0, 0, 0.00, NULL, '2025-09-26 12:09:09', '2025-09-26 12:09:09');

--
-- Триггеры `ideas`
--
DELIMITER $$
CREATE TRIGGER `update_idea_timestamp` BEFORE UPDATE ON `ideas` FOR EACH ROW BEGIN
  SET NEW.updated_at = CURRENT_TIMESTAMP;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `idea_attachments`
--

CREATE TABLE `idea_attachments` (
  `id` int(11) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `filename` varchar(255) NOT NULL,
  `original_name` varchar(255) NOT NULL,
  `file_path` varchar(500) NOT NULL,
  `file_type` varchar(100) NOT NULL,
  `file_size` int(11) NOT NULL,
  `upload_date` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `idea_attachments`
--

INSERT INTO `idea_attachments` (`id`, `idea_id`, `filename`, `original_name`, `file_path`, `file_type`, `file_size`, `upload_date`) VALUES
(1, 10, '68d53ebd53fad_1758805693_0.jpg', 'photo_2025-09-24_19-21-20.jpg', 'uploads/68d53ebd53fad_1758805693_0.jpg', 'image/jpeg', 39602, '2025-09-25 13:08:13');

-- --------------------------------------------------------

--
-- Дублирующая структура для представления `idea_stats`
-- (См. Ниже фактическое представление)
--
CREATE TABLE `idea_stats` (
`id` int(11)
,`title` varchar(255)
,`status` enum('На рассмотрении','Принято','В работе','Отклонено')
,`created_at` timestamp
,`updated_at` timestamp
,`author` varchar(255)
,`total_votes` bigint(21)
,`likes` bigint(21)
,`dislikes` bigint(21)
,`attachments_count` bigint(21)
);

-- --------------------------------------------------------

--
-- Структура таблицы `idea_votes`
--

CREATE TABLE `idea_votes` (
  `id` int(11) NOT NULL,
  `idea_id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `vote_type` enum('like','dislike') NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Дамп данных таблицы `idea_votes`
--

INSERT INTO `idea_votes` (`id`, `idea_id`, `user_id`, `vote_type`, `created_at`, `updated_at`) VALUES
(11, 8, 10, 'like', '2025-09-25 16:18:18', '2025-09-25 16:18:18'),
(12, 7, 10, 'like', '2025-09-25 16:18:19', '2025-09-25 16:18:19'),
(13, 3, 10, 'like', '2025-09-25 16:18:20', '2025-09-26 11:01:19'),
(15, 1, 1, 'dislike', '2025-09-26 11:46:53', '2025-09-26 11:46:53');

--
-- Триггеры `idea_votes`
--
DELIMITER $$
CREATE TRIGGER `update_idea_votes_after_delete` AFTER DELETE ON `idea_votes` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_idea_votes_after_insert` AFTER INSERT ON `idea_votes` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;
DELIMITER $$
CREATE TRIGGER `update_idea_votes_after_update` AFTER UPDATE ON `idea_votes` FOR EACH ROW BEGIN
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
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Структура таблицы `notifications`
--

CREATE TABLE `notifications` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL COMMENT 'Получатель уведомления',
  `type` varchar(50) NOT NULL COMMENT 'Тип уведомления (info, success, warning, error)',
  `title` varchar(255) NOT NULL COMMENT 'Заголовок уведомления',
  `message` text NOT NULL COMMENT 'Текст уведомления',
  `related_type` varchar(50) DEFAULT NULL COMMENT 'Тип связанного объекта (idea, vote, comment)',
  `related_id` int(11) DEFAULT NULL COMMENT 'ID связанного объекта',
  `sender_id` int(11) DEFAULT NULL COMMENT 'Отправитель уведомления (если есть)',
  `is_read` tinyint(1) NOT NULL DEFAULT 0 COMMENT 'Прочитано ли уведомление',
  `read_at` timestamp NULL DEFAULT NULL COMMENT 'Время прочтения',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Система уведомлений пользователей';

--
-- Дамп данных таблицы `notifications`
--

INSERT INTO `notifications` (`id`, `user_id`, `type`, `title`, `message`, `related_type`, `related_id`, `sender_id`, `is_read`, `read_at`, `created_at`) VALUES
(1, 12, 'info', 'обро пожаловать!', 'Спасибо за использование нашего API', NULL, NULL, NULL, 0, NULL, '2025-09-26 11:00:15');

-- --------------------------------------------------------

--
-- Структура таблицы `realtime_events`
--

CREATE TABLE `realtime_events` (
  `id` int(11) NOT NULL,
  `event_type` varchar(50) NOT NULL COMMENT 'Тип события',
  `event_data` longtext CHARACTER SET utf8mb4 COLLATE utf8mb4_bin NOT NULL COMMENT 'Данные события в JSON формате' CHECK (json_valid(`event_data`)),
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='События для real-time обновлений';

-- --------------------------------------------------------

--
-- Структура таблицы `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(255) DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  `fullname` varchar(255) NOT NULL,
  `email` varchar(100) NOT NULL,
  `role` enum('user','admin') DEFAULT 'user'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Дамп данных таблицы `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `created_at`, `fullname`, `email`, `role`) VALUES
(1, '', '$2y$10$2lzR28dQbX8ebiUPqrPtiuanPXcR1cLpRP/U8kaXTS/tvmN4wa6h2', '2025-09-22 05:37:39', '123', '123@mail.ru', 'admin'),
(10, 'popowk1rill', '$2y$10$eGBwAiXN2pEDcTxW7NI.tunGA/jyRlWSuySxqxEnbdLHmvwLmvjq2', '2025-09-24 07:52:39', 'Ушаков Станислав Иванович', 'popowk1rill@yandex.ru', 'user'),
(11, '312', '$2y$10$RtII62SzZ6jfY8XPRTaHTOaqU/2MMB13qvx61OksAYkXQwcJPqFHO', '2025-09-24 13:17:00', '312', '312@yandex.ru', 'user'),
(12, 'testuser', '$2y$10$UPG9gzaSUz69pLKbMVGO6..jvYYY8yckK0ni7Qsy82LcploM3LYwK', '2025-09-26 07:47:42', '', '', 'user');

-- --------------------------------------------------------

--
-- Структура для представления `idea_stats`
--
DROP TABLE IF EXISTS `idea_stats`;

CREATE ALGORITHM=UNDEFINED DEFINER=`root`@`localhost` SQL SECURITY DEFINER VIEW `idea_stats`  AS SELECT `i`.`id` AS `id`, `i`.`title` AS `title`, `i`.`status` AS `status`, `i`.`created_at` AS `created_at`, `i`.`updated_at` AS `updated_at`, `u`.`username` AS `author`, count(distinct `iv`.`id`) AS `total_votes`, count(distinct case when `iv`.`vote_type` = 'like' then `iv`.`id` end) AS `likes`, count(distinct case when `iv`.`vote_type` = 'dislike' then `iv`.`id` end) AS `dislikes`, count(distinct `a`.`id`) AS `attachments_count` FROM (((`ideas` `i` left join `users` `u` on(`i`.`user_id` = `u`.`id`)) left join `idea_votes` `iv` on(`i`.`id` = `iv`.`idea_id`)) left join `attachments` `a` on(`i`.`id` = `a`.`idea_id`)) GROUP BY `i`.`id` ;

--
-- Индексы сохранённых таблиц
--

--
-- Индексы таблицы `attachments`
--
ALTER TABLE `attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_idea_id` (`idea_id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Индексы таблицы `ideas`
--
ALTER TABLE `ideas`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`),
  ADD KEY `idx_likes_count` (`likes_count`),
  ADD KEY `idx_total_score` (`total_score`),
  ADD KEY `idx_popularity_rank` (`popularity_rank`),
  ADD KEY `idx_updated_at` (`updated_at`),
  ADD KEY `idx_status_created` (`status`,`created_at`);

--
-- Индексы таблицы `idea_attachments`
--
ALTER TABLE `idea_attachments`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_idea_id` (`idea_id`),
  ADD KEY `idx_idea_attachments_idea_id` (`idea_id`);

--
-- Индексы таблицы `idea_votes`
--
ALTER TABLE `idea_votes`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_user_vote` (`idea_id`,`user_id`),
  ADD KEY `idx_idea_votes` (`idea_id`),
  ADD KEY `idx_user_votes` (`user_id`),
  ADD KEY `idx_vote_type` (`vote_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Индексы таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_id` (`user_id`),
  ADD KEY `idx_is_read` (`is_read`),
  ADD KEY `idx_created_at` (`created_at`),
  ADD KEY `idx_related` (`related_type`,`related_id`),
  ADD KEY `idx_sender_id` (`sender_id`);

--
-- Индексы таблицы `realtime_events`
--
ALTER TABLE `realtime_events`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_event_type` (`event_type`),
  ADD KEY `idx_created_at` (`created_at`);

--
-- Индексы таблицы `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `email` (`email`);

--
-- AUTO_INCREMENT для сохранённых таблиц
--

--
-- AUTO_INCREMENT для таблицы `attachments`
--
ALTER TABLE `attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `ideas`
--
ALTER TABLE `ideas`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT для таблицы `idea_attachments`
--
ALTER TABLE `idea_attachments`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `idea_votes`
--
ALTER TABLE `idea_votes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT для таблицы `notifications`
--
ALTER TABLE `notifications`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT для таблицы `realtime_events`
--
ALTER TABLE `realtime_events`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT для таблицы `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=13;

--
-- Ограничения внешнего ключа сохраненных таблиц
--

--
-- Ограничения внешнего ключа таблицы `attachments`
--
ALTER TABLE `attachments`
  ADD CONSTRAINT `fk_attachments_idea` FOREIGN KEY (`idea_id`) REFERENCES `ideas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `fk_attachments_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `ideas`
--
ALTER TABLE `ideas`
  ADD CONSTRAINT `ideas_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `idea_attachments`
--
ALTER TABLE `idea_attachments`
  ADD CONSTRAINT `idea_attachments_ibfk_1` FOREIGN KEY (`idea_id`) REFERENCES `ideas` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `idea_votes`
--
ALTER TABLE `idea_votes`
  ADD CONSTRAINT `idea_votes_ibfk_1` FOREIGN KEY (`idea_id`) REFERENCES `ideas` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `idea_votes_ibfk_2` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Ограничения внешнего ключа таблицы `notifications`
--
ALTER TABLE `notifications`
  ADD CONSTRAINT `fk_notifications_sender` FOREIGN KEY (`sender_id`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `fk_notifications_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
