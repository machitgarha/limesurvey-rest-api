<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Api\ControllerDependencyContainer;

use MAChitgarha\LimeSurveyRestApi\Authorization\Authorizer;

/**
 * @todo Rename to Authorizer
 */
trait AuthorizerGetter
{
    abstract public function getContainer(): ControllerDependencyContainer;

    public function getAuthorizer(): Authorizer
    {
        return $this->getContainer()->getAuthorizer();
    }

    public function authorize(): Authorizer
    {
        return $this->getContainer()->getAuthorizer()->authorize();
    }
}
