<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

class ResourceIdNotFoundError extends Error
{
    public function __construct(string $resouceName, int $resourceId, ...$args)
    {
        parent::__construct("Resource '$resourceName' with id '$resourceId' not found");
    }
}
