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
});
