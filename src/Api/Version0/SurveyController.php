<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0;

use Survey;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;

use MAChitgarha\LimeSurveyRestApi\Helper\Permission;
use MAChitgarha\LimeSurveyRestApi\Helper\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

use function MAChitgarha\LimeSurveyRestApi\Utility\Response\data;

class SurveyController implements Controller
{
    use Traits\ContainerProperty;
    use Traits\AuthorizerGetter;
    use Traits\RequestGetter;
    use Traits\SerializerGetter;
    use Traits\RequestValidator;
    use Traits\PathParameterGetter;

    public const PATH = '/surveys';
    public const PATH_BY_ID = '/surveys/{survey_id}';

    public function list(): JsonResponse
    {
        $this->validateRequest();

        $userId = $this->authorize()->getId();

        $survey = Survey::model();
        $survey->permission($userId);

        $userSurveys = $survey->findAll();

        $data = [];
        foreach ($userSurveys as $survey) {
            $data[] = self::makeSurveyData($survey);
        }

        return new JsonResponse(
            data($data)
        );
    }

    public function get(): JsonResponse
    {
        $this->validateRequest();

        $userId = $this->authorize()->getId();

        $survey = Survey::model()->findByPk(
            $this->getPathParameterAsInt('survey_id')
        );

        PermissionChecker::assertHasSurveyPermission(
            $survey,
            Permission::READ,
            $userId
        );

        return new JsonResponse(
            data(self::makeSurveyData($survey))
        );
    }

    private static function makeSurveyData(Survey $survey): array
    {
        return [
            'id' => $survey->sid,
            'is_active' => $survey->active !== 'N',
            'creation_time' => \strtotime($survey->datecreated),
            'owner_id' => $survey->owner_id,
            'l10n' => [
                'title' => $survey->languagesettings[$survey->language]->surveyls_title ?? ''
            ],
        ];
    }
}
