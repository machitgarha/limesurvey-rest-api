<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class BadRequestError extends Error
{
    public function getHttpStatusCode(): int
    {
        return Response::HTTP_BAD_REQUEST;
    }
}
