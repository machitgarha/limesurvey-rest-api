<?php

namespace MAChitgarha\LimeSurveyRestApi\Api;

use MAChitgarha\LimeSurveyRestApi\Authorization\Authorizer;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Serializer\Serializer;

class ControllerDependencyContainer
{
    /** @var Request */
    private $request;

    /** @var Serializer */
    private $serializer;

    /** @var Authorizer */
    private $authorizer;

    public function __construct(
        Request $request,
        Serializer $serializer,
        Authorizer $authorizer
    ) {
        $this->request = $request;
        $this->serializer = $serializer;
        $this->authorizer = $authorizer;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getSerializer(): Serializer
    {
        return $this->serializer;
    }

    public function getAuthorizer(): Authorizer
    {
        return $this->authorizer;
    }
}
