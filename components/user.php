<?php include 'user_auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../css/user.css">
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
            <select id="category-filter-user">
                <option value="">Все категории</option>
                <?php
                try {
                    $categorySql = "SELECT DISTINCT category FROM ideas WHERE user_id = ? AND category IS NOT NULL AND category != '' ORDER BY category ASC";
                    $categoryStmt = $pdo->prepare($categorySql);
                    $categoryStmt->execute([$_SESSION['user_id']]);
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
            
            <select id="status-filter-user">
                <option value="">Все статусы</option>
                <option value="На рассмотрении">На рассмотрении</option>
                <option value="Принято">Принято</option>
                <option value="В работе">В работе</option>
                <option value="Отклонено">Отклонено</option>
            </select>
            
            <button id="reset-filters-user">Сбросить фильтры</button>
        </div>
        
        <div class="burger-btn mobile-only" onclick="toggleMobileMenu()">
            <span></span>
            <span></span>
            <span></span>
        </div>
        
        <div class="right-block desktop-only">
            <button id="idea">Подать идею</button>
            <button id="out">Выход</button>
        </div>
        
        <div class="mobile-menu" id="mobileMenu">
            <div class="mobile-menu-content">
                <a href="../index.html" onclick="closeMobileMenu()">Главная</a>
                <a href="idea.html" onclick="closeMobileMenu()">Подать идею</a>
                <button onclick="window.location.href='logout.php'">Выйти</button>
            </div>
        </div>
    </div>
    <div class="container">
        <div class="text">
            <h1>Мои предложения</h1>
            <?php if (isset($_GET['success'])): ?>
                <div class="success-message" style="color: green; margin: 10px 0; padding: 10px; background: #e8f5e8; border-radius: 5px;">
                    <?= htmlspecialchars($_GET['success']) ?>
                </div>
            <?php endif; ?>
            <p class="search-label">Поиск</p>
            <div class="search-container">
                <input type="text" id="search-input-user" placeholder="Поиск по названию, категории или описанию...">
                <button id="clear-search-user" class="clear-btn" title="Очистить поиск">✕</button>
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
                $sql = "SELECT * FROM ideas WHERE user_id = ? ORDER BY created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_SESSION['user_id']]);
                $ideas = $stmt->fetchAll();
                
                // Получаем файлы для всех идей пользователя
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
                <div class="card" data-category="<?= htmlspecialchars($idea['category']) ?>" data-status="<?= htmlspecialchars($idea['status']) ?>">
                    <p><span class="green">Идея</span>: <?= htmlspecialchars($idea['title']) ?></p>
                    <p><span class="green">Описание</span>: <?= nl2br(htmlspecialchars($idea['description'])) ?></p>
                    <p><span class="green">Категория</span>: <?= htmlspecialchars($idea['category']) ?></p>
                    <p><span class="green">Статус</span>: <span class="<?= $statusClass ?>"><?= htmlspecialchars($idea['status']) ?></span></p>
                    <p><span class="green">Дата подачи</span>: <?= date('d.m.Y H:i', strtotime($idea['created_at'])) ?></p>
                    
                    <?php if (isset($attachments[$idea['id']]) && !empty($attachments[$idea['id']])): ?>
                    <div class="attachments-preview">
                        <p><span class="green">Прикрепленные файлы</span>: <?= count($attachments[$idea['id']]) ?> файл(ов)</p>
                        <div class="attachment-thumbnails">
                            <?php 
                            $shown = 0;
                            foreach ($attachments[$idea['id']] as $attachment): 
                                if ($shown >= 3) break;
                                $isImage = strpos($attachment['file_type'], 'image/') === 0;
                            ?>
                                <a href="download_file.php?id=<?= $attachment['id'] ?>" class="attachment-thumb" title="<?= htmlspecialchars($attachment['original_name']) ?>">
                                    <?php if ($isImage): ?>
                                        <img src="view_image.php?id=<?= $attachment['id'] ?>" 
                                             alt="<?= htmlspecialchars($attachment['original_name']) ?>">
                                    <?php else: ?>
                                        <div class="file-icon-small"><?= getFileIcon($attachment['file_type']) ?></div>
                                    <?php endif; ?>
                                </a>
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
                    
                    <?php if (!empty($idea['admin_notes'])): ?>
                        <p><span class="green">Комментарий администратора</span>: <?= nl2br(htmlspecialchars($idea['admin_notes'])) ?></p>
                    <?php endif; ?>
                </div>
            <?php 
                    endforeach;
                else:
            ?>
                <div class="no-ideas" style="text-align: center; padding: 40px; color: #666;">
                    <h3>У вас пока нет предложений</h3>
                    <p>Нажмите кнопку "Подать идею", чтобы создать первое предложение.</p>
                </div>
            <?php 
                endif;
            } catch (PDOException $e) {
                echo '<div class="error" style="color: red; padding: 20px;">Ошибка загрузки данных: ' . $e->getMessage() . '</div>';
            }
            ?>
        </div>
    </div>
    <script src="../js/user.js"></script>
    <script src="../js/burger-menu.js"></script>
</body>
</html>