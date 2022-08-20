<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Api\ControllerDependencyContainer;

trait RequestValidator
{
    abstract public function getContainer(): ControllerDependencyContainer;

    public function validateRequest(): void
    {
        $this->getContainer()->getRequestValidator()->validate();
    }
}
