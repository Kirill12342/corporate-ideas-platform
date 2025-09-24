# Corporate Ideas Platform

Платформа для корпоративных идей - веб-приложение для сбора и управления идеями от сотрудников.

## Описание проекта

Платформа предназначена для:
- Подачи идей сотрудниками
- Управления идеями администраторами
- Авторизации пользователей
- Просмотра информации о компании

## Структура проекта

```
├── index.html              # Главная страница
├── components/             # PHP компоненты и страницы
│   ├── about.html         # Страница "О нас"
│   ├── admin.html         # Интерфейс администратора
│   ├── admin.php          # Логика администратора
│   ├── admin_auth.php     # Авторизация администратора
│   ├── config.php         # Конфигурация базы данных
│   ├── contacts.html      # Страница контактов
│   ├── idea.html          # Страница подачи идеи
│   ├── login.html         # Страница входа
│   ├── login.php          # Обработка входа
│   ├── logout.php         # Выход из системы
│   ├── register.php       # Регистрация пользователей
│   ├── signup.html        # Страница регистрации
│   ├── submit_idea.php    # Подача идеи
│   ├── update_idea.php    # Обновление идеи
│   ├── user.php          # Панель пользователя
│   └── user_auth.php      # Авторизация пользователя
├── css/                   # Стили
├── js/                    # JavaScript файлы
└── image/                 # Изображения

```

## Технологии

- **Frontend**: HTML5, CSS3, JavaScript
- **Backend**: PHP
- **База данных**: MySQL
- **Веб-сервер**: Apache (XAMPP)

## Установка и запуск

1. Убедитесь, что у вас установлен XAMPP
2. Склонируйте репозиторий в папку `htdocs`:
   ```bash
   git clone https://github.com/Kirill12342/corporate-ideas-platform.git
   cd corporate-ideas-platform
   ```
3. Запустите Apache и MySQL в XAMPP
4. Настройте базу данных в файле `components/config.php`
5. Откройте браузер и перейдите по адресу `http://localhost/corporate-ideas-platform`

## Git Workflow

Проект использует Git для контроля версий с двумя основными ветками:

- **master** - стабильная версия для продакшна
- **develop** - ветка разработки для новых функций

### Основные команды Git

```bash
# Переключение на ветку разработки
git checkout develop

# Создание новой feature-ветки
git checkout -b feature/название-функции

# Добавление изменений
git add .
git commit -m "Описание изменений"

# Отправка изменений
git push origin feature/название-функции

# Переключение обратно на develop
git checkout develop
git merge feature/название-функции
```

## Функциональность

### Для пользователей:
- Регистрация и авторизация
- Подача новых идей
- Просмотр статуса своих идей

### Для администраторов:
- Просмотр всех поданных идей
- Изменение статуса идей
- Управление пользователями

## Контрибьют

1. Создайте fork репозитория
2. Создайте feature-ветку (`git checkout -b feature/amazing-feature`)
3. Зафиксируйте изменения (`git commit -m 'Add amazing feature'`)
4. Отправьте ветку (`git push origin feature/amazing-feature`)
5. Создайте Pull Request

## Лицензия

Этот проект создан в образовательных целях.

## Автор

Проект разработан для изучения веб-разработки и работы с Git.


# Просмотр статуса
git status

# Переключение между ветками
git checkout master
git checkout develop

# Создание новой feature-ветки
git checkout -b feature/new-feature

# Добавление и коммит изменений
git add .
git commit -m "Описание изменений"

# Отправка в GitHub
git push origin develop

# Просмотр истории коммитов
git log --oneline

# Слияние веток
git merge develop