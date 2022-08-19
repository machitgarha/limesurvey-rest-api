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
        $pathInfo = new PathInfo($_SERVER['PATH_INFO'] ?? '');

        if (!$pathInfo->isBelongedToThisPlugin()) {
            return;
        }

        $request = Request::createFromGlobals();

        // Disable default request handling
        $this->event->set('run', false);

        $this->setDebugging();

        $this->log(
            "New request caught: {$request->getMethod()} {$pathInfo->get()}",
            Logger::LEVEL_INFO
        );

        $this->handleRequest($request, $pathInfo);

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

    private function handleRequest(Request $request, PathInfo $pathInfo): void
    {
        try {
            [[$controllerClass, $method], $params] =
                (new Router($request, $pathInfo))->route();

            $response = $this
                ->makeController($controllerClass, $params, $request)
                ->$method();

        } catch (Throwable $error) {
            $response = (new JsonErrorResponseGenerator($this))->generate($error);
        }

        /** @var Response $response */
        $response->send();
    }

    private function makeController(string $controllerClass, array $params, Request $request): Controller
    {
        /** @var Controller $controller */
        $controller = new $controllerClass();

        $request->attributes->replace($params);

        $container = new ControllerDependencyContainer(
            $request,
            new Serializer([], [new JsonEncoder()]),
            new BearerTokenAuthorizer($request)
        );

        return $controller
            ->setContainer($container);
    }
}
