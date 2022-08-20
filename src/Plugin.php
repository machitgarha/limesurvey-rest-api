<?php

namespace MAChitgarha\LimeSurveyRestApi;

use Throwable;
use PluginBase;
use CLogger as Logger;

use LimeSurvey\PluginManager\PluginManager;

use MAChitgarha\LimeSurveyRestApi\Api\JsonErrorResponseGenerator;

use MAChitgarha\LimeSurveyRestApi\Config;

use MAChitgarha\LimeSurveyRestApi\Api\ControllerDependencyContainer;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Authorization\BearerTokenAuthorizer;

use MAChitgarha\LimeSurveyRestApi\Routing\PathInfo;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugMode;

use MAChitgarha\LimeSurveyRestApi\Routing\Router;

use MAChitgarha\LimeSurveyRestApi\Validation\RequestValidator;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Serializer\Encoder\JsonEncoder;

use Symfony\Component\Serializer\Serializer;

use function MAChitgarha\LimeSurveyRestApi\Utility\Response\error;

class Plugin extends PluginBase
{
    protected static $name = 'LimeSurveyRestApi';
    protected static $description = 'LimeSurvey REST API provider';

    public function init(): void
    {
        $this->subscribe('beforeControllerAction');
    }

    public function beforeControllerAction(): void
    {
        $pathInfoValue = $_SERVER['PATH_INFO'] ?? '';

        if (!Router::shouldWeHandle($pathInfoValue)) {
            return;
        }
        // Else, disable default request handling
        $this->event->set('run', false);

        $request = Request::createFromGlobals();

        $this->setDebugging();

        $this->log(
            "New request caught: {$request->getMethod()} {$pathInfoValue}",
            Logger::LEVEL_INFO
        );

        $this->handleRequest($request, $pathInfoValue);

        App()->end();
    }

    private function setDebugging(): void
    {
        if (Config::DEBUG_MODE === DebugMode::FULL) {
            $handler = function (int $code, string $message, string $file, int $line) {
                $this->log(
                    "$message (at $file:$line, code: $code)",
                    Logger::LEVEL_ERROR
                );
            };
            \set_error_handler($handler, \E_ALL);

            // Logging is not possible for some odd reason
            \error_reporting(\E_ALL);
            \ini_set('display_errors', 1);
        }
    }

    private function handleRequest(Request $request, string $pathInfoValue): void
    {
        try {
            $router = new Router(
                $pathInfo = new PathInfo($pathInfoValue)
            );

            [[$controllerClass, $method], $params] = $router->route($request);

            $response = $this
                ->makeController($controllerClass, $params, $request, $router)
                ->$method();

        } catch (Throwable $error) {
            $response = (new JsonErrorResponseGenerator($this))->generate($error);
        }

        /** @var Response $response */
        $response->send();
    }

    private function makeController(
        string $controllerClass,
        array $params,
        Request $request,
        Router $router
    ): Controller {
        /** @var Controller $controller */
        $controller = new $controllerClass();

        $request->attributes->replace($params);

        $pathInfo = $router->getPathInfo();

        $container = new ControllerDependencyContainer(
            $request,
            $pathInfo,
            new Serializer([], [new JsonEncoder()]),
            new BearerTokenAuthorizer($request),
            new RequestValidator($request, $pathInfo, new FilesystemAdapter())
        );

        return $controller
            ->setContainer($container);
    }
}
