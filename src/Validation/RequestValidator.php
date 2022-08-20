<?php

namespace MAChitgarha\LimeSurveyRestApi\Validation;

use MAChitgarha\LimeSurveyRestApi\Routing\PathInfo;

use Nyholm\Psr7\Factory\Psr17Factory;

use Psr\Http\Message\RequestInterface as PsrRequest;

use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class RequestValidator
{
    /** @var PsrRequest */
    private $request;

    /** @var PathInfo */
    private $pathInfo;

    /** @var ValidatorBuilder */
    private $validatorBuilder;

    public function __construct(SymfonyRequest $request, PathInfo $pathInfo, ValidatorBuilder $validatorBuilder)
    {
        $this->request = self::makePsrRequest($request);
        $this->pathInfo = $pathInfo;
        $this->validatorBuilder = $validatorBuilder;
    }

    private static function makePsrRequest(SymfonyRequest $request): PsrRequest
    {
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        return $psrHttpFactory->createRequest($request);
    }

    public function validate(): void
    {
        $validator = $this->validatorBuilder->getServerRequestValidator();

        $validator->validate(
            $this->request->withUri($this->request->getUri()->withPath(
                $this->pathInfo->getRoutedPath()
            ))
        );
    }
}
