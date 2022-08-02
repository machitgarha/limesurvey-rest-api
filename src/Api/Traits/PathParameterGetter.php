<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use RuntimeException;

use Symfony\Component\HttpFoundation\Request;

trait PathParameterGetter
{
    abstract public function getRequest(): Request;

    public function getPathParameter(string $key): string
    {
        $param = $this->getRequest()->attributes->get($key);

        if ($param !== null) {
            return (string) $param;
        }
        throw new RuntimeException("Cannot get path parameter '$key'");
    }
}
