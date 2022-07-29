<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

abstract class Error extends \Exception
{
    protected const DEFAULT_MESSAGE = null;

    abstract public function getHttpStatusCode(): int;

    public function __construct(string $message = null, ...$args)
    {
        parent::__construct($message ?? self::DEFAULT_MESSAGE ?? '', ...$args);
    }

    public function getId(): string
    {
        return \str_replace([__NAMESPACE__ . '\\', 'Error'], '', static::class);
    }
}
