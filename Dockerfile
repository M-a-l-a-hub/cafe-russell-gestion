FROM php:8.3-fpm

# nginx (serveur web) + php-fpm (exécution PHP) + supervisor (lance les deux
# dans un seul conteneur, comme l'attend Render pour un service web).
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        nginx supervisor gettext-base libpq-dev unzip \
    && docker-php-ext-install pdo_pgsql opcache \
    && rm -rf /var/lib/apt/lists/* /etc/nginx/sites-enabled/default

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader --no-interaction

COPY . .

# Configuration du serveur (hors du dossier public/).
COPY docker/nginx.conf.template /etc/nginx/nginx.conf.template
COPY docker/php-fpm-pool.conf /usr/local/etc/php-fpm.d/zzz-app.conf
COPY docker/php.ini /usr/local/etc/php/conf.d/zzz-app.ini
COPY docker/supervisord.conf /etc/supervisord-app.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh \
    && mkdir -p storage/logs \
    && chown -R www-data:www-data storage

# Les processus maîtres démarrent en root (nécessaire pour nginx et php-fpm
# dans ce conteneur) ; les processus qui traitent les requêtes tournent
# sous l'utilisateur www-data, sans droit d'écriture sur le code.
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
