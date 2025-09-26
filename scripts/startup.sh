#!/bin/bash

# Очистка BOM символов и лишних пробелов в PHP файлах
echo "Очистка PHP файлов от BOM символов..."
find /var/www/html -name "*.php" -type f -exec sed -i '1s/^\xEF\xBB\xBF//' {} \;

# Удаление trailing пробелов в конце строк
echo "Удаление trailing пробелов..."
find /var/www/html -name "*.php" -type f -exec sed -i 's/[[:space:]]*$//' {} \;

# Проверка и исправление прав доступа
echo "Настройка прав доступа..."
chown -R www-data:www-data /var/www/html
chmod -R 755 /var/www/html

echo "Очистка завершена. Запуск PHP-FPM..."
exec "$@"
