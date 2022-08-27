<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use Question;
use remotecontrol_handle;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;
use MAChitgarha\LimeSurveyRestApi\Error\ResourceIdNotFoundError;

use MAChitgarha\LimeSurveyRestApi\Helper\Permission;
use MAChitgarha\LimeSurveyRestApi\Helper\SurveyHelper;
use MAChitgarha\LimeSurveyRestApi\Helper\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

use function MAChitgarha\LimeSurveyRestApi\Utility\Response\data;

class QuestionController implements Controller
{
    use Traits\ContainerProperty;
    use Traits\AuthorizerGetter;
    use Traits\PathParameterGetter;
    use Traits\RequestGetter;
    use Traits\RequestValidator;

    public const PATH = '/surveys/{survey_id}/questions';
    public const PATH_BY_ID = '/surveys/{survey_id}/questions/{question_id}';

    public function list(): JsonResponse
    {
        $this->validateRequest();

        $userId = $this->authorize()->getId();

        $survey = SurveyHelper::get(
            $surveyId = $this->getPathParameterAsInt('survey_id')
        );

        PermissionChecker::assertHasSurveyPermission($survey, Permission::READ, $userId);

        // TODO: Add support for multilingual
        $language = $survey->language;
        $questionList = $survey->allQuestions;

        $data = [];
        foreach ($questionList as $question) {
            $data[] = self::makeQuestionData($question, $language);
        }

        return new JsonResponse(
            data($data)
        );
    }

    public function get(): JsonResponse
    {
        $this->validateRequest();

        $userId = $this->authorize()->getId();

        $survey = SurveyHelper::get(
            $surveyId = $this->getPathParameterAsInt('survey_id')
        );

        PermissionChecker::assertHasSurveyPermission($survey, Permission::READ, $userId);

        // TODO: Add support for multilingual
        $language = $survey->language;
        $question = Question::model()->findByPk(
            $questionId = $this->getPathParameterAsInt('question_id'),
            'sid = :survey_id',
            ['survey_id' => $surveyId]
        );

        if ($question === null) {
            throw new ResourceIdNotFoundError('question', $questionId);
        }

        return new JsonResponse(
            data(
                self::makeQuestionData($question, $survey->language)
            )
        );
    }

    private static function makeQuestionData(Question $question, string $language): array
    {
        $l10n = $question->questionl10ns[$language];
        return [
            'id' => $question->qid,
            'group_id' => $question->gid,
            'type' => $question->type,
            'mandatory' => $question->mandatory,
            'code' => $question->title,
            'order_in_group' => $question->question_order,
            'l10n' => [
                'question' => $l10n->question,
                'help' => $l10n->help,
            ],
        ];
    }
}
