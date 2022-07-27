<?php

namespace MAChitgarha\LimeSurveyRestApi\Routing;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Login\BearerTokenController;

use MAChitgarha\LimeSurveyRestApi\Utility\HttpMethod;

class Routes
{
    /**
     * @see self::VALUE
     * @var array[]
     */
    public const VERSION_0 = [
        [
            'path' => BearerTokenController::PATH,
            'http_method' => HttpMethod::POST,
            'name' => BearerTokenController::class . '.new',
        ], [
            'path' => BearerTokenController::PATH,
            'http_method' => HttpMethod::DELETE,
            'name' => BearerTokenController::class . '.delete',
        ],
    ];

    /**
     * The routes, indexed by versions.
     *
     * Routes data for each version is held separately, for API versioning management to
     * become easier. Each version, contains an array of routes.
     *
     * Each route is an array containing the about the route, including 'name', 'route' and
     * 'method'. 'path' and 'http_method' together defines the incoming HTTP request.
     *
     * 'name' contains the information of the handler method. It consists of the controller
     * class name and its related method name joined by a dot.
     *
     * For instance, if a request is going to be handled by SomeController::new(), then
     * its route 'name' was 'SomeController.new'.
     *
     * @var array[]
     */
    public const VALUE = [
        'v0' => self::VERSION_0,
    ];

    public const ROUTE_NAME_DELIMITER = '.';
}
