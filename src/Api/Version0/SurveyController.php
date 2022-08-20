<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0;

use Yii;
use User;
use Survey;
use Permission;
use RuntimeException;
use SurveyLanguageSetting;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Error\Error;
use MAChitgarha\LimeSurveyRestApi\Error\InternalServerError;
use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;
use MAChitgarha\LimeSurveyRestApi\Error\ResourceIdNotFoundError;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;
use MAChitgarha\LimeSurveyRestApi\Utility\Response\EmptyResponse;

use MAChitgarha\LimeSurveyRestApi\Utility\ContentTypeValidator;

use function MAChitgarha\LimeSurveyRestApi\Utility\Response\data;

class SurveyController implements Controller
{
    use Traits\ContainerProperty;
    use Traits\AuthorizerGetter;
    use Traits\RequestGetter;
    use Traits\SerializerGetter;
    use Traits\RequestValidator;

    public const PATH = '/surveys';
    public const PATH_BY_ID = '/surveys/{survey_id}';

    public function list(): JsonResponse
    {
        $this->validateRequest();

        $userId = $this->authorize()->getId();

        $survey = new Survey();
        $survey->permission($userId);

        $userSurveys = $survey->findAll();

        $data = [];
        foreach ($userSurveys as $survey) {
            $data[] = [
                'id' => $survey->sid,
                'is_active' => $survey->active !== 'N',
                'creation_time' => \strtotime($survey->datecreated),
                'owner_id' => $survey->owner_id,
                'l10n' => [
                    'title' => $survey->languagesettings[$survey->language]->surveyls_title ?? ''
                ],
            ];
        }

        return new JsonResponse(
            data($data),
            JsonResponse::HTTP_OK
        );
    }

    public function get(): JsonResponse
    {
        throw new NotImplementedError();
    }
}
