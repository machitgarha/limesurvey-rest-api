<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Api\ControllerDependencyContainer;

use MAChitgarha\LimeSurveyRestApi\Authorization\Authorizer;

trait AuthorizerGetter
{
    abstract public function getContainer(): ControllerDependencyContainer;

    public function getAuthorizer(): Authorizer
    {
        return $this->getContainer()->getAuthorizer();
    }
}