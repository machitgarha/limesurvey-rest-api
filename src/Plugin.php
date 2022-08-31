<?php

namespace MAChitgarha\LimeSurveyRestApi;

use Throwable;
use PluginBase;
use CLogger as Logger;

use MAChitgarha\LimeSurveyRestApi\Api\JsonErrorResponseGenerator;

use MAChitgarha\LimeSurveyRestApi\Config;

use MAChitgarha\LimeSurveyRestApi\Api\ControllerDependencyContainer;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Authorization\BearerTokenAuthorizer;

use MAChitgarha\LimeSurveyRestApi\Error\DebugError;

use MAChitgarha\LimeSurveyRestApi\Routing\PathInfo;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugMode;

use MAChitgarha\LimeSurveyRestApi\Routing\Router;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugOption;
use MAChitgarha\LimeSurveyRestApi\Utility\LogVerbosity;

use MAChitgarha\LimeSurveyRestApi\Validation\RequestValidator;
use MAChitgarha\LimeSurveyRestApi\Validation\ValidatorBuilder;
use MAChitgarha\LimeSurveyRestApi\Validation\ResponseValidator;

use Symfony\Component\Cache\Adapter\FilesystemAdapter;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Serializer\Encoder\JsonEncoder;

use Symfony\Component\Serializer\Serializer;

use function MAChitgarha\LimeSurveyRestApi\Helper\convertThrowableToLogMessage;
use function MAChitgarha\LimeSurveyRestApi\Helper\addThrowableAsDebugMessageToResponse;

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
        if (Config::getInstance()->getDebugMode() === DebugMode::FULL) {
            $handler = function (int $code, string $message, string $file, int $line): bool {
                $this->log(
                    "$message (at $file:$line, code: $code)",
                    Logger::LEVEL_ERROR
                );
                return true;
            };
            \set_error_handler($handler, \E_ALL);

            // Logging is not possible for some odd reason
            \error_reporting(\E_ALL);
            \ini_set('display_errors', 'On');
        }
    }

    private function handleRequest(Request $request, string $pathInfoValue): void
    {
        $validatorBuilder = new ValidatorBuilder(new FilesystemAdapter());

        try {
            $router = new Router(
                $pathInfo = new PathInfo($pathInfoValue)
            );

            [[$controllerClass, $method], $params] = $router->route($request);

            $response = $this
                ->makeController($controllerClass, $params, $request, $router, $validatorBuilder)
                ->$method();

        } catch (Throwable $error) {
            $response = (new JsonErrorResponseGenerator($this))->generate($error);
        }

        try {
            if (Config::getInstance()->hasDebugOption(DebugOption::VALIDATE_RESPONSE)) {
                $this->assertIsResponseValid($response, $pathInfoValue, $validatorBuilder, $request);
            }
        } catch (Throwable $throwable) {
            $response = addThrowableAsDebugMessageToResponse($response, $throwable);
            $this->logThrowable($throwable);
        }

        $this->removeUnnecessaryHeaders();

        /** @var Response $response */
        $response->send();
    }

    private function makeController(
        string $controllerClass,
        array $params,
        Request $request,
        Router $router,
        ValidatorBuilder $validatorBuilder
    ): Controller {
        /** @var Controller $controller */
        $controller = new $controllerClass();

        $request->attributes->replace($params);

        $pathInfo = $router->getPathInfo();

        $container = new ControllerDependencyContainer(
            $request,
            new Serializer([], [new JsonEncoder()]),
            new BearerTokenAuthorizer($request),
            new RequestValidator($request, $pathInfo, $validatorBuilder)
        );

        return $controller
            ->setContainer($container);
    }

    private function assertIsResponseValid(
        Response $response,
        string $pathInfoValue,
        ValidatorBuilder $validatorBuilder,
        Request $request
    ): void {
        (new ResponseValidator(
            $response,
            new PathInfo($pathInfoValue),
            $validatorBuilder,
            $request->getMethod()
        ))->validate();
    }

    public function logThrowable(Throwable $throwable): void
    {
        $message = convertThrowableToLogMessage($throwable);
        $this->log(\get_class($throwable) . ": $message", Logger::LEVEL_ERROR);
    }

    private static function removeUnnecessaryHeaders(): void
    {
        foreach ([
            'Set-Cookie'
        ] as $headerName) {
            \header_remove($headerName);
        }
    }
}
