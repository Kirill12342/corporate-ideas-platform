document.addEventListener('DOMContentLoaded', function() {
    // Файловые вложения
    const fileUpload = document.getElementById('file-upload');
    const fileDropZone = document.getElementById('fileDropZone');
    const filePreview = document.getElementById('file-preview');
    let selectedFiles = [];

    // Обработка клика по зоне загрузки
    fileDropZone.addEventListener('click', () => {
        fileUpload.click();
    });

    // Обработка выбора файлов
    fileUpload.addEventListener('change', (e) => {
        handleFiles(e.target.files);
    });

    // Drag and Drop обработка
    fileDropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        fileDropZone.classList.add('dragover');
    });

    fileDropZone.addEventListener('dragleave', (e) => {
        e.preventDefault();
        fileDropZone.classList.remove('dragover');
    });

    fileDropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        fileDropZone.classList.remove('dragover');
        handleFiles(e.dataTransfer.files);
    });

    // Функция обработки файлов
    function handleFiles(files) {
        Array.from(files).forEach(file => {
            if (validateFile(file)) {
                selectedFiles.push(file);
                displayFilePreview(file);
            }
        });
        updateFileInput();
    }

    // Валидация файлов
    function validateFile(file) {
        const maxSize = 10 * 1024 * 1024; // 10 МБ
        const allowedTypes = [
            'image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/bmp',
            'application/pdf', 'application/msword', 
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'application/vnd.ms-powerpoint',
            'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'text/plain', 'text/csv', 'application/zip', 'application/x-rar-compressed'
        ];

        if (file.size > maxSize) {
            alert(`Файл "${file.name}" слишком большой. Максимальный размер: 10 МБ`);
            return false;
        }

        if (!allowedTypes.includes(file.type)) {
            alert(`Недопустимый тип файла: "${file.name}"`);
            return false;
        }

        return true;
    }

    // Отображение превью файлов
    function displayFilePreview(file) {
        const previewItem = document.createElement('div');
        previewItem.className = 'file-preview-item';
        previewItem.dataset.fileName = file.name;

        const isImage = file.type.startsWith('image/');
        
        if (isImage) {
            const img = document.createElement('img');
            const reader = new FileReader();
            reader.onload = (e) => {
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
            previewItem.appendChild(img);
        } else {
            const icon = document.createElement('div');
            icon.className = 'file-icon';
            icon.textContent = getFileIcon(file.type);
            previewItem.appendChild(icon);
        }

        const fileName = document.createElement('span');
        fileName.className = 'file-name';
        fileName.textContent = file.name;
        previewItem.appendChild(fileName);

        const fileSize = document.createElement('span');
        fileSize.className = 'file-size';
        fileSize.textContent = formatFileSize(file.size);
        previewItem.appendChild(fileSize);

        const removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'remove-file';
        removeBtn.innerHTML = '×';
        removeBtn.onclick = () => removeFile(file.name);
        previewItem.appendChild(removeBtn);

        filePreview.appendChild(previewItem);
    }

    // Получение иконки для файла
    function getFileIcon(type) {
        if (type.includes('pdf')) return '📄';
        if (type.includes('word')) return '📝';
        if (type.includes('excel') || type.includes('spreadsheet')) return '📊';
        if (type.includes('powerpoint') || type.includes('presentation')) return '📈';
        if (type.includes('zip') || type.includes('rar')) return '📦';
        if (type.includes('text')) return '📃';
        return '📄';
    }

    // Форматирование размера файла
    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }

    // Удаление файла
    function removeFile(fileName) {
        selectedFiles = selectedFiles.filter(file => file.name !== fileName);
        const previewItem = filePreview.querySelector(`[data-file-name="${fileName}"]`);
        if (previewItem) {
            previewItem.remove();
        }
        updateFileInput();
    }

    // Обновление input файлов
    function updateFileInput() {
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        fileUpload.files = dt.files;
    }

    // Обработка формы
    const ideaForm = document.getElementById('ideaForm');
    if (ideaForm) {
        ideaForm.addEventListener('submit', function(e) {
            // Форма будет отправлена со всеми файлами автоматически
            console.log('Отправка формы с файлами:', selectedFiles.length);
        });
    }
});