<?php

namespace MAChitgarha\LimeSurveyRestApi\Routing;

use MAChitgarha\LimeSurveyRestApi\Error\ApiVersionNotFoundError;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Routing\Matcher\UrlMatcher;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class Router
{
    /** @var PathInfo */
    private $pathInfo;

    public static function shouldWeHandle(string $pathInfoValue): bool
    {
        return \str_starts_with($pathInfoValue, '/' . PathInfo::PREFIX);
    }

    public function __construct(PathInfo $pathInfo)
    {
        $this->pathInfo = $pathInfo;
    }

    public function getPathInfo(): PathInfo
    {
        return $this->pathInfo;
    }

    /**
     * Returns a pair of controller name and its method name to handle the request.
     *
     * @return array{0:array{0:string,1:string},1:array} A pair of the handler function
     * information, and the parameters extracted from the path (e.g. /survey/{survey_id}
     * transforms to the key 'survey_id'). The first element of the pair is another pair of the
     * controller's fully-qualified name and its method name.
     */
    public function route(Request $request): array
    {
        $routeCollection = new RouteCollection();

        $apiVersion = $this->pathInfo->getApiVersion();
        $routes = $this->getRoutesForApiVersion($apiVersion);

        foreach ($routes as $key => $route) {
            $routeCollection->add(
                (string) $key,
                self::makeRoute($route)
            );
        }

        $matcher = new UrlMatcher(
            $routeCollection,
            (new RequestContext())->fromRequest($request)
        );
        $params = $matcher->match($this->pathInfo->getRoutedPath());

        $route = $routes[(int) $params['_route']];

        return [self::getControllerMethodPair($route['name']), $params];
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
        return new Route($route['path'], [], [], [], null, [], $route['http_method']);
    }

    private static function getControllerMethodPair(string $routeName): array
    {
        return \explode(Routes::ROUTE_NAME_DELIMITER, $routeName);
    }
}
