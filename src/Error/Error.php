<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Throwable;

abstract class Error extends \Exception
{
    protected const DEFAULT_MESSAGE = null;

    abstract public function getHttpStatusCode(): int;

    public function __construct(string $message = null, int $code = 0, ?Throwable $previous = null)
    {
        parent::__construct($message ?? self::DEFAULT_MESSAGE ?? '', $code, $previous);
    }

    public function getId(): string
    {
        // @phan-suppress-next-line PhanParamSuspiciousOrder
        return \str_replace([__NAMESPACE__ . '\\', 'Error'], '', static::class);
    }
}
