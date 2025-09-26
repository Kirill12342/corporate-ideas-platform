<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.html?error=auth_required");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Топ идеи - Corporate Ideas Platform</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../css/user.css">
    <link rel="stylesheet" href="../css/burger-menu.css">
    <style>
        .top-ideas-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
        }

        .top-header {
            text-align: center;
            margin-bottom: 30px;
        }

        .top-header h1 {
            color: var(--color_black);
            font-size: 28px;
            margin-bottom: 10px;
        }

        .top-header p {
            color: #666;
            font-size: 16px;
        }

        /* Стили для header и логотипа */
        .header {
            height: 150px;
        }

        .header .left_block img {
            height: 130px;
            width: auto;
            object-fit: contain;
        }

        .header .left_block p {
            display: none; /* Убираем белую надпись */
        }

        .header .right-block {
            display: flex;
            gap: 15px;
            align-items: center;
        }

        .header .right-block button {
            padding: 12px 20px;
            background: var(--color_button, #49AD09);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header .right-block button:hover {
            background: #3a8f07;
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.15);
        }

        .header .right-block button:active {
            transform: translateY(0);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .filters-section {
            background: var(--color_white);
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .filters-row {
            display: flex;
            gap: 15px;
            align-items: center;
            flex-wrap: wrap;
        }

        .filter-group {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }

        .filter-group label {
            font-size: 12px;
            color: #666;
            font-weight: 500;
        }

        .filter-select, .filter-input {
            padding: 8px 12px;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 14px;
            min-width: 120px;
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: var(--color_white);
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }

        .stat-number {
            font-size: 24px;
            font-weight: bold;
            color: var(--color_button);
        }

        .stat-label {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }

        .top-ideas-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
            gap: 20px;
        }

        .top-idea-card {
            background: var(--color_white);
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
        }

        .top-idea-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }

        .rank-badge {
            position: absolute;
            top: 15px;
            right: 15px;
            background: var(--color_button);
            color: var(--color_white);
            width: 30px;
            height: 30px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 14px;
        }

        .rank-badge.gold {
            background: linear-gradient(135deg, #ffd700, #ffed4a);
            color: #000;
        }

        .rank-badge.silver {
            background: linear-gradient(135deg, #c0c0c0, #e5e5e5);
            color: #000;
        }

        .rank-badge.bronze {
            background: linear-gradient(135deg, #cd7f32, #d4a574);
            color: var(--color_white);
        }

        .idea-title {
            font-size: 16px;
            font-weight: bold;
            color: var(--color_black);
            margin-bottom: 8px;
            padding-right: 40px;
        }

        .idea-description {
            font-size: 14px;
            color: #666;
            margin-bottom: 12px;
            line-height: 1.4;
            display: -webkit-box;
            -webkit-line-clamp: 3;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        .idea-meta {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 12px;
            color: #999;
            margin-bottom: 15px;
        }

        .idea-rating {
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .rating-item {
            display: flex;
            align-items: center;
            gap: 4px;
            font-weight: 500;
        }

        .likes {
            color: #28a745;
        }

        .dislikes {
            color: #dc3545;
        }

        .popularity {
            color: #ffc107;
        }

        .loading {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        .no-results {
            text-align: center;
            padding: 40px;
            color: #666;
        }

        @media (max-width: 768px) {
            .top-ideas-grid {
                grid-template-columns: 1fr;
            }

            .stats-grid {
                grid-template-columns: repeat(2, 1fr);
            }

            .filters-row {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-group {
                width: 100%;
            }

            /* Скрываем десктоп меню на мобильных */
            .desktop-only {
                display: none !important;
            }

            /* Показываем бургер-меню на мобильных */
            .mobile-only {
                display: block !important;
            }

            /* Увеличиваем логотип на мобильных */
            .header .left_block img {
                height: 100px;
            }


        }

        /* Переопределяем стили бургер-меню для темного header */
        .header .burger-btn {
            flex-direction: column !important;
            justify-content: space-around !important;
            width: 35px !important;
            height: 35px !important;
            padding: 6px !important;
            background: rgba(255,255,255,0.1) !important;
            border-radius: 6px !important;
            margin-right: 30px !important;
            cursor: pointer !important;
        }

        .header .burger-btn:hover {
            background: rgba(255,255,255,0.2) !important;
        }

        .header .burger-btn span {
            display: block !important;
            background-color: #333 !important;
            height: 3px !important;
            width: 100% !important;
            border-radius: 2px !important;
            transition: all 0.3s ease !important;
        }

        /* Активное состояние бургера */
        .header .burger-btn.active span:nth-child(1) {
            transform: rotate(45deg) translate(8px, 8px) !important;
            background-color: #333 !important;
        }

        .header .burger-btn.active span:nth-child(2) {
            opacity: 0 !important;
        }

        .header .burger-btn.active span:nth-child(3) {
            transform: rotate(-45deg) translate(8px, -8px) !important;
            background-color: #333 !important;
        }

        /* Стили для мобильных устройств */
        @media (max-width: 768px) {
            .mobile-only {
                display: flex !important;
            }

            .header .burger-btn {
                display: flex !important;
            }

            .desktop-only {
                display: none !important;
            }
        }

        /* Стили для десктопа */
        @media (min-width: 769px) {
            .desktop-only {
                display: flex !important;
            }

            .mobile-only, .header .burger-btn {
                display: none !important;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="left_block">
            <img src="../image/logo2.png" alt="Logo">
            <p>Топ идеи</p>
        </div>

        <div class="burger-btn mobile-only" onclick="toggleMobileMenu()">
            <span></span>
            <span></span>
            <span></span>
        </div>

        <div class="right-block desktop-only">
            <button onclick="window.location.href='user.php'">Все идеи</button>
            <button onclick="window.location.href='idea.html'">Подать идею</button>
            <button id="out">Выход</button>
        </div>

        <div class="mobile-menu" id="mobileMenu">
            <div class="mobile-menu-content">
                <a href="../index.html" onclick="closeMobileMenu()">Главная</a>
                <a href="user.php" onclick="closeMobileMenu()">Все идеи</a>
                <a href="idea.html" onclick="closeMobileMenu()">Подать идею</a>
                <button onclick="window.location.href='logout.php'">Выйти</button>
            </div>
        </div>
    </div>

    <div class="top-ideas-container">
        <div class="top-header">
            <h1>🏆 Топ идеи</h1>
            <p>Самые популярные и высоко оцененные идеи сотрудников</p>
        </div>

        <div class="filters-section">
            <div class="filters-row">
                <div class="filter-group">
                    <label for="period-filter">Период</label>
                    <select id="period-filter" class="filter-select">
                        <option value="month">За месяц</option>
                        <option value="week">За неделю</option>
                        <option value="all_time">За все время</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="category-filter">Категория</label>
                    <select id="category-filter" class="filter-select">
                        <option value="all">Все категории</option>
                    </select>
                </div>
                <div class="filter-group">
                    <label for="limit-filter">Количество</label>
                    <select id="limit-filter" class="filter-select">
                        <option value="10">10 идей</option>
                        <option value="20">20 идей</option>
                        <option value="50">50 идей</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-number" id="total-ideas">-</div>
                <div class="stat-label">Всего идей</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="total-likes">-</div>
                <div class="stat-label">Общее количество лайков</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="total-dislikes">-</div>
                <div class="stat-label">Общее количество дизлайков</div>
            </div>
            <div class="stat-card">
                <div class="stat-number" id="avg-popularity">-</div>
                <div class="stat-label">Средняя популярность</div>
            </div>
        </div>

        <div id="loading" class="loading">
            <p>Загрузка топ идей...</p>
        </div>

        <div class="top-ideas-grid" id="top-ideas-grid">
            <!-- Идеи будут загружены через JavaScript -->
        </div>

        <div id="no-results" class="no-results" style="display: none;">
            <h3>Нет идей для отображения</h3>
            <p>Попробуйте изменить параметры фильтрации</p>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const periodFilter = document.getElementById('period-filter');
            const categoryFilter = document.getElementById('category-filter');
            const limitFilter = document.getElementById('limit-filter');
            const topIdeasGrid = document.getElementById('top-ideas-grid');
            const loading = document.getElementById('loading');
            const noResults = document.getElementById('no-results');

            // Загружаем топ идеи
            async function loadTopIdeas() {
                try {
                    loading.style.display = 'block';
                    topIdeasGrid.innerHTML = '';
                    noResults.style.display = 'none';

                    const params = new URLSearchParams({
                        period: periodFilter.value,
                        category: categoryFilter.value,
                        limit: limitFilter.value
                    });

                    const response = await fetch(`get_top_ideas.php?${params}`);
                    const result = await response.json();

                    if (result.success) {
                        displayTopIdeas(result.data.ideas);
                        updateStats(result.data.stats);
                        updateCategories(result.data.categories);
                    } else {
                        showError(result.message);
                    }
                } catch (error) {
                    console.error('Ошибка загрузки:', error);
                    showError('Ошибка при загрузке данных');
                } finally {
                    loading.style.display = 'none';
                }
            }

            // Отображаем топ идеи
            function displayTopIdeas(ideas) {
                if (ideas.length === 0) {
                    noResults.style.display = 'block';
                    return;
                }

                topIdeasGrid.innerHTML = ideas.map((idea, index) => {
                    const rankClass = index === 0 ? 'gold' : index === 1 ? 'silver' : index === 2 ? 'bronze' : '';

                    return `
                        <div class="top-idea-card">
                            <div class="rank-badge ${rankClass}">${index + 1}</div>
                            <div class="idea-title">${escapeHtml(idea.title)}</div>
                            <div class="idea-description">${escapeHtml(idea.description)}</div>
                            <div class="idea-meta">
                                <span>Автор: ${escapeHtml(idea.author_name)}</span>
                                <span>${idea.formatted_date}</span>
                            </div>
                            <div class="idea-rating">
                                <div class="rating-item likes">
                                    <span>👍</span>
                                    <span>${idea.likes_count}</span>
                                </div>
                                <div class="rating-item dislikes">
                                    <span>👎</span>
                                    <span>${idea.dislikes_count}</span>
                                </div>
                                <div class="rating-item popularity">
                                    <span>⭐</span>
                                    <span>${idea.popularity_rank}%</span>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            // Обновляем статистику
            function updateStats(stats) {
                document.getElementById('total-ideas').textContent = stats.total_ideas;
                document.getElementById('total-likes').textContent = stats.total_likes;
                document.getElementById('total-dislikes').textContent = stats.total_dislikes;
                document.getElementById('avg-popularity').textContent = stats.avg_popularity + '%';
            }

            // Обновляем категории
            function updateCategories(categories) {
                const currentValue = categoryFilter.value;
                categoryFilter.innerHTML = '<option value="all">Все категории</option>';

                categories.forEach(category => {
                    const option = document.createElement('option');
                    option.value = category;
                    option.textContent = category;
                    categoryFilter.appendChild(option);
                });

                categoryFilter.value = currentValue;
            }

            // Показываем ошибку
            function showError(message) {
                topIdeasGrid.innerHTML = `
                    <div style="text-align: center; padding: 40px; color: #dc3545; grid-column: 1 / -1;">
                        <h3>Ошибка</h3>
                        <p>${message}</p>
                    </div>
                `;
            }

            // Экранирование HTML
            function escapeHtml(text) {
                const div = document.createElement('div');
                div.textContent = text;
                return div.innerHTML;
            }

            // Обработчики событий
            periodFilter.addEventListener('change', loadTopIdeas);
            categoryFilter.addEventListener('change', loadTopIdeas);
            limitFilter.addEventListener('change', loadTopIdeas);

            // Кнопка выхода
            document.getElementById('out').addEventListener('click', function() {
                if (confirm('Вы уверены, что хотите выйти?')) {
                    window.location.href = 'logout.php';
                }
            });

            // Загружаем данные при загрузке страницы
            loadTopIdeas();
        });
    </script>

    <script src="../js/burger-menu.js"></script>
</body>
</html>