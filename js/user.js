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
});