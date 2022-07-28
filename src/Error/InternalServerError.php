<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class InternalServerError extends Error
{
    public function getId(): string
    {
        return 'internal_server_error';
    }

    public function getCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
