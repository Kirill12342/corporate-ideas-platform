@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

if "%1"=="" goto help

if "%1"=="up" goto up
if "%1"=="down" goto down
if "%1"=="rebuild" goto rebuild
if "%1"=="logs" goto logs
if "%1"=="logs-php" goto logs-php
if "%1"=="shell" goto shell
if "%1"=="clean-php" goto clean-php
if "%1"=="test-db" goto test-db
if "%1"=="status" goto status
if "%1"=="clean" goto clean
goto help

:up
echo 🚀 Запуск контейнеров...
docker-compose up -d
goto end

:down
echo ⏹️  Остановка контейнеров...
docker-compose down
goto end

:rebuild
echo 🔄 Пересборка контейнеров (исправление проблем с заголовками)...
docker-compose down
docker-compose build --no-cache
docker-compose up -d
echo ✅ Готово! Проверьте http://localhost
goto end

:logs
echo 📋 Просмотр логов...
docker-compose logs -f
goto end

:logs-php
echo 📋 Просмотр логов PHP...
docker-compose logs -f php
goto end

:shell
echo 💻 Вход в PHP контейнер...
docker-compose exec php bash
goto end

:clean-php
echo 🧹 Очистка PHP файлов от BOM символов...
if exist "scripts\clean-php-files.sh" (
    bash scripts/clean-php-files.sh
) else (
    echo ❌ Файл scripts\clean-php-files.sh не найден
)
goto end

:test-db
echo 🔍 Тест подключения к базе данных...
docker-compose exec php php -r "try { $pdo = new PDO('mysql:host=db;dbname=stuffVoice', 'root', '7JH-Ek2-P96-ngB'); echo 'БД подключена успешно\n'; } catch(Exception $e) { echo 'Ошибка: ' . $e->getMessage() . '\n'; }"
goto end

:status
echo 📊 Статус контейнеров...
docker-compose ps
goto end

:clean
echo 🗑️  Полная очистка...
docker-compose down -v --rmi all
goto end

:help
echo.
echo 🐳 Docker Management для Corporate Ideas Platform
echo.
echo Доступные команды:
echo   docker-manage up          - Запуск контейнеров
echo   docker-manage down        - Остановка контейнеров
echo   docker-manage rebuild     - Пересборка (исправляет проблемы с заголовками)
echo   docker-manage logs        - Просмотр всех логов
echo   docker-manage logs-php    - Просмотр логов PHP
echo   docker-manage shell       - Войти в PHP контейнер
echo   docker-manage clean-php   - Очистить PHP файлы от BOM
echo   docker-manage test-db     - Тест подключения к БД
echo   docker-manage status      - Статус контейнеров
echo   docker-manage clean       - Полная очистка
echo.
echo 💡 Для исправления ошибок заголовков используйте: docker-manage rebuild
echo.

:end
