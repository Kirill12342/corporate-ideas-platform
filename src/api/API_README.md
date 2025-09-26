# 🚀 Corporate Ideas Platform REST API

Полнофункциональный REST API для платформы корпоративных идей с JWT аутентификацией, системой голосования, пагинацией и расширенной безопасностью.

## 📋 Особенности

- ✅ **JWT Аутентификация** с безопасным хешированием паролей
- ✅ **CRUD операции** для идей с полной валидацией
- ✅ **Система голосования** с автоматическим подсчётом рейтинга
- ✅ **Пагинация и фильтрация** для всех списков
- ✅ **Rate Limiting** для защиты от злоупотреблений
- ✅ **CORS поддержка** для кросс-доменных запросов
- ✅ **OpenAPI/Swagger документация**
- ✅ **Интерактивный API тестер**

## 🏗️ Структура API

```
api/
├── v1/
│   ├── index.php              # Главный роутер
│   ├── config/
│   │   ├── cors.php          # CORS настройки
│   │   └── database.php      # Подключение к БД
│   ├── middleware/
│   │   ├── auth.php          # JWT аутентификация
│   │   └── security.php      # Rate limiting и безопасность
│   ├── controllers/
│   │   ├── AuthController.php
│   │   ├── IdeasController.php
│   │   ├── UsersController.php
│   │   └── VotesController.php
│   ├── routes/
│   │   ├── auth.php
│   │   ├── ideas.php
│   │   ├── users.php
│   │   └── votes.php
│   └── utils/
│       └── response.php      # Утилиты для ответов
├── docs/
│   ├── index.html           # Swagger UI
│   └── openapi.yaml         # OpenAPI спецификация
└── test/
    └── index.html           # Интерактивный тестер
```

## 🚦 Быстрый старт

### 1. Установка

Скопируйте API файлы в директорию веб-сервера:

```bash
/praktica_popov/api/
```

### 2. Настройка базы данных

API использует существующую конфигурацию БД из `components/config.php`.

### 3. Тестирование

Откройте в браузере:
- **Документация**: `/praktica_popov/api/docs/`
- **Интерактивный тестер**: `/praktica_popov/api/test/`

## 📡 Основные Endpoints

### 🔐 Аутентификация

| Метод | Endpoint | Описание |
|-------|----------|----------|
| POST | `/api/v1/auth/login` | Вход в систему |
| POST | `/api/v1/auth/register` | Регистрация |
| POST | `/api/v1/auth/refresh` | Обновление токена |

### 💡 Идеи

| Метод | Endpoint | Описание |
|-------|----------|----------|
| GET | `/api/v1/ideas` | Список идей с фильтрацией |
| GET | `/api/v1/ideas/{id}` | Конкретная идея |
| POST | `/api/v1/ideas` | Создание идеи |
| PUT | `/api/v1/ideas/{id}` | Обновление идеи |
| DELETE | `/api/v1/ideas/{id}` | Удаление идеи |
| GET | `/api/v1/ideas/top` | Топ идеи |
| GET | `/api/v1/ideas/my` | Мои идеи |

### 🗳️ Голосование

| Метод | Endpoint | Описание |
|-------|----------|----------|
| POST | `/api/v1/ideas/{id}/vote` | Голосование за идею |
| DELETE | `/api/v1/ideas/{id}/vote` | Отмена голоса |
| GET | `/api/v1/ideas/{id}/votes` | Статистика голосов |

### 👤 Пользователи

| Метод | Endpoint | Описание |
|-------|----------|----------|
| GET | `/api/v1/users/profile` | Мой профиль |
| PUT | `/api/v1/users/profile` | Обновление профиля |
| GET | `/api/v1/users/{id}` | Информация о пользователе |

## 💻 Примеры использования

### Аутентификация

```bash
# Вход в систему
curl -X POST http://localhost/praktica_popov/api/v1/auth/login \\
  -H "Content-Type: application/json" \\
  -d '{"username": "testuser", "password": "password123"}'

# Ответ
{
  "success": true,
  "message": "Авторизация успешна",
  "data": {
    "token": "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...",
    "user": {
      "id": 1,
      "username": "testuser",
      "role": "user"
    }
  }
}
```

### Создание идеи

```bash
curl -X POST http://localhost/praktica_popov/api/v1/ideas \\
  -H "Content-Type: application/json" \\
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \\
  -d '{
    "title": "Улучшение процесса разработки",
    "description": "Предлагаю внедрить CI/CD для ускорения релизов",
    "category": "Технологии"
  }'
```

### Голосование

```bash
curl -X POST http://localhost/praktica_popov/api/v1/ideas/1/vote \\
  -H "Content-Type: application/json" \\
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \\
  -d '{"vote_type": "like"}'
```

### Получение идей с фильтрацией

```bash
curl "http://localhost/praktica_popov/api/v1/ideas?status=approved&category=Технологии&page=1&limit=10" \\
  -H "Authorization: Bearer YOUR_JWT_TOKEN"
```

## 🔒 Безопасность

### JWT токены
- Токены действительны 24 часа
- Используется алгоритм HS256
- Автоматическая валидация всех защищённых endpoints

### Rate Limiting
- **Неавторизованные**: 30 запросов/минуту
- **Авторизованные**: 100 запросов/минуту
- Заголовки: `X-RateLimit-Limit`, `X-RateLimit-Remaining`

### Валидация данных
- Строгая проверка всех входящих данных
- Sanitization HTML для защиты от XSS
- Параметризованные запросы для защиты от SQL инъекций

## 📊 Пагинация

Все endpoints со списками поддерживают пагинацию:

```json
{
  "success": true,
  "data": {
    "items": [...],
    "pagination": {
      "current_page": 1,
      "per_page": 20,
      "total": 150,
      "total_pages": 8,
      "has_next": true,
      "has_prev": false
    }
  }
}
```

Параметры:
- `page` - номер страницы (начиная с 1)
- `limit` - количество элементов (максимум 100)

## 🔍 Фильтрация идей

Доступные параметры для `/api/v1/ideas`:

- `status` - статус идеи (`pending`, `approved`, `rejected`)
- `category` - категория идеи
- `author_id` - ID автора
- `search` - поиск по тексту
- `sort_by` - поле сортировки (`created_at`, `likes_count`, `popularity_rank`)
- `sort_order` - порядок (`asc`, `desc`)

## 🛠️ Коды ошибок

| Код | Описание |
|-----|----------|
| `AUTH_REQUIRED` | Требуется аутентификация |
| `ACCESS_DENIED` | Недостаточно прав |
| `VALIDATION_ERROR` | Ошибка валидации |
| `NOT_FOUND` | Ресурс не найден |
| `RATE_LIMITED` | Превышен лимит запросов |
| `DATABASE_ERROR` | Ошибка базы данных |

## 🧪 Тестирование

### Интерактивный тестер
Откройте `/praktica_popov/api/test/` для удобного тестирования всех endpoints.

### Swagger UI
Полная документация доступна по адресу `/praktica_popov/api/docs/`.

### Примеры cURL
Все примеры запросов есть в документации Swagger.

## 🚀 Возможности для расширения

1. **Комментарии к идеям** - добавить endpoints для комментариев
2. **Файлы и вложения** - поддержка загрузки файлов
3. **Уведомления** - система push/email уведомлений
4. **Аналитика** - endpoints для статистики и метрик
5. **Теги** - система тегов для категоризации
6. **Webhooks** - интеграция с внешними системами

## ⚡ Производительность

- Использование подготовленных запросов (prepared statements)
- Индексы БД для быстрого поиска
- Оптимизированные SQL запросы с JOIN
- Кеширование rate limiting в файловой системе

## 🔧 Настройка

Основные настройки в файлах:

- `middleware/auth.php` - секретный ключ JWT
- `middleware/security.php` - лимиты rate limiting
- `config/cors.php` - разрешённые домены

---

**Создано для:** Corporate Ideas Platform  
**Версия:** 1.0.0  
**Дата:** 26 сентября 2025  

🎉 **REST API успешно развёрнут и готов к использованию!**