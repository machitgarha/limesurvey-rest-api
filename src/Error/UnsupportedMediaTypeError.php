<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Symfony\Component\HttpFoundation\Response;

class UnsupportedMediaTypeError extends Error
{
    public function getId(): string
    {
        return 'unsupported_media_type';
    }

    public function getCode(): int
    {
        return Response::HTTP_UNSUPPORTED_MEDIA_TYPE;
    }
}
