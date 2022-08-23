<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Error\MalformedRequestBodyError;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Serializer\Exception\NotEncodableValueException;

use Symfony\Component\Serializer\Serializer;

trait RequestBodyDecoder
{
    abstract public function getRequest(): Request;
    abstract public function getSerializer(): Serializer;

    public function decodeJsonRequestBody()
    {
        try {
            return $this->getSerializer()->decode(
                $this->getRequest()->getContent(),
                'json'
            );
        } catch (NotEncodableValueException $e) {
            throw new MalformedRequestBodyError();
        }
    }

    /**
     * Decodes the request body as JSON, and returns its 'data' property.
     *
     * @return mixed
     */
    public function decodeJsonRequestBodyInnerData(): array
    {
        return $this->decodeJsonRequestBody()['data'];
    }
}
