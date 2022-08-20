<?php

namespace MAChitgarha\LimeSurveyRestApi\Validation;

use League\OpenAPIValidation\PSR7\OperationAddress;
use League\OpenAPIValidation\PSR7\ValidatorBuilder;

use League\Uri\Uri;

use MAChitgarha\LimeSurveyRestApi\Config;

use MAChitgarha\LimeSurveyRestApi\Routing\PathInfo;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugMode;

use Nyholm\Psr7\Factory\Psr17Factory;

use Psr\Cache\CacheItemPoolInterface;

use Psr\Http\Message\RequestInterface as PsrRequest;

use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;

use Symfony\Component\HttpFoundation\Request as SymfonyRequest;

class RequestValidator
{
    private const OPENAPI_SPEC_FILE_PATH = __DIR__ . '/../../spec/openapi.yaml';

    /** @var PsrRequest */
    private $request;

    /** @var PathInfo */
    private $pathInfo;

    /** @var CacheItemPoolInterface */
    private $cachePool;

    public function __construct(SymfonyRequest $request, PathInfo $pathInfo, CacheItemPoolInterface $cachePool)
    {
        $this->request = self::makePsrRequest($request);
        $this->pathInfo = $pathInfo;
        $this->cachePool = $cachePool;
    }

    private static function makePsrRequest(SymfonyRequest $request): PsrRequest
    {
        $psr17Factory = new Psr17Factory();
        $psrHttpFactory = new PsrHttpFactory($psr17Factory, $psr17Factory, $psr17Factory, $psr17Factory);

        return $psrHttpFactory->createRequest($request);
    }

    public function validate(): void
    {
        if (Config::CACHE_REBUILD) {
            $this->cachePool->clear();
        }

        $validatorBuilder = (new ValidatorBuilder())
            ->fromYamlFile(self::OPENAPI_SPEC_FILE_PATH)
            ->setCache($this->cachePool);

        $validator = $validatorBuilder->getServerRequestValidator();

        $validator->validate(
            $this->request->withUri(
                $this->request->getUri()->withPath($this->pathInfo->getRoutedPath())
            )
        );
    }
}
