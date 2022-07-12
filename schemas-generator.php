<?php

error_reporting(E_ALL ^ E_DEPRECATED);

require_once(getenv("HOME") . "/.composer/vendor/autoload.php");
require_once(__DIR__ . "/third_party/autoload.php");
require_once(__DIR__ . "/framework/yii.php");

const BASEPATH = __DIR__ . "/framework";
const APPPATH = __DIR__ . "/application";

require_once(__DIR__ . '/application/core/LSYii_Application.php');

set_include_path(get_include_path() . PATH_SEPARATOR . APPPATH);
set_include_path(get_include_path() . PATH_SEPARATOR . APPPATH . "/core");
set_include_path(get_include_path() . PATH_SEPARATOR . APPPATH . "/models");
set_include_path(get_include_path() . PATH_SEPARATOR . APPPATH . "/models/Traits");
set_include_path(get_include_path() . PATH_SEPARATOR . APPPATH . "/models/Interfaces");

use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\PathItem;

$modelFiles = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator(__DIR__ . "/application/models"),
    FilesystemIterator::SKIP_DOTS,
);

$schemas = [];

foreach ($modelFiles as $modelFile) {
    $className = explode(".", $modelFile->getFilename())[0];

    if (
        $modelFile->isFile() &&
        $modelFile->getExtension() === "php" &&
        !\in_array($className, [])
    ) {
        require_once($modelFile->getPathname());

        $reflection = new ReflectionClass($className);
        $docs = $reflection->getDocComment();

        preg_match_all(
            '/@property\s+(\w+)\s+\$(\w+)\s*([a-z0-9 .,;]*)\s*\n/i',
            $docs,
            $matches,
            PREG_SET_ORDER
        );

        $schemaName = strtolower(preg_replace(
            '/(?<=\d)(?=[A-Za-z])|(?<=[A-Za-z])(?=\d)|(?<=[a-z])(?=[A-Z])/', '_', $className
        ));

        if (array_key_exists($schemaName, $schemas)) {
            exit("Oops!");
        }

        $schemas[$schemaName] = [
            'type' => 'object',
            'properties' => [],
        ];

        foreach ($matches as $match) {
            var_dump($match);
            $schemas[$schemaName]['properties'][$match[2]] = [
                'type' => $match[1],
                'summary' => $match[3],
            ];
        }
    }
}

ksort($schemas);

// create base API Description
$openapi = new OpenApi([
    'openapi' => '3.0.2',
    'components' => [
        'schemas' => $schemas
    ],
]);

file_put_contents("test.yaml", \cebe\openapi\Writer::writeToYaml($openapi));
