// Расширенный JavaScript для админ-панели с улучшенными функциями
class AdminDashboard {
    constructor() {
        this.currentSection = 'overview';
        this.charts = {};
        this.filters = new FilterManager();
        this.users = new UsersManager();
        this.analytics = new AnalyticsManager();
        this.reports = new ReportsManager();

        this.init();
    }

    init() {
        this.setupNavigation();
        this.loadDashboardData();
        this.setupEventListeners();
        this.setupRefreshInterval();
    }

    setupNavigation() {
        const navItems = document.querySelectorAll('.nav-item');
        navItems.forEach(item => {
            item.addEventListener('click', (e) => {
                e.preventDefault();
                const section = item.dataset.section;
                this.switchSection(section);
            });
        });
    }

    switchSection(sectionName) {
        // Скрываем все секции
        document.querySelectorAll('.dashboard-section').forEach(section => {
            section.style.display = 'none';
        });

        // Показываем выбранную секцию
        const targetSection = document.getElementById(`section-${sectionName}`);
        if (targetSection) {
            targetSection.style.display = 'block';
        }

        // Обновляем активный пункт меню
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.remove('active');
        });
        document.querySelector(`[data-section="${sectionName}"]`).classList.add('active');

        this.currentSection = sectionName;

        // Загружаем данные для секции
        this.loadSectionData(sectionName);
    }

    async loadSectionData(section) {
        switch(section) {
            case 'overview':
                await this.loadOverviewData();
                break;
            case 'users':
                await this.users.loadUsers();
                break;
            case 'analytics':
                await this.analytics.loadAnalytics();
                break;
            case 'reports':
                await this.reports.loadReports();
                break;
            case 'ideas':
                await this.loadIdeasData();
                break;
        }
    }

    async loadIdeasData() {
        // Заглушка для загрузки данных идей - используем существующий функционал из dashboard.js
        console.log('Загрузка данных идей...');
        // Здесь может быть логика загрузки идей, если необходимо
    }

    async loadDashboardData() {
        try {
            const response = await fetch('admin_analytics.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_dashboard_stats' })
            });

            const data = await response.json();
            if (data.success) {
                this.updateStatsCards(data.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки данных дашборда:', error);
        }
    }

    async loadOverviewData() {
        try {
            // Загружаем основные графики
            await Promise.all([
                this.loadIdeasTimelineChart(),
                this.loadCategoriesChart(),
                this.loadStatusChart()
            ]);
        } catch (error) {
            console.error('Ошибка загрузки данных обзора:', error);
        }
    }

    async loadIdeasTimelineChart() {
        try {
            const response = await fetch('admin_analytics.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_ideas_timeline',
                    period: 30,
                    group_by: 'day'
                })
            });

            const data = await response.json();
            if (data.success) {
                this.createTimelineChart(data.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки графика динамики:', error);
        }
    }

    createTimelineChart(timelineData) {
        const ctx = document.getElementById('ideas-timeline-chart');
        if (!ctx) return;

        // Группируем данные по датам и статусам
        const dates = [...new Set(timelineData.map(item => item.period))].sort();
        const statuses = [...new Set(timelineData.map(item => item.status))];

        const datasets = statuses.map(status => {
            const color = this.getStatusColor(status);
            return {
                label: status,
                data: dates.map(date => {
                    const item = timelineData.find(d => d.period === date && d.status === status);
                    return item ? item.count : 0;
                }),
                borderColor: color,
                backgroundColor: color + '20',
                tension: 0.4
            };
        });

        if (this.charts.timeline) {
            this.charts.timeline.destroy();
        }

        this.charts.timeline = new Chart(ctx, {
            type: 'line',
            data: {
                labels: dates,
                datasets: datasets
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Динамика подачи идей'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    async loadCategoriesChart() {
        try {
            const response = await fetch('admin_analytics.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_categories_stats' })
            });

            const data = await response.json();
            if (data.success) {
                this.createCategoriesChart(data.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки графика категорий:', error);
        }
    }

    async loadStatusChart() {
        try {
            const response = await fetch('admin_analytics.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_status_distribution' })
            });

            const data = await response.json();
            if (data.success) {
                this.createStatusChart(data.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки графика статусов:', error);
        }
    }

    createCategoriesChart(categories) {
        const ctx = document.getElementById('categories-chart');
        if (!ctx) return;

        if (this.charts.categories) {
            this.charts.categories.destroy();
        }

        this.charts.categories = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: categories.map(cat => cat.category),
                datasets: [{
                    data: categories.map(cat => cat.count),
                    backgroundColor: [
                        '#3498db', '#e74c3c', '#f39c12', '#27ae60',
                        '#9b59b6', '#1abc9c', '#34495e', '#f1c40f'
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Распределение по категориям'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    createStatusChart(statuses) {
        const ctx = document.getElementById('ideas-status-chart');
        if (!ctx) return;

        if (this.charts.status) {
            this.charts.status.destroy();
        }

        const colors = {
            'На рассмотрении': '#f39c12',
            'В работе': '#3498db',
            'Принято': '#27ae60',
            'Отклонено': '#e74c3c'
        };

        this.charts.status = new Chart(ctx, {
            type: 'pie',
            data: {
                labels: statuses.map(s => s.status),
                datasets: [{
                    data: statuses.map(s => s.count),
                    backgroundColor: statuses.map(s => colors[s.status] || '#95a5a6')
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Статусы идей'
                    },
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    updateStatsCards(stats) {
        const cards = {
            'total_ideas': stats.total_ideas,
            'ideas_in_progress': stats.ideas_in_progress,
            'ideas_approved': stats.ideas_approved,
            'active_users': stats.active_users
        };

        Object.entries(cards).forEach(([key, value]) => {
            const card = document.querySelector(`[data-stat="${key}"] .stat-value`);
            if (card) {
                this.animateValue(card, 0, value, 1000);
            }
        });
    }

    animateValue(element, start, end, duration) {
        const startTime = performance.now();
        const animate = (currentTime) => {
            const elapsed = currentTime - startTime;
            const progress = Math.min(elapsed / duration, 1);
            const current = Math.floor(start + (end - start) * progress);

            element.textContent = current.toLocaleString();

            if (progress < 1) {
                requestAnimationFrame(animate);
            }
        };
        requestAnimationFrame(animate);
    }

    getStatusColor(status) {
        const colors = {
            'На рассмотрении': '#f39c12',
            'В работе': '#3498db',
            'Принято': '#27ae60',
            'Отклонено': '#e74c3c'
        };
        return colors[status] || '#95a5a6';
    }

    setupEventListeners() {
        // Обновление данных
        document.getElementById('refresh-data')?.addEventListener('click', () => {
            this.loadDashboardData();
            this.loadSectionData(this.currentSection);
        });

        // Экспорт данных
        document.querySelectorAll('[data-export]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const format = e.target.dataset.export;
                this.exportData(format);
            });
        });
    }

    setupRefreshInterval() {
        // Автообновление каждые 5 минут
        setInterval(() => {
            this.loadDashboardData();
        }, 5 * 60 * 1000);
    }

    async exportData(format) {
        try {
            const response = await fetch('admin_reports.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_report',
                    report_type: 'summary',
                    format: format,
                    date_from: new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0],
                    date_to: new Date().toISOString().split('T')[0]
                })
            });

            const data = await response.json();
            if (data.success) {
                // Создаем скрытую ссылку для скачивания
                const link = document.createElement('a');
                link.href = data.data.file_url;
                link.download = data.data.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                this.showNotification('Отчет успешно экспортирован', 'success');
            } else {
                this.showNotification('Ошибка экспорта: ' + data.error, 'error');
            }
        } catch (error) {
            this.showNotification('Ошибка: ' + error.message, 'error');
        }
    }

    showNotification(message, type = 'info') {
        // Создаем уведомление
        const notification = document.createElement('div');
        notification.className = `notification notification-${type}`;
        notification.textContent = message;

        notification.style.cssText = `
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 12px 20px;
            background: ${type === 'success' ? '#27ae60' : type === 'error' ? '#e74c3c' : '#3498db'};
            color: white;
            border-radius: 4px;
            z-index: 10000;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.3s ease;
        `;

        document.body.appendChild(notification);

        // Показываем уведомление
        setTimeout(() => {
            notification.style.opacity = '1';
            notification.style.transform = 'translateX(0)';
        }, 100);

        // Скрываем через 3 секунды
        setTimeout(() => {
            notification.style.opacity = '0';
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => {
                document.body.removeChild(notification);
            }, 300);
        }, 3000);
    }
}

// Менеджер пользователей
class UsersManager {
    constructor() {
        this.currentPage = 1;
        this.pageSize = 20;
        this.searchTerm = '';
        this.sortBy = 'created_at';
        this.sortOrder = 'DESC';

        this.setupEventListeners();
    }

    setupEventListeners() {
        // Поиск пользователей
        const searchInput = document.getElementById('users-search');
        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.searchTerm = e.target.value;
                    this.currentPage = 1;
                    this.loadUsers();
                }, 500);
            });
        }

        // Сортировка
        const sortSelect = document.getElementById('users-sort');
        if (sortSelect) {
            sortSelect.addEventListener('change', (e) => {
                this.sortBy = e.target.value;
                this.loadUsers();
            });
        }

        // Экспорт пользователей
        document.getElementById('export-users')?.addEventListener('click', () => {
            this.exportUsers();
        });
    }

    async loadUsers() {
        try {
            const response = await fetch('admin_users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_users',
                    page: this.currentPage,
                    limit: this.pageSize,
                    search: this.searchTerm,
                    sort: this.sortBy,
                    order: this.sortOrder
                })
            });

            const data = await response.json();
            if (data.success) {
                this.renderUsersTable(data.data.users);
                this.renderPagination(data.data.pagination);
                this.updateUsersStats(data.data.users);
            }
        } catch (error) {
            console.error('Ошибка загрузки пользователей:', error);
        }
    }

    renderUsersTable(users) {
        const tbody = document.getElementById('users-tbody');
        if (!tbody) return;

        tbody.innerHTML = users.map(user => `
            <tr>
                <td>
                    <div class="user-info">
                        <div class="user-avatar">${user.fullname.charAt(0).toUpperCase()}</div>
                        <div>
                            <div class="user-name">${user.fullname}</div>
                            <div class="user-position">${user.position || 'Не указана'}</div>
                        </div>
                    </div>
                </td>
                <td>${user.email}</td>
                <td>${user.department || 'Не указан'}</td>
                <td>
                    <span class="ideas-count">${user.ideas_count}</span>
                    ${user.approved_ideas > 0 ? `<span class="approved-badge">${user.approved_ideas} одобрено</span>` : ''}
                </td>
                <td>
                    <div class="rating-info">
                        <span class="total-score">${user.total_score}</span>
                        <span class="total-likes">❤️ ${user.total_likes}</span>
                    </div>
                </td>
                <td>
                    <span class="status-badge ${user.is_active ? 'active' : 'inactive'}">
                        ${user.is_active ? 'Активен' : 'Неактивен'}
                    </span>
                </td>
                <td>
                    <div class="action-buttons">
                        <button class="btn-sm btn-outline" onclick="window.dashboard.users.viewUser(${user.id})" title="Просмотр">
                            👁️
                        </button>
                        <button class="btn-sm btn-primary" onclick="window.dashboard.users.toggleUserStatus(${user.id}, ${user.is_active})" title="${user.is_active ? 'Деактивировать' : 'Активировать'}">
                            ${user.is_active ? '🔒' : '🔓'}
                        </button>
                        <button class="btn-sm btn-danger" onclick="window.dashboard.users.deleteUser(${user.id})" title="Удалить">
                            🗑️
                        </button>
                    </div>
                </td>
            </tr>
        `).join('');
    }

    async viewUser(userId) {
        try {
            const response = await fetch('admin_users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_user_details',
                    user_id: userId
                })
            });

            const data = await response.json();
            if (data.success) {
                this.showUserDetailsModal(data.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки данных пользователя:', error);
        }
    }

    showUserDetailsModal(user) {
        const modal = document.getElementById('user-details-modal');
        const content = document.getElementById('user-details-content');

        content.innerHTML = `
            <div class="user-details">
                <div class="user-header">
                    <div class="user-avatar-large">${user.fullname.charAt(0).toUpperCase()}</div>
                    <div class="user-main-info">
                        <h3>${user.fullname}</h3>
                        <p>${user.email}</p>
                        <p>${user.department || 'Отдел не указан'} • ${user.position || 'Должность не указана'}</p>
                    </div>
                </div>
                
                <div class="user-stats-grid">
                    <div class="stat-item">
                        <span class="stat-label">Всего идей</span>
                        <span class="stat-value">${user.ideas_count}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Одобрено</span>
                        <span class="stat-value">${user.approved_ideas}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">В работе</span>
                        <span class="stat-value">${user.in_progress_ideas}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Отклонено</span>
                        <span class="stat-value">${user.rejected_ideas}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Общий рейтинг</span>
                        <span class="stat-value">${user.total_score}</span>
                    </div>
                    <div class="stat-item">
                        <span class="stat-label">Лайки</span>
                        <span class="stat-value">${user.total_likes}</span>
                    </div>
                </div>

                ${user.recent_ideas && user.recent_ideas.length > 0 ? `
                <div class="recent-ideas">
                    <h4>Последние идеи</h4>
                    <div class="ideas-list">
                        ${user.recent_ideas.map(idea => `
                            <div class="idea-item">
                                <div class="idea-title">${idea.title}</div>
                                <div class="idea-meta">
                                    <span class="idea-status status-${idea.status.toLowerCase().replace(' ', '-')}">${idea.status}</span>
                                    <span class="idea-score">⭐ ${idea.total_score}</span>
                                    <span class="idea-date">${new Date(idea.created_at).toLocaleDateString('ru-RU')}</span>
                                </div>
                            </div>
                        `).join('')}
                    </div>
                </div>
                ` : ''}
            </div>
        `;

        modal.style.display = 'block';
    }

    async toggleUserStatus(userId, currentStatus) {
        const newStatus = !currentStatus;
        const action = newStatus ? 'активировать' : 'деактивировать';

        if (confirm(`Вы уверены, что хотите ${action} этого пользователя?`)) {
            try {
                const response = await fetch('admin_users.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'update_user_status',
                        user_id: userId,
                        is_active: newStatus
                    })
                });

                const data = await response.json();
                if (data.success) {
                    window.dashboard.showNotification(data.message, 'success');
                    this.loadUsers();
                } else {
                    window.dashboard.showNotification('Ошибка: ' + data.error, 'error');
                }
            } catch (error) {
                window.dashboard.showNotification('Ошибка: ' + error.message, 'error');
            }
        }
    }

    async deleteUser(userId) {
        if (confirm('Вы уверены, что хотите удалить этого пользователя? Это действие нельзя отменить.')) {
            try {
                const response = await fetch('admin_users.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        action: 'delete_user',
                        user_id: userId
                    })
                });

                const data = await response.json();
                if (data.success) {
                    window.dashboard.showNotification(data.message, 'success');
                    this.loadUsers();
                } else {
                    window.dashboard.showNotification('Ошибка: ' + data.error, 'error');
                }
            } catch (error) {
                window.dashboard.showNotification('Ошибка: ' + error.message, 'error');
            }
        }
    }

    async exportUsers() {
        try {
            const response = await fetch('admin_users.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'export_users',
                    format: 'csv'
                })
            });

            const data = await response.json();
            if (data.success) {
                // Создаем скрытую ссылку для скачивания
                const link = document.createElement('a');
                link.href = data.file_url;
                link.download = `users_export_${new Date().toISOString().split('T')[0]}.csv`;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                window.dashboard.showNotification('Список пользователей успешно экспортирован', 'success');
            } else {
                window.dashboard.showNotification('Ошибка экспорта: ' + data.error, 'error');
            }
        } catch (error) {
            window.dashboard.showNotification('Ошибка: ' + error.message, 'error');
        }
    }

    renderPagination(pagination) {
        const container = document.getElementById('users-pagination');
        if (!container) return;

        let paginationHTML = '';

        // Предыдущая страница
        if (pagination.current_page > 1) {
            paginationHTML += `<button class="page-btn" onclick="window.dashboard.users.goToPage(${pagination.current_page - 1})">‹</button>`;
        }

        // Номера страниц
        const startPage = Math.max(1, pagination.current_page - 2);
        const endPage = Math.min(pagination.total_pages, pagination.current_page + 2);

        for (let i = startPage; i <= endPage; i++) {
            const isActive = i === pagination.current_page ? 'active' : '';
            paginationHTML += `<button class="page-btn ${isActive}" onclick="window.dashboard.users.goToPage(${i})">${i}</button>`;
        }

        // Следующая страница
        if (pagination.current_page < pagination.total_pages) {
            paginationHTML += `<button class="page-btn" onclick="window.dashboard.users.goToPage(${pagination.current_page + 1})">›</button>`;
        }

        container.innerHTML = paginationHTML;
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadUsers();
    }

    updateUsersStats(users) {
        const totalUsersEl = document.getElementById('total-users');
        const activeUsersEl = document.getElementById('active-users');
        const newUsersEl = document.getElementById('new-users');

        if (totalUsersEl) totalUsersEl.textContent = users.length;
        if (activeUsersEl) activeUsersEl.textContent = users.filter(u => u.is_active).length;
        if (newUsersEl) newUsersEl.textContent = users.filter(u => {
            const createdDate = new Date(u.created_at);
            const weekAgo = new Date(Date.now() - 7 * 24 * 60 * 60 * 1000);
            return createdDate >= weekAgo;
        }).length;
    }
}

// Менеджер аналитики
class AnalyticsManager {
    constructor() {
        this.period = 30;
        this.charts = {};
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Изменение периода
        const periodSelect = document.getElementById('analytics-period');
        if (periodSelect) {
            periodSelect.addEventListener('change', (e) => {
                this.period = parseInt(e.target.value);
                this.loadAnalytics();
            });
        }

        // Обновление аналитики
        document.getElementById('refresh-analytics')?.addEventListener('click', () => {
            this.loadAnalytics();
        });
    }

    async loadAnalytics() {
        try {
            await Promise.all([
                this.loadDepartmentsStats(),
                this.loadEngagementMetrics(),
                this.loadTopContributors()
            ]);
        } catch (error) {
            console.error('Ошибка загрузки аналитики:', error);
        }
    }

    async loadDepartmentsStats() {
        try {
            const response = await fetch('admin_analytics.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_departments_stats' })
            });

            const data = await response.json();
            if (data.success) {
                this.createDepartmentsChart(data.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки статистики отделов:', error);
        }
    }

    createDepartmentsChart(departments) {
        const ctx = document.getElementById('departments-chart');
        if (!ctx) return;

        if (this.charts.departments) {
            this.charts.departments.destroy();
        }

        this.charts.departments = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: departments.map(d => d.department),
                datasets: [{
                    label: 'Количество идей',
                    data: departments.map(d => d.ideas_count),
                    backgroundColor: '#3498db',
                    borderColor: '#2980b9',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    title: {
                        display: true,
                        text: 'Активность отделов'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    async loadEngagementMetrics() {
        try {
            const response = await fetch('admin_analytics.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_engagement_metrics' })
            });

            const data = await response.json();
            if (data.success) {
                this.renderEngagementMetrics(data.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки метрик вовлеченности:', error);
        }
    }

    renderEngagementMetrics(metrics) {
        const container = document.getElementById('engagement-metrics');
        if (!container) return;

        container.innerHTML = `
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-value">${Math.round(metrics.ideas_with_likes_percent)}%</div>
                    <div class="metric-label">Идей с лайками</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">${Math.round(metrics.avg_likes_per_idea * 10) / 10}</div>
                    <div class="metric-label">Ср. лайков на идею</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">${metrics.unique_contributors}</div>
                    <div class="metric-label">Уникальных авторов</div>
                </div>
                <div class="metric-card">
                    <div class="metric-value">${Math.round(metrics.avg_response_days || 0)}</div>
                    <div class="metric-label">Дней до ответа</div>
                </div>
            </div>
        `;
    }

    async loadTopContributors() {
        try {
            const response = await fetch('admin_analytics.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_top_contributors',
                    limit: 10
                })
            });

            const data = await response.json();
            if (data.success) {
                this.renderTopContributors(data.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки топ участников:', error);
        }
    }

    renderTopContributors(contributors) {
        const container = document.getElementById('top-contributors');
        if (!container) return;

        container.innerHTML = contributors.map((contributor, index) => `
            <div class="contributor-item">
                <div class="contributor-rank">#${index + 1}</div>
                <div class="contributor-info">
                    <div class="contributor-name">${contributor.fullname}</div>
                    <div class="contributor-department">${contributor.department || 'Отдел не указан'}</div>
                </div>
                <div class="contributor-stats">
                    <span class="stat-badge">💡 ${contributor.ideas_count}</span>
                    <span class="stat-badge">⭐ ${contributor.total_score}</span>
                    <span class="stat-badge">✅ ${contributor.approved_ideas}</span>
                </div>
            </div>
        `).join('');
    }
}

// Менеджер отчетов
class ReportsManager {
    constructor() {
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Форма создания отчета
        const reportForm = document.getElementById('report-form');
        if (reportForm) {
            reportForm.addEventListener('submit', (e) => {
                e.preventDefault();
                this.generateReport();
            });
        }

        // Настройка автоматических отчетов
        document.getElementById('schedule-report')?.addEventListener('click', () => {
            this.showScheduleModal();
        });
    }

    async loadReports() {
        try {
            const response = await fetch('admin_reports.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'get_report_templates' })
            });

            const data = await response.json();
            if (data.success) {
                this.renderReportTemplates(data.data);
            }
        } catch (error) {
            console.error('Ошибка загрузки шаблонов отчетов:', error);
        }
    }

    renderReportTemplates(templates) {
        const container = document.getElementById('report-templates');
        if (!container) return;

        container.innerHTML = Object.entries(templates).map(([key, template]) => `
            <div class="template-card" data-template="${key}">
                <h4>${template.name}</h4>
                <p>${template.description}</p>
                <div class="template-actions">
                    <button class="btn-primary" onclick="window.dashboard.reports.quickGenerate('${key}', 'pdf')">
                        📄 PDF
                    </button>
                    <button class="btn-secondary" onclick="window.dashboard.reports.quickGenerate('${key}', 'excel')">
                        📊 Excel
                    </button>
                </div>
            </div>
        `).join('');
    }

    async generateReport() {
        const formData = new FormData(document.getElementById('report-form'));
        const reportData = Object.fromEntries(formData);

        try {
            const response = await fetch('admin_reports.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_report',
                    ...reportData
                })
            });

            const data = await response.json();
            if (data.success) {
                // Скачиваем файл
                const link = document.createElement('a');
                link.href = data.data.file_url;
                link.download = data.data.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                window.dashboard.showNotification('Отчет успешно сгенерирован', 'success');
            } else {
                window.dashboard.showNotification('Ошибка генерации отчета: ' + data.error, 'error');
            }
        } catch (error) {
            window.dashboard.showNotification('Ошибка: ' + error.message, 'error');
        }
    }

    async quickGenerate(template, format) {
        const dateFrom = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];
        const dateTo = new Date().toISOString().split('T')[0];

        try {
            const response = await fetch('admin_reports.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'generate_report',
                    report_type: template,
                    format: format,
                    date_from: dateFrom,
                    date_to: dateTo
                })
            });

            const data = await response.json();
            if (data.success) {
                const link = document.createElement('a');
                link.href = data.data.file_url;
                link.download = data.data.filename;
                document.body.appendChild(link);
                link.click();
                document.body.removeChild(link);

                window.dashboard.showNotification('Отчет успешно сгенерирован', 'success');
            } else {
                window.dashboard.showNotification('Ошибка: ' + data.error, 'error');
            }
        } catch (error) {
            window.dashboard.showNotification('Ошибка: ' + error.message, 'error');
        }
    }
}

// Менеджер фильтров (для секции идей)
class FilterManager {
    constructor() {
        this.filters = {};
        this.setupEventListeners();
    }

    setupEventListeners() {
        // Применение фильтров
        document.getElementById('apply-filters')?.addEventListener('click', () => {
            this.applyFilters();
        });

        // Сброс фильтров
        document.getElementById('reset-filters')?.addEventListener('click', () => {
            this.resetFilters();
        });
    }

    applyFilters() {
        const filterInputs = document.querySelectorAll('.filter-select, .filter-input');
        this.filters = {};

        filterInputs.forEach(input => {
            if (input.value) {
                this.filters[input.name] = input.value;
            }
        });

        // Обновляем таблицу идей с фильтрами
        this.loadFilteredIdeas();
    }

    resetFilters() {
        const filterInputs = document.querySelectorAll('.filter-select, .filter-input');
        filterInputs.forEach(input => {
            input.value = '';
        });
        this.filters = {};
        this.loadFilteredIdeas();
    }

    getFilters() {
        return this.filters;
    }

    async loadFilteredIdeas() {
        // Здесь должна быть логика загрузки идей с фильтрами
        // Пока оставляем заглушку
        console.log('Загрузка идей с фильтрами:', this.filters);
    }
}

// Утилиты для модальных окон
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

// Инициализация при загрузке страницы
document.addEventListener('DOMContentLoaded', function() {
    window.dashboard = new AdminDashboard();

    // Устанавливаем текущие даты по умолчанию
    const today = new Date().toISOString().split('T')[0];
    const monthAgo = new Date(Date.now() - 30 * 24 * 60 * 60 * 1000).toISOString().split('T')[0];

    const dateFromInput = document.getElementById('date-from');
    const dateToInput = document.getElementById('date-to');

    if (dateFromInput) dateFromInput.value = monthAgo;
    if (dateToInput) dateToInput.value = today;
});
