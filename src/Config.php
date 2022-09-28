<?php

namespace MAChitgarha\LimeSurveyRestApi;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugMode;
use MAChitgarha\LimeSurveyRestApi\Utility\LogVerbosity;

class Config
{
    public const SETTINGS = [
        'development_section' => [
            'type' => 'info',
            'label' => '<h2>Development</h2>',
        ],
        self::KEY_DEBUG_MODE => [
            'type' => 'int',
            'label' => 'Debug mode',
            'help' => <<<HTML
            Possible values:
            <ul>
               <li>0: Disable (default)</li>
               <li>-1: Full</li>
            </ul>
            Setting this to full (-1) causes the plugin to:
            <ol>
                <li>log all errors, including PHP notices and warnings,</li>
                <li>validate response errors based on the spec (1),</li>
                <li>include errors in the responses in a 'debug' field (2).</li>
            </ol>
            To enable only a limited set of the options above, use the number in the parentheses (they can also be
            bitwise-xor-ed).
            <br/>
            Note that, by default, all errors are logged.
HTML,
            'default' => self::DEFAULT_DEBUG_MODE,
        ],
        self::KEY_CACHE_REBUILD => [
            'type' => 'checkbox',
            'label' => 'Disable cache',
            'help' => <<<HTML
            Clears the spec cache every time, meaning no cache will be used. Useful when the spec is being updated 
            frequently (e.g. via Docker or rsync). Negatively impacts performance.
HTML,
            'default' => self::DEFAULT_CACHE_REBUILD,
        ],
        self::KEY_LOG_VERBOSITY => [
            'type' => 'int',
            'label' => 'Log verbosity',
            'help' => <<<HTML
            Possible values:
            <ul>
                <li>1: Minimal (default)</li>
                <li>-1: Full</li>
            </ul>
HTML,
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
        return (int)($this->getter)(self::KEY_DEBUG_MODE, null, null, self::DEFAULT_DEBUG_MODE);
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
        return (int)($this->getter)(self::KEY_LOG_VERBOSITY, null, null, self::DEFAULT_LOG_VERBOSITY);
    }
}
