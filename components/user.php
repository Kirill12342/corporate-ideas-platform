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
            <button>По Категориям</button>
            <button>По статусу</button>
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
        </div>
        <div class="cards">
            <?php
            try {
                $sql = "SELECT * FROM ideas WHERE user_id = ? ORDER BY created_at DESC";
                $stmt = $pdo->prepare($sql);
                $stmt->execute([$_SESSION['user_id']]);
                $ideas = $stmt->fetchAll();
                
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
                <div class="card">
                    <p><span class="green">Идея</span>: <?= htmlspecialchars($idea['title']) ?></p>
                    <p><span class="green">Описание</span>: <?= nl2br(htmlspecialchars($idea['description'])) ?></p>
                    <p><span class="green">Категория</span>: <?= htmlspecialchars($idea['category']) ?></p>
                    <p><span class="green">Статус</span>: <span class="<?= $statusClass ?>"><?= htmlspecialchars($idea['status']) ?></span></p>
                    <p><span class="green">Дата подачи</span>: <?= date('d.m.Y H:i', strtotime($idea['created_at'])) ?></p>
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