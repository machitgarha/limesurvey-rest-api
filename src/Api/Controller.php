<?php

namespace MAChitgarha\LimeSurveyRestApi\Api;

use TypeError;
use RuntimeException;

use MAChitgarha\LimeSurveyRestApi\Authorization\Authorizer;

use MAChitgarha\LimeSurveyRestApi\Error\MalformedRequestBodyError;
use MAChitgarha\LimeSurveyRestApi\Error\TypeMismatchError;

use MAChitgarha\LimeSurveyRestApi\Validation\RequestValidator;

use Symfony\Component\HttpFoundation\Request;

use Symfony\Component\Serializer\Exception\NotEncodableValueException;
use Symfony\Component\Serializer\Serializer;

abstract class Controller
{
    /** @var Request */
    private $request;

    /** @var Serializer */
    private $serializer;

    /** @var Authorizer */
    private $authorizer;

    /** @var RequestValidator */
    private $requestValidator;

    public function __construct(
        Request $request,
        Serializer $serializer,
        Authorizer $authorizer,
        RequestValidator $requestValidator
    ) {
        $this->request = $request;
        $this->serializer = $serializer;
        $this->authorizer = $authorizer;
        $this->requestValidator = $requestValidator;
    }

    protected function getRequest(): Request
    {
        return $this->request;
    }

    protected function getSerializer(): Serializer
    {
        return $this->serializer;
    }

    protected function getAuthorizer(): Authorizer
    {
        return $this->authorizer;
    }

    protected function getPathParameter(string $key): string
    {
        $param = $this->getRequest()->attributes->get($key);

        if ($param !== null) {
            return (string) $param;
        }

        throw new RuntimeException("Cannot get path parameter '$key'");
    }

    protected function getPathParameterAsInt(string $key): int
    {
        return (int)($this->getPathParameter($key));
    }

    /**
     * @return int[]
     */
    protected function getPathParameterListAsInt(string ...$keys): array
    {
        return \array_map([$this, 'getPathParameterAsInt'], $keys);
    }

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

    protected function authorize(): Authorizer
    {
        return $this->authorizer->authorize();
    }

    public function validateRequest(): void
    {
        try {
            $this->requestValidator->validate();
        } catch (TypeError $error) {
            // HACK: The validator cannot handle invalid base64 inputs sometimes
            if (\str_contains($error->getMessage(), 'base64_encode')) {
                throw new TypeMismatchError(
                    'A keyword is required to be a valid base64-encoded string'
                );
            } else {
                throw $error;
            }
        }
    }
}