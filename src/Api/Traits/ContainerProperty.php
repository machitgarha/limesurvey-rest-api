<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use LogicException;

use MAChitgarha\LimeSurveyRestApi\Api\ControllerDependencyContainer;

trait ContainerProperty
{
    /** @var ControllerDependencyContainer|null */
    private $container = null;

    public function getContainer(): ControllerDependencyContainer
    {
        if ($this->container === null) {
            throw new LogicException('ControllerDependencyContainer is not set yet');
        }
        return $this->container;
    }

    /**
     * @return $this
     */
    public function setContainer(ControllerDependencyContainer $container)
    {
        $this->container = $container;
        return $this;
    }
}
