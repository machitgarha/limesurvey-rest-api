<?php

namespace MAChitgarha\LimeSurveyRestApi\Utility;

use MAChitgarha\LimeSurveyRestApi\Error\UnsupportedMediaTypeError;

use Symfony\Component\HttpFoundation\Request;

class ContentTypeValidator
{
    public static function validateIsJson(Request $request): void
    {
        if ($request->getContentType() !== ContentType::JSON) {
            throw new UnsupportedMediaTypeError();
        }
    }
}
