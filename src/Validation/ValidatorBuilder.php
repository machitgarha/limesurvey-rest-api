<?php

namespace MAChitgarha\LimeSurveyRestApi\Validation;

use League\OpenAPIValidation\PSR7\ValidatorBuilder as LeagueValidatorBuilder;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\ServerRequestValidator;

use MAChitgarha\LimeSurveyRestApi\Config;

use Psr\Cache\CacheItemPoolInterface;

class ValidatorBuilder extends LeagueValidatorBuilder
{
    private const OPENAPI_SPEC_FILE_PATH = __DIR__ . '/../../spec/openapi.yaml';

    public function __construct(CacheItemPoolInterface $cachePool)
    {
        $this
            ->fromYamlFile(self::OPENAPI_SPEC_FILE_PATH)
            ->setCache($cachePool);
    }

    private function clearCacheIfNeeded(): void
    {
        if (Config::CACHE_REBUILD) {
            $this->cache->clear();
        }
    }

    public function getServerRequestValidator(): ServerRequestValidator
    {
        $this->clearCacheIfNeeded();
        return parent::getServerRequestValidator();
    }

    public function getResponseValidator(): ResponseValidator
    {
        $this->clearCacheIfNeeded();
        return parent::getResponseValidator();
    }
}
