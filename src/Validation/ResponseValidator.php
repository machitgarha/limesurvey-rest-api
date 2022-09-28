<?php

namespace MAChitgarha\LimeSurveyRestApi\Validation;

use League\OpenAPIValidation\PSR7\OperationAddress;

use MAChitgarha\LimeSurveyRestApi\Routing\PathInfo;

use Nyholm\Psr7\Factory\Psr17Factory;

use Psr\Http\Message\ResponseInterface as PsrResponse;

use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class ResponseValidator
{
    /** @var PsrResponse */
    private $response;

    /** @var PathInfo */
    private $pathInfo;

    /** @var ValidatorBuilder */
    private $validatorBuilder;

    /** @var string */
    private $requestMethod;

    public function __construct(
        SymfonyResponse $response,
        PathInfo $pathInfo,
        ValidatorBuilder $validatorBuilder,
        string $requestMethod
    ) {
        $this->response = self::makePsrResponse($response);
        $this->pathInfo = $pathInfo;
        $this->validatorBuilder = $validatorBuilder;
        $this->requestMethod = $requestMethod;
    }

    private static function makePsrResponse(SymfonyResponse $response): PsrResponse
    {
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        return $psrHttpFactory->createResponse($response);
    }

    public function validate(): void
    {
        $validator = $this->validatorBuilder->getResponseValidator();

        $validator->validate(
            new OperationAddress(
                $this->pathInfo->getRoutedPath(),
                \strtolower($this->requestMethod)
            ),
            $this->response
        );
    }
}
