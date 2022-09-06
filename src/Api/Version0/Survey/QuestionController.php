<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use Question;

use MAChitgarha\LimeSurveyRestApi\Api\Controller;

use MAChitgarha\LimeSurveyRestApi\Error\ResourceIdNotFoundError;
use MAChitgarha\LimeSurveyRestApi\Error\InvalidPathParameterError;

use MAChitgarha\LimeSurveyRestApi\Helper\Permission;
use MAChitgarha\LimeSurveyRestApi\Helper\SurveyHelper;
use MAChitgarha\LimeSurveyRestApi\Helper\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

use function MAChitgarha\LimeSurveyRestApi\Utility\Response\data;

class QuestionController extends Controller
{
    public const PATH = '/surveys/{survey_id}/questions';
    public const PATH_BY_ID = '/surveys/{survey_id}/questions/{question_id}';

    public function list(): JsonResponse
    {
        $this->validateRequest();

        $userId = $this->authorize()->getId();

        $survey = SurveyHelper::get(
            $this->getPathParameterAsInt('survey_id')
        );

        PermissionChecker::assertHasSurveyPermission($survey, Permission::READ, $userId);

        // TODO: Add support for multilingual
        $language = $survey->language;
        $questionList = $survey->baseQuestions;

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

        if ((int) $question->parent_qid !== 0) {
            throw new InvalidPathParameterError(
                'Only base questions can be retrieved, and subquestions can be accessed via their parent questions'
            );
        }

        return new JsonResponse(
            data(
                self::makeQuestionData($question, $language)
            )
        );
    }

    private static function makeQuestionData(Question $question, string $language): array
    {
        $l10n = $question->questionl10ns[$language];
        $attributes = self::makeAttributesData($question, $language);

        return [
            'id' => (int) $question->qid,
            'group_id' => (int) $question->gid,

            'l10n' => [
                'text' => $l10n->question,
                'help' => $l10n->help,
            ],

            'type' => $question->type,

            'code' => $question->title,
            'is_hidden' => $attributes['hidden'] == '1',
            'is_other_enabled' => $question->other === 'Y',
            'mandatory' => $question->mandatory,
            'order' => (int) $question->question_order,
            'randomization_id' => $attributes['random_group'],
            'relevance' => $question->relevance,
            'theme_name' => $question->getQuestionTheme()->name,
            'validation_pattern' => $question->preg,

            'attributes' => $attributes,
            'subquestions' => self::makeSubQuestionsData($question, $language),
            'answers' => self::makeAnswersData($question, $language)
        ];
    }

    private static function makeAttributesData(Question $question, string $language): array
    {
        $result = [];

        foreach ($question->questionattributes as $attribute) {
            if (\in_array($attribute->language, ['', $language])) {
                $result[$attribute->attribute] = $attribute->value;
            }
        }

        return $result;
    }

    private static function makeSubQuestionsData(Question $question, string $language): ?array
    {
        $result = [];

        if (empty($question->subquestions)) {
            return null;
        }

        foreach ($question->subquestions as $subQuestion) {
            $result[] = [
                'code' => $subQuestion->title,
                'relevance' => $subQuestion->relevance,
                'scale' => $subQuestion->scale_id,
                'l10n' => [
                    'text' => $subQuestion->questionl10ns[$language]->question,
                ],
            ];
        }

        return $result;
    }

    private static function makeAnswersData(Question $question, string $language): ?array
    {
        $result = [];

        if (empty($question->answers)) {
            return null;
        }

        foreach ($question->answers as $answer) {
            $result[] = [
                'code' => $answer->code,
                'order' => $answer->sortorder,
                'scale' => $answer->scale_id,
                'l10n' => [
                    'text' => $answer->answerl10ns[$language]->answer,
                ]
            ];
        }

        return $result;
    }
}
