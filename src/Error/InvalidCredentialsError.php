<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class InvalidCredentialsError extends Error
{
    public function getHttpStatusCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
