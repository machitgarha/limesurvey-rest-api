<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use LogicException;

use Symfony\Component\HttpFoundation\Request;

trait RequestProperty
{
    /** @var Request|null */
    private $request = null;

    public function getRequest(): Request
    {
        if ($this->request === null) {
            throw new LogicException('Request is not set');
        }
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
