# Makefile для управления Docker контейнерами

.PHONY: up down build rebuild clean logs shell clean-php test

# Запуск контейнеров
up:
	docker-compose up -d

# Остановка контейнеров
down:
	docker-compose down

# Сборка контейнеров
build:
	docker-compose build

# Пересборка и запуск (решение проблем с заголовками)
rebuild:
	docker-compose down
	docker-compose build --no-cache
	docker-compose up -d

# Полная очистка (контейнеры, образы, volumes)
clean:
	docker-compose down -v --rmi all

# Просмотр логов
logs:
	docker-compose logs -f

# Логи только PHP контейнера
logs-php:
	docker-compose logs -f php

# Зайти в PHP контейнер
shell:
	docker-compose exec php bash

# Очистка PHP файлов от BOM символов
clean-php:
	@echo "Очистка PHP файлов..."
	@if [ -f "./scripts/clean-php-files.sh" ]; then \
		chmod +x ./scripts/clean-php-files.sh; \
		./scripts/clean-php-files.sh; \
	else \
		echo "Файл clean-php-files.sh не найден"; \
	fi

# Тест подключения к базе данных
test-db:
	docker-compose exec php php -r "try { $$pdo = new PDO('mysql:host=db;dbname=stuffVoice', 'root', '7JH-Ek2-P96-ngB'); echo 'БД подключена успешно\n'; } catch(Exception $$e) { echo 'Ошибка: ' . $$e->getMessage() . '\n'; }"

# Показать статус контейнеров
status:
	docker-compose ps

# Помощь
help:
	@echo "Доступные команды:"
	@echo "  make up          - Запуск контейнеров"
	@echo "  make down        - Остановка контейнеров"
	@echo "  make rebuild     - Пересборка (исправляет проблемы с заголовками)"
	@echo "  make logs        - Просмотр всех логов"
	@echo "  make logs-php    - Просмотр логов PHP"
	@echo "  make shell       - Войти в PHP контейнер"
	@echo "  make clean-php   - Очистить PHP файлы от BOM"
	@echo "  make test-db     - Тест подключения к БД"
	@echo "  make clean       - Полная очистка"
	@echo "  make status      - Статус контейнеров"
