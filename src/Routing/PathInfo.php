<?php

namespace MAChitgarha\LimeSurveyRestApi\Routing;

use MAChitgarha\LimeSurveyRestApi\Error\ApiVersionMissingError;

class PathInfo
{
    public const PREFIX = 'restApi';

    /** @var string */
    private $pathInfo;

    /** @var string */
    private $apiVersion;

    /** @var string */
    private $routedPath;

    public function __construct(string $pathInfo)
    {
        $this->pathInfo = $pathInfo;

        [$this->apiVersion, $this->routedPath] = self::splitPathInfo($pathInfo);
    }

    public function get(): string
    {
        return $this->pathInfo;
    }

    public function getApiVersion(): string
    {
        return $this->apiVersion;
    }

    public function getRoutedPath(): string
    {
        return $this->routedPath;
    }

    private static function splitPathInfo(string $pathInfo): array
    {
        $pattern = '/^\/(v\d+)(\/.*)?$/';

        if (!\preg_match($pattern, self::normalizePathInfo($pathInfo), $matches)) {
            throw new ApiVersionMissingError();
        }

        return [
            $matches[1],
            $matches[2] ?? '/',
        ];
    }

    private static function normalizePathInfo(string $pathInfo): string
    {
        $pathInfo = \str_replace('/' . self::PREFIX, '', $pathInfo);

        // Remove trailing slash
        if (\str_ends_with($pathInfo, '/')) {
            $pathInfo = \rtrim($pathInfo, '/');
        }

        return $pathInfo;
    }
}
