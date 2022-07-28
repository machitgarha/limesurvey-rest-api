<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

abstract class Error extends \Exception
{
    abstract public function getHttpStatusCode(): int;
    abstract public function getId(): string;
}
