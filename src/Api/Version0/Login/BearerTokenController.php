<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Login;

use MAChitgarha\LimeSurveyRestApi\Api\Traits\RequestProperty;

use MAChitgarha\LimeSurveyRestApi\Utility\ContentTypeValidator;

class BearerTokenController
{
    use RequestProperty;

    public const PATH = '/login/bearer_token';

    public function new(): string
    {
        ContentTypeValidator::validateIsJson($this->getRequest());

        return '{}';
    }

    public function delete(): string
    {
        ContentTypeValidator::validateIsJson($this->getRequest());

        return '{}';
    }
}
