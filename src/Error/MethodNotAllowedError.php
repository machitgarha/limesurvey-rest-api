<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class MethodNotAllowedError extends Error
{
    public function getHttpStatusCode(): int
    {
        return Response::HTTP_METHOD_NOT_ALLOWED;
    }
}
