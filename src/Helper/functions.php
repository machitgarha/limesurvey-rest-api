<?php

namespace MAChitgarha\LimeSurveyRestApi\Helper;

use Throwable;
use InvalidArgumentException;

use MAChitgarha\LimeSurveyRestApi\Config;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugOption;
use MAChitgarha\LimeSurveyRestApi\Utility\LogVerbosity;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;
use MAChitgarha\LimeSurveyRestApi\Utility\Response\EmptyResponse;

use Symfony\Component\HttpFoundation\Response;

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
function addDebugMessageToResponse(Response $response, string $debugMessage): Response
{
    if (!Config::getInstance()->hasDebugOption(DebugOption::IN_RESPONSE)) {
        return $response;
    }

    if ($response instanceof JsonResponse) {
        $data = jsonDecode($response->getContent());

        $data['debug'] = $data['debug'] ?? [];
        $data['debug'][] = $debugMessage;

        $response->setData($data);
        return $response;
    }

    if ($response instanceof EmptyResponse) {
        return new JsonResponse(
            ['debug' => [$debugMessage]],
            JsonResponse::HTTP_OK,
            $response->headers->all()
        );
    }

    throw new InvalidArgumentException('Unhandled response type: ' . \get_class($response));
}

function addThrowableAsDebugMessageToResponse(Response $response, Throwable $throwable): Response
{
    return addDebugMessageToResponse($response, convertThrowableToLogMessage($throwable));
}
