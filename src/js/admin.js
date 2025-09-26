document.addEventListener('DOMContentLoaded', () => {
  const modal = document.getElementById('modal');
  const closeBtn = modal.querySelector('.modal-close');
  const statusSelect = document.getElementById('status-select');
  const saveBtn = document.getElementById('save-status');
  const adminNotesTextarea = document.getElementById('admin-notes'); 
  const cancelBtn = document.getElementById('cancel-modal');

  let currentIdeaId = null;
  let currentIdeaData = null;

  // Обработчики для кнопок "Подробнее"
  document.querySelectorAll('.card_btn button').forEach(button => {
    button.addEventListener('click', () => {
      const ideaData = JSON.parse(button.getAttribute('data-idea'));
      currentIdeaData = ideaData;
      currentIdeaId = ideaData.id;
      
      document.getElementById('modal-author').textContent = ideaData.fullname;
      document.getElementById('modal-email').textContent = ideaData.email;
      document.getElementById('modal-idea').textContent = ideaData.title;
      document.getElementById('modal-description').textContent = ideaData.description;
      document.getElementById('modal-category').textContent = ideaData.category;
      document.getElementById('modal-date').textContent = new Date(ideaData.created_at).toLocaleString('ru-RU');
      
      statusSelect.value = ideaData.status;
      adminNotesTextarea.value = ideaData.admin_notes || '';

      // Загружаем файлы для этой идеи
      loadIdeaAttachments(ideaData.id);

      modal.classList.remove('hidden');
    });
  });

  function closeModal() {
    modal.classList.add('hidden');
    currentIdeaId = null;
    currentIdeaData = null;
  }

  // Обработчик для кнопки удаления в модальном окне
  const deleteBtn = document.getElementById('delete-idea');
  deleteBtn.addEventListener('click', async () => {
    if (!currentIdeaId || !currentIdeaData) return;
    
    const ideaTitle = currentIdeaData.title;
    const authorName = currentIdeaData.fullname;
    
    if (confirm(`Вы уверены, что хотите удалить эту идею?\n\nИдея: ${ideaTitle}\nАвтор: ${authorName}\n\nЭто действие нельзя отменить!`)) {
      try {
        const response = await fetch('delete_idea.php', {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
          },
          body: JSON.stringify({
            idea_id: currentIdeaId
          })
        });
        
        const result = await response.json();
        
        if (result.success) {
          // Находим карточку для удаления
          const card = document.querySelector(`[data-idea-id="${currentIdeaId}"]`);
          if (card) {
            // Анимация удаления карточки
            card.style.transition = 'all 0.3s ease-out';
            card.style.opacity = '0';
            card.style.transform = 'translateX(-100%)';
            
            setTimeout(() => {
              card.remove();
              
              // Проверяем, остались ли карточки
              const remainingCards = document.querySelectorAll('.card');
              if (remainingCards.length === 0) {
                const cardsContainer = document.querySelector('.cards');
                cardsContainer.innerHTML = `
                  <div class="no-ideas" style="text-align: center; padding: 40px; color: #666;">
                    <h3>Нет идей для отображения</h3>
                    <p>Пока никто не подал предложений.</p>
                  </div>
                `;
              }
            }, 300);
          }
          
          alert(result.message || 'Идея успешно удалена');
          closeModal();
        } else {
          alert('Ошибка при удалении: ' + result.error);
        }
      } catch (error) {
        alert('Ошибка сети: ' + error.message);
        console.error('Delete error:', error);
      }
    }
  });

  closeBtn.addEventListener('click', closeModal);
  cancelBtn.addEventListener('click', closeModal);

  modal.addEventListener('click', (e) => {
    if (e.target === modal) {
      closeModal();
    }
  });

  saveBtn.addEventListener('click', async () => {
    if (!currentIdeaId) return;
    
    const newStatus = statusSelect.value;
    const newNotes = adminNotesTextarea.value;
    
    try {
      const response = await fetch('update_idea.php', {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          idea_id: currentIdeaId,
          status: newStatus,
          admin_notes: newNotes
        })
      });
      
      const result = await response.json();
      
      if (result.success) {
        const card = document.querySelector(`[data-idea-id="${currentIdeaId}"]`);
        if (card) {
          const statusSpan = card.querySelector('.card-text span[class*="rasmotr"], .card-text span[class*="prinat"], .card-text span[class*="vrabote"], .card-text span[class*="otkloneno"]');
          if (statusSpan) {
            statusSpan.className = '';
            statusSpan.textContent = newStatus;
            
            if (newStatus === 'На рассмотрении') {
              statusSpan.classList.add('rasmotr');
            } else if (newStatus === 'Принято') {
              statusSpan.classList.add('prinat');
            } else if (newStatus === 'В работе') {
              statusSpan.classList.add('vrabote');
            } else if (newStatus === 'Отклонено') {
              statusSpan.classList.add('otkloneno');
            }
          }
          
          const button = card.querySelector('.card_btn button');
          if (button) {
            const updatedData = {...currentIdeaData, status: newStatus, admin_notes: newNotes};
            button.setAttribute('data-idea', JSON.stringify(updatedData));
          }
        }
        
        alert('Статус успешно обновлен!');
        closeModal();
      } else {
        alert('Ошибка: ' + result.error);
      }
    } catch (error) {
      alert('Ошибка сети: ' + error.message);
    }
  });

  document.getElementById('out').addEventListener('click', function() {
    if (confirm('Вы уверены, что хотите выйти?')) {
      window.location.href = 'logout.php';
    }
  });

  // Функциональность фильтрации
  const categoryFilter = document.getElementById('category-filter');
  const statusFilter = document.getElementById('status-filter');
  const resetFiltersBtn = document.getElementById('reset-filters');
  const searchInput = document.getElementById('search-input');

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
      const cardTitle = card.querySelector('.card-text p:first-child').textContent.toLowerCase();
      const cardCategoryText = card.querySelector('.card-text p:nth-child(2)').textContent.toLowerCase();
      const cardAuthor = card.querySelector('.zag p').textContent.toLowerCase();
      
      // Собираем весь текст для поиска
      const allText = `${cardTitle} ${cardCategoryText} ${cardAuthor}`.toLowerCase();
      
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
    let noResultsDiv = document.querySelector('.no-results-filter');
    
    if (show && !noResultsDiv) {
      noResultsDiv = document.createElement('div');
      noResultsDiv.className = 'no-results-filter';
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
    searchInput.parentElement.classList.remove('has-text');
    filterCards();
  }

  // Обработчики для кнопки очистки поиска
  const clearSearchBtn = document.getElementById('clear-search');
  const searchContainer = searchInput.parentElement;

  // Показать/скрыть кнопку очистки поиска
  function toggleClearButton() {
    if (searchInput.value.trim() !== '') {
      searchContainer.classList.add('has-text');
    } else {
      searchContainer.classList.remove('has-text');
    }
  }

  // Очистить поиск
  function clearSearch() {
    searchInput.value = '';
    searchContainer.classList.remove('has-text');
    filterCards();
  }

  // Обработчики событий для фильтров
  categoryFilter.addEventListener('change', filterCards);
  statusFilter.addEventListener('change', filterCards);
  resetFiltersBtn.addEventListener('click', resetAllFilters);
  searchInput.addEventListener('input', () => {
    toggleClearButton();
    filterCards();
  });
  clearSearchBtn.addEventListener('click', clearSearch);

  // Инициализация состояния кнопки очистки
  toggleClearButton();

  // Функция для загрузки файлов идеи
  function loadIdeaAttachments(ideaId) {
    fetch(`get_attachments.php?idea_id=${ideaId}`)
      .then(response => response.json())
      .then(data => {
        const attachmentsContainer = document.getElementById('modal-attachments');
        const attachmentsList = document.getElementById('attachments-list');
        
        if (data.success && data.attachments.length > 0) {
          attachmentsList.innerHTML = '';
          
          data.attachments.forEach(attachment => {
            const attachmentItem = document.createElement('div');
            attachmentItem.className = 'attachment-item';
            
            const isImage = attachment.file_type.startsWith('image/');
            
            if (isImage) {
              attachmentItem.innerHTML = `
                <img src="${attachment.file_path}" alt="${attachment.original_name}">
                <div class="file-name">${attachment.original_name}</div>
                <div class="file-size">${formatFileSize(attachment.file_size)}</div>
                <button class="download-btn" onclick="downloadFile(${attachment.id})">Скачать</button>
              `;
            } else {
              attachmentItem.innerHTML = `
                <div class="file-icon">${getFileIconJs(attachment.file_type)}</div>
                <div class="file-name">${attachment.original_name}</div>
                <div class="file-size">${formatFileSize(attachment.file_size)}</div>
                <button class="download-btn" onclick="downloadFile(${attachment.id})">Скачать</button>
              `;
            }
            
            attachmentsList.appendChild(attachmentItem);
          });
          
          attachmentsContainer.style.display = 'block';
        } else {
          attachmentsContainer.style.display = 'none';
        }
      })
      .catch(error => {
        console.error('Ошибка при загрузке файлов:', error);
        document.getElementById('modal-attachments').style.display = 'none';
      });
  }

  // Функция для скачивания файла
  window.downloadFile = function(attachmentId) {
    window.open(`download_file.php?id=${attachmentId}`, '_blank');
  };

  // Функция получения иконки файла
  function getFileIconJs(type) {
    if (type.includes('pdf')) return '📄';
    if (type.includes('word')) return '📝';
    if (type.includes('excel') || type.includes('spreadsheet')) return '📊';
    if (type.includes('powerpoint') || type.includes('presentation')) return '📈';
    if (type.includes('zip') || type.includes('rar')) return '📦';
    if (type.includes('text')) return '📃';
    return '📄';
  }

  // Функция форматирования размера файла
  function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }
});
