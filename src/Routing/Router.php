<?php

namespace MAChitgarha\LimeSurveyRestApi\Routing;

use MAChitgarha\LimeSurveyRestApi\Config;

use MAChitgarha\LimeSurveyRestApi\Error\ApiVersionMissingError;
use MAChitgarha\LimeSurveyRestApi\Error\ApiVersionNotFoundError;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Routing\Matcher\UrlMatcher;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class Router
{
    /** @var Request */
    private $request;

    /** @var PathInfo */
    private $pathInfo;

    public function __construct(Request $request, PathInfo $pathInfo)
    {
        $this->request = $request;
        $this->pathInfo = $pathInfo;
    }

    /**
     * Returns pair of controller name and its method name to handle the request.
     *
     * @return array{0:array{0:string,1:string},1:array} A pair of the handler function
     * information, and the parameters extracted from the path (e.g. /survey/{survey_id}
     * transforms to the key 'survey_id'). The first element of the pair is another pair of the
     * controller's fully-qualified name and its method name.
     */
    public function route(): array
    {
        $routeCollection = new RouteCollection();
        $apiVersion = $this->pathInfo->getApiVersion();

        foreach ($this->getRoutesForApiVersion($apiVersion) as $route) {
            $routeCollection->add(
                $route['name'],
                self::makeRoute($route)
            );
        }

        $matcher = new UrlMatcher($routeCollection, $this->getContext());
        $params = $matcher->match($this->pathInfo->getRoutingPath());

        return [self::getControllerMethodPair($params['_route']), $params];
    }

    private static function getRoutesForApiVersion(string $version): array
    {
        if (!\array_key_exists($version, Routes::VALUE)) {
            throw new ApiVersionNotFoundError();
        }

        return Routes::VALUE[$version];
    }

    /**
     * Make a Route class from route data.
     *
     * @return Route
     */
    private static function makeRoute(array $route): Route
    {
        return new Route(
            $route['path'],
            [],
            $route['requirements'] ?? [],
            [],
            null,
            [],
            $route['http_method']
        );
    }

    private function getContext(): RequestContext
    {
        return (new RequestContext())
            ->fromRequest($this->request);
    }

    private static function getControllerMethodPair(string $routeName): array
    {
        return \explode(Routes::ROUTE_NAME_DELIMITER, $routeName);
    }
}
