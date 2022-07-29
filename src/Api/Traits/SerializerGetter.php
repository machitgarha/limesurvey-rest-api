<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Api\ControllerDependencyContainer;

use Symfony\Component\Serializer\Serializer;

trait SerializerGetter
{
    abstract public function getContainer(): ControllerDependencyContainer;

    public function getSerializer(): Serializer
    {
        return $this->getContainer()->getSerializer();
    }
}
