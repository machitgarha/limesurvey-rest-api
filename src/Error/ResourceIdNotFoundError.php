<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

class ResourceIdNotFoundError extends ResourceNotFoundError
{
    public function __construct(string $resourceName, int $resourceId, ...$args)
    {
        parent::__construct("Resource '$resourceName' with id '$resourceId' not found");
    }
}
