<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

class ResourceIdNotFoundError extends ResourceNotFoundError
{
    /**
     * @param int|string $resourceId
     */
    public function __construct(string $resourceName, $resourceId, ...$args)
    {
        parent::__construct("Resource '$resourceName' with id '$resourceId' not found", ...$args);
    }
}
