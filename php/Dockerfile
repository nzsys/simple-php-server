FROM php:8.4-cli

RUN apt-get update && apt-get install -y \
    && docker-php-ext-install pcntl

WORKDIR /usr/src

COPY ./src/server.php /usr/src/server.php
COPY ./public /usr/src/public

CMD ["php", "server.php", "8080"]
