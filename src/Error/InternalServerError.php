<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class InternalServerError extends Error
{
    public function getId(): string
    {
        return __CLASS__;
    }

    public function getHttpStatusCode(): int
    {
        return Response::HTTP_INTERNAL_SERVER_ERROR;
    }
}
