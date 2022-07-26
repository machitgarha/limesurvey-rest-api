FROM php:7.2-alpine

ARG PLUGIN_SOURCE_DIR=plugin-src
ARG PLUGIN_VOLUME_DIR=plugin
ENV PLUGIN_SOURCE_DIR=$PLUGIN_SOURCE_DIR
ENV PLUGIN_VOLUME_DIR=$PLUGIN_VOLUME_DIR

# TODO: Maybe get the Composer phar file ourselves?
RUN apk add composer

# Supposing the build context is the repo root, i.e. run it with -f when building
COPY . "$PLUGIN_SOURCE_DIR"

RUN mv "$PLUGIN_SOURCE_DIR/build-aux/docker/entrypoint.sh" /usr/bin

RUN cd "$PLUGIN_SOURCE_DIR" && \
    # Make sure to use PHP 7.2, not the one installed by APK (e.g. 7.3)
    /usr/local/bin/php /usr/bin/composer install --no-dev

VOLUME ["$PLUGIN_VOLUME_DIR"]

ENTRYPOINT ["entrypoint.sh"]
