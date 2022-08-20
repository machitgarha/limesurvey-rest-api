<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

class InvalidSecurityError extends UnauthorizedError
{
    public function __construct()
    {
        parent::__construct('Authorization header missing or unsupported');
    }
}
