<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

class SurveyController
{
    use Traits\AuthorizerProperty;
    use Traits\RequestProperty;
    use Traits\SerializerProperty;

    public const PATH = '/surveys';
    public const PATH_BY_ID = '/surveys/{survey_id}';

    public function list(): JsonResponse
    {
        throw new NotImplementedError();
    }

    public function new(): Response
    {
        throw new NotImplementedError();
    }

    public function get(): JsonResponse
    {
        throw new NotImplementedError();
    }

    public function update(): Response
    {
        throw new NotImplementedError();
    }

    public function delete(): Response
    {
        throw new NotImplementedError();
    }
}
