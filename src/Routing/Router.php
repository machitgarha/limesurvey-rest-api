<?php

namespace MAChitgarha\LimeSurveyRestApi\Routing;

use LSHttpRequest;

use MAChitgarha\LimeSurveyRestApi\Api\Config;

use Symfony\Component\Routing\Exception\NoConfigurationException;

use Symfony\Component\Routing\Matcher\UrlMatcher;

use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;

class Router
{
    /** @var LSHttpRequest */
    private $request;

    public function __construct(LSHttpRequest $request)
    {
        $this->request = $request;
    }

    /**
     * Returns pair of controller name and its method name to handle the request.
     *
     * @param string $path Must start with a forward slash, followed by
     * the API version.
     * @return string[] The pair of controller's fully-qualified name and its method name.
     */
    public function route(string $path): array
    {
        $routeCollection = new RouteCollection();

        [$apiVersion, $path] = self::splitPathApiVersion($path);

        foreach (Routes::VALUE[$apiVersion] as $route) {
            $routeCollection->add(
                $route['name'],
                self::makeRoute($route)
            );
        }

        $matcher = new UrlMatcher($routeCollection, $this->getContext());

        try {
            $params = $matcher->match($path);
        } catch (NoConfigurationException $e) {
            // TODO: Make this a 404 status code
            throw new \Exception();
        }

        return self::getControllerMethodPair($params['_route']);
    }

    /**
     * Splits a path into its API version and the remaining part.
     *
     * @return string[] A pair of API version (without replacing 'v' prefix, e.g. 'v0') and
     * the rest of the path as string.
     */
    private static function splitPathApiVersion(string $path)
    {
        $pattern = '/^\/' . Config::PATH_PREFIX . '\/(v\d+)(\/.+)$/';

        if (!\preg_match($pattern, $path, $matches)) {
            // TODO: Make this a 404 error code
            throw new \Exception();
        }

        return [
            $matches[1],
            $matches[2],
        ];
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
            ->setMethod($this->request->getRequestType());
    }

    private static function getControllerMethodPair(string $routeName): array
    {
        return \explode(Routes::ROUTE_NAME_DELIMITER, $routeName);
    }
}