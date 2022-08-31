<?php

namespace MAChitgarha\LimeSurveyRestApi\Helper;

use Throwable;

use MAChitgarha\LimeSurveyRestApi\Config;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugOption;
use MAChitgarha\LimeSurveyRestApi\Utility\LogVerbosity;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

use Symfony\Component\Serializer\Encoder\JsonDecode;

function jsonDecode(string $json)
{
    return (new JsonDecode())->decode($json, '', [
        JsonDecode::ASSOCIATIVE => true,
    ]);
}

function convertThrowableToLogMessage(Throwable $throwable): string
{
    return (Config::getInstance()->getLogVerbosity() === LogVerbosity::FULL)
        ? $throwable->__toString()
        : $throwable->getMessage();
}

/**
 * Pushes a debug message to a JsonResponse, if the related config is enabled.
 */
function addDebugMessageToJsonResponse(JsonResponse $jsonResponse, string $debugMessage): void
{
    if (!Config::getInstance()->hasDebugOption(DebugOption::IN_RESPONSE)) {
        return;
    }

    $data = jsonDecode($jsonResponse->getContent());

    $data['debug'] = $data['debug'] ?? [];
    $data['debug'][] = $debugMessage;

    $jsonResponse->setData($data);
}

function addThrowableAsDebugMessageToJsonResponse(JsonResponse $jsonResponse, Throwable $throwable): void
{
    addDebugMessageToJsonResponse($jsonResponse, convertThrowableToLogMessage($throwable));
}
