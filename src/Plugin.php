<?php

namespace MAChitgarha\LimeSurveyRestApi;

use CLogger as Logger;
use Throwable;
use PluginBase;

use LimeSurvey\PluginManager\PluginManager;

use MAChitgarha\LimeSurveyRestApi\Api\Config;

use MAChitgarha\LimeSurveyRestApi\Error\Error;
use MAChitgarha\LimeSurveyRestApi\Error\BadRequestError;
use MAChitgarha\LimeSurveyRestApi\Error\PathNotFoundError;
use MAChitgarha\LimeSurveyRestApi\Error\InternalServerError;
use MAChitgarha\LimeSurveyRestApi\Error\MethodNotAllowedError;
use MAChitgarha\LimeSurveyRestApi\Error\MalformedRequestBodyError;

use MAChitgarha\LimeSurveyRestApi\Routing\Router;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Routing\Exception\MethodNotAllowedException;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;

use Symfony\Component\Serializer\Exception\NotEncodableValueException;

use Symfony\Component\Serializer\Encoder\JsonEncoder;

use Symfony\Component\Serializer\Serializer;

use function MAChitgarha\LimeSurveyRestApi\Helper\Response\error;

class Plugin extends PluginBase
{
    protected static $name = 'LimeSurveyRestApi';
    protected static $description = 'LimeSurvey REST API provider';

    /** @var Request */
    private $request;

    public function __construct(PluginManager $pluginManager, $id)
    {
        parent::__construct($pluginManager, $id);

        $this->request = Request::createFromGlobals();
    }

    public function init(): void
    {
        $this->subscribe('beforeControllerAction');
    }

    public function beforeControllerAction(): void
    {
        $path = $this->request->getPathInfo();

        if (!\str_starts_with($path, '/' . Config::PATH_PREFIX)) {
            return;
        }

        // Disable default request handling
        $this->event->set('run', false);

        $this->log("New request caught: $path", Logger::LEVEL_INFO);
        $this->handleRequest();

        App()->end();
    }

    private function handleRequest(): void
    {
        try {
            [$controllerClass, $method] = (new Router($this->request))->route();

            /** @var JsonResponse */
            $response = $this
                ->makeController($controllerClass)
                ->$method();
        } catch (Error $error) {
            $response = $this->makeJsonErrorResponse($error);
        } catch (ResourceNotFoundException $error) {
            $response = $this->makeJsonErrorResponse(new PathNotFoundError());
        } catch (MethodNotAllowedException $error) {
            $response = $this->makeJsonErrorResponse(new MethodNotAllowedError());
        } catch (NotEncodableValueException $error) {
            $response = $this->makeJsonErrorResponse(new MalformedRequestBodyError());
        } catch (Throwable $error) {
            $this->log(
                \get_class($error) . ": {$error->getMessage()}",
                Logger::LEVEL_ERROR
            );

            $response = $this->makeJsonErrorResponse(new InternalServerError());
        }

        echo $response;
    }

    private function makeController(string $controllerClass): object
    {
        $controller = new $controllerClass();

        $controller->setRequest($this->request);
        $controller->setSerializer($this->makeSerializer());

        return $controller;
    }

    private function makeSerializer(): Serializer
    {
        return new Serializer([], [
            new JsonEncoder()
        ]);
    }

    private function makeJsonErrorResponse(Error $error): JsonResponse
    {
        $errorData = ['id' => $error->getId()];

        if (!empty($error->getMessage())) {
            $errorData['message'] = $error->getMessage();
        }

        return new JsonResponse(
            error($errorData),
            $error->getHttpStatusCode()
        );
    }
}
