// Скрипт для тестирования исправлений UI

document.addEventListener('DOMContentLoaded', function() {
    console.log('🔧 Применение исправлений UI...');
    
    // Проверяем что анимации работают корректно
    const checkAnimations = () => {
        const animatedElements = document.querySelectorAll('[class*="animate-"]');
        console.log(`✅ Найдено ${animatedElements.length} анимированных элементов`);
        
        // Убеждаемся что параллакс отключен
        const parallaxElements = document.querySelectorAll('.conteiner_2 img, .conteiner_3 img');
        parallaxElements.forEach(element => {
            element.style.transform = 'none';
            element.style.transition = 'none';
        });
        console.log('✅ Параллакс эффект отключен');
    };
    
    // Проверяем размеры кнопок
    const checkButtonSizes = () => {
        const buttons = document.querySelectorAll('button');
        let fixedButtons = 0;
        
        buttons.forEach(button => {
            const computedStyle = window.getComputedStyle(button);
            if (computedStyle.whiteSpace !== 'nowrap') {
                button.style.whiteSpace = 'nowrap';
                fixedButtons++;
            }
        });
        
        console.log(`✅ Исправлено ${fixedButtons} кнопок`);
    };
    
    // Проверяем z-index слоев
    const checkZIndex = () => {
        const containers = document.querySelectorAll('.conteiner_1, .conteiner_2, .conteiner_3');
        containers.forEach((container, index) => {
            const zIndex = 3 - index; // 3, 2, 1
            container.style.position = 'relative';
            container.style.zIndex = zIndex;
        });
        console.log('✅ Z-index слоев исправлен');
    };
    
    // Применяем все исправления
    setTimeout(() => {
        checkAnimations();
        checkButtonSizes();
        checkZIndex();
        console.log('🎉 Все исправления применены!');
    }, 100);
    
    // Добавляем обработчик для плавной прокрутки
    const smoothScroll = () => {
        document.documentElement.style.scrollBehavior = 'smooth';
    };
    
    smoothScroll();
});

// Функция для ручного исправления проблем
window.applyUIFixes = function() {
    console.log('🔧 Применение ручных исправлений...');
    
    // Убираем все transform с изображений
    const images = document.querySelectorAll('.conteiner_2 img, .conteiner_3 img');
    images.forEach(img => {
        img.style.transform = 'none !important';
        img.style.transition = 'none !important';
    });
    
    // Исправляем кнопки
    const buttons = document.querySelectorAll('button');
    buttons.forEach(button => {
        button.style.whiteSpace = 'nowrap';
        button.style.overflow = 'hidden';
        button.style.textOverflow = 'ellipsis';
    });
    
    console.log('✅ Ручные исправления применены');
    
    if (window.toastManager) {
        window.toastManager.info('Ручные исправления применены');
    }
};

// Экспортируем для глобального использования
window.UIFixes = {
    applyFixes: window.applyUIFixes
};