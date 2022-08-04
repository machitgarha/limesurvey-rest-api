<?php

namespace MAChitgarha\LimeSurveyRestApi;

use CLogger as Logger;
use Throwable;
use PluginBase;

use LimeSurvey\PluginManager\PluginManager;

use MAChitgarha\LimeSurveyRestApi\Api\Config;
use MAChitgarha\LimeSurveyRestApi\Api\ControllerDependencyContainer;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Authorization\BearerTokenAuthorizer;

use MAChitgarha\LimeSurveyRestApi\Error\Error;
use MAChitgarha\LimeSurveyRestApi\Error\PathNotFoundError;
use MAChitgarha\LimeSurveyRestApi\Error\InternalServerError;
use MAChitgarha\LimeSurveyRestApi\Error\InvalidKeyValueError;
use MAChitgarha\LimeSurveyRestApi\Error\MethodNotAllowedError;
use MAChitgarha\LimeSurveyRestApi\Error\RequiredKeyMissingError;
use MAChitgarha\LimeSurveyRestApi\Error\MalformedRequestBodyError;

use MAChitgarha\LimeSurveyRestApi\Routing\Router;

use Respect\Validation\Exceptions\KeyException;
use Respect\Validation\Exceptions\ValidationException;

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

        $this->log("New request caught: {$this->request->getMethod()} $path", Logger::LEVEL_INFO);
        $this->handleRequest();

        App()->end();
    }

    private function handleRequest(): void
    {
        try {
            [[$controllerClass, $method], $params] =
                (new Router($this->request))->route();

            /** @var JsonResponse $response */
            $response = $this
                ->makeController($controllerClass, $params)
                ->$method();

        } catch (Error $error) {
            $response = $this->makeJsonErrorResponse($error);

        } catch (ResourceNotFoundException $_) {
            $response = $this->makeJsonErrorResponse(new PathNotFoundError());

        } catch (MethodNotAllowedException $exception) {
            $response = $this->makeJsonErrorResponse(new MethodNotAllowedError());
            $response->headers->set(
                'Allow',
                \implode(', ', $exception->getAllowedMethods())
            );

        } catch (NotEncodableValueException $_) {
            $response = $this->makeJsonErrorResponse(new MalformedRequestBodyError());

        } catch (KeyException $exception) {
            $response = $this->makeJsonErrorResponse(
                new RequiredKeyMissingError($exception->getMessage())
            );

        } catch (ValidationException $exception) {
            $response = $this->makeJsonErrorResponse(
                new InvalidKeyValueError($exception->getMessage())
            );

        } catch (Throwable $error) {
            $this->log(
                $error->__toString(),
                Logger::LEVEL_ERROR
            );

            $response = $this->makeJsonErrorResponse(new InternalServerError());
        }

        $response->send();
    }

    private function makeController(string $controllerClass, array $params): Controller
    {
        /** @var Controller $controller */
        $controller = new $controllerClass();

        $this->request->attributes->replace($params);

        $container = new ControllerDependencyContainer(
            $this->request,
            new Serializer([], [new JsonEncoder()]),
            new BearerTokenAuthorizer($this->request)
        );

        return $controller
            ->setContainer($container);
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
