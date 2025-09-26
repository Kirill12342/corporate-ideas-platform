FROM php:8.3-fpm

# Устанавливаем необходимые расширения PHP
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Создаем кастомный php.ini для решения проблем с заголовками
RUN echo "output_buffering = 4096" >> /usr/local/etc/php/conf.d/docker-php-output.ini && \
    echo "implicit_flush = Off" >> /usr/local/etc/php/conf.d/docker-php-output.ini && \
    echo "session.auto_start = 0" >> /usr/local/etc/php/conf.d/docker-php-output.ini && \
    echo "session.cache_limiter = nocache" >> /usr/local/etc/php/conf.d/docker-php-output.ini && \
    echo "expose_php = Off" >> /usr/local/etc/php/conf.d/docker-php-output.ini && \
    echo "default_charset = UTF-8" >> /usr/local/etc/php/conf.d/docker-php-output.ini

# Копируем скрипт запуска
COPY scripts/startup.sh /usr/local/bin/startup.sh
RUN chmod +x /usr/local/bin/startup.sh

WORKDIR /var/www/html

ENTRYPOINT ["/usr/local/bin/startup.sh"]
CMD ["php-fpm"]
