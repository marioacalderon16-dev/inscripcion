FROM php:8.3-cli
RUN docker-php-ext-install pdo_mysql
WORKDIR /app
COPY . /app
# Railway asigna el puerto en $PORT
CMD php -S 0.0.0.0:${PORT:-8080} -t /app
