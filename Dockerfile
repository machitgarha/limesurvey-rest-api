FROM php:7.2-alpine AS alpine

# TODO: Maybe get the Composer phar file ourselves?
RUN apk add composer

COPY . plugin-src

RUN cd plugin-src && \
    # Make sure to use PHP 7.2, not the one installed by APK (e.g. 7.3)
    /usr/local/bin/php /usr/bin/composer install --no-dev

VOLUME ["/plugin"]

ENTRYPOINT /bin/cp -a /plugin-src/* /plugin
