<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class NotImplementedError extends Error
{
    public function getHttpStatusCode(): int
    {
        return Response::HTTP_NOT_IMPLEMENTED;
    }
}
