<?php

namespace MAChitgarha\LimeSurveyRestApi;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugMode;
use MAChitgarha\LimeSurveyRestApi\Utility\LogVerbosity;

class Config
{
    public const SETTINGS = [
        self::KEY_DEBUG_MODE => [
            'type' => 'int',
            'label' => 'Debug mode',
            'help' => '0: Disable (default), -1: Full',
            'default' => self::DEFAULT_DEBUG_MODE,
        ],
        self::KEY_CACHE_REBUILD => [
            'type' => 'checkbox',
            'label' => 'Disable cache',
            'help' => 'Negatively impacts performance, but useful for development purposes',
            'default' => self::DEFAULT_CACHE_REBUILD,
        ],
        self::KEY_LOG_VERBOSITY => [
            'type' => 'int',
            'label' => 'How verbose the logs and debug messages should be',
            'help' => '1: Minimal (default), -1: Full',
            'default' => self::DEFAULT_LOG_VERBOSITY,
        ],
    ];

    public const KEY_DEBUG_MODE = 'debug_mode';
    public const KEY_CACHE_REBUILD = 'cache_rebuild';
    public const KEY_LOG_VERBOSITY = 'log_verbosity';

    public const DEFAULT_DEBUG_MODE = DebugMode::OFF;
    public const DEFAULT_CACHE_REBUILD = false;
    public const DEFAULT_LOG_VERBOSITY = LogVerbosity::MINIMAL;

    /** @var callable(string|null, string|null, int|null, mixed): mixed */
    private $getter;

    public function __construct(callable $getter)
    {
        $this->getter = $getter;
    }

    public function getDebugMode(): int
    {
        return (int) ($this->getter)(self::KEY_DEBUG_MODE, null, null, self::DEFAULT_DEBUG_MODE);
    }

    public function hasDebugOption(int $debugOption): bool
    {
        return ($this->getDebugMode() & $debugOption) !== 0;
    }

    /**
     * Enabling this causes the OpenAPI spec cache (used in request validation)
     * to rebuild, which is useful for development purposes.
     */
    public function getCacheRebuild(): bool
    {
        return ($this->getter)(self::KEY_CACHE_REBUILD, null, null, self::DEFAULT_CACHE_REBUILD);
    }

    public function getLogVerbosity(): int
    {
        return (int) ($this->getter)(self::KEY_LOG_VERBOSITY, null, null, self::DEFAULT_LOG_VERBOSITY);
    }
}
