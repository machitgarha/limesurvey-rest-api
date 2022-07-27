<?php
/**
 * Inserts the plugin record to the database.
 */

function error(string $message)
{
    file_put_contents('php://stderr', $message . PHP_EOL, FILE_APPEND);
    exit(1);
}

function makeTempSslCaFile(string $contents)
{
    $file = tmpfile();
    if ($file === false) {
        error('Cannot create temporary file to store MySQL SSL CA');
    }

    fwrite($file, $contents);
    return $file;
}

function mysqlConnect(array $argv)
{
    $mysql = mysqli_init();

    $sslCaFile = empty($argv[6]) ? null : makeTempSslCaFile($argv[6]);
    if ($sslCaFile !== null) {
        $mysql->ssl_set(null, null, stream_get_meta_data($sslCaFile)['uri'], null, null);
    }

    [$host, $socket] = explode(':', $argv[1], 2);
    $port = 0;
    if (is_numeric($socket)) {
        $port = (int) $socket;
        $socket = null;
    }

    $successful = $mysql->real_connect(
        $host,
        $argv[2],
        $argv[3],
        $argv[4],
        $port,
        $socket,
        MYSQLI_CLIENT_SSL_DONT_VERIFY_SERVER_CERT
    );

    if (!$successful) {
        error(
            "Cannot connect to MySQL: {$mysql->connect_error} " .
            "(error code {$mysql->connect_error})"
        );
    }

    return $mysql;
}

function makeTableName(string $tablePrefix)
{
    if (!preg_match('/^[a-z0-9_$]*$/i', $tablePrefix)) {
        error('Table prefix contains invalid characters');
    }

    return $tablePrefix . 'plugins';
}

function isPluginsTableExists(\mysqli $mysql, string $tableName): bool
{
    $result = $mysql->query(<<<SQL
        SHOW TABLES LIKE '$tableName'
SQL
);

    return $result->num_rows === 1;
}

function insertPlugin(\mysqli $mysql, string $tableName): bool
{
    return $mysql->query(<<<SQL
        INSERT INTO `$tableName`(`name`, `plugin_type`, `active`)
            VALUES ('LimeSurveyRestApi', 'upload', 1)
SQL
);
}

function main(array $argv)
{
    echo 'Registering the plugin...' . PHP_EOL;

    $mysql = mysqlConnect($argv);

    $tableName = $mysql->real_escape_string($argv[5] . 'plugins');

    while (!isPluginsTableExists($mysql, $tableName)) {
        echo 'Waiting for the plugins table to be created...' . PHP_EOL;
        sleep(2);
    }

    if (!insertPlugin($mysql, $tableName)) {
        error('Cannot insert a new record to plugins table');
    }

    echo 'Plugin registered and activated successfully...!' . PHP_EOL;
}

main($argv);
