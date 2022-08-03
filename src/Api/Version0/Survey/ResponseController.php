<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use Question;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;
use MAChitgarha\LimeSurveyRestApi\Api\Permission;
use MAChitgarha\LimeSurveyRestApi\Api\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\AnswerValidatorBuilder;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\SurveyController;

use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;
use MAChitgarha\LimeSurveyRestApi\Error\RequiredKeyMissingError;

use MAChitgarha\LimeSurveyRestApi\Utility\ContentTypeValidator;

use Respect\Validation\Exceptions\ValidatorException;

use Respect\Validation\Validator;
use Respect\Validation\Validator as v;

use Symfony\Component\HttpFoundation\JsonResponse;

use function MAChitgarha\LimeSurveyRestApi\Helper\Response\data;

class ResponseController implements Controller
{
    use Traits\ContainerProperty;
    use Traits\AuthorizerGetter;
    use Traits\PathParameterGetter;
    use Traits\RequestGetter;
    use Traits\SerializerGetter;
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
        v::create()
            ->key('submit_time', v::intType(), false)
            ->key('start_time', v::intType(), false)
            ->key('end_time', v::intType(), false)
            ->key('answers', $this->buildAnswersValidators())
            ->check($data);
    }

    private function buildAnswersValidators(): Validator
    {
        $validator = v::create();

        $questionList = QuestionController::getQuestionList(
            $this->getPathParameter('survey_id')
        );

        foreach ($questionList as $question) {
            $validator->key(
                $question->qid,
                AnswerValidatorBuilder::build($question),
                false
            );
        }

        return $validator;
    }
}

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController;

use Question;

use Respect\Validation\Validator;
use Respect\Validation\Validator as v;

class AnswerValidatorBuilder
{
    public const BUILDER_METHODS_MAP = [
        Question::QT_5_POINT_CHOICE => 'buildFor5PointChoice',
    ];

    public static function build(Question $question): Validator
    {
        $method = self::BUILDER_METHODS_MAP[$question->type] ?? 'buildForDummy';
        $keyName = "answers.$question->qid";

        /** @var Validator $validator */
        $validator = self::{$method}();
        $validator->setName($keyName);

        return $question->mandatory === 'Y'
            ? $validator
            : v::nullable($validator)->setName($keyName);
    }

    // TODO: Get rid of it
    public static function buildForDummy(): Validator
    {
        return v::create();
    }

    public static function buildFor5PointChoice(): Validator
    {
        return v::create()
            ->intType()
            ->between(1, 5);
    }
}
