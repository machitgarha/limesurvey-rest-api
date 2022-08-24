<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\EmptyResponse;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Serializer\Encoder\JsonDecode;

class DebugError extends InternalServerError
{
    public static function buildInvalidResponse(Response $response): self
    {
        $result = new self('The resulting response might be invalid (againts the spec)');

        if ($response instanceof JsonResponse) {
            $result->extraParams['response'] = (new JsonDecode())
                ->decode($response->getContent(), '', [
                    JsonDecode::ASSOCIATIVE => true
                ]);
        }
        if ($response instanceof EmptyResponse) {
            $result->extraParams['response'] = $response->getContent();
        }

        return $result;
    }

    public function getId(): string
    {
        return Error::getId();
    }
}
