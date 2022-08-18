<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

use LogicException;
use BadFunctionCallException;
use InvalidArgumentException;

class ErrorBucket extends Error
{
    /** @var Error[] */
    private $bucket = [];

    /** @var int */
    private $statusCode = null;

    public function addItem(Error $error): self
    {
        $this->bucket[] = $error;

        if ($this->statusCode === null) {
            $this->statusCode = $error->getHttpStatusCode();
        } else {
            if ($error->getHttpStatusCode() !== $this->statusCode) {
                throw new InvalidArgumentException(
                    'All errors must have the same HTTP status code'
                );
            }
        }

        return $this;
    }

    public function getItems(): array
    {
        return $this->bucket;
    }

    public function isEmpty(): bool
    {
        return empty($this->bucket);
    }

    public function getHttpStatusCode(): int
    {
        if ($this->statusCode === null) {
            throw new LogicException(
                'Cannot get HTTP status code of an empty bucket'
            );
        }

        return $this->statusCode;
    }

    public function getId(): string
    {
        throw new BadFunctionCallException();
    }
}
