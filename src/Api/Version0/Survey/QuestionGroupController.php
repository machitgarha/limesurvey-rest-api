<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use QuestionGroup;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Error\ResourceIdNotFoundError;

use MAChitgarha\LimeSurveyRestApi\Helper\Permission;
use MAChitgarha\LimeSurveyRestApi\Helper\SurveyHelper;
use MAChitgarha\LimeSurveyRestApi\Helper\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

use function MAChitgarha\LimeSurveyRestApi\Utility\Response\data;

class QuestionGroupController implements Controller
{
    use Traits\ContainerProperty;
    use Traits\AuthorizerGetter;
    use Traits\PathParameterGetter;
    use Traits\RequestGetter;
    use Traits\RequestValidator;

    public const PATH = '/surveys/{survey_id}/question_groups';
    public const PATH_BY_ID = '/surveys/{survey_id}/question_groups/{question_group_id}';

    public function list(): JsonResponse
    {
        $this->validateRequest();

        $userId = $this->authorize()->getId();

        $survey = SurveyHelper::get(
            $this->getPathParameterAsInt('survey_id')
        );

        PermissionChecker::assertHasSurveyPermission($survey, Permission::READ, $userId);

        $language = $survey->language;
        $questionGroupList = $survey->groups;

        $data = [];
        foreach ($questionGroupList as $questionGroup) {
            $data[] = self::makeQuestionGroupData($questionGroup, $language);
        }

        return new JsonResponse(
            data($data)
        );
    }

    public function get(): JsonResponse
    {
        $this->validateRequest();

        $userId = $this->authorize()->getId();

        [$surveyId, $questionGroupId] = $this->getPathParameterListAsInt(
            'survey_id',
            'question_group_id'
        );

        $survey = SurveyHelper::get($surveyId);

        // TODO: Probably use Permission::model()->hasSurveyPermission()
        PermissionChecker::assertHasSurveyPermission($survey, Permission::READ, $userId);

        $language = $survey->language;
        $questionGroup = QuestionGroup::model()->findByPk(
            $questionGroupId,
            'sid = :survey_id',
            ['survey_id' => $surveyId]
        );

        if ($questionGroup === null) {
            throw new ResourceIdNotFoundError('question_group', $questionGroupId);
        }

        return new JsonResponse(
            data(
                self::makeQuestionGroupData($questionGroup, $language)
            )
        );
    }

    private static function makeQuestionGroupData(QuestionGroup $questionGroup, string $language): array
    {
        $l10n = $questionGroup->questiongroupl10ns[$language];
        return [
            'id' => $questionGroup->gid,
            'order' => $questionGroup->group_order,
            'randomization_id' => $questionGroup->randomization_group,
            'relevance' => $questionGroup->grelevance,
            'l10n' => [
                'name' => $l10n->group_name,
                'description' => $l10n->description,
            ],
        ];
    }
}
