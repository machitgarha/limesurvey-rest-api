<?php

namespace MAChitgarha\LimeSurveyRestApi\Utility;

class DebugOption
{
    /**
     * Adds a 'debug' field in the responses, including debug messages.
     * @var int
     */
    public const IN_RESPONSE = 1;

    /**
     * Validate the response of a request before reaching the user.
     * @var int
     */
    public const VALIDATE_RESPONSE = 2;
}
