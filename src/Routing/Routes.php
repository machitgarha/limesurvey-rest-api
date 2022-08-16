<?php

namespace MAChitgarha\LimeSurveyRestApi\Routing;

use MAChitgarha\LimeSurveyRestApi\Api\Version0 as V0;

use MAChitgarha\LimeSurveyRestApi\Utility\HttpMethod;

class Routes
{
    /**
     * @see self::VALUE
     * @var array[]
     */
    public const VERSION_0 = [
        [
            'name' => V0\Login\BearerTokenController::class . '.new',
            'path' => V0\Login\BearerTokenController::PATH,
            'http_method' => HttpMethod::POST,
        ], [
            'name' => V0\Login\BearerTokenController::class . '.delete',
            'path' => V0\Login\BearerTokenController::PATH,
            'http_method' => HttpMethod::DELETE,
        ],

        [
            'name' => V0\SurveyController::class . '.list',
            'path' => V0\SurveyController::PATH,
            'http_method' => HttpMethod::GET,
        ], [
            'name' => V0\SurveyController::class . '.get',
            'path' => V0\SurveyController::PATH_BY_ID,
            'http_method' => HttpMethod::GET,
        ],

        [
            'name' => V0\Survey\QuestionController::class . '.list',
            'path' => V0\Survey\QuestionController::PATH,
            'http_method' => HttpMethod::GET,
        ], [
            'name' => V0\Survey\QuestionController::class . '.get',
            'path' => V0\Survey\QuestionController::PATH_BY_ID,
            'http_method' => HttpMethod::GET,
        ],

        [
            'name' => V0\Survey\ResponseController::class . '.list',
            'path' => V0\Survey\ResponseController::PATH,
            'http_method' => HttpMethod::GET,
        ], [
            'name' => V0\Survey\ResponseController::class . '.new',
            'path' => V0\Survey\ResponseController::PATH,
            'http_method' => HttpMethod::POST,
        ],

        [
            'name' => V0\Survey\Response\FileController::class . '.get',
            'path' => V0\Survey\Response\FileController::PATH_BY_ID,
            'http_method' => HttpMethod::GET,
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
