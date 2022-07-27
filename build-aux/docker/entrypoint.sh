#!/bin/sh

# Exit on error
set -eu

insertPluginToDatabase() {
    "$PLUGIN_SOURCE_DIR"/build-aux/docker/wait-for "$LIMESURVEY_DB_HOST:3306" -t 30 -- \
        echo "MySQL service detected!" && \
        php "$PLUGIN_SOURCE_DIR"/build-aux/docker/register-plugin.php \
            "$LIMESURVEY_DB_HOST" \
            "$LIMESURVEY_DB_USER" \
            "$LIMESURVEY_DB_PASSWORD" \
            "$LIMESURVEY_DB_NAME" \
            "$LIMESURVEY_TABLE_PREFIX" \
            "$MYSQL_SSL_CA_CONTENTS"
}

main() {
    cp -a "$PLUGIN_SOURCE_DIR"/* "$PLUGIN_VOLUME_DIR"

    insertPluginToDatabase

    sleep infinity
}

main
