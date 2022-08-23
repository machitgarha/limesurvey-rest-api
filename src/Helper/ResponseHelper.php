<?php

namespace MAChitgarha\LimeSurveyRestApi\Helper;

use Response;

use MAChitgarha\LimeSurveyRestApi\Error\ResourceIdNotFoundError;

class ResponseHelper
{
    public static function get(int $surveyId, int $responseId): Response
    {
        $response = Response::model($surveyId)->findByPk($responseId);

        if ($response === null) {
            throw new ResourceIdNotFoundError('survey', $surveyId);
        }
        return $response;
    }
}
