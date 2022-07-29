<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use LogicException;

use MAChitgarha\LimeSurveyRestApi\Authorization\Authorizer;

trait AuthorizerProperty
{
    /** @var Authorizer|null */
    private $authorizer = null;

    public function getAuthorizer(): Authorizer
    {
        if ($this->authorizer === null) {
            throw new LogicException('Authorizer is not set');
        }
        return $this->authorizer;
    }

    /**
     * @return $this
     */
    public function setAuthorizer(Authorizer $authorizer)
    {
        $this->authorizer = $authorizer;
        return $this;
    }
}
