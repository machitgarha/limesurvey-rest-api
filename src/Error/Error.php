<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

abstract class Error extends \Exception
{
    abstract public function getId(): string;
}
