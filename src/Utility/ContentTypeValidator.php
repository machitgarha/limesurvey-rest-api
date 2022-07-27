<?php

namespace MAChitgarha\LimeSurveyRestApi\Utility;

use MAChitgarha\LimeSurveyRestApi\Error\UnsupportedMediaTypeError;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentTypeValidator
{
    public static function validateIsJson(Request $request): void
    {
        if ($request->getContentType() !== ContentType::APPLICATION_JSON) {
            throw new UnsupportedMediaTypeError();
        }
    }
}
