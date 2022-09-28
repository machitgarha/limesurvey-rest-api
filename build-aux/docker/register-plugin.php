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
        '',
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

function isDatabaseExists(\mysqli $mysql, string $databaseName): bool
{
    $result = $mysql->query(<<<SQL
        SHOW DATABASES LIKE '$databaseName'
SQL
);

    return $result->num_rows === 1;
}

function makeTableName(string $tablePrefix): string
{
    if (!preg_match('/^[a-z0-9_$]*$/i', $tablePrefix)) {
        error('Table prefix contains invalid characters');
    }

    return $tablePrefix . 'plugins';
}

function isTableExists(\mysqli $mysql, string $tableName): bool
{
    $result = $mysql->query(<<<SQL
        SHOW TABLES LIKE '$tableName'
SQL
);

    return $result->num_rows === 1;
}

function isPluginAlreadyInserted(\mysqli $mysql, string $tableName): bool
{
    $result = $mysql->query(<<<SQL
        SELECT `id` FROM `$tableName` WHERE `name` = 'RestApi'
SQL
);

    return $result->num_rows > 0;
}

function insertPlugin(\mysqli $mysql, string $tableName): bool
{
    return $mysql->query(<<<SQL
        INSERT INTO `$tableName`(`name`, `plugin_type`, `active`)
            VALUES ('RestApi', 'upload', 1)
SQL
);
}

function main(array $argv)
{
    echo 'Registering the plugin...' . PHP_EOL;

    $mysql = mysqlConnect($argv);

    $databaseName = $mysql->real_escape_string($argv[4]);

    while (!isDatabaseExists($mysql, $databaseName)) {
        echo 'Waiting for the database to be created...' . PHP_EOL;
        sleep(3);
    }

    $mysql->select_db($databaseName);

    $pluginsTableName = makeTableName($argv[5]);

    while (!isTableExists($mysql, $pluginsTableName)) {
        echo "Waiting for the $pluginsTableName table to be created..." . PHP_EOL;
        sleep(3);
    }

    if (isPluginAlreadyInserted($mysql, $pluginsTableName)) {
        echo 'Plugin already registered, nothing to do.' . PHP_EOL;
        return;
    }

    if (!insertPlugin($mysql, $pluginsTableName)) {
        error("Cannot insert a new record to $pluginsTableName table!");
    }

    echo 'Plugin registered and activated successfully...!' . PHP_EOL;
}

main($argv);
