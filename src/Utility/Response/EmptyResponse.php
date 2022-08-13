<?php

namespace MAChitgarha\LimeSurveyRestApi\Utility\Response;

use Symfony\Component\HttpFoundation\Response;

class EmptyResponse extends Response
{
    public function __construct(int $status = parent::HTTP_NO_CONTENT, array $headers = [])
    {
        parent::__construct('', $status, $headers);
    }
}
