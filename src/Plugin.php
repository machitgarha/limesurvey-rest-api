<?php

namespace MAChitgarha\LimeSurveyRestApi;

use CLogger;
use LSHttpRequest;
use PluginBase;

use LimeSurvey\PluginManager\PluginManager;

use MAChitgarha\LimeSurveyRestApi\Api\Config;

use MAChitgarha\LimeSurveyRestApi\Routing\Router;

class Plugin extends PluginBase
{
    protected static $name = 'LimeSurveyRestApi';
    protected static $description = 'LimeSurvey REST API provider';

    /** @var \LSHttpRequest */
    private $request;

    public function __construct(PluginManager $pluginManager, $id)
    {
        parent::__construct($pluginManager, $id);

        $this->request = $this->api->getRequest();
    }

    public function init(): void
    {
        $this->subscribe('beforeControllerAction');
    }

    public function beforeControllerAction(): void
    {
        $path = $this->getBasePath();

        if (!\str_starts_with($path, '/' . Config::PATH_PREFIX)) {
            return;
        }

        // Disable default request handling
        $this->event->set('run', false);

        $this->log("New request caught: $path", CLogger::LEVEL_INFO);

        try {
            [$controllerClass, $method] = (new Router($this->request))->route($path);

            $controller = new $controllerClass();
            $controller->setRequest($this->request);
            echo $controller->$method();

        } catch (\Throwable $e) {
            $this->log("{$e->getMessage()} (code: {$e->getCode()})", CLogger::LEVEL_ERROR);
            // TODO: Return a 500 error
        }
    }

    /**
     * Returns the path after the script url.
     * @return string
     */
    private function getBasePath(): string
    {
        return \str_replace(
            $this->request->getScriptUrl(),
            '',
            $this->request->getUrl(),
        );
    }
}
