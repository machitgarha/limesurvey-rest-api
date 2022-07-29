<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Interfaces;

use MAChitgarha\LimeSurveyRestApi\Api\ControllerDependencyContainer;

interface Controller
{
    /**
     * @return $this
     */
    public function setContainer(ControllerDependencyContainer $container);
}
