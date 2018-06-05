FROM scomm/php5.6-apache

# Copy Contents into container
COPY app /var/www/html/app
COPY bin /var/www/html/bin
COPY src /var/www/html/src
COPY vendor /var/www/html/vendor
COPY web /var/www/html/web

# Set ENV
ENV SYMFONY_ENV=prod
ENV APP_ENV=prod

# Make sure cache in clean
RUN rm -fr app/cache/*

# Build Cache
RUN php app/console cache:warmup --env=$APP_ENV --no-debug
RUN php app/console assets:install --env=$APP_ENV
RUN php app/console assetic:dump --env=$APP_ENV --no-debug

# Set permissions
RUN chown -R www-data:www-data /var && chmod -R 0755 /var