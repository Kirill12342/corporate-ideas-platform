<?php include 'admin_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/admin.css">
    <link rel="stylesheet" href="../css/burger-menu.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,opsz,wght@0,14..32,100..900;1,14..32,100..900&display=swap" rel="stylesheet">
    <title>Document</title>
</head>
<body>
    <div class="header">
        <div class="left_block">
            <p>Фильтровать:</p>
            <select id="category-filter">
                <option value="">Все категории</option>
                <?php
                try {
                    $categorySql = "SELECT DISTINCT category FROM ideas WHERE category IS NOT NULL AND category != '' ORDER BY category ASC";
                    $categoryStmt = $pdo->prepare($categorySql);
                    $categoryStmt->execute();
                    $categories = $categoryStmt->fetchAll();

                    foreach ($categories as $cat):
                ?>
                    <option value="<?= htmlspecialchars($cat['category']) ?>"><?= htmlspecialchars($cat['category']) ?></option>
                <?php
                    endforeach;
                } catch (PDOException $e) {
                    // Обработка ошибки, но не прерываем работу страницы
                }
                ?>
            </select>

            <select id="status-filter">
                <option value="">Все статусы</option>
                <option value="На рассмотрении">На рассмотрении</option>
                <option value="Принято">Принято</option>
                <option value="В работе">В работе</option>
                <option value="Отклонено">Отклонено</option>
            </select>

            <button id="reset-filters">Сбросить фильтры</button>
        </div>

        <div class="burger-btn mobile-only" onclick="toggleMobileMenu()">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div class="right-block desktop-only">
            <button onclick="window.location.href='top_ideas.php'">🏆 Топ идеи</button>
            <button onclick="window.location.href='admin.html'">📊 Дашборд</button>
            <button id="out">Выход</button>
        </div>

        <div class="mobile-menu" id="mobileMenu">
            <div class="mobile-menu-content">
                <a href="../index.html" onclick="closeMobileMenu()">Главная</a>
                <a href="top_ideas.php" onclick="closeMobileMenu()">🏆 Топ идеи</a>
                <a href="admin.html" onclick="closeMobileMenu()">📊 Дашборд</a>
                <a href="idea.html" onclick="closeMobileMenu()">Подать идею</a>
                <button onclick="window.location.href='logout.php'">Выйти</button>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="text">
            <h1>Управление идеями</h1>
            <p class="search-label">Поиск</p>
            <div class="search-container">
                <input type="text" id="search-input" placeholder="Поиск по названию, категории, описанию или автору...">
                <button id="clear-search" class="clear-btn" title="Очистить поиск">✕</button>
            </div>
        </div>
        <div class="cards">
            <?php
            function getFileIcon($type) {
                if (strpos($type, 'pdf') !== false) return '📄';
                if (strpos($type, 'word') !== false) return '📝';
                if (strpos($type, 'excel') !== false || strpos($type, 'spreadsheet') !== false) return '📊';
                if (strpos($type, 'powerpoint') !== false || strpos($type, 'presentation') !== false) return '📈';
                if (strpos($type, 'zip') !== false || strpos($type, 'rar') !== false) return '📦';
                if (strpos($type, 'text') !== false) return '📃';
                return '📄';
            }

            try {
                $sql = "SELECT i.*, u.fullname, u.email
                        FROM ideas i
                        JOIN users u ON i.user_id = u.id
                        ORDER BY i.total_score DESC, i.likes_count DESC, i.created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute();
                $ideas = $stmt->fetchAll();

                // Получаем файлы для всех идей
                $attachments = [];
                if (!empty($ideas)) {
                    $idea_ids = array_column($ideas, 'id');
                    $placeholders = str_repeat('?,', count($idea_ids) - 1) . '?';
                    $attachSql = "SELECT * FROM idea_attachments WHERE idea_id IN ($placeholders) ORDER BY upload_date ASC";
                    $attachStmt = $pdo->prepare($attachSql);
                    $attachStmt->execute($idea_ids);
                    $allAttachments = $attachStmt->fetchAll();

                    // Группируем по idea_id
                    foreach ($allAttachments as $attachment) {
                        $attachments[$attachment['idea_id']][] = $attachment;
                    }
                }

                if (count($ideas) > 0):
                    foreach ($ideas as $idea):
                        $statusClass = '';
                        switch($idea['status']) {
                            case 'На рассмотрении':
                                $statusClass = 'rasmotr';
                                break;
                            case 'Принято':
                                $statusClass = 'prinat';
                                break;
                            case 'В работе':
                                $statusClass = 'vrabote';
                                break;
                            case 'Отклонено':
                                $statusClass = 'otkloneno';
                                break;
                        }
            ?>
                <div class="card" data-idea-id="<?= $idea['id'] ?>" data-category="<?= htmlspecialchars($idea['category']) ?>" data-status="<?= htmlspecialchars($idea['status']) ?>">
                    <div class="zag"><p>Предложение #<?= $idea['id'] ?> от <?= htmlspecialchars($idea['fullname']) ?></p></div>
                    <div class="card-text">
                        <p><span class="green">Идея</span>: <?= htmlspecialchars($idea['title']) ?></p>
                        <p><span class="green">Категория</span>: <?= htmlspecialchars($idea['category']) ?></p>
                        <p><span class="green">Статус</span>: <span class="<?= $statusClass ?>"><?= htmlspecialchars($idea['status']) ?></span></p>
                        <p><span class="green">Дата</span>: <?= date('d.m.Y H:i', strtotime($idea['created_at'])) ?></p>

                        <!-- Рейтинг и голосование -->
                        <div class="rating-stats">
                            <span class="likes-count">
                                <i class="like-icon">👍</i> <?= $idea['likes_count'] ?? 0 ?>
                            </span>
                            <span class="dislikes-count">
                                <i class="dislike-icon">👎</i> <?= $idea['dislikes_count'] ?? 0 ?>
                            </span>
                            <?php if (($idea['likes_count'] ?? 0) > 0 || ($idea['dislikes_count'] ?? 0) > 0): ?>
                            <span class="popularity-rank">
                                <i class="star-icon">⭐</i> <?= round($idea['popularity_rank'] ?? 0, 1) ?>%
                            </span>
                            <?php endif; ?>
                        </div>

                        <?php if (isset($attachments[$idea['id']]) && !empty($attachments[$idea['id']])): ?>
                        <div class="attachments-preview">
                            <p><span class="green">Вложения</span>: <?= count($attachments[$idea['id']]) ?> файл(ов)</p>
                            <div class="attachment-thumbnails">
                                <?php
                                $shown = 0;
                                foreach ($attachments[$idea['id']] as $attachment):
                                    if ($shown >= 3) break;
                                    $isImage = strpos($attachment['file_type'], 'image/') === 0;
                                ?>
                                    <div class="attachment-thumb">
                                        <?php if ($isImage): ?>
                                            <img src="view_image.php?id=<?= $attachment['id'] ?>"
                                                 alt="<?= htmlspecialchars($attachment['original_name']) ?>"
                                                 title="<?= htmlspecialchars($attachment['original_name']) ?>">
                                        <?php else: ?>
                                            <div class="file-icon-small"><?= getFileIcon($attachment['file_type']) ?></div>
                                        <?php endif; ?>
                                    </div>
                                <?php
                                    $shown++;
                                endforeach;
                                if (count($attachments[$idea['id']]) > 3):
                                ?>
                                    <div class="more-files">+<?= count($attachments[$idea['id']]) - 3 ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <div class="card_btn">
                        <button data-idea='<?= json_encode($idea) ?>'>Подробнее</button>
                    </div>
                </div>
            <?php
                    endforeach;
                else:
            ?>
                <div class="no-ideas" style="text-align: center; padding: 40px; color: #666;">
                    <h3>Нет идей для отображения</h3>
                    <p>Пока никто не подал предложений.</p>
                </div>
            <?php
                endif;
            } catch (PDOException $e) {
                echo '<div class="error" style="color: red; padding: 20px;">Ошибка загрузки данных: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>
    </div>


    <div id="modal" class="modal hidden">
        <div class="modal-content">
            <span class="modal-close">&times;</span>
            <h2 id="modal-title">Детали предложения</h2>
            <div id="modal-content">
                <p><strong>Автор:</strong> <span id="modal-author"></span></p>
                <p><strong>Email:</strong> <span id="modal-email"></span></p>
                <p><strong>Идея:</strong> <span id="modal-idea"></span></p>
                <p><strong>Описание:</strong> <span id="modal-description"></span></p>
                <p><strong>Категория:</strong> <span id="modal-category"></span></p>
                <p><strong>Дата подачи:</strong> <span id="modal-date"></span></p>
                <div id="modal-attachments" class="modal-attachments" style="display: none;">
                    <p><strong>Прикрепленные файлы:</strong></p>
                    <div id="attachments-list" class="attachments-list"></div>
                </div>
            </div>
            <div style="margin-top: 20px;">
                <label for="status-select"><strong>Статус:</strong></label>
                <select id="status-select">
                    <option value="На рассмотрении">На рассмотрении</option>
                    <option value="Принято">Принято</option>
                    <option value="В работе">В работе</option>
                    <option value="Отклонено">Отклонено</option>
                </select>
            </div>
            <div style="margin-top: 15px;">
                <label for="admin-notes"><strong>Комментарий администратора:</strong></label>
                <textarea id="admin-notes" rows="4" cols="50" placeholder="Добавьте комментарий для пользователя..."></textarea>
            </div>
            <div class="modal-buttons">
                <button id="save-status">Сохранить изменения</button>
                <button id="delete-idea">Удалить идею</button>
                <button id="cancel-modal">Отмена</button>
            </div>
        </div>
    </div>


    <script src="../js/admin.js"></script>
    <script src="../js/burger-menu.js"></script>

</body>
</html>