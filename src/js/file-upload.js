/**
 * Современный Drag & Drop файл-менеджер с прогресс баром
 */

class DragDropFileUpload {
  constructor(options = {}) {
    this.options = {
      container: options.container || '.file-upload-area',
      acceptedTypes: options.acceptedTypes || ['image/*', 'application/pdf', '.doc', '.docx'],
      maxFileSize: options.maxFileSize || 10 * 1024 * 1024, // 10MB
      maxFiles: options.maxFiles || 5,
      apiEndpoint: options.apiEndpoint || '/praktica_popov/api/v1/attachments',
      onSuccess: options.onSuccess || (() => {}),
      onError: options.onError || ((error) => console.error(error)),
      onProgress: options.onProgress || (() => {}),
      ...options
    };

    this.files = new Map();
    this.uploadQueue = [];
    this.isUploading = false;
    
    this.init();
  }

  init() {
    this.createUploadArea();
    this.bindEvents();
  }

  createUploadArea() {
    // Если container уже DOM элемент, используем его напрямую, иначе ищем по селектору
    let container;
    
    if (this.options.container instanceof HTMLElement) {
      container = this.options.container;
    } else if (typeof this.options.container === 'string') {
      container = document.querySelector(this.options.container);
    } else {
      console.error('Invalid container option:', typeof this.options.container, this.options.container);
      return;
    }
    
    if (!container) {
      console.error('Контейнер для загрузки файлов не найден:', this.options.container);
      return;
    }

    container.innerHTML = `
      <div class="drag-drop-area" id="dragDropArea">
        <div class="drag-drop-content">
          <div class="upload-icon">📁</div>
          <h3>Перетащите файлы сюда</h3>
          <p>или <span class="browse-link">выберите файлы</span></p>
          <div class="file-requirements">
            <small>
              Поддерживаемые форматы: ${this.getAcceptedTypesText()}<br>
              Максимальный размер: ${this.formatFileSize(this.options.maxFileSize)}<br>
              Максимум файлов: ${this.options.maxFiles}
            </small>
          </div>
        </div>
        
        <input type="file" 
               id="fileInput" 
               multiple 
               accept="${this.options.acceptedTypes.join(',')}"
               style="display: none;">
        
        <div class="files-preview" id="filesPreview" style="display: none;">
          <h4>Выбранные файлы:</h4>
          <div class="files-list" id="filesList"></div>
          <div class="upload-controls">
            <button type="button" class="btn btn-primary upload-btn" id="uploadBtn">
              <span class="btn-text">Загрузить файлы</span>
              <div class="spinner" style="display: none;"></div>
            </button>
            <button type="button" class="btn btn-secondary clear-btn" id="clearBtn">Очистить</button>
          </div>
        </div>
      </div>
    `;

    this.dragDropArea = container.querySelector('#dragDropArea');
    this.fileInput = container.querySelector('#fileInput');
    this.filesPreview = container.querySelector('#filesPreview');
    this.filesList = container.querySelector('#filesList');
    this.uploadBtn = container.querySelector('#uploadBtn');
    this.clearBtn = container.querySelector('#clearBtn');
  }

  bindEvents() {
    if (!this.dragDropArea) return;

    // Drag & Drop события
    this.dragDropArea.addEventListener('dragover', this.handleDragOver.bind(this));
    this.dragDropArea.addEventListener('dragenter', this.handleDragEnter.bind(this));
    this.dragDropArea.addEventListener('dragleave', this.handleDragLeave.bind(this));
    this.dragDropArea.addEventListener('drop', this.handleDrop.bind(this));

    // Клик для выбора файлов
    this.dragDropArea.addEventListener('click', () => this.fileInput.click());
    
    // Изменение файлов через input
    this.fileInput.addEventListener('change', this.handleFileSelect.bind(this));

    // Кнопки управления
    this.uploadBtn?.addEventListener('click', this.uploadFiles.bind(this));
    this.clearBtn?.addEventListener('click', this.clearFiles.bind(this));

    // Предотвращаем стандартное поведение браузера
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
      document.addEventListener(eventName, this.preventDefaults, false);
    });
  }

  preventDefaults(e) {
    e.preventDefault();
    e.stopPropagation();
  }

  handleDragOver(e) {
    e.preventDefault();
    this.dragDropArea.classList.add('drag-active');
  }

  handleDragEnter(e) {
    e.preventDefault();
    this.dragDropArea.classList.add('drag-active');
  }

  handleDragLeave(e) {
    e.preventDefault();
    if (!this.dragDropArea.contains(e.relatedTarget)) {
      this.dragDropArea.classList.remove('drag-active');
    }
  }

  handleDrop(e) {
    e.preventDefault();
    this.dragDropArea.classList.remove('drag-active');
    
    const files = Array.from(e.dataTransfer.files);
    this.processFiles(files);
  }

  handleFileSelect(e) {
    const files = Array.from(e.target.files);
    this.processFiles(files);
  }

  processFiles(newFiles) {
    const validFiles = [];
    const errors = [];

    // Проверяем каждый файл
    newFiles.forEach(file => {
      const validation = this.validateFile(file);
      if (validation.valid) {
        validFiles.push(file);
      } else {
        errors.push(`${file.name}: ${validation.error}`);
      }
    });

    // Проверяем общее количество файлов
    const totalFiles = this.files.size + validFiles.length;
    if (totalFiles > this.options.maxFiles) {
      const allowedCount = this.options.maxFiles - this.files.size;
      errors.push(`Можно загрузить максимум ${this.options.maxFiles} файлов. Доступно слотов: ${allowedCount}`);
      validFiles.splice(allowedCount);
    }

    // Показываем ошибки
    if (errors.length > 0) {
      window.toastManager?.error(errors.join('\n'));
    }

    // Добавляем валидные файлы
    validFiles.forEach(file => {
      const fileId = this.generateFileId();
      this.files.set(fileId, {
        file,
        id: fileId,
        status: 'pending',
        progress: 0
      });
    });

    this.renderFiles();
  }

  validateFile(file) {
    // Проверяем размер
    if (file.size > this.options.maxFileSize) {
      return {
        valid: false,
        error: `Размер файла превышает ${this.formatFileSize(this.options.maxFileSize)}`
      };
    }

    // Проверяем тип файла
    const isTypeAllowed = this.options.acceptedTypes.some(type => {
      if (type.includes('*')) {
        return file.type.startsWith(type.replace('*', ''));
      }
      return file.type === type || file.name.toLowerCase().endsWith(type);
    });

    if (!isTypeAllowed) {
      return {
        valid: false,
        error: `Неподдерживаемый тип файла`
      };
    }

    return { valid: true };
  }

  renderFiles() {
    if (this.files.size === 0) {
      this.filesPreview.style.display = 'none';
      return;
    }

    this.filesPreview.style.display = 'block';
    this.filesList.innerHTML = '';

    this.files.forEach((fileData, fileId) => {
      const fileItem = this.createFileItem(fileData);
      this.filesList.appendChild(fileItem);
    });

    // Обновляем состояние кнопок
    this.updateUploadButton();
  }

  createFileItem(fileData) {
    const { file, id, status, progress } = fileData;
    
    const item = document.createElement('div');
    item.className = `file-item ${status}`;
    item.dataset.fileId = id;

    const preview = this.createFilePreview(file);
    const statusIcon = this.getStatusIcon(status);
    
    item.innerHTML = `
      <div class="file-preview">
        ${preview}
      </div>
      <div class="file-info">
        <div class="file-name" title="${file.name}">${file.name}</div>
        <div class="file-size">${this.formatFileSize(file.size)}</div>
        <div class="file-progress" style="display: ${status === 'uploading' ? 'block' : 'none'}">
          <div class="progress-bar">
            <div class="progress-fill" style="width: ${progress}%"></div>
          </div>
          <span class="progress-text">${progress}%</span>
        </div>
      </div>
      <div class="file-actions">
        <div class="file-status">${statusIcon}</div>
        <button type="button" class="btn-remove" onclick="fileUploadManager.removeFile('${id}')" ${status === 'uploading' ? 'disabled' : ''}>
          ✕
        </button>
      </div>
    `;

    return item;
  }

  createFilePreview(file) {
    if (file.type.startsWith('image/')) {
      const url = URL.createObjectURL(file);
      return `<img src="${url}" alt="${file.name}" onload="URL.revokeObjectURL(this.src)">`;
    }

    // Иконки для разных типов файлов
    const iconMap = {
      'application/pdf': '📄',
      'application/msword': '📝',
      'application/vnd.openxmlformats-officedocument.wordprocessingml.document': '📝',
      'application/vnd.ms-excel': '📊',
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet': '📊',
      'text/plain': '📄',
      'application/zip': '🗜️',
      'application/x-rar-compressed': '🗜️'
    };

    const icon = iconMap[file.type] || '📎';
    return `<div class="file-icon">${icon}</div>`;
  }

  getStatusIcon(status) {
    const icons = {
      pending: '⏳',
      uploading: '⬆️',
      success: '✅',
      error: '❌'
    };
    return icons[status] || '';
  }

  updateUploadButton() {
    const hasPendingFiles = Array.from(this.files.values()).some(f => f.status === 'pending');
    const isUploading = this.isUploading;
    
    this.uploadBtn.disabled = !hasPendingFiles || isUploading;
    
    const btnText = this.uploadBtn.querySelector('.btn-text');
    const spinner = this.uploadBtn.querySelector('.spinner');
    
    if (isUploading) {
      btnText.textContent = 'Загружается...';
      spinner.style.display = 'inline-block';
    } else {
      btnText.textContent = 'Загрузить файлы';
      spinner.style.display = 'none';
    }
  }

  async uploadFiles() {
    if (this.isUploading) return;

    const pendingFiles = Array.from(this.files.entries())
      .filter(([_, fileData]) => fileData.status === 'pending');

    if (pendingFiles.length === 0) return;

    this.isUploading = true;
    this.updateUploadButton();

    // Загружаем файлы по очереди для лучшего контроля прогресса
    for (const [fileId, fileData] of pendingFiles) {
      await this.uploadSingleFile(fileId, fileData);
    }

    this.isUploading = false;
    this.updateUploadButton();

    // Показываем результаты
    this.showUploadResults();
  }

  async uploadSingleFile(fileId, fileData) {
    const { file } = fileData;
    
    // Обновляем статус
    fileData.status = 'uploading';
    fileData.progress = 0;
    this.updateFileItem(fileId, fileData);

    try {
      const formData = new FormData();
      formData.append('file', file);
      
      // Добавляем дополнительные параметры если нужно
      if (this.options.entityType) {
        formData.append('entity_type', this.options.entityType);
      }
      if (this.options.entityId) {
        formData.append('entity_id', this.options.entityId);
      }

      const response = await this.uploadWithProgress(formData, fileId);
      
      // Успешная загрузка
      fileData.status = 'success';
      fileData.progress = 100;
      fileData.response = response;
      this.updateFileItem(fileId, fileData);

      // Вызываем колбэк успеха
      this.options.onSuccess(response, file);

    } catch (error) {
      // Ошибка загрузки
      fileData.status = 'error';
      fileData.error = error.message;
      this.updateFileItem(fileId, fileData);

      // Вызываем колбэк ошибки
      this.options.onError(error, file);
    }
  }

  async uploadWithProgress(formData, fileId) {
    return new Promise((resolve, reject) => {
      const xhr = new XMLHttpRequest();

      // Отслеживаем прогресс
      xhr.upload.addEventListener('progress', (e) => {
        if (e.lengthComputable) {
          const progress = Math.round((e.loaded / e.total) * 100);
          const fileData = this.files.get(fileId);
          if (fileData) {
            fileData.progress = progress;
            this.updateFileItem(fileId, fileData);
            this.options.onProgress(progress, fileData.file);
          }
        }
      });

      xhr.addEventListener('load', () => {
        if (xhr.status >= 200 && xhr.status < 300) {
          try {
            const response = JSON.parse(xhr.responseText);
            resolve(response);
          } catch (error) {
            reject(new Error('Ошибка парсинга ответа сервера'));
          }
        } else {
          try {
            const errorResponse = JSON.parse(xhr.responseText);
            reject(new Error(errorResponse.error?.message || `HTTP ${xhr.status}`));
          } catch (error) {
            reject(new Error(`HTTP ${xhr.status}: ${xhr.statusText}`));
          }
        }
      });

      xhr.addEventListener('error', () => {
        reject(new Error('Ошибка сети при загрузке файла'));
      });

      xhr.addEventListener('abort', () => {
        reject(new Error('Загрузка была отменена'));
      });

      // Настраиваем запрос
      xhr.open('POST', this.options.apiEndpoint);
      
      // Добавляем заголовок авторизации если токен есть
      if (window.apiClient?.token) {
        xhr.setRequestHeader('Authorization', `Bearer ${window.apiClient.token}`);
      }

      xhr.send(formData);
    });
  }

  updateFileItem(fileId, fileData) {
    const fileItem = document.querySelector(`[data-file-id="${fileId}"]`);
    if (!fileItem) return;

    // Обновляем класс статуса
    fileItem.className = `file-item ${fileData.status}`;

    // Обновляем иконку статуса
    const statusElement = fileItem.querySelector('.file-status');
    if (statusElement) {
      statusElement.textContent = this.getStatusIcon(fileData.status);
    }

    // Обновляем прогресс бар
    const progressContainer = fileItem.querySelector('.file-progress');
    const progressFill = fileItem.querySelector('.progress-fill');
    const progressText = fileItem.querySelector('.progress-text');
    const removeBtn = fileItem.querySelector('.btn-remove');

    if (fileData.status === 'uploading') {
      progressContainer.style.display = 'block';
      if (progressFill) progressFill.style.width = `${fileData.progress}%`;
      if (progressText) progressText.textContent = `${fileData.progress}%`;
      if (removeBtn) removeBtn.disabled = true;
    } else {
      progressContainer.style.display = 'none';
      if (removeBtn) removeBtn.disabled = false;
    }
  }

  removeFile(fileId) {
    if (this.isUploading) return;

    this.files.delete(fileId);
    this.renderFiles();
    
    window.toastManager?.info('Файл удален');
  }

  clearFiles() {
    if (this.isUploading) return;

    this.files.clear();
    this.renderFiles();
    this.fileInput.value = '';
    
    window.toastManager?.info('Все файлы удалены');
  }

  showUploadResults() {
    const results = Array.from(this.files.values());
    const successful = results.filter(f => f.status === 'success').length;
    const failed = results.filter(f => f.status === 'error').length;

    if (successful > 0) {
      window.toastManager?.success(`Успешно загружено файлов: ${successful}`);
    }
    
    if (failed > 0) {
      window.toastManager?.error(`Ошибок при загрузке: ${failed}`);
    }
  }

  // Утилитарные методы
  generateFileId() {
    return Date.now() + '_' + Math.random().toString(36).substr(2, 9);
  }

  formatFileSize(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
  }

  getAcceptedTypesText() {
    return this.options.acceptedTypes
      .map(type => type.replace('application/', '').replace('*', 'все'))
      .join(', ');
  }

  // Публичные методы для управления
  reset() {
    this.clearFiles();
  }

  getUploadedFiles() {
    return Array.from(this.files.values())
      .filter(f => f.status === 'success')
      .map(f => f.response);
  }

  setOptions(newOptions) {
    this.options = { ...this.options, ...newOptions };
  }
}

// CSS стили для drag & drop (добавляем к animations.css)
const dragDropStyles = `
/* Drag & Drop File Upload Styles */
.drag-drop-area {
  border: 3px dashed var(--border-color);
  border-radius: var(--border-radius-lg);
  padding: 40px 20px;
  text-align: center;
  background: var(--background-light);
  transition: all var(--transition-smooth);
  cursor: pointer;
  position: relative;
  min-height: 200px;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
}

.drag-drop-area:hover {
  border-color: var(--primary-color);
  background: var(--background-hover);
  transform: translateY(-2px);
}

.drag-drop-area.drag-active {
  border-color: var(--success-color);
  background: rgba(var(--success-rgb), 0.1);
  transform: scale(1.02);
}

.drag-drop-content {
  pointer-events: none;
}

.upload-icon {
  font-size: 3rem;
  margin-bottom: 16px;
  opacity: 0.6;
}

.browse-link {
  color: var(--primary-color);
  text-decoration: underline;
  cursor: pointer;
}

.file-requirements {
  margin-top: 16px;
  opacity: 0.7;
  max-width: 400px;
}

.files-preview {
  margin-top: 24px;
  padding: 20px;
  background: var(--background-light);
  border-radius: var(--border-radius);
  border: 1px solid var(--border-color);
}

.files-list {
  max-height: 400px;
  overflow-y: auto;
  margin: 16px 0;
}

.file-item {
  display: flex;
  align-items: center;
  gap: 16px;
  padding: 12px;
  border: 1px solid var(--border-color);
  border-radius: var(--border-radius);
  margin-bottom: 8px;
  background: white;
  transition: all var(--transition-smooth);
}

.file-item:hover {
  border-color: var(--primary-color);
  box-shadow: var(--shadow-sm);
}

.file-item.uploading {
  border-color: var(--warning-color);
  background: rgba(var(--warning-rgb), 0.05);
}

.file-item.success {
  border-color: var(--success-color);
  background: rgba(var(--success-rgb), 0.05);
}

.file-item.error {
  border-color: var(--error-color);
  background: rgba(var(--error-rgb), 0.05);
}

.file-preview {
  width: 48px;
  height: 48px;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: var(--border-radius);
  overflow: hidden;
  background: var(--background-light);
  flex-shrink: 0;
}

.file-preview img {
  width: 100%;
  height: 100%;
  object-fit: cover;
}

.file-icon {
  font-size: 24px;
}

.file-info {
  flex: 1;
  min-width: 0;
}

.file-name {
  font-weight: 500;
  margin-bottom: 4px;
  white-space: nowrap;
  overflow: hidden;
  text-overflow: ellipsis;
}

.file-size {
  font-size: 0.875rem;
  color: var(--text-secondary);
}

.file-progress {
  margin-top: 8px;
  display: flex;
  align-items: center;
  gap: 8px;
}

.progress-bar {
  flex: 1;
  height: 6px;
  background: var(--background-light);
  border-radius: 3px;
  overflow: hidden;
}

.progress-fill {
  height: 100%;
  background: var(--primary-color);
  transition: width var(--transition-smooth);
  border-radius: 3px;
}

.progress-text {
  font-size: 0.75rem;
  font-weight: 500;
  min-width: 35px;
  text-align: right;
}

.file-actions {
  display: flex;
  align-items: center;
  gap: 8px;
  flex-shrink: 0;
}

.file-status {
  font-size: 18px;
  width: 24px;
  text-align: center;
}

.btn-remove {
  width: 32px;
  height: 32px;
  border: none;
  background: var(--error-color);
  color: white;
  border-radius: 50%;
  cursor: pointer;
  transition: all var(--transition-smooth);
  display: flex;
  align-items: center;
  justify-content: center;
  font-size: 14px;
  font-weight: bold;
}

.btn-remove:hover {
  background: var(--error-dark);
  transform: scale(1.1);
}

.btn-remove:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
}

.upload-controls {
  display: flex;
  gap: 12px;
  justify-content: center;
  margin-top: 16px;
}

.upload-controls .btn {
  min-width: 120px;
  position: relative;
}

/* Responsive */
@media (max-width: 768px) {
  .drag-drop-area {
    padding: 30px 15px;
    min-height: 150px;
  }
  
  .file-item {
    flex-direction: column;
    text-align: center;
    gap: 12px;
  }
  
  .file-info {
    width: 100%;
  }
  
  .upload-controls {
    flex-direction: column;
  }
}
`;

// Добавляем стили к существующему CSS
if (!document.querySelector('#drag-drop-styles')) {
  const styleSheet = document.createElement('style');
  styleSheet.id = 'drag-drop-styles';
  styleSheet.textContent = dragDropStyles;
  document.head.appendChild(styleSheet);
}

// Глобальный экземпляр для управления
window.DragDropFileUpload = DragDropFileUpload;

// Автоинициализация для элементов с data-file-upload
document.addEventListener('DOMContentLoaded', () => {
  const fileUploadElements = document.querySelectorAll('[data-file-upload]');
  
  fileUploadElements.forEach(element => {
    try {
      const dataOptions = element.dataset.fileUpload ? JSON.parse(element.dataset.fileUpload) : {};
      const options = {
        container: element, // Передаем DOM элемент напрямую
        ...dataOptions
      };
      
      new DragDropFileUpload(options);
    } catch (error) {
      console.error('Ошибка инициализации drag & drop:', error, element);
    }
  });
});

// Экспортируем в глобальную область для удобного доступа
window.fileUploadManager = null;