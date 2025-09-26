# Docker Setup для Corporate Ideas Platform

Эта конфигурация Docker решает проблемы с заголовками PHP и сессиями.

## Проблемы которые решает эта настройка:

1. **Warning: session_start(): Session cannot be started after headers have already been sent**
2. **Warning: Cannot modify header information - headers already sent**
3. BOM символы в PHP файлах
4. Лишние пробелы перед открывающими тегами PHP

## Быстрый запуск:

```bash
# Остановить существующие контейнеры
docker-compose down

# Пересобрать и запустить
docker-compose up --build
```

## Ручная очистка файлов (если нужно):

```bash
# В Linux/Mac/WSL
chmod +x scripts/clean-php-files.sh
./scripts/clean-php-files.sh

# В Windows Git Bash
bash scripts/clean-php-files.sh
```

## Что изменено:

### Dockerfile:
- Добавлена буферизация вывода (output_buffering = 4096)
- Настройки сессий для предотвращения автостарта
- Автоматическая очистка BOM символов при запуске контейнера
- Правильная настройка кодировки UTF-8

### PHP файлы:
- Добавлен `ob_start()` в файлы аутентификации
- Удалены лишние символы перед открывающими тегами

### Скрипты:
- `startup.sh` - автоматическая очистка при старте контейнера
- `clean-php-files.sh` - ручная очистка файлов

## Структура:

```
├── Dockerfile (обновлен)
├── docker-compose.yml (обновлен)
├── scripts/
│   ├── startup.sh (новый)
│   └── clean-php-files.sh (новый)
└── src/components/
    ├── user_auth.php (исправлен)
    └── admin_auth.php (исправлен)
```

## Отладка:

Если проблемы остаются, проверьте:
1. Логи контейнера: `docker-compose logs php`
2. Конфигурацию PHP: зайти в контейнер и выполнить `php --ini`
3. Проверить файлы на BOM: `hexdump -C file.php | head`
