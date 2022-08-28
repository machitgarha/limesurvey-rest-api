<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0;

use Survey;
use IApplicationComponent;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;

use MAChitgarha\LimeSurveyRestApi\Helper\Permission;
use MAChitgarha\LimeSurveyRestApi\Helper\SurveyHelper;
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

        PermissionChecker::assertHasSurveyPermission(
            $survey = Survey::model(),
            Permission::READ,
            $userId
        );

        $surveyList = $survey->findAll();

        $data = [];
        foreach ($surveyList as $survey) {
            $data[] = self::makeSurveyDataFromInfo(
                SurveyHelper::getInfo($survey->sid)
            );
        }

        return new JsonResponse(
            data($data)
        );
    }

    public function get(): JsonResponse
    {
        $this->validateRequest();

        $userId = $this->authorize()->getId();

        $surveyInfo = SurveyHelper::getInfo(
            $this->getPathParameterAsInt('survey_id')
        );

        PermissionChecker::assertHasSurveyPermission(
            $surveyInfo['oSurvey'],
            Permission::READ,
            $userId
        );

        return new JsonResponse(
            data(self::makeSurveyDataFromInfo($surveyInfo))
        );
    }

    private static function makeSurveyDataFromInfo(array $surveyInfo): array
    {
        return [
            'id' => $surveyInfo['sid'],
            'group_id' => $surveyInfo['gsid'],

            'l10n' => [
                'title' => $surveyInfo['surveyls_title'],
                'description' => $surveyInfo['surveyls_description'],

                'welcome_message' => $surveyInfo['surveyls_welcometext'],
                'end_message' => $surveyInfo['surveyls_endtext'],
                'end_url' => $surveyInfo['surveyls_url'],
                'end_url_title' => $surveyInfo['surveyls_urldescription'],
            ],

            'creation_time' => $surveyInfo['datecreated'],
            'start_time' => $surveyInfo['startdate'],
            'expiry_time' => $surveyInfo['expires'],

            'format' => $surveyInfo['format'],

            'is_active' => $surveyInfo['active'] === 'Y',
            'is_backward_navigation_allowed' => $surveyInfo['allowprev'] === 'Y',
            'is_datestamps_stored' => $surveyInfo['datestamp'] === 'Y',
            'is_ip_address_stored' => $surveyInfo['ipaddr'] === 'Y',
            'is_progress_shown' => $surveyInfo['showprogress'] === 'Y',
            'is_welcome_message_shown' => $surveyInfo['showwelcome'] === 'Y',

            'navigation_delay' => $surveyInfo['navigationdelay'],
        ];
    }
}
