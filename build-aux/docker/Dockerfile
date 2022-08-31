FROM php:7.2-alpine

ARG PLUGIN_SOURCE_DIR=/plugin-src \
    PLUGIN_VOLUME_DIR=/plugin

ENV PLUGIN_SOURCE_DIR=$PLUGIN_SOURCE_DIR \
    PLUGIN_VOLUME_DIR=$PLUGIN_VOLUME_DIR

ENV LIMESURVEY_DB_HOST=mysql \
    LIMESURVEY_DB_NAME=limesurvey \
    LIMESURVEY_DB_USER=root \
    LIMESURVEY_DB_PASSWORD='' \
    LIMESURVEY_TABLE_PREFIX='' \
    MYSQL_SSL_CA_CONTENTS=''

ENV LIMESURVEY_REST_API_ENV=''

RUN \
    # TODO: Maybe get the Composer phar file ourselves?
    apk add composer && \
    # To insert plugin data to plugins database
    docker-php-ext-install mysqli

# Supposing the build context is the repo root, i.e. run it with -f when building
COPY . "$PLUGIN_SOURCE_DIR"

RUN mv "$PLUGIN_SOURCE_DIR/build-aux/docker/entrypoint.sh" /usr/bin

RUN cd "$PLUGIN_SOURCE_DIR" && \
    # Make sure to use PHP 7.2, not the one installed by APK (e.g. 7.3)
    /usr/local/bin/php /usr/bin/composer install --no-dev && \

    echo "${LIMESURVEY_REST_API_ENV}" > .env

VOLUME ["$PLUGIN_VOLUME_DIR"]

ENTRYPOINT ["entrypoint.sh"]
