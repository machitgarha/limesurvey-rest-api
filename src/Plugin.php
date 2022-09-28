<?php

namespace MAChitgarha\LimeSurveyRestApi;

use Throwable;
use PluginBase;
use CLogger as Logger;

use MAChitgarha\LimeSurveyRestApi\Api\JsonErrorResponseGenerator;

use MAChitgarha\LimeSurveyRestApi\Api\Controller;

use MAChitgarha\LimeSurveyRestApi\Authorization\BearerTokenAuthorizer;

use MAChitgarha\LimeSurveyRestApi\Routing\PathInfo;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugMode;

use MAChitgarha\LimeSurveyRestApi\Routing\Router;

use MAChitgarha\LimeSurveyRestApi\Utility\DebugOption;

use MAChitgarha\LimeSurveyRestApi\Validation\RequestValidator;
use MAChitgarha\LimeSurveyRestApi\Validation\ValidatorBuilder;
use MAChitgarha\LimeSurveyRestApi\Validation\ResponseValidator;

use Psr\Cache\CacheItemPoolInterface;

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

    protected $storage = 'DbStorage';
    protected $settings = Config::SETTINGS;

    /** @var Config */
    private $_config;

    public function init(): void
    {
        $this->subscribe('beforeControllerAction');
        $this->subscribe('beforeDeactivate');
    }

    public function beforeControllerAction(): void
    {
        $pathInfoValue = $_SERVER['PATH_INFO'] ?? '';

        if (!Router::shouldWeHandle($pathInfoValue)) {
            return;
        }
        // Else, disable default request handling
        $this->event->set('run', false);

        $this->_config = self::makeConfig();
        $request = Request::createFromGlobals();

        $this->setDebugging();

        $this->log(
            "New request caught: {$request->getMethod()} $pathInfoValue",
            Logger::LEVEL_INFO
        );

        $this->handleRequest($request, $pathInfoValue);

        App()->end();
    }

    /**
     * @phan-suppress PhanTypeMismatchArgumentProbablyReal
     * @return Config
     */
    private function makeConfig(): Config
    {
        return new Config(
            \Closure::fromCallable([$this, 'get'])
        );
    }

    private function setDebugging(): void
    {
        if ($this->_config->getDebugMode() === DebugMode::FULL) {
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
        $validatorBuilder = new ValidatorBuilder($this->makeCache(), $this->_config);

        try {
            $router = new Router(new PathInfo($pathInfoValue));

            [[$controllerClass, $method], $params] = $router->route($request);

            $response = $this
                ->makeController($controllerClass, $params, $request, $router, $validatorBuilder)
                ->$method();

        } catch (Throwable $error) {
            $response = (new JsonErrorResponseGenerator($this, $this->_config))->generate($error);
        }

        try {
            if ($this->_config->hasDebugOption(DebugOption::VALIDATE_RESPONSE)) {
                $this->assertIsResponseValid($response, $pathInfoValue, $validatorBuilder, $request);
            }
        } catch (Throwable $throwable) {
            $response = addThrowableAsDebugMessageToResponse($response, $throwable, $this->_config);
            $this->logThrowable($throwable);
        }

        $this->removeUnnecessaryHeaders();

        /** @var Response $response */
        $response->send();
    }

    private function makeCache(): CacheItemPoolInterface
    {
        return new FilesystemAdapter();
    }

    private function makeController(
        string $controllerClass,
        array $params,
        Request $request,
        Router $router,
        ValidatorBuilder $validatorBuilder
    ): Controller {
        $request->attributes->replace($params);

        return new $controllerClass(
            $request,
            new Serializer([], [new JsonEncoder()]),
            new BearerTokenAuthorizer($request),
            new RequestValidator($request, $router->getPathInfo(), $validatorBuilder)
        );
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
        $message = convertThrowableToLogMessage($throwable, $this->_config);
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

    public function beforeDeactivate(): void
    {
        // No need to check the output
        $this->makeCache()->clear();
    }
}
