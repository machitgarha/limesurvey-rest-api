<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use Question;
use SurveyDynamic;
use LogicException;
use RuntimeException;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;
use MAChitgarha\LimeSurveyRestApi\Api\Permission;
use MAChitgarha\LimeSurveyRestApi\Api\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\AnswerValidatorBuilder;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\SurveyController;

use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;
use MAChitgarha\LimeSurveyRestApi\Error\SurveyNotActiveError;
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

        $userId = $this->authorize()->getId();

        $survey = SurveyController::getSurvey(
            $surveyId = (int) $this->getPathParameter('survey_id')
        );

        PermissionChecker::assertHasSurveyPermission(
            $survey,
            Permission::CREATE,
            $userId,
            'responses'
        );

        $data = $this->decodeJsonRequestBodyInnerData();
        $this->validateResponseData($data, $survey);

        if (!App()->db->schema->getTable($survey->responsesTableName)) {
            if ($survey->active !== 'N') {
                throw new LogicException('Survey responses table name is not created');
            } else {
                throw new SurveyNotActiveError();
            }
        }

        throw new NotImplementedError();
    }

    private function validateResponseData(array $responseData, Survey $survey): void
    {
        v::create()
            ->key('submit_time', v::intType(), false)
            ->key('start_time', v::intType(), false)
            ->key('end_time', v::intType(), false)
            ->key('answers', AnswerValidatorBuilder::buildForAll($survey))
            ->check($responseData);
    }
}

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController;

use Question;

use Respect\Validation\Validator;
use Respect\Validation\Validator as v;

/**
 * @internal
 */
class AnswerValidatorBuilder
{
    public const BUILDER_METHODS_MAP = [
        Question::QT_5_POINT_CHOICE => 'buildFor5PointChoice',
    ];

    public static function buildForAll(Survey $survey): Validator
    {
        $validator = v::create();

        foreach ($survey->allQuestions as $question) {
            $validator->key(
                $question->qid,
                self::build($question),
                false
            );
        }

        return $validator;
    }

    private static function build(Question $question): Validator
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
    private static function buildForDummy(): Validator
    {
        return v::create();
    }

    private static function buildFor5PointChoice(): Validator
    {
        return v::create()
            ->intType()
            ->between(1, 5);
    }
}
