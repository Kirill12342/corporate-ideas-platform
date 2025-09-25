document.addEventListener('DOMContentLoaded', function() {
    document.getElementById('out').addEventListener('click', function() {
        if (confirm('Вы уверены, что хотите выйти?')) {
            window.location.href = 'logout.php';
        }
    });
    
    const ideaBtn = document.getElementById('idea');
    if (ideaBtn) {
        ideaBtn.addEventListener('click', function() {
            window.location.href = 'idea.html';
        });
    }

    // Функциональность фильтрации для панели пользователя
    const categoryFilter = document.getElementById('category-filter-user');
    const statusFilter = document.getElementById('status-filter-user');
    const resetFiltersBtn = document.getElementById('reset-filters-user');
    const searchInput = document.getElementById('search-input-user');

    // Функция фильтрации карточек
    function filterCards() {
        const selectedCategory = categoryFilter.value.toLowerCase();
        const selectedStatus = statusFilter.value.toLowerCase();
        const searchText = searchInput.value.toLowerCase();
        
        const cards = document.querySelectorAll('.card');
        let visibleCount = 0;

        cards.forEach(card => {
            const cardCategory = card.getAttribute('data-category').toLowerCase();
            const cardStatus = card.getAttribute('data-status').toLowerCase();
            
            // Получаем все текстовые данные для поиска
            const cardTitle = card.querySelector('p:first-child').textContent.toLowerCase();
            const cardDescription = card.querySelector('p:nth-child(2)').textContent.toLowerCase();
            const cardCategoryText = card.querySelector('p:nth-child(3)').textContent.toLowerCase();
            
            // Собираем весь текст для поиска
            const allText = `${cardTitle} ${cardDescription} ${cardCategoryText}`.toLowerCase();
            
            // Проверяем соответствие фильтрам
            const matchesCategory = selectedCategory === '' || cardCategory === selectedCategory;
            const matchesStatus = selectedStatus === '' || cardStatus === selectedStatus;
            const matchesSearch = searchText === '' || allText.includes(searchText);
            
            if (matchesCategory && matchesStatus && matchesSearch) {
                card.style.display = 'block';
                card.style.animation = 'fadeIn 0.3s ease-in';
                visibleCount++;
            } else {
                card.style.display = 'none';
            }
        });

        // Показываем сообщение "нет результатов" если карточки не найдены
        showNoResultsMessage(visibleCount === 0);
    }

    // Функция показа/скрытия сообщения "нет результатов"
    function showNoResultsMessage(show) {
        let noResultsDiv = document.querySelector('.no-results-filter-user');
        
        if (show && !noResultsDiv) {
            noResultsDiv = document.createElement('div');
            noResultsDiv.className = 'no-results-filter-user';
            noResultsDiv.innerHTML = `
                <div style="text-align: center; padding: 40px; color: #666;">
                    <h3>По вашему запросу ничего не найдено</h3>
                    <p>Попробуйте изменить параметры фильтрации или поиска.</p>
                </div>
            `;
            document.querySelector('.cards').appendChild(noResultsDiv);
        } else if (!show && noResultsDiv) {
            noResultsDiv.remove();
        }
    }

    // Функция сброса всех фильтров
    function resetAllFilters() {
        categoryFilter.value = '';
        statusFilter.value = '';
        searchInput.value = '';
        if (searchInput) {
            const searchContainer = searchInput.parentElement;
            searchContainer.classList.remove('has-text');
        }
        filterCards();
    }

    // Обработчики для кнопки очистки поиска
    const clearSearchBtn = document.getElementById('clear-search-user');
    
    // Показать/скрыть кнопку очистки поиска
    function toggleClearButton() {
        if (searchInput && searchInput.value.trim() !== '') {
            searchInput.parentElement.classList.add('has-text');
        } else if (searchInput) {
            searchInput.parentElement.classList.remove('has-text');
        }
    }

    // Очистить поиск
    function clearSearch() {
        if (searchInput) {
            searchInput.value = '';
            searchInput.parentElement.classList.remove('has-text');
            filterCards();
        }
    }

    // Обработчики событий для фильтров (только если элементы существуют)
    if (categoryFilter) categoryFilter.addEventListener('change', filterCards);
    if (statusFilter) statusFilter.addEventListener('change', filterCards);
    if (resetFiltersBtn) resetFiltersBtn.addEventListener('click', resetAllFilters);
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            toggleClearButton();
            filterCards();
        });
        // Инициализация состояния кнопки очистки
        toggleClearButton();
    }
    if (clearSearchBtn) clearSearchBtn.addEventListener('click', clearSearch);

    // Система голосования
    const voteButtons = document.querySelectorAll('.vote-btn');
    
    voteButtons.forEach(button => {
        button.addEventListener('click', async function() {
            const ideaId = this.getAttribute('data-idea-id');
            const voteType = this.getAttribute('data-vote-type');
            const isActive = this.classList.contains('active');
            
            // Если кнопка уже активна, отменяем голос
            const actualVoteType = isActive ? 'remove' : voteType;
            
            try {
                // Блокируем кнопки во время запроса
                const allVoteButtons = document.querySelectorAll(`[data-idea-id="${ideaId}"]`);
                allVoteButtons.forEach(btn => btn.disabled = true);
                
                const response = await fetch('vote_idea.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        idea_id: parseInt(ideaId),
                        vote_type: actualVoteType
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    // Обновляем UI
                    updateVoteButtons(ideaId, result.stats, result.user_vote);
                    
                    // Показываем уведомление
                    showNotification(result.message, 'success');
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                console.error('Ошибка при голосовании:', error);
                showNotification('Ошибка при отправке голоса', 'error');
            } finally {
                // Разблокируем кнопки
                const allVoteButtons = document.querySelectorAll(`[data-idea-id="${ideaId}"]`);
                allVoteButtons.forEach(btn => btn.disabled = false);
            }
        });
    });

    // Функция обновления кнопок голосования
    function updateVoteButtons(ideaId, stats, userVote) {
        const likeBtn = document.querySelector(`.like-btn[data-idea-id="${ideaId}"]`);
        const dislikeBtn = document.querySelector(`.dislike-btn[data-idea-id="${ideaId}"]`);
        
        if (likeBtn && dislikeBtn) {
            // Обновляем счетчики
            likeBtn.querySelector('.vote-count').textContent = stats.likes_count;
            dislikeBtn.querySelector('.vote-count').textContent = stats.dislikes_count;
            
            // Обновляем активные состояния
            likeBtn.classList.toggle('active', userVote === 'like');
            dislikeBtn.classList.toggle('active', userVote === 'dislike');
            
            // Обновляем рейтинг популярности
            const card = document.querySelector(`[data-idea-id="${ideaId}"]`);
            const popularityElement = card.querySelector('.popularity-rank');
            
            if (popularityElement) {
                if (stats.likes_count > 0 || stats.dislikes_count > 0) {
                    popularityElement.innerHTML = `<i class="star-icon">⭐</i> ${stats.popularity_rank}% популярность`;
                    popularityElement.style.display = 'inline-flex';
                } else {
                    popularityElement.style.display = 'none';
                }
            }
        }
    }

    // Функция показа уведомлений
    function showNotification(message, type = 'info') {
        // Удаляем предыдущие уведомления
        const existingNotifications = document.querySelectorAll('.notification');
        existingNotifications.forEach(notification => notification.remove());
        
        const notification = document.createElement('div');
        notification.className = `notification ${type}`;
        notification.innerHTML = `
            <span>${message}</span>
            <button class="notification-close">&times;</button>
        `;
        
        // Добавляем стили для уведомления
        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            border-radius: 8px;
            color: white;
            font-weight: 500;
            z-index: 10000;
            display: flex;
            align-items: center;
            gap: 10px;
            max-width: 300px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
            animation: slideIn 0.3s ease-out;
        `;
        
        if (type === 'success') {
            notification.style.backgroundColor = '#28a745';
        } else if (type === 'error') {
            notification.style.backgroundColor = '#dc3545';
        } else {
            notification.style.backgroundColor = '#17a2b8';
        }
        
        // Добавляем анимацию
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideIn {
                from {
                    transform: translateX(100%);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }
        `;
        document.head.appendChild(style);
        
        document.body.appendChild(notification);
        
        // Обработчик закрытия
        const closeBtn = notification.querySelector('.notification-close');
        closeBtn.style.cssText = `
            background: none;
            border: none;
            color: white;
            font-size: 18px;
            cursor: pointer;
            padding: 0;
            margin-left: auto;
        `;
        
        closeBtn.addEventListener('click', () => notification.remove());
        
        // Автоматическое удаление через 5 секунд
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
});