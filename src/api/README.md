# REST API Спецификация для Платформы Корпоративных Идей

## Общие принципы

- **Версионирование**: `/api/v1/`
- **Формат ответов**: JSON
- **Аутентификация**: JWT токены
- **Коды ответов**: Стандартные HTTP коды
- **CORS**: Поддержка кросс-доменных запросов

## Структура ответов

### Успешный ответ
```json
{
  "success": true,
  "data": { ... },
  "message": "Операция выполнена успешно"
}
```

### Ошибка
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Описание ошибки",
    "details": { ... }
  }
}
```

## API Endpoints

### 🔐 Аутентификация (/api/v1/auth)

| Метод | Endpoint | Описание | Аутентификация |
|-------|----------|----------|----------------|
| POST | `/api/v1/auth/login` | Вход в систему | - |
| POST | `/api/v1/auth/register` | Регистрация | - |
| POST | `/api/v1/auth/refresh` | Обновление токена | JWT |
| POST | `/api/v1/auth/logout` | Выход из системы | JWT |

### 👤 Пользователи (/api/v1/users)

| Метод | Endpoint | Описание | Аутентификация |
|-------|----------|----------|----------------|
| GET | `/api/v1/users/profile` | Профиль текущего пользователя | JWT |
| PUT | `/api/v1/users/profile` | Обновление профиля | JWT |
| GET | `/api/v1/users/{id}` | Публичная информация пользователя | JWT |

### 💡 Идеи (/api/v1/ideas)

| Метод | Endpoint | Описание | Аутентификация |
|-------|----------|----------|----------------|
| GET | `/api/v1/ideas` | Список идей с фильтрацией и пагинацией | JWT |
| GET | `/api/v1/ideas/{id}` | Конкретная идея с полной информацией | JWT |
| POST | `/api/v1/ideas` | Создание новой идеи | JWT |
| PUT | `/api/v1/ideas/{id}` | Обновление идеи (автор или админ) | JWT |
| DELETE | `/api/v1/ideas/{id}` | Удаление идеи (автор или админ) | JWT |
| GET | `/api/v1/ideas/top` | Топ идеи за период | JWT |
| GET | `/api/v1/ideas/my` | Мои идеи | JWT |

### 🗳️ Голосование (/api/v1/votes)

| Метод | Endpoint | Описание | Аутентификация |
|-------|----------|----------|----------------|
| POST | `/api/v1/ideas/{id}/vote` | Голосование за идею | JWT |
| DELETE | `/api/v1/ideas/{id}/vote` | Отмена голоса | JWT |
| GET | `/api/v1/ideas/{id}/votes` | Статистика голосов по идее | JWT |

### 💬 Комментарии (/api/v1/comments)

| Метод | Endpoint | Описание | Аутентификация |
|-------|----------|----------|----------------|
| GET | `/api/v1/ideas/{id}/comments` | Комментарии к идее | JWT |
| POST | `/api/v1/ideas/{id}/comments` | Добавить комментарий | JWT |
| PUT | `/api/v1/comments/{id}` | Обновить комментарий | JWT |
| DELETE | `/api/v1/comments/{id}` | Удалить комментарий | JWT |

### 👑 Администрирование (/api/v1/admin)

| Метод | Endpoint | Описание | Аутентификация |
|-------|----------|----------|----------------|
| GET | `/api/v1/admin/ideas` | Все идеи для модерации | Admin JWT |
| PUT | `/api/v1/admin/ideas/{id}/status` | Изменение статуса идеи | Admin JWT |
| GET | `/api/v1/admin/statistics` | Статистика платформы | Admin JWT |
| GET | `/api/v1/admin/users` | Управление пользователями | Admin JWT |

### 📊 Аналитика (/api/v1/analytics)

| Метод | Endpoint | Описание | Аутентификация |
|-------|----------|----------|----------------|
| GET | `/api/v1/analytics/dashboard` | Данные для дашборда | JWT |
| GET | `/api/v1/analytics/ideas/trends` | Тренды по идеям | JWT |
| GET | `/api/v1/analytics/export` | Экспорт данных | JWT |

## Параметры запросов

### Пагинация
- `page` - номер страницы (по умолчанию 1)
- `limit` - количество элементов (по умолчанию 20, максимум 100)

### Фильтрация идей
- `status` - статус идеи (pending, approved, rejected)
- `category` - категория идеи
- `author_id` - ID автора
- `created_from` - дата создания с
- `created_to` - дата создания до
- `search` - поиск по тексту

### Сортировка
- `sort_by` - поле сортировки (created_at, likes_count, popularity_rank)
- `sort_order` - порядок сортировки (asc, desc)

## Коды ошибок

- `AUTH_REQUIRED` - Требуется аутентификация
- `ACCESS_DENIED` - Нет прав доступа
- `VALIDATION_ERROR` - Ошибка валидации данных
- `NOT_FOUND` - Ресурс не найден
- `RATE_LIMITED` - Превышен лимит запросов
- `INTERNAL_ERROR` - Внутренняя ошибка сервера