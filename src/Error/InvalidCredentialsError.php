<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class InvalidCredentialsError extends Error
{
    public function getId(): string
    {
        return 'invalid_credentails';
    }

    public function getCode(): int
    {
        return Response::HTTP_UNAUTHORIZED;
    }
}
