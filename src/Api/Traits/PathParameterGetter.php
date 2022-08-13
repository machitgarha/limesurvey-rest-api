<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use Closure;
use RuntimeException;

use MAChitgarha\LimeSurveyRestApi\Error\InvalidPathParameterError;

use Respect\Validation\Exceptions\IntValException;

use Symfony\Component\HttpFoundation\Request;

use Respect\Validation\Validator as v;

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

    public function getPathParameterAsInt(string $key): int
    {
        $param = $this->getPathParameter($key);

        try {
            v::intVal()->check($param);
        } catch (IntValException $exception) {
            throw new InvalidPathParameterError($key);
        }

        return $param;
    }

    /**
     * @return string[]
     */
    public function getPathParameterList(string ...$keys): array
    {
        return \array_map(
            [$this, 'getPathParameter'],
            $keys
        );
    }

    /**
     * @return int[]
     */
    public function getPathParameterListAsInt(string ...$keys): array
    {
        return \array_map(
            [$this, 'getPathParameterAsInt'],
            $keys
        );
    }
}
