FROM php:8.2-apache

RUN apt-get update && apt-get install -y \
    libzip-dev \
    libonig-dev \
    && docker-php-ext-install pdo pdo_mysql mbstring \
    && rm -rf /var/lib/apt/lists/*

RUN rm -f /etc/apache2/mods-enabled/mpm_event.* /etc/apache2/mods-enabled/mpm_worker.* \
    && a2enmod mpm_prefork \
    && a2enmod rewrite

WORKDIR /var/www/html
COPY . /var/www/html/

# Запускаем встроенный PHP-сервер, чтобы слушать на $PORT (Railway).
RUN echo '#!/bin/bash\nexec php -S 0.0.0.0:${PORT:-80} -t /var/www/html\n' > /start.sh \
    && chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]
