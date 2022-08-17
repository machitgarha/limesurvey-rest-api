<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class ServiceUnavailableError extends Error
{
    public function getHttpStatusCode(): int
    {
        return Response::HTTP_SERVICE_UNAVAILABLE;
    }
}
