<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use LogicException;

use Symfony\Component\Serializer\Serializer;

trait SerializerProperty
{
    /** @var Serializer|null */
    private $serializer = null;

    public function getSerializer(): Serializer
    {
        if ($this->serializer === null) {
            throw new LogicException('Serializer is not set');
        }
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
