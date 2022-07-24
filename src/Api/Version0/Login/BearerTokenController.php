<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Login;

use MAChitgarha\LimeSurveyRestApi\Api\Traits\RequestProperty;

class BearerTokenController
{
    use RequestProperty;

    public const PATH = '/login/bearer_token';

    public function new(): string
    {
    }

    public function delete(): string
    {
    }
}
