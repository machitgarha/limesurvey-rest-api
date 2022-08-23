<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;
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

        $userId = $this->getAuthorizer()->authorize()->getId();

        $survey = SurveyHelper::get(
            $surveyId = $this->getPathParameterAsInt('survey_id')
        );

        PermissionChecker::assertHasSurveyPermission($survey, Permission::READ, $userId);

        // TODO: Add support for multilingual
        $language = $survey->language;
        $questionList = $survey->allQuestions;

        $data = [];
        foreach ($questionList as $question) {
            $l10n = $question->questionl10ns[$language];
            $data[] = [
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
