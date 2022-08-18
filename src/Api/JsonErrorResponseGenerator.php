<?php

namespace MAChitgarha\LimeSurveyRestApi\Api;

use Throwable;

use MAChitgarha\LimeSurveyRestApi\Error\Error;
use MAChitgarha\LimeSurveyRestApi\Error\InvalidValueError;
use MAChitgarha\LimeSurveyRestApi\Error\PathNotFoundError;
use MAChitgarha\LimeSurveyRestApi\Error\TypeMismatchError;
use MAChitgarha\LimeSurveyRestApi\Error\InternalServerError;
use MAChitgarha\LimeSurveyRestApi\Error\MethodNotAllowedError;
use MAChitgarha\LimeSurveyRestApi\Error\RequiredKeyMissingError;
use MAChitgarha\LimeSurveyRestApi\Error\MalformedRequestBodyError;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

use Respect\Validation\Exceptions\KeyException;
use Respect\Validation\Exceptions\IntTypeException;
use Respect\Validation\Exceptions\BoolTypeException;
use Respect\Validation\Exceptions\NullTypeException;
use Respect\Validation\Exceptions\ArrayTypeException;
use Respect\Validation\Exceptions\FloatTypeException;
use Respect\Validation\Exceptions\StringTypeException;
use Respect\Validation\Exceptions\ValidationException;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

use Symfony\Component\Serializer\Exception\NotEncodableValueException;

class JsonErrorResponseGenerator
{
    public function __construct()
    {
    }

    public function generate(Throwable $throwable): Response
    {
        $extraHeaders = [];

        // To make elseif blocks look aligned
        if (false) {
            // Nothing
        }
        elseif ($throwable instanceof Error) {
            $error = $throwable;
        }
        elseif ($throwable instanceof ResourceNotFoundException) {
            $error = new PathNotFoundError();
        }
        elseif ($throwable instanceof MethodNotAllowedException) {
            $error = new MethodNotAllowedError();
            $extraHeaders[] = ['Allow' => \implode(', ', $throwable->getAllowedMethods())];
        }
        elseif ($throwable instanceof NotEncodableValueException) {
            $error = new MalformedRequestBodyError();
        }
        elseif ($throwable instanceof KeyException) {
            $error = new RequiredKeyMissingError($throwable->getMessage());
        }
        elseif ($throwable instanceof ValidationException) {
            $error = self::convertValidationExceptionToError($throwable);
        }
        // For the sake of being similar to try/catch blocks
        elseif ($throwable instanceof Throwable) {
            $error = new InternalServerError();
            $this->logThrowable($error);
        }

        $response = $this->generateForError($error);
        foreach ($extraHeaders as $headerName => $headerValue) {
            $response->headers->set($headerName, $headerValue);
        }

        return $response;
    }

    private static function convertValidationExceptionToError(ValidationException $exception): Error
    {
        if (\in_array(
            \get_class($exception),
            [
                ArrayTypeException::class,
                BoolTypeException::class,
                FloatTypeException::class,
                IntTypeException::class,
                NullTypeException::class,
                StringTypeException::class,
            ]
        )) {
            return new TypeMismatchError($exception->getMessage());
        }

        return new InvalidValueError($exception->getMessage());
    }

    private function generateForError(Error $error): JsonResponse
    {
        $errorData = ['id' => $error->getId()];

        if (!empty($error->getMessage())) {
            $errorData['message'] = $error->getMessage();
        }

        return new JsonResponse(
            error($errorData),
            $error->getHttpStatusCode()
        );
    }

    private function logThrowable(Throwable $error): void
    {
        $message = Config::DEBUG_MODE === DebugMode::FULL
            ? $error->__toString()
            : $error->getMessage();

        $this->log($message, Logger::LEVEL_ERROR);
    }
}
