<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use Throwable;

abstract class Error extends \Exception
{
    /** @var null|string */
    protected const DEFAULT_MESSAGE = null;

    /** @var mixed[] */
    protected $extraParams = [];

    /** @var string[] */
    protected $headers = [];

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

    public function getExtraParams(): array
    {
        return $this->extraParams;
    }

    public function addHeader(string $name, string $value): void
    {
        $this->headers[$name] = $value;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }
}
