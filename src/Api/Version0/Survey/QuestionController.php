<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use Question;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;
use MAChitgarha\LimeSurveyRestApi\Api\Permission;
use MAChitgarha\LimeSurveyRestApi\Api\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\SurveyController;

use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;

use MAChitgarha\LimeSurveyRestApi\Utility\ContentTypeValidator;

use Symfony\Component\HttpFoundation\JsonResponse;

use function MAChitgarha\LimeSurveyRestApi\Helper\Response\data;

class QuestionController implements Controller
{
    use Traits\ContainerProperty;
    use Traits\AuthorizerGetter;
    use Traits\PathParameterGetter;
    use Traits\RequestGetter;

    public const PATH = '/surveys/{survey_id}/questions';
    public const PATH_BY_ID = '/surveys/{survey_id}/questions/{question_id}';

    public function list(): JsonResponse
    {
        ContentTypeValidator::validateIsJson($this->getRequest());

        $userId = $this->getAuthorizer()->authorize()->getId();

        $survey = SurveyController::getSurvey(
            $surveyId = $this->getPathParameter('survey_id')
        );

        PermissionChecker::assertHasSurveyPermission($survey, Permission::READ, $userId);

        // TODO: Add support for multilingual
        $language = $survey->language;
        $questionList = $survey->allQuestions;

        $data = [];
        foreach ($questionList as $question) {
            $l10n = $question->questionl10ns[$sLanguage];
            $data[] = [
                'id' => $question->qid,
                'group_id' => $question->gid,
                'type' => $question->type,
                'code' => $question->title,
                'order_in_group' => $question->question_order,
                'l10n' => [
                    'question' => $l10n->question,
                    'help' => $l10n->help,
                ],
            ];
        }

        return new JsonResponse(
            data($data),
            JsonResponse::HTTP_OK
        );
    }

    /**
     * @return Question[]
     */
    public static function getQuestionList(int $surveyId): array
    {
        $survey = SurveyController::getSurvey($surveyId);
        return $survey->allQuestions;
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
