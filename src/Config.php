<?php

namespace MAChitgarha\LimeSurveyRestApi;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugMode;
use MAChitgarha\LimeSurveyRestApi\Utility\LogVerbosity;

class Config
{
    public const KEY_DEBUG_MODE = 'debug_mode';
    public const KEY_CACHE_REBUILD = 'cache_rebuild';
    public const KEY_LOG_VERBOSITY = 'log_verbosity';

    public const DEFAULT_DEBUG_MODE = DebugMode::OFF;
    public const DEFAULT_CACHE_REBUILD = false;
    public const DEFAULT_LOG_VERBOSITY = LogVerbosity::MINIMAL;

    /** @var int */
    private $debugMode;

    /**
     * Enabling this causes the OpenAPI spec cache (used in request validation)
     * to rebuild, which is useful for development purposes.
     * @var bool
     */
    private $cacheRebuild;

    /** @var int */
    private $logVerbosity;

    public function __construct(int $debugMode, bool $cacheRebuild, int $logVerbosity)
    {
        $this->debugMode = $debugMode;
        $this->cacheRebuild = $cacheRebuild;
        $this->logVerbosity = $logVerbosity;
    }

    public function getDebugMode(): int
    {
        return $this->debugMode;
    }

    public function hasDebugOption(int $debugOption): bool
    {
        return ($this->debugMode & $debugOption) !== 0;
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
