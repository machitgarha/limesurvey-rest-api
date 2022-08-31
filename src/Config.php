<?php

namespace MAChitgarha\LimeSurveyRestApi;

use Dotenv\Dotenv;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugMode;
use MAChitgarha\LimeSurveyRestApi\Utility\LogVerbosity;

class Config
{
    /** @var int */
    private $debugMode = DebugMode::OFF;

    /**
     * Whether to rebuild the cache on every request (e.g. for validation).
     * Enabling this causes the OpenAPI spec cache (used in request validation)
     * to rebuild, which is useful for development purposes.
     * @var bool
     */
    private $cacheRebuild = false;

    /** @var int */
    private $logVerbosity = LogVerbosity::MINIMAL;

    /** @var ?static */
    private static $instance = null;

    private function __construct()
    {
        Dotenv::createImmutable(__DIR__ . '/../')->load();

        $this->debugMode = (int)($_ENV['DEBUG_MODE']) ?? $this->debugMode;
        $this->cacheRebuild = (bool)($_ENV['CACHE_REBUILD']) ?? $this->cacheRebuild;
        $this->logVerbosity = (bool)($_ENV['LOG_VERBOSITY']) ?? $this->logVerbosity;
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new static();
        }
        return self::$instance;
    }

    public function getDebugMode(): int
    {
        return $this->debugMode;
    }

    public function hasDebugOption(int $debugOption): bool
    {
        return $this->debugMode & $debugOption;
    }

    public function getCacheRebuild(): bool
    {
        return $this->cacheRebuild;
    }

    public function getLogVerbosity(): int
    {
        return $this->logVerbosity;
    }
}
