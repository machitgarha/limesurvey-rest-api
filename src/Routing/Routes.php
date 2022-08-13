<?php

namespace MAChitgarha\LimeSurveyRestApi\Routing;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Login\BearerTokenController;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\QuestionController;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\FileController;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\SurveyController;

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

        [
            'path' => SurveyController::PATH,
            'http_method' => HttpMethod::GET,
            'name' => SurveyController::class . '.list',
        ], [
            'path' => SurveyController::PATH,
            'http_method' => HttpMethod::POST,
            'name' => SurveyController::class . '.new',
        ], [
            'path' => SurveyController::PATH_BY_ID,
            'http_method' => HttpMethod::GET,
            'name' => SurveyController::class . '.get',
        ], [
            'path' => SurveyController::PATH_BY_ID,
            'http_method' => HttpMethod::PUT,
            'name' => SurveyController::class . '.update',
        ], [
            'path' => SurveyController::PATH_BY_ID,
            'http_method' => HttpMethod::DELETE,
            'name' => SurveyController::class . '.delete',
        ],

        [
            'path' => QuestionController::PATH,
            'http_method' => HttpMethod::GET,
            'name' => QuestionController::class . '.list',
        ], [
            'path' => QuestionController::PATH,
            'http_method' => HttpMethod::POST,
            'name' => QuestionController::class . '.new',
        ], [
            'path' => QuestionController::PATH_BY_ID,
            'http_method' => HttpMethod::GET,
            'name' => QuestionController::class . '.get',
        ], [
            'path' => QuestionController::PATH_BY_ID,
            'http_method' => HttpMethod::PUT,
            'name' => QuestionController::class . '.update',
        ], [
            'path' => QuestionController::PATH_BY_ID,
            'http_method' => HttpMethod::DELETE,
            'name' => QuestionController::class . '.delete',
        ],

        [
            'path' => ResponseController::PATH,
            'http_method' => HttpMethod::GET,
            'name' => ResponseController::class . '.list',
        ], [
            'path' => ResponseController::PATH,
            'http_method' => HttpMethod::POST,
            'name' => ResponseController::class . '.new',
        ],

        [
            'path' => FileController::PATH_BY_ID,
            'http_method' => HttpMethod::GET,
            'name' => FileController::class . '.get',
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
