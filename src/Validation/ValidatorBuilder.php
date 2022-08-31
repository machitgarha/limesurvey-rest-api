<?php

namespace MAChitgarha\LimeSurveyRestApi\Validation;

use League\OpenAPIValidation\PSR7\ValidatorBuilder as LeagueValidatorBuilder;
use League\OpenAPIValidation\PSR7\ResponseValidator;
use League\OpenAPIValidation\PSR7\RequestValidator;

use MAChitgarha\LimeSurveyRestApi\Config;

use Psr\Cache\CacheItemPoolInterface;

class ValidatorBuilder extends LeagueValidatorBuilder
{
    private const OPENAPI_SPEC_FILE_PATH = __DIR__ . '/../../spec/openapi.yaml';

    /** @var Config */
    private $config;

    public function __construct(CacheItemPoolInterface $cachePool, Config $config)
    {
        $this->config = $config;

        $this
            ->fromYamlFile(self::OPENAPI_SPEC_FILE_PATH)
            ->setCache($cachePool);
    }

    private function clearCacheIfNeeded(): void
    {
        if ($this->config->getCacheRebuild()) {
            $this->cache->clear();
        }
    }

    public function getRequestValidator(): RequestValidator
    {
        $this->clearCacheIfNeeded();
        return parent::getRequestValidator();
    }

    public function getResponseValidator(): ResponseValidator
    {
        $this->clearCacheIfNeeded();
        return parent::getResponseValidator();
    }
}
