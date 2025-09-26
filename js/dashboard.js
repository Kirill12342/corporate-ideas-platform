// Модуль для работы с графиками дашборда
class DashboardCharts {
    constructor() {
        this.charts = new Map();
        this.colors = {
            primary: '#49AD09',
            secondary: '#6c757d',
            success: '#27ae60',
            danger: '#e74c3c',
            warning: '#f39c12',
            info: '#3498db',
            gradient: {
                primary: ['#49AD09', '#6bcf3a'],
                secondary: ['#667eea', '#764ba2'],
                success: ['#27ae60', '#2ecc71'],
                danger: ['#e74c3c', '#c0392b'],
                warning: ['#f39c12', '#e67e22'],
                info: ['#3498db', '#2980b9']
            }
        };
        this.init();
    }

    init() {
        // Настройка Chart.js по умолчанию
        if (typeof Chart !== 'undefined') {
            Chart.defaults.font.family = 'Inter, sans-serif';
            Chart.defaults.color = '#7f8c8d';
            Chart.defaults.plugins.legend.labels.usePointStyle = true;
            Chart.defaults.plugins.legend.labels.padding = 20;
        }
    }

    // Создание градиента
    createGradient(ctx, colors, direction = 'vertical') {
        const gradient = direction === 'vertical' 
            ? ctx.createLinearGradient(0, 0, 0, 400)
            : ctx.createLinearGradient(0, 0, 400, 0);
        
        gradient.addColorStop(0, colors[0]);
        gradient.addColorStop(1, colors[1]);
        return gradient;
    }

    // График статистики по статусам идей
    createIdeasStatusChart(canvasId, data) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        
        const chart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['На рассмотрении', 'Принято', 'В работе', 'Отклонено'],
                datasets: [{
                    data: [
                        data.pending || 0,
                        data.approved || 0,
                        data.inProgress || 0,
                        data.rejected || 0
                    ],
                    backgroundColor: [
                        this.colors.warning,
                        this.colors.success,
                        this.colors.info,
                        this.colors.danger
                    ],
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 15,
                            font: {
                                size: 12
                            }
                        }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                const percentage = ((context.raw / total) * 100).toFixed(1);
                                return `${context.label}: ${context.raw} (${percentage}%)`;
                            }
                        }
                    }
                },
                cutout: '60%'
            }
        });

        this.charts.set(canvasId, chart);
        return chart;
    }

    // График динамики идей по времени
    createIdeasTimelineChart(canvasId, data) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        const gradient = this.createGradient(ctx, this.colors.gradient.primary);

        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Количество идей',
                    data: data.values || [],
                    borderColor: this.colors.primary,
                    backgroundColor: gradient,
                    borderWidth: 3,
                    fill: true,
                    tension: 0.4,
                    pointBackgroundColor: this.colors.primary,
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 6,
                    pointHoverRadius: 8
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0,0,0,0.8)',
                        titleColor: '#ffffff',
                        bodyColor: '#ffffff',
                        borderColor: this.colors.primary,
                        borderWidth: 1
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        },
                        ticks: {
                            stepSize: 1,
                            font: {
                                size: 11
                            }
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        },
                        ticks: {
                            font: {
                                size: 11
                            }
                        }
                    }
                },
                interaction: {
                    intersect: false,
                    mode: 'index'
                }
            }
        });

        this.charts.set(canvasId, chart);
        return chart;
    }

    // График категорий идей
    createCategoriesChart(canvasId, data) {
        const ctx = document.getElementById(canvasId).getContext('2d');

        const chart = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: data.labels || [],
                datasets: [{
                    label: 'Количество идей',
                    data: data.values || [],
                    backgroundColor: [
                        this.colors.primary,
                        this.colors.info,
                        this.colors.success,
                        this.colors.warning,
                        this.colors.danger,
                        this.colors.secondary
                    ],
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        this.charts.set(canvasId, chart);
        return chart;
    }

    // График активности пользователей
    createUserActivityChart(canvasId, data) {
        const ctx = document.getElementById(canvasId).getContext('2d');
        const gradientActive = this.createGradient(ctx, this.colors.gradient.info);
        const gradientRegistered = this.createGradient(ctx, this.colors.gradient.secondary);

        const chart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels || [],
                datasets: [
                    {
                        label: 'Активные пользователи',
                        data: data.activeUsers || [],
                        borderColor: this.colors.info,
                        backgroundColor: gradientActive,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    },
                    {
                        label: 'Новые регистрации',
                        data: data.newRegistrations || [],
                        borderColor: this.colors.secondary,
                        backgroundColor: gradientRegistered,
                        borderWidth: 2,
                        fill: true,
                        tension: 0.4
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'top'
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0,0,0,0.1)'
                        }
                    },
                    x: {
                        grid: {
                            display: false
                        }
                    }
                }
            }
        });

        this.charts.set(canvasId, chart);
        return chart;
    }

    // Обновление данных графика
    updateChart(canvasId, newData) {
        const chart = this.charts.get(canvasId);
        if (!chart) return;

        if (newData.labels) {
            chart.data.labels = newData.labels;
        }

        if (newData.datasets) {
            newData.datasets.forEach((dataset, index) => {
                if (chart.data.datasets[index]) {
                    chart.data.datasets[index].data = dataset.data;
                }
            });
        } else if (newData.data) {
            chart.data.datasets[0].data = newData.data;
        }

        chart.update('smooth');
    }

    // Уничтожение графика
    destroyChart(canvasId) {
        const chart = this.charts.get(canvasId);
        if (chart) {
            chart.destroy();
            this.charts.delete(canvasId);
        }
    }

    // Изменение размера всех графиков
    resizeCharts() {
        this.charts.forEach(chart => {
            chart.resize();
        });
    }

    // Получение данных для графика с сервера
    async fetchChartData(endpoint, params = {}) {
        try {
            // Строим URL с параметрами
            const url = new URL(endpoint, window.location.href);
            Object.keys(params).forEach(key => 
                url.searchParams.append(key, params[key])
            );

            const response = await fetch(url);
            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            return data.success !== false ? data : null;
        } catch (error) {
            console.error('Ошибка получения данных для графика:', error);
            return null;
        }
    }

    // Экспорт графика как изображение
    exportChart(canvasId, filename = 'chart.png') {
        const chart = this.charts.get(canvasId);
        if (!chart) return;

        const url = chart.toBase64Image();
        const link = document.createElement('a');
        link.download = filename;
        link.href = url;
        link.click();
    }

    // Показать загрузку на графике
    showChartLoading(canvasId) {
        const canvas = document.getElementById(canvasId);
        const container = canvas.closest('.chart-container');
        
        // Добавляем индикатор загрузки
        const loadingDiv = document.createElement('div');
        loadingDiv.className = 'chart-loading';
        loadingDiv.innerHTML = `
            <div class="loading-spinner"></div>
            <span>Загрузка данных...</span>
        `;
        
        canvas.style.display = 'none';
        container.appendChild(loadingDiv);
    }

    // Скрыть загрузку
    hideChartLoading(canvasId) {
        const canvas = document.getElementById(canvasId);
        const container = canvas.closest('.chart-container');
        const loading = container.querySelector('.chart-loading');
        
        if (loading) {
            loading.remove();
        }
        canvas.style.display = 'block';
    }
}

// Модуль для работы с фильтрами
class DashboardFilters {
    constructor() {
        this.filters = {};
        this.callbacks = [];
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadSavedFilters();
    }

    bindEvents() {
        // Обработка изменений фильтров
        document.addEventListener('change', (e) => {
            if (e.target.matches('.filter-input, .filter-select')) {
                this.updateFilter(e.target.name, e.target.value);
            }
        });

        // Обработка чекбоксов
        document.addEventListener('change', (e) => {
            if (e.target.matches('.filter-checkbox input')) {
                this.updateCheckboxFilter(e.target.name, e.target.value, e.target.checked);
            }
        });

        // Кнопка применить фильтры
        document.addEventListener('click', (e) => {
            if (e.target.matches('#apply-filters')) {
                this.applyFilters();
            }
        });

        // Кнопка сбросить фильтры
        document.addEventListener('click', (e) => {
            if (e.target.matches('#reset-filters')) {
                this.resetFilters();
            }
        });
    }

    updateFilter(name, value) {
        // Если выбрано "Все", убираем фильтр
        if (value === '' || value === 'all' || value.startsWith('Все')) {
            delete this.filters[name];
        } else {
            this.filters[name] = value;
        }
        this.saveFilters();
    }

    updateCheckboxFilter(name, value, checked) {
        if (!this.filters[name]) {
            this.filters[name] = [];
        }

        if (checked) {
            if (!this.filters[name].includes(value)) {
                this.filters[name].push(value);
            }
        } else {
            this.filters[name] = this.filters[name].filter(v => v !== value);
        }

        this.saveFilters();
    }

    applyFilters() {
        this.callbacks.forEach(callback => callback(this.filters));
    }

    resetFilters() {
        this.filters = {};
        
        // Очистка всех полей фильтров
        document.querySelectorAll('.filter-input').forEach(input => {
            input.value = '';
        });

        // Сброс select'ов к первому значению (Все)
        document.querySelectorAll('.filter-select').forEach(select => {
            select.selectedIndex = 0;
        });

        document.querySelectorAll('.filter-checkbox input').forEach(checkbox => {
            checkbox.checked = false;
        });

        this.saveFilters();
        this.applyFilters();
    }

    // Подписка на изменения фильтров
    onFiltersChange(callback) {
        this.callbacks.push(callback);
    }

    // Сохранение фильтров в localStorage
    saveFilters() {
        localStorage.setItem('dashboardFilters', JSON.stringify(this.filters));
    }

    // Загрузка сохраненных фильтров
    loadSavedFilters() {
        const saved = localStorage.getItem('dashboardFilters');
        if (saved) {
            this.filters = JSON.parse(saved);
            this.restoreFilterValues();
        }
    }

    // Восстановление значений в форме
    restoreFilterValues() {
        Object.keys(this.filters).forEach(name => {
            const value = this.filters[name];
            const input = document.querySelector(`[name="${name}"]`);
            
            if (input) {
                if (Array.isArray(value)) {
                    // Чекбоксы
                    value.forEach(v => {
                        const checkbox = document.querySelector(`[name="${name}"][value="${v}"]`);
                        if (checkbox) checkbox.checked = true;
                    });
                } else {
                    input.value = value;
                }
            }
        });
    }

    // Получение текущих фильтров
    getFilters() {
        return { ...this.filters };
    }
}

// Модуль экспорта отчетов
class DashboardExport {
    constructor() {
        this.init();
    }

    init() {
        document.addEventListener('click', (e) => {
            if (e.target.matches('[data-export]')) {
                const type = e.target.dataset.export;
                this.exportData(type);
            }
        });
    }

    async exportData(type) {
        const button = document.querySelector(`[data-export="${type}"]`);
        const originalText = button.textContent;
        
        button.textContent = 'Экспорт...';
        button.disabled = true;

        try {
            const response = await fetch(`export.php?type=${type}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(dashboard.filters.getFilters())
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            // Получение файла
            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `report_${type}_${new Date().toISOString().split('T')[0]}.${type}`;
            a.click();
            
            window.URL.revokeObjectURL(url);

        } catch (error) {
            console.error('Ошибка экспорта:', error);
            alert('Ошибка при экспорте данных');
        } finally {
            button.textContent = originalText;
            button.disabled = false;
        }
    }
}

// Главный класс дашборда
class Dashboard {
    constructor() {
        this.charts = new DashboardCharts();
        this.filters = new DashboardFilters();
        this.export = new DashboardExport();
        this.refreshInterval = null;
        this.init();
    }

    init() {
        this.loadInitialData();
        this.bindEvents();
        this.setupAutoRefresh();
        
        // Подписка на изменения фильтров
        this.filters.onFiltersChange((filters) => {
            this.updateDashboard(filters);
        });
    }

    bindEvents() {
        // Переключение разделов дашборда
        document.addEventListener('click', (e) => {
            if (e.target.matches('.nav-item')) {
                this.switchSection(e.target.dataset.section);
            }
        });

        // Мобильное меню
        document.addEventListener('click', (e) => {
            if (e.target.matches('.mobile-menu-toggle')) {
                this.toggleMobileMenu();
            }
        });

        // Обновление данных
        document.addEventListener('click', (e) => {
            if (e.target.matches('#refresh-data')) {
                this.refreshData();
            }
        });

        // Адаптивность графиков при изменении размера окна
        window.addEventListener('resize', () => {
            this.charts.resizeCharts();
        });
    }

    async loadInitialData() {
        console.log('Dashboard: Загрузка начальных данных...');
        await this.updateDashboard();
        await this.updateIdeasTable();
        console.log('Dashboard: Начальная загрузка завершена');
    }

    async updateDashboard(filters = {}) {
        try {
            // Загрузка статистики
            const statsData = await this.charts.fetchChartData('./analytics.php', {
                action: 'stats',
                ...filters
            });

            if (statsData && statsData.data) {
                this.updateStatsCards(statsData.data);
            }

            // Загрузка данных для графиков
            const chartsData = await this.charts.fetchChartData('./analytics.php', {
                action: 'charts',
                ...filters
            });

            if (chartsData && chartsData.data) {
                this.updateCharts(chartsData.data);
            }

            // Загрузка таблицы идей
            await this.updateIdeasTable(filters);

        } catch (error) {
            console.error('Ошибка обновления дашборда:', error);
        }
    }

    updateStatsCards(data) {
        // Обновление карточек статистики
        Object.keys(data).forEach(key => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                const valueEl = element.querySelector('.stat-value');
                const changeEl = element.querySelector('.stat-change');
                
                if (valueEl) valueEl.textContent = data[key].value || 0;
                if (changeEl && data[key].change !== undefined) {
                    changeEl.textContent = `${data[key].change > 0 ? '+' : ''}${data[key].change}%`;
                    changeEl.className = `stat-change ${data[key].change > 0 ? 'change-positive' : 
                                                      data[key].change < 0 ? 'change-negative' : 'change-neutral'}`;
                }
            }
        });
    }

    updateCharts(data) {
        // Обновление графиков
        if (data.ideasByStatus) {
            this.charts.updateChart('ideas-status-chart', data.ideasByStatus);
        }

        if (data.ideasTimeline) {
            this.charts.updateChart('ideas-timeline-chart', data.ideasTimeline);
        }

        if (data.categoriesChart) {
            this.charts.updateChart('categories-chart', data.categoriesChart);
        }

        if (data.userActivity) {
            this.charts.updateChart('user-activity-chart', data.userActivity);
        }
    }

    async updateIdeasTable(filters = {}) {
        console.log('Dashboard: Обновление таблицы идей с фильтрами:', filters);
        try {
            const response = await fetch('./analytics.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    action: 'ideas_table',
                    ...filters
                })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('Dashboard: Ответ API для таблицы идей:', data);
            
            if (data.success) {
                const tbody = document.getElementById('ideas-table-body');
                if (tbody) {
                    console.log('Dashboard: Обновление HTML таблицы, длина:', data.html?.length || 0);
                    tbody.innerHTML = data.html || '<tr><td colspan="7" style="text-align: center; color: #6c757d;">Нет данных для отображения</td></tr>';
                }
                this.updatePagination(data.pagination);
                console.log('Dashboard: Таблица идей обновлена успешно');
            } else {
                console.error('Ошибка API:', data.error);
                const tbody = document.getElementById('ideas-table-body');
                if (tbody) {
                    tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #dc3545;">Ошибка API: ' + (data.error || 'Неизвестная ошибка') + '</td></tr>';
                }
            }

        } catch (error) {
            console.error('Ошибка обновления таблицы:', error);
            
            // Показать сообщение об ошибке пользователю
            const tbody = document.getElementById('ideas-table-body');
            if (tbody) {
                tbody.innerHTML = '<tr><td colspan="7" style="text-align: center; color: #dc3545;">Ошибка загрузки данных: ' + error.message + '</td></tr>';
            }
        }
    }

    updatePagination(pagination) {
        const container = document.querySelector('.pagination-controls');
        if (!container || !pagination) return;

        let html = '';
        
        // Предыдущая страница
        if (pagination.currentPage > 1) {
            html += `<button class="page-btn" data-page="${pagination.currentPage - 1}">‹</button>`;
        }

        // Номера страниц
        for (let i = Math.max(1, pagination.currentPage - 2); 
             i <= Math.min(pagination.totalPages, pagination.currentPage + 2); 
             i++) {
            html += `<button class="page-btn ${i === pagination.currentPage ? 'active' : ''}" data-page="${i}">${i}</button>`;
        }

        // Следующая страница
        if (pagination.currentPage < pagination.totalPages) {
            html += `<button class="page-btn" data-page="${pagination.currentPage + 1}">›</button>`;
        }

        container.innerHTML = html;

        // Информация о пагинации
        const info = document.querySelector('.pagination-info');
        if (info) {
            info.textContent = `Показано ${pagination.start}-${pagination.end} из ${pagination.total}`;
        }
    }

    setupAutoRefresh() {
        // Автообновление каждые 30 секунд
        this.refreshInterval = setInterval(() => {
            this.refreshData();
        }, 30000);
    }

    async refreshData() {
        const refreshBtn = document.getElementById('refresh-data');
        if (refreshBtn) {
            refreshBtn.disabled = true;
            refreshBtn.innerHTML = '<span class="loading-spinner"></span>';
        }

        await this.updateDashboard(this.filters.getFilters());

        if (refreshBtn) {
            refreshBtn.disabled = false;
            refreshBtn.innerHTML = '🔄';
        }
    }

    switchSection(section) {
        // Переключение активного пункта меню
        document.querySelectorAll('.nav-item').forEach(item => {
            item.classList.toggle('active', item.dataset.section === section);
        });

        // Показ соответствующего раздела
        document.querySelectorAll('.dashboard-section').forEach(section => {
            section.style.display = 'none';
        });

        const targetSection = document.getElementById(`section-${section}`);
        if (targetSection) {
            targetSection.style.display = 'block';
        }
    }

    toggleMobileMenu() {
        const sidebar = document.querySelector('.dashboard-sidebar');
        sidebar.classList.toggle('mobile-open');
    }

    destroy() {
        if (this.refreshInterval) {
            clearInterval(this.refreshInterval);
        }
        
        this.charts.charts.forEach((chart, id) => {
            this.charts.destroyChart(id);
        });
    }
}

// Инициализация дашборда при загрузке страницы
let dashboard;

document.addEventListener('DOMContentLoaded', function() {
    dashboard = new Dashboard();
    
    // Создание графиков после загрузки Chart.js
    if (typeof Chart !== 'undefined') {
        initializeCharts();
    } else {
        // Ждем загрузки Chart.js
        window.addEventListener('chartjs-loaded', initializeCharts);
    }
});

function initializeCharts() {
    // Инициализация графиков с начальными данными
    dashboard.charts.createIdeasStatusChart('ideas-status-chart', {
        pending: 15,
        approved: 25,
        inProgress: 10,
        rejected: 5
    });

    dashboard.charts.createIdeasTimelineChart('ideas-timeline-chart', {
        labels: ['Янв', 'Фев', 'Мар', 'Апр', 'Май', 'Июн'],
        values: [12, 19, 25, 18, 22, 28]
    });

    dashboard.charts.createCategoriesChart('categories-chart', {
        labels: ['IT-сервисы', 'HR', 'Офис', 'Финансы', 'Другое'],
        values: [15, 12, 8, 5, 3]
    });

    dashboard.charts.createUserActivityChart('user-activity-chart', {
        labels: ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс'],
        activeUsers: [45, 52, 48, 61, 58, 35, 28],
        newRegistrations: [2, 5, 3, 8, 6, 1, 1]
    });
}