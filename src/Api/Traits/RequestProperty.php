<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use Symfony\Component\HttpFoundation\Request;

trait RequestProperty
{
    /** @var Request */
    private $request;

    public function getRequest(): Request
    {
        return $this->request;
    }

    /**
     * @return $this
     */
    public function setRequest(Request $request)
    {
        $this->request = $request;
        return $this;
    }
}
