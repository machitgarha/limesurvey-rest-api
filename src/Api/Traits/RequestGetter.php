<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Api\ControllerDependencyContainer;

use Symfony\Component\HttpFoundation\Request;

trait RequestGetter
{
    abstract public function getContainer(): ControllerDependencyContainer;

    public function getRequest(): Request
    {
        return $this->getContainer()->getRequest();
    }
}
