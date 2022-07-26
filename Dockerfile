FROM php:7.2-alpine AS alpine

# TODO: Maybe get the Composer phar file ourselves?
RUN apk add composer

COPY . LimeSurveyRestApi

RUN cd LimeSurveyRestApi && \
    # Make sure to use PHP 7.2, not the one installed by APK (e.g. 7.3)
    /usr/local/bin/php /usr/bin/composer install --no-dev

VOLUME ["/LimeSurveyRestApi"]
