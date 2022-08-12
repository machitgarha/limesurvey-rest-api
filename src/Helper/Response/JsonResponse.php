<?php

namespace MAChitgarha\LimeSurveyRestApi\Helper\Response;

use \Symfony\Component\HttpFoundation\JsonResponse as SymfonyJsonResponse;

class JsonResponse extends SymfonyJsonResponse
{
    protected $encodingOptions = JSON_UNESCAPED_UNICODE;
}
