<?php

namespace MAChitgarha\LimeSurveyRestApi\Api;

use Throwable;

use League\OpenAPIValidation\PSR7\Exception\NoContentType;

use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidBody;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidPath;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidHeaders;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidSecurity;
use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidParameter;

use League\OpenAPIValidation\Schema\Exception\TypeMismatch;
use League\OpenAPIValidation\Schema\Exception\KeywordMismatch;

use MAChitgarha\LimeSurveyRestApi\Error\DebugError;

use MAChitgarha\LimeSurveyRestApi\Plugin;

use MAChitgarha\LimeSurveyRestApi\Error\Error;
use MAChitgarha\LimeSurveyRestApi\Error\ErrorBucket;
use MAChitgarha\LimeSurveyRestApi\Error\InternalServerError;
use MAChitgarha\LimeSurveyRestApi\Error\InvalidPathParameterError;
use MAChitgarha\LimeSurveyRestApi\Error\InvalidSecurityError;
use MAChitgarha\LimeSurveyRestApi\Error\KeywordMismatchError;
use MAChitgarha\LimeSurveyRestApi\Error\MalformedRequestBodyError;
use MAChitgarha\LimeSurveyRestApi\Error\MethodNotAllowedError;
use MAChitgarha\LimeSurveyRestApi\Error\PathNotFoundError;
use MAChitgarha\LimeSurveyRestApi\Error\TypeMismatchError;
use MAChitgarha\LimeSurveyRestApi\Error\UnsupportedMediaTypeError;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

use Respect\Validation\Exceptions\ValidationException;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;


use function MAChitgarha\LimeSurveyRestApi\Helper\addThrowableAsDebugMessageToJsonResponse;

use function MAChitgarha\LimeSurveyRestApi\Utility\Response\{error, errors};

class JsonErrorResponseGenerator
{
    /** @var Plugin */
    private $plugin;

    public function __construct(Plugin $plugin)
    {
        $this->plugin = $plugin;
    }

    public function generate(Throwable $throwable): JsonResponse
    {
        $this->plugin->logThrowable($throwable);

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
            $extraHeaders['Allow'] = \implode(', ', $throwable->getAllowedMethods());
        }
        elseif ($throwable instanceof InvalidBody) {
            $error = self::convertInvalidBodyToError($throwable);
        }
        elseif ($throwable instanceof InvalidHeaders) {
            $error = self::convertInvalidHeadersToError($throwable);
        }
        elseif ($throwable instanceof InvalidPath) {
            $error = self::convertInvalidPathToError($throwable);
        }
        elseif ($throwable instanceof InvalidSecurity) {
            $error = new InvalidSecurityError();
        }
        elseif ($throwable instanceof NoContentType) {
            $error = new UnsupportedMediaTypeError();
        }
        elseif ($throwable instanceof ValidationException) {
            $error = self::convertValidationExceptionToError($throwable);
        }
        else {
            $error = self::makeInternalServerError($throwable);
        }

        $response = $error instanceof ErrorBucket
            ? self::generateForErrorBucket($error)
            : self::generateForError($error);

        if ($error instanceof InternalServerError) {
            addThrowableAsDebugMessageToJsonResponse($response, $throwable);
        }

        foreach ($extraHeaders as $headerName => $headerValue) {
            $response->headers->set($headerName, $headerValue);
        }

        return $response;
    }

    private function convertInvalidBodyToError(InvalidBody $exception): Error
    {
        $message = $exception->getMessage();
        $previous = $exception->getPrevious();

        if (\str_contains($message, 'Syntax error')) {
            return new MalformedRequestBodyError();
        }

        if ($previous instanceof TypeMismatch) {
            return new TypeMismatchError(
                $previous->getMessage()
            );
        }

        if ($previous instanceof KeywordMismatch) {
            // Remove the 'Keyword validation failed: ' section
            $previousMessage = $previous->getMessage();
            if (\str_contains($previousMessage, ': ')) {
                return new KeywordMismatchError(
                    \explode(': ', $previousMessage, 2)[1]
                );
            }
        }

        return $this->makeInternalServerError($exception);
    }

    private function convertInvalidHeadersToError(InvalidHeaders $exception): Error
    {
        if (\str_contains($exception->getMessage(), 'Content-Type')) {
            return new UnsupportedMediaTypeError();
        }

        return $this->makeInternalServerError($exception);
    }

    private function convertInvalidPathToError(InvalidPath $exception): Error
    {
        $previous = $exception->getPrevious();
        $previous2nd = $previous ? $previous->getPrevious() : null;

        if (
            $previous instanceof InvalidParameter &&
            $previous2nd instanceof TypeMismatch
        ) {
            return new InvalidPathParameterError(
                "{$previous->getMessage()}. {$previous2nd->getMessage()}"
            );
        }

        return $this->makeInternalServerError($exception);
    }

    private static function convertValidationExceptionToError(ValidationException $exception): Error
    {
        return new KeywordMismatchError($exception->getMessage());
    }

    private static function makeInternalServerError(Throwable $throwable): InternalServerError
    {
        return new InternalServerError();
    }

    private static function generateDataForError(Error $error): array
    {
        $errorData = ['id' => $error->getId()];

        if (!empty($error->getMessage())) {
            $errorData['message'] = $error->getMessage();
        }

        $errorData += $error->getExtraParams();

        return $errorData;
    }

    private static function generateForError(Error $error): JsonResponse
    {
        return new JsonResponse(
            error(self::generateDataForError($error)),
            $error->getHttpStatusCode(),
            $error->getHeaders()
        );
    }

    private static function generateForErrorBucket(ErrorBucket $errorBucket): JsonResponse
    {
        $headers = $errorBucket->getHeaders();
        $errorDataList = [];

        foreach ($errorBucket->getItems() as $error) {
            $errorDataList[] = self::generateDataForError($error);
            $headers += $error->getHeaders();
        }

        return new JsonResponse(
            errors($errorDataList),
            $errorBucket->getHttpStatusCode(),
            $headers
        );
    }
}
