<?php

namespace MAChitgarha\LimeSurveyRestApi\Routing;

use MAChitgarha\LimeSurveyRestApi\Api\Config;

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

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * Returns pair of controller name and its method name to handle the request.
     *
     * @return string[] The pair of controller's fully-qualified name and its method name.
     */
    public function route(): array
    {
        $routeCollection = new RouteCollection();

        [$apiVersion, $path] = self::splitPathApiVersion($this->request->getPathInfo());

        foreach ($this->getRoutesForApiVersion($apiVersion) as $route) {
            $routeCollection->add(
                $route['name'],
                self::makeRoute($route)
            );
        }

        $matcher = new UrlMatcher($routeCollection, $this->getContext());
        $params = $matcher->match($path);

        return self::getControllerMethodPair($params['_route']);
    }

    /**
     * Splits a path into its API version and the remaining part.
     *
     * @return string[] A pair of API version (without replacing 'v' prefix, e.g. 'v0') and
     * the rest of the path (i.e. the path relative to the version).
     */
    private static function splitPathApiVersion(string $path)
    {
        $pattern = '/^\/(v\d+)(\/.*)?$/';
        $path = \str_replace('/' . Config::PATH_PREFIX, '', $path);

        if (!\preg_match($pattern, $path, $matches)) {
            throw new ApiVersionMissingError();
        }

        return [
            $matches[1],
            $matches[2] ?? '/',
        ];
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
            ->setMethod($this->request->getMethod());
    }

    private static function getControllerMethodPair(string $routeName): array
    {
        return \explode(Routes::ROUTE_NAME_DELIMITER, $routeName);
    }
}
