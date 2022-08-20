<?php

namespace MAChitgarha\LimeSurveyRestApi;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugMode;

class Config
{
    /** @var string */
    public const DEBUG_MODE = DebugMode::OFF;

    /** @var bool Whether to rebuild the cache or not (e.g. for validation). Useful for development. */
    public const CACHE_REBUILD = false;
}
