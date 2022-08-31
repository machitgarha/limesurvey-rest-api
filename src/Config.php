<?php

namespace MAChitgarha\LimeSurveyRestApi;

use Dotenv\Dotenv;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugMode;

class Config
{
    /** @var int */
    private $debugMode = DebugMode::OFF;

    /**
     * Whether to rebuild the cache or not (e.g. for validation). Useful for development.
     * @var bool
     */
    private $cacheRebuild = false;

    /** @var ?static */
    private static $instance = null;

    private function __construct()
    {
        Dotenv::createImmutable(__DIR__ . '/../')->load();

        $this->debugMode = (int)($_ENV['DEBUG_MODE']) ?? $this->debugMode;
        $this->cacheRebuild = (bool)($_ENV['CACHE_REBUILD']) ?? $this->cacheRebuild;
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
}
