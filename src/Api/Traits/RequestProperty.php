<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use LSHttpRequest;

trait RequestProperty
{
    /** @var LSHttpRequest */
    private $request;

    public function getRequest(): LSHttpRequest
    {
        return $this->request;
    }

    /**
     * @return $this
     */
    public function setRequest(LSHttpRequest $request)
    {
        $this->request = $request;
        return $this;
    }
}
