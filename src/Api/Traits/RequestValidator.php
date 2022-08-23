<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Traits;

use TypeError;

use MAChitgarha\LimeSurveyRestApi\Api\ControllerDependencyContainer;

use MAChitgarha\LimeSurveyRestApi\Error\TypeMismatchError;

trait RequestValidator
{
    abstract public function getContainer(): ControllerDependencyContainer;

    public function validateRequest(): void
    {
        try {
            $this->getContainer()->getRequestValidator()->validate();
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
