<?php

namespace MAChitgarha\LimeSurveyRestApi\Routing;

use MAChitgarha\LimeSurveyRestApi\Config;

use MAChitgarha\LimeSurveyRestApi\Error\ApiVersionMissingError;

class PathInfo
{
    private const PREFIX = 'restApi';

    /** @var string */
    private $pathInfo;

    /** @var ?string */
    private $apiVersion = null;

    /** @var ?string */
    private $routingPath = null;

    public function __construct(string $pathInfo)
    {
        $this->pathInfo = $pathInfo;
    }

    public function isBelongedToThisPlugin(): bool
    {
        return \str_starts_with($this->pathInfo, '/' . self::PREFIX);
    }

    public function get(): string
    {
        return $this->pathInfo;
    }

    public function getApiVersion(): string
    {
        $this->makeApiVersionAndRoutingPathIfNeeded();
        return $this->apiVersion;
    }

    public function getRoutingPath(): string
    {
        $this->makeApiVersionAndRoutingPathIfNeeded();
        return $this->routingPath;
    }

    private function makeApiVersionAndRoutingPathIfNeeded(): void
    {
        if ($this->apiVersion !== null) {
            return;
        }

        $pattern = '/^\/(v\d+)(\/.*)?$/';

        if (!\preg_match($pattern, self::normalizePathInfo($this->pathInfo), $matches)) {
            throw new ApiVersionMissingError();
        }

        $this->apiVersion = $matches[1];
        $this->routingPath = $matches[2] ?? '/';
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
