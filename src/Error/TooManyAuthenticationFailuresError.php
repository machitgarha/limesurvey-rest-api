<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class TooManyAuthenticationFailuresError extends Error
{
    public function getId(): string
    {
        return 'too_many_authentication_failures';
    }

    public function getHttpStatusCode(): int
    {
        return Response::HTTP_TOO_MANY_REQUESTS;
    }
}
