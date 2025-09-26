#!/bin/bash

# Скрипт для ручной очистки PHP файлов от BOM и проблемных символов

echo "🔧 Запуск очистки PHP файлов..."

# Перейти в директорию проекта
cd "$(dirname "$0")/.." || exit 1

echo "📁 Рабочая директория: $(pwd)"

# Очистка BOM символов
echo "🧹 Удаление BOM символов из PHP файлов..."
find ./src -name "*.php" -type f -exec sed -i '1s/^\xEF\xBB\xBF//' {} \; 2>/dev/null || true

# Удаление trailing пробелов
echo "✂️  Удаление лишних пробелов в конце строк..."
find ./src -name "*.php" -type f -exec sed -i 's/[[:space:]]*$//' {} \; 2>/dev/null || true

# Проверка на наличие пробелов перед <?php
echo "🔍 Проверка пробелов перед открывающими тегами PHP..."
find ./src -name "*.php" -type f -exec bash -c '
    if head -1 "$1" | grep -q "^[[:space:]]\+<\?php"; then
        echo "⚠️  Найдены пробелы перед <?php в файле: $1"
        sed -i "1s/^[[:space:]]*<?php/<?php/" "$1"
        echo "✅ Исправлено: $1"
    fi
' _ {} \;

echo "✅ Очистка завершена!"
echo "🐳 Теперь вы можете пересобрать Docker контейнеры командой:"
echo "   docker-compose down && docker-compose up --build"
