<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

abstract class Error extends \Exception
{
    abstract public function getHttpStatusCode(): int;

    public function getId(): string
    {
        return \str_replace('Error', '', static::class);
    }
}
