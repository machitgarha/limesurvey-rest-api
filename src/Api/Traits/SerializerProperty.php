<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use Symfony\Component\Serializer\Serializer;

trait SerializerProperty
{
    /** @var Serializer */
    private $serializer;

    public function getSerializer(): Serializer
    {
        return $this->serializer;
    }

    /**
     * @return $this
     */
    public function setSerializer(Serializer $serializer)
    {
        $this->serializer = $serializer;
        return $this;
    }
}
