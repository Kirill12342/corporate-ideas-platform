/**
 * Система Real-time уведомлений и утилит для API
 */

// Утилиты для работы с API
class APIClient {
  constructor(baseURL = '/praktica_popov/api/v1') {
    this.baseURL = baseURL;
    this.token = localStorage.getItem('api_token');
  }

  // Устанавливаем токен авторизации
  setToken(token) {
    this.token = token;
    localStorage.setItem('api_token', token);
  }

  // Очищаем токен
  clearToken() {
    this.token = null;
    localStorage.removeItem('api_token');
  }

  // Получаем заголовки для запроса
  getHeaders() {
    const headers = {
      'Content-Type': 'application/json'
    };
    
    if (this.token) {
      headers['Authorization'] = `Bearer ${this.token}`;
    }
    
    return headers;
  }

  // Базовый метод для HTTP запросов
  async request(method, endpoint, data = null) {
    const url = `${this.baseURL}${endpoint}`;
    const options = {
      method,
      headers: this.getHeaders()
    };

    if (data && (method === 'POST' || method === 'PUT')) {
      options.body = JSON.stringify(data);
    }

    try {
      const response = await fetch(url, options);
      const result = await response.json();

      if (!response.ok) {
        throw new Error(result.error?.message || 'Ошибка API');
      }

      return result;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  // HTTP методы
  async get(endpoint) { return this.request('GET', endpoint); }
  async post(endpoint, data) { return this.request('POST', endpoint, data); }
  async put(endpoint, data) { return this.request('PUT', endpoint, data); }
  async delete(endpoint) { return this.request('DELETE', endpoint); }
}

// Система Toast уведомлений
class ToastManager {
  constructor() {
    this.container = null;
    this.toasts = new Map();
    this.init();
  }

  init() {
    // Создаем контейнер для toast'ов если его нет
    this.container = document.querySelector('.toast-container');
    if (!this.container) {
      this.container = document.createElement('div');
      this.container.className = 'toast-container';
      document.body.appendChild(this.container);
    }
  }

  show(message, type = 'info', duration = 5000) {
    const toast = this.createToast(message, type);
    const toastId = Date.now();
    
    this.toasts.set(toastId, toast);
    this.container.appendChild(toast);

    // Автоматически удаляем toast через заданное время
    if (duration > 0) {
      setTimeout(() => {
        this.remove(toastId);
      }, duration);
    }

    return toastId;
  }

  createToast(message, type) {
    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    
    const iconMap = {
      success: '✅',
      error: '❌',
      warning: '⚠️',
      info: 'ℹ️'
    };

    toast.innerHTML = `
      <div style="display: flex; align-items: center; gap: 12px;">
        <span style="font-size: 18px;">${iconMap[type] || iconMap.info}</span>
        <span style="flex: 1;">${message}</span>
      </div>
      <button class="toast-close" onclick="toastManager.removeElement(this.parentElement)">×</button>
    `;

    return toast;
  }

  remove(toastId) {
    const toast = this.toasts.get(toastId);
    if (toast && toast.parentElement) {
      toast.style.animation = 'slideInRight 0.3s ease-out reverse';
      setTimeout(() => {
        if (toast.parentElement) {
          toast.parentElement.removeChild(toast);
        }
        this.toasts.delete(toastId);
      }, 300);
    }
  }

  removeElement(element) {
    // Находим ID toast'а по элементу
    for (const [id, toast] of this.toasts) {
      if (toast === element) {
        this.remove(id);
        break;
      }
    }
  }

  // Методы для разных типов уведомлений
  success(message, duration) { return this.show(message, 'success', duration); }
  error(message, duration) { return this.show(message, 'error', duration); }
  warning(message, duration) { return this.show(message, 'warning', duration); }
  info(message, duration) { return this.show(message, 'info', duration); }
}

// Real-time уведомления через Server-Sent Events
class RealtimeNotifications {
  constructor(apiClient, toastManager) {
    this.apiClient = apiClient;
    this.toast = toastManager;
    this.eventSource = null;
    this.reconnectAttempts = 0;
    this.maxReconnectAttempts = 5;
    this.reconnectInterval = 5000;
    this.callbacks = new Map();
  }

  // Подключение к SSE потоку
  connect() {
    if (!this.apiClient.token) {
      console.warn('Нет токена авторизации для real-time уведомлений');
      return;
    }

    const url = `${this.apiClient.baseURL}/realtime/notifications?authorization=${encodeURIComponent(this.apiClient.token)}`;
    
    try {
      this.eventSource = new EventSource(url);
      
      this.eventSource.onopen = () => {
        console.log('Real-time уведомления подключены');
        this.reconnectAttempts = 0;
      };

      this.eventSource.onmessage = (event) => {
        try {
          const data = JSON.parse(event.data);
          this.handleMessage(data);
        } catch (error) {
          console.error('Ошибка парсинга SSE сообщения:', error);
        }
      };

      this.eventSource.onerror = (error) => {
        console.error('Ошибка SSE соединения:', error);
        this.handleConnectionError();
      };

    } catch (error) {
      console.error('Ошибка создания SSE соединения:', error);
      this.handleConnectionError();
    }
  }

  // Обработка входящих сообщений
  handleMessage(data) {
    switch (data.type) {
      case 'notification':
        this.handleNotification(data.data);
        break;
      case 'unread_count':
        this.updateUnreadCount(data.data.count);
        break;
      case 'heartbeat':
        // Пинг для поддержания соединения
        break;
      case 'error':
        this.toast.error(data.data.message);
        break;
    }

    // Вызываем пользовательские колбэки
    const callback = this.callbacks.get(data.type);
    if (callback) {
      callback(data.data);
    }
  }

  // Обработка нового уведомления
  handleNotification(notification) {
    // Показываем toast
    const message = `${notification.title}: ${notification.message}`;
    this.toast.show(message, notification.type, 7000);

    // Обновляем счетчик в UI (если элемент существует)
    this.updateUnreadCount();
    
    // Воспроизводим звук уведомления (опционально)
    this.playNotificationSound();
  }

  // Обновление счетчика непрочитанных уведомлений
  updateUnreadCount(count = null) {
    const countElement = document.querySelector('.notification-count, .unread-count, [data-notification-count]');
    
    if (countElement) {
      if (count !== null) {
        countElement.textContent = count;
        countElement.style.display = count > 0 ? 'inline' : 'none';
      } else {
        // Запрашиваем актуальный счетчик
        this.apiClient.get('/notifications/unread-count')
          .then(response => {
            const actualCount = response.data.unread_count;
            countElement.textContent = actualCount;
            countElement.style.display = actualCount > 0 ? 'inline' : 'none';
          })
          .catch(error => console.error('Ошибка получения счетчика уведомлений:', error));
      }
    }
  }

  // Воспроизведение звука уведомления
  playNotificationSound() {
    try {
      const audio = new Audio('data:audio/wav;base64,UklGRnoGAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YQoGAACBhYqFbF1fdJivrJBhNjVgodDbq2EcBj+a2/LDciUFLIHO8tiJNwgZaLvt559NEAxQp+PwtmMcBjiR1/LMeSwFJHfH8N2QQAoUXrTp66hVFApGn+DyvmAaAA==');
      audio.volume = 0.3;
      audio.play().catch(() => {}); // Игнорируем ошибки автозапуска
    } catch (error) {
      // Звук не критичен
    }
  }

  // Обработка ошибок соединения
  handleConnectionError() {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }

    // Попытка переподключения
    if (this.reconnectAttempts < this.maxReconnectAttempts) {
      this.reconnectAttempts++;
      console.log(`Попытка переподключения ${this.reconnectAttempts}/${this.maxReconnectAttempts}`);
      
      setTimeout(() => {
        this.connect();
      }, this.reconnectInterval * this.reconnectAttempts);
    } else {
      console.error('Не удалось подключиться к real-time уведомлениям');
      this.toast.error('Отсутствует соединение с сервером для уведомлений');
    }
  }

  // Регистрация колбэка для определенного типа сообщений
  on(eventType, callback) {
    this.callbacks.set(eventType, callback);
  }

  // Отключение от SSE потока
  disconnect() {
    if (this.eventSource) {
      this.eventSource.close();
      this.eventSource = null;
    }
    this.reconnectAttempts = 0;
  }
}

// Система загрузки с индикаторами
class LoadingManager {
  constructor() {
    this.activeLoaders = new Set();
  }

  // Показать загрузку
  show(element, type = 'spinner') {
    if (typeof element === 'string') {
      element = document.querySelector(element);
    }

    if (!element) return;

    element.classList.add('loading');
    this.activeLoaders.add(element);

    if (type === 'skeleton') {
      this.showSkeleton(element);
    } else {
      this.showSpinner(element);
    }
  }

  // Скрыть загрузку
  hide(element) {
    if (typeof element === 'string') {
      element = document.querySelector(element);
    }

    if (!element) return;

    element.classList.remove('loading');
    this.activeLoaders.delete(element);

    // Удаляем индикаторы загрузки
    const loader = element.querySelector('.loading-overlay');
    if (loader) {
      loader.remove();
    }

    // Восстанавливаем содержимое если это был skeleton
    const skeletonContent = element.querySelector('.skeleton-content');
    if (skeletonContent) {
      element.innerHTML = element.dataset.originalContent || '';
      delete element.dataset.originalContent;
    }
  }

  // Показать спиннер
  showSpinner(element) {
    const overlay = document.createElement('div');
    overlay.className = 'loading-overlay';
    overlay.style.cssText = `
      position: absolute;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background: rgba(255, 255, 255, 0.8);
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 10;
    `;

    const spinner = document.createElement('div');
    spinner.className = 'spinner';
    
    overlay.appendChild(spinner);
    
    // Делаем родительский элемент относительно позиционированным
    if (getComputedStyle(element).position === 'static') {
      element.style.position = 'relative';
    }
    
    element.appendChild(overlay);
  }

  // Показать skeleton
  showSkeleton(element) {
    // Сохраняем оригинальное содержимое
    element.dataset.originalContent = element.innerHTML;
    
    const skeletonHTML = `
      <div class="skeleton-content">
        <div class="skeleton skeleton-title"></div>
        <div class="skeleton skeleton-text"></div>
        <div class="skeleton skeleton-text" style="width: 80%;"></div>
        <div class="skeleton skeleton-text" style="width: 60%;"></div>
      </div>
    `;
    
    element.innerHTML = skeletonHTML;
  }

  // Скрыть все активные загрузки
  hideAll() {
    this.activeLoaders.forEach(element => {
      this.hide(element);
    });
  }
}

// Глобальные экземпляры
window.apiClient = new APIClient();
window.toastManager = new ToastManager();
window.loadingManager = new LoadingManager();
window.realtimeNotifications = new RealtimeNotifications(window.apiClient, window.toastManager);

// Автоматическое подключение к real-time уведомлениям при загрузке страницы
document.addEventListener('DOMContentLoaded', () => {
  // Проверяем, есть ли токен в localStorage
  const token = localStorage.getItem('api_token');
  if (token) {
    window.apiClient.setToken(token);
    window.realtimeNotifications.connect();
  }

  // Добавляем анимации к существующим элементам
  const cards = document.querySelectorAll('.card, .idea-card, .idea-item');
  cards.forEach((card, index) => {
    card.classList.add('card-animated');
    card.style.animationDelay = `${index * 0.1}s`;
    card.classList.add('animate-fade-in-up');
  });

  // Добавляем hover эффекты к кнопкам
  const buttons = document.querySelectorAll('button, .btn, input[type="submit"]');
  buttons.forEach(btn => {
    if (!btn.classList.contains('btn-animated')) {
      btn.classList.add('btn-animated', 'hover-lift');
    }
  });
});

// Утилитарные функции
window.UIUtils = {
  // Показать успешное сообщение
  showSuccess: (message) => window.toastManager.success(message),
  
  // Показать ошибку
  showError: (message) => window.toastManager.error(message),
  
  // Показать предупреждение
  showWarning: (message) => window.toastManager.warning(message),
  
  // Показать информацию
  showInfo: (message) => window.toastManager.info(message),
  
  // Показать загрузку
  showLoading: (selector) => window.loadingManager.show(selector),
  
  // Скрыть загрузку
  hideLoading: (selector) => window.loadingManager.hide(selector),
  
  // Анимация элемента
  animate: (element, animation) => {
    if (typeof element === 'string') {
      element = document.querySelector(element);
    }
    if (element) {
      element.classList.add(`animate-${animation}`);
    }
  }
};