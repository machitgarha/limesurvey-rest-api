<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class NotFoundError extends Error
{
    public function getHttpStatusCode(): int
    {
        return Response::HTTP_NOT_FOUND;
    }
}
