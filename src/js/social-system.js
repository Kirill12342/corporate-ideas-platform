/**
 * Модуль социальных функций для платформы корпоративных идей
 * Включает комментарии, репутацию, челленджи, достижения
 */

class SocialSystem {
    constructor() {
        this.apiBaseUrl = '/praktica_popov/api/v1';
        this.currentUser = null;
        this.notifications = [];
        this.init();
    }

    async init() {
        await this.loadCurrentUser();
        this.setupEventListeners();
        this.checkForNewAchievements();
        setInterval(() => this.checkForNewAchievements(), 60000); // Проверяем каждую минуту
    }

    // === КОММЕНТАРИИ ===

    async loadComments(ideaId, page = 1) {
        try {
            const response = await this.apiCall(`/comments?idea_id=${ideaId}&page=${page}`);
            const container = document.getElementById('comments-list');
            
            if (page === 1) {
                container.innerHTML = '';
            }
            
            response.comments.forEach(comment => {
                this.renderComment(comment, container);
            });
            
            this.updateCommentsPagination(response.pagination);
            return response;
        } catch (error) {
            this.showNotification('Ошибка загрузки комментариев', 'error');
            console.error('Error loading comments:', error);
        }
    }

    renderComment(comment, container) {
        const commentElement = document.createElement('div');
        commentElement.className = `comment ${comment.parent_id ? `reply level-${comment.depth}` : ''}`;
        commentElement.dataset.commentId = comment.id;
        
        commentElement.innerHTML = `
            <div class="comment-header">
                <div class="comment-author">
                    <div class="comment-author-avatar" style="background: ${this.getAvatarColor(comment.username)}">
                        ${comment.username.charAt(0).toUpperCase()}
                    </div>
                    <div class="comment-author-info">
                        <div class="comment-author-name">${comment.username}</div>
                        <div class="comment-author-meta">
                            <span class="user-reputation">
                                <div class="user-level">
                                    <div class="user-level-badge ${this.getLevelBadgeClass(comment.level)}">${comment.level}</div>
                                    <span class="reputation-points">${comment.total_points}</span>
                                </div>
                            </span>
                            ${comment.department ? `<span>${comment.department}</span>` : ''}
                        </div>
                    </div>
                </div>
                <span class="comment-date">${this.formatDate(comment.created_at)}</span>
            </div>
            <div class="comment-content ${comment.is_edited ? 'edited' : ''}">${comment.content}</div>
            <div class="comment-footer">
                <div class="comment-actions-left">
                    <button class="comment-like-btn ${comment.user_liked ? 'liked' : ''}" 
                            onclick="socialSystem.toggleCommentLike(${comment.id})">
                        <i class="fas fa-heart"></i>
                        <span>${comment.likes_count}</span>
                    </button>
                    <button class="comment-reply-btn" onclick="socialSystem.showReplyForm(${comment.id})">
                        <i class="fas fa-reply"></i>
                        Ответить
                    </button>
                    ${comment.user_id === this.currentUser?.id ? 
                        `<button class="comment-edit-btn" onclick="socialSystem.editComment(${comment.id})">
                            <i class="fas fa-edit"></i>
                            Изменить
                        </button>` : ''}
                </div>
            </div>
        `;
        
        container.appendChild(commentElement);
    }

    async createComment(ideaId, content, parentId = null) {
        try {
            const response = await this.apiCall('/comments', 'POST', {
                idea_id: ideaId,
                content: content,
                parent_id: parentId
            });
            
            this.showNotification('Комментарий добавлен!', 'success');
            await this.loadComments(ideaId); // Перезагружаем комментарии
            return response;
        } catch (error) {
            this.showNotification('Ошибка при добавлении комментария', 'error');
            console.error('Error creating comment:', error);
        }
    }

    async toggleCommentLike(commentId) {
        try {
            const response = await this.apiCall('/comments/like', 'POST', {
                comment_id: commentId
            });
            
            // Обновляем UI
            const button = document.querySelector(`[data-comment-id="${commentId}"] .comment-like-btn`);
            const countSpan = button.querySelector('span');
            
            if (response.action === 'liked') {
                button.classList.add('liked');
                button.classList.add('bounce');
                setTimeout(() => button.classList.remove('bounce'), 500);
            } else {
                button.classList.remove('liked');
            }
            
            countSpan.textContent = response.likes_count;
            
        } catch (error) {
            this.showNotification('Ошибка при обработке лайка', 'error');
            console.error('Error toggling comment like:', error);
        }
    }

    showReplyForm(commentId) {
        // Скрываем другие формы ответа
        document.querySelectorAll('.reply-form').forEach(form => form.remove());
        
        const commentElement = document.querySelector(`[data-comment-id="${commentId}"]`);
        const replyForm = document.createElement('div');
        replyForm.className = 'reply-form comment-form';
        replyForm.innerHTML = `
            <textarea class="comment-textarea" placeholder="Напишите ответ..." rows="3"></textarea>
            <div class="comment-actions">
                <div></div>
                <div>
                    <button class="comment-submit" onclick="socialSystem.submitReply(${commentId})">
                        Ответить
                    </button>
                    <button class="comment-submit" style="background: #6c757d; margin-left: 10px;" 
                            onclick="this.closest('.reply-form').remove()">
                        Отмена
                    </button>
                </div>
            </div>
        `;
        
        commentElement.appendChild(replyForm);
        replyForm.querySelector('textarea').focus();
    }

    async submitReply(parentId) {
        const replyForm = document.querySelector(`[data-comment-id="${parentId}"] .reply-form`);
        const textarea = replyForm.querySelector('textarea');
        const content = textarea.value.trim();
        
        if (!content) {
            this.showNotification('Введите текст ответа', 'warning');
            return;
        }
        
        const ideaId = this.getCurrentIdeaId();
        await this.createComment(ideaId, content, parentId);
        replyForm.remove();
    }

    // === РЕПУТАЦИЯ И РЕЙТИНГИ ===

    async loadUserReputation(userId = null) {
        try {
            const url = userId ? `/reputation?user_id=${userId}` : '/reputation';
            const response = await this.apiCall(url);
            return response;
        } catch (error) {
            console.error('Error loading reputation:', error);
        }
    }

    async loadLeaderboard(period = 'all', page = 1) {
        try {
            const response = await this.apiCall(`/reputation/leaderboard?period=${period}&page=${page}`);
            this.renderLeaderboard(response);
            return response;
        } catch (error) {
            this.showNotification('Ошибка загрузки рейтинга', 'error');
            console.error('Error loading leaderboard:', error);
        }
    }

    renderLeaderboard(data) {
        const container = document.getElementById('leaderboard-list');
        if (!container) return;
        
        container.innerHTML = '';
        
        data.leaders.forEach(leader => {
            const item = document.createElement('div');
            item.className = 'leaderboard-item';
            item.innerHTML = `
                <div class="leaderboard-rank rank-${leader.rank}">${leader.rank}</div>
                <div class="leaderboard-user">
                    <div class="leaderboard-avatar" style="background: ${this.getAvatarColor(leader.username)}">
                        ${leader.username.charAt(0).toUpperCase()}
                    </div>
                    <div class="leaderboard-info">
                        <h4 class="leaderboard-name">${leader.username}</h4>
                        <p class="leaderboard-meta">
                            ${leader.department || 'Без отдела'} • Уровень ${leader.level}
                        </p>
                    </div>
                </div>
                <div class="leaderboard-stats">
                    <div class="leaderboard-points">${leader.total_points}</div>
                    <div class="leaderboard-details">
                        ${leader.achievements_count || leader.ideas_count || 0} ${this.getMetricLabel(data.period)}
                    </div>
                </div>
            `;
            container.appendChild(item);
        });
    }

    // === ЧЕЛЛЕНДЖИ ===

    async loadChallenges(filters = {}) {
        try {
            const params = new URLSearchParams(filters);
            const response = await this.apiCall(`/challenges?${params}`);
            this.renderChallenges(response.challenges);
            return response;
        } catch (error) {
            this.showNotification('Ошибка загрузки челленджей', 'error');
            console.error('Error loading challenges:', error);
        }
    }

    renderChallenges(challenges) {
        const container = document.getElementById('challenges-grid');
        if (!container) return;
        
        container.innerHTML = '';
        
        challenges.forEach(challenge => {
            const card = document.createElement('div');
            card.className = `challenge-card ${challenge.status}`;
            card.innerHTML = `
                <div class="challenge-header">
                    <div class="challenge-type">${this.getChallengeTypeLabel(challenge.challenge_type)}</div>
                    <h3 class="challenge-title">${challenge.title}</h3>
                    <p class="challenge-dates">
                        ${this.formatDate(challenge.start_date)} - ${this.formatDate(challenge.end_date)}
                    </p>
                </div>
                <div class="challenge-body">
                    <p class="challenge-description">${challenge.description}</p>
                    
                    <div class="challenge-stats">
                        <div class="challenge-stat">
                            <h4 class="challenge-stat-value">${challenge.participants_count}</h4>
                            <p class="challenge-stat-label">Участников</p>
                        </div>
                        <div class="challenge-stat">
                            <h4 class="challenge-stat-value">${challenge.reward_points}</h4>
                            <p class="challenge-stat-label">Награда</p>
                        </div>
                    </div>
                    
                    ${challenge.is_participating ? `
                        <div class="challenge-progress">
                            <div class="challenge-progress-header">
                                <span class="challenge-progress-label">Ваш прогресс</span>
                                <span class="challenge-progress-percentage">${Math.round(challenge.progress_percentage)}%</span>
                            </div>
                            <div class="challenge-progress-bar">
                                <div class="challenge-progress-fill" style="width: ${challenge.progress_percentage}%"></div>
                            </div>
                        </div>
                    ` : ''}
                    
                    <div class="challenge-actions">
                        ${this.getChallengeActionButtons(challenge)}
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
    }

    getChallengeActionButtons(challenge) {
        if (challenge.status === 'completed') {
            return '<button class="challenge-view-btn">Посмотреть результаты</button>';
        }
        
        if (challenge.is_participating) {
            if (challenge.user_completed) {
                return '<button class="challenge-view-btn pulse">✓ Выполнено!</button>';
            } else {
                return `
                    <button class="challenge-leave-btn" onclick="socialSystem.leaveChallenge(${challenge.id})">
                        Покинуть
                    </button>
                    <button class="challenge-view-btn" onclick="socialSystem.viewChallengeLeaderboard(${challenge.id})">
                        Рейтинг
                    </button>
                `;
            }
        } else {
            return `
                <button class="challenge-join-btn" onclick="socialSystem.joinChallenge(${challenge.id}, '${challenge.challenge_type}')">
                    Присоединиться
                </button>
            `;
        }
    }

    async joinChallenge(challengeId, challengeType) {
        try {
            let teamName = null;
            if (challengeType === 'team') {
                teamName = prompt('Введите название вашей команды:');
                if (!teamName || !teamName.trim()) {
                    this.showNotification('Название команды обязательно для командного челленджа', 'warning');
                    return;
                }
            }
            
            await this.apiCall(`/challenges/${challengeId}/join`, 'POST', { team_name: teamName });
            this.showNotification('Вы присоединились к челленджу!', 'success');
            await this.loadChallenges(); // Перезагружаем челленджи
        } catch (error) {
            this.showNotification('Ошибка при присоединении к челленджу', 'error');
            console.error('Error joining challenge:', error);
        }
    }

    async leaveChallenge(challengeId) {
        if (!confirm('Вы уверены, что хотите покинуть этот челлендж?')) {
            return;
        }
        
        try {
            await this.apiCall(`/challenges/${challengeId}/leave`, 'POST');
            this.showNotification('Вы покинули челлендж', 'info');
            await this.loadChallenges();
        } catch (error) {
            this.showNotification('Ошибка при выходе из челленджа', 'error');
            console.error('Error leaving challenge:', error);
        }
    }

    // === ДОСТИЖЕНИЯ ===

    async loadAchievements(category = 'all') {
        try {
            const response = await this.apiCall(`/achievements?category=${category}`);
            this.renderAchievements(response.achievements);
            this.renderAchievementStats(response.stats);
            return response;
        } catch (error) {
            this.showNotification('Ошибка загрузки достижений', 'error');
            console.error('Error loading achievements:', error);
        }
    }

    renderAchievements(achievements) {
        const container = document.getElementById('achievements-grid');
        if (!container) return;
        
        container.innerHTML = '';
        
        achievements.forEach(achievement => {
            const card = document.createElement('div');
            card.className = `achievement-card ${achievement.rarity} ${achievement.is_unlocked ? 'unlocked' : ''}`;
            card.innerHTML = `
                ${achievement.is_unlocked ? '<div class="achievement-unlocked-badge"><i class="fas fa-check"></i></div>' : ''}
                ${achievement.points_reward > 0 ? `<div class="achievement-reward">+${achievement.points_reward}</div>` : ''}
                
                <div class="achievement-header">
                    <div class="achievement-icon ${achievement.badge_type}">
                        <i class="${achievement.icon_class || 'fas fa-trophy'}"></i>
                    </div>
                    <div class="achievement-info">
                        <h3 class="achievement-title">${achievement.title}</h3>
                        <p class="achievement-description">${achievement.description}</p>
                    </div>
                </div>
                
                ${!achievement.is_unlocked ? `
                    <div class="achievement-progress">
                        <div class="achievement-progress-bar">
                            <div class="achievement-progress-fill" style="width: ${achievement.progress_percentage}%"></div>
                        </div>
                        <div class="achievement-progress-text">
                            <span>${achievement.progress_description}</span>
                            <span>${Math.round(achievement.progress_percentage)}%</span>
                        </div>
                    </div>
                ` : `
                    <div class="achievement-progress">
                        <div class="achievement-progress-text">
                            <span>✓ Разблокировано ${this.formatDate(achievement.unlocked_at)}</span>
                        </div>
                    </div>
                `}
            `;
            container.appendChild(card);
        });
    }

    async checkForNewAchievements() {
        try {
            const response = await this.apiCall('/achievements/check', 'POST');
            if (response.newly_unlocked && response.newly_unlocked.length > 0) {
                response.newly_unlocked.forEach(achievement => {
                    this.showAchievementNotification(achievement);
                });
            }
        } catch (error) {
            // Тихо игнорируем ошибки проверки достижений
            console.debug('Achievement check failed:', error);
        }
    }

    showAchievementNotification(achievement) {
        const notification = document.createElement('div');
        notification.className = 'notification-toast success glow';
        notification.innerHTML = `
            <div class="notification-header">
                <h4 class="notification-title">🏆 Новое достижение!</h4>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
            <p class="notification-message">
                <strong>${achievement.title}</strong><br>
                ${achievement.description}
                ${achievement.points_reward > 0 ? `<br>+${achievement.points_reward} баллов репутации` : ''}
            </p>
        `;
        
        document.body.appendChild(notification);
        
        // Автоматически удаляем через 10 секунд
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 10000);
    }

    // === УТИЛИТЫ И ВСПОМОГАТЕЛЬНЫЕ МЕТОДЫ ===

    async apiCall(endpoint, method = 'GET', data = null) {
        const options = {
            method,
            headers: {
                'Content-Type': 'application/json',
            }
        };
        
        if (data) {
            options.body = JSON.stringify(data);
        }
        
        const token = localStorage.getItem('auth_token');
        if (token) {
            options.headers.Authorization = `Bearer ${token}`;
        }
        
        const response = await fetch(this.apiBaseUrl + endpoint, options);
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.message || 'API error');
        }
        
        return result.data;
    }

    async loadCurrentUser() {
        try {
            // Пытаемся получить информацию о текущем пользователе
            this.currentUser = await this.apiCall('/auth/me');
        } catch (error) {
            console.debug('No authenticated user');
        }
    }

    setupEventListeners() {
        // Обработчик для форм комментариев
        document.addEventListener('submit', (e) => {
            if (e.target.classList.contains('comment-form')) {
                e.preventDefault();
                const textarea = e.target.querySelector('.comment-textarea');
                const content = textarea.value.trim();
                
                if (content) {
                    const ideaId = this.getCurrentIdeaId();
                    this.createComment(ideaId, content);
                    textarea.value = '';
                }
            }
        });
        
        // Обработчики для фильтров
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('social-filter')) {
                this.applyFilters();
            }
        });
    }

    showNotification(message, type = 'info') {
        const notification = document.createElement('div');
        notification.className = `notification-toast ${type}`;
        notification.innerHTML = `
            <div class="notification-header">
                <h4 class="notification-title">${this.getNotificationTitle(type)}</h4>
                <button class="notification-close" onclick="this.parentElement.parentElement.remove()">×</button>
            </div>
            <p class="notification-message">${message}</p>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentElement) {
                notification.remove();
            }
        }, 5000);
    }

    getNotificationTitle(type) {
        const titles = {
            success: 'Успешно',
            error: 'Ошибка',
            warning: 'Предупреждение',
            info: 'Информация'
        };
        return titles[type] || 'Уведомление';
    }

    formatDate(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffMs = now - date;
        const diffMins = Math.floor(diffMs / 60000);
        const diffHours = Math.floor(diffMs / 3600000);
        const diffDays = Math.floor(diffMs / 86400000);
        
        if (diffMins < 1) return 'только что';
        if (diffMins < 60) return `${diffMins} мин назад`;
        if (diffHours < 24) return `${diffHours} ч назад`;
        if (diffDays < 7) return `${diffDays} дн назад`;
        
        return date.toLocaleDateString('ru-RU');
    }

    getAvatarColor(username) {
        const colors = [
            '#667eea', '#764ba2', '#f093fb', '#f5576c', 
            '#4facfe', '#00f2fe', '#43e97b', '#38f9d7',
            '#ffecd2', '#fcb69f', '#a8edea', '#fed6e3'
        ];
        const hash = username.split('').reduce((acc, char) => acc + char.charCodeAt(0), 0);
        return colors[hash % colors.length];
    }

    getLevelBadgeClass(level) {
        if (level >= 50) return 'platinum';
        if (level >= 25) return 'gold';
        if (level >= 10) return 'silver';
        return 'bronze';
    }

    getChallengeTypeLabel(type) {
        const labels = {
            individual: 'Личный',
            team: 'Командный',
            department: 'Отдельский'
        };
        return labels[type] || type;
    }

    getMetricLabel(period) {
        const labels = {
            all: 'идей всего',
            month: 'идей за месяц',
            week: 'идей за неделю'
        };
        return labels[period] || 'активности';
    }

    getCurrentIdeaId() {
        // Получаем ID идеи из URL или data-атрибута
        const urlParams = new URLSearchParams(window.location.search);
        return urlParams.get('idea_id') || document.body.dataset.ideaId;
    }
}

// Инициализация системы
const socialSystem = new SocialSystem();

// Экспорт для использования в других модулях
if (typeof module !== 'undefined' && module.exports) {
    module.exports = SocialSystem;
}