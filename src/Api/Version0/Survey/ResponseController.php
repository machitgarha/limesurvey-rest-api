<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use Question;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;
use MAChitgarha\LimeSurveyRestApi\Api\Permission;
use MAChitgarha\LimeSurveyRestApi\Api\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\SurveyController;

use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;
use MAChitgarha\LimeSurveyRestApi\Error\RequiredKeyMissingError;

use MAChitgarha\LimeSurveyRestApi\Utility\ContentTypeValidator;

use Respect\Validation\Validator as v;

use Symfony\Component\HttpFoundation\JsonResponse;

use function MAChitgarha\LimeSurveyRestApi\Helper\Response\data;

class ResponseController implements Controller
{
    use Traits\ContainerProperty;
    use Traits\AuthorizerGetter;
    use Traits\PathParameterGetter;
    use Traits\RequestGetter;
    use Traits\RequestBodyDecoder;

    public const PATH = '/surveys/{survey_id}/responses';

    public function list(): JsonResponse
    {
        throw new NotImplementedError();
    }

    public function new(): Response
    {
        ContentTypeValidator::validateIsJson($this->getRequest());

        $this->authorize();

        $data = $this->decodeJsonRequestBodyInnerData();
        $this->validateDataForNew($data);

        throw new NotImplementedError();
    }

    private function validateDataForNew(array $data): void
    {
        v   ::key('submit_date', v::intType())
            ->key('answers', v::arrayType())
            ->check($data);

        $validationMethodList = [
            Question::QT_5_POINT_CHOICE => 'validate5PointChoiceAnswer',
        ];

        $questionList = QuestionController::getQuestionList(
            $this->getPathParameter('survey_id')
        );

        foreach ($questionList as $question) {
            $answer = $data['answers'][$question->qid] ?? null;

            if (isset($answer)) {
                throw new RequiredKeyMissingError(
                    "Answer for question with ID '$question->qid' missing"
                );
            }

            $validationMethod = $validationMethodList[$question->type];
            $this->$validationMethod($answer);
        }
    }

    private function validate5PointChoiceQuestionType(int $answer): void
    {
        v   ::between(0, 5)
            ->check($answer);
    }
}
