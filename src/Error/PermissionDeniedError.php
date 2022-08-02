<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class PermissionDeniedError extends Error
{
    public function getHttpStatusCode(): int
    {
        return Response::HTTP_FORBIDDEN;
    }
}
