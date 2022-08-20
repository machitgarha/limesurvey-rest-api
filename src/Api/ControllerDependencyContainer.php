<?php

namespace MAChitgarha\LimeSurveyRestApi\Api;

use MAChitgarha\LimeSurveyRestApi\Authorization\Authorizer;

use MAChitgarha\LimeSurveyRestApi\Routing\PathInfo;

use MAChitgarha\LimeSurveyRestApi\Validation\RequestValidator;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Serializer\Serializer;

class ControllerDependencyContainer
{
    /** @var Request */
    private $request;

    /** @var PathInfo */
    private $pathInfo;

    /** @var Serializer */
    private $serializer;

    /** @var Authorizer */
    private $authorizer;

    /** @var RequestValidator */
    private $requestValidator;

    public function __construct(
        Request $request,
        PathInfo $pathInfo,
        Serializer $serializer,
        Authorizer $authorizer,
        RequestValidator $requestValidator
    ) {
        $this->request = $request;
        $this->pathInfo = $pathInfo;
        $this->serializer = $serializer;
        $this->authorizer = $authorizer;
        $this->requestValidator = $requestValidator;
    }

    public function getRequest(): Request
    {
        return $this->request;
    }

    public function getPathInfo(): PathInfo
    {
        return $this->pathInfo;
    }

    public function getSerializer(): Serializer
    {
        return $this->serializer;
    }

    public function getAuthorizer(): Authorizer
    {
        return $this->authorizer;
    }

    public function getRequestValidator(): RequestValidator
    {
        return $this->requestValidator;
    }
}
