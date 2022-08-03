<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use Survey;
use Question;
use SurveyDynamic;
use LogicException;
use RuntimeException;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;
use MAChitgarha\LimeSurveyRestApi\Api\Permission;
use MAChitgarha\LimeSurveyRestApi\Api\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\AnswerFieldGenerator;
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
use Symfony\Component\HttpFoundation\Response;

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

        $recordData = $this->generateResponseRecord($data, $survey);
        $response = $this->makeResponse($recordData, $survey);

        if (!$response->id) {
            throw new RuntimeException('Cannot create response');
        }

        return new Response(
            data(),
            Response::HTTP_CREATED
        );
    }

    private function validateResponseData(array $responseData, Survey $survey): void
    {
        $dateTimeFormat = 'Y-m-d H:i:s';

        v::create()
            ->key('submit_time', v::dateTime($dateTimeFormat), false)
            ->key('start_time', v::dateTime($dateTimeFormat), false)
            ->key('end_time', v::dateTime($dateTimeFormat), false)
            ->key('answers', AnswerValidatorBuilder::buildForAll($survey))
            ->check($responseData);
    }

    private static function generateResponseRecord(array $responseData, Survey $survey): array
    {
        $result = [
            'submitdate' => $responseData['submit_time'],
            'startdate' => $responseData['start_time'],
            'datestamp' => $responseData['end_time'],

            // TODO: Add support for specifying it in the request
            'startlanguage' => $survey->language,

        ] + \iterator_to_array(
            AnswerFieldGenerator::generateForAll($survey, $responseData)
        );

        return $result;
    }

    private static function makeResponse(array $recordData, Survey $survey): SurveyDynamic
    {
        SurveyDynamic::sid($survey->sid);

        $response = new SurveyDynamic();

        // Make sure the record data
        \assert(
            \array_keys($response->tableSchema->columnNames) ==
                \array_keys($recordData)
        );

        $response->setAttributes($recordData, false);

        return $response;
    }
}

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController;

use Survey;
use Question;
use Generator;

use Respect\Validation\Validator;
use Respect\Validation\Validator as v;

/**
 * @internal
 */
class AnswerValidatorBuilder
{
    private const BUILDER_METHOD_MAP = [
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
        $method = self::BUILDER_METHOD_MAP[$question->type] ?? 'buildForDummy';
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

/**
 * @internal
 */
class AnswerFieldGenerator
{
    private const GENERATOR_METHOD_MAP = [
        Question::QT_5_POINT_CHOICE => 'generateFor5PointChoice',
    ];

    public static function generateForAll(Survey $survey, array $answersData): Generator
    {
        foreach ($survey->allQuestions as $question) {
            $method = self::GENERATOR_METHOD_MAP[$question->type] ?? 'generateForDummy';

            yield from self::{$method}(
                $question,
                $answersData[$question->qid]
            );
        }
    }

    private static function makeFieldName(Question $question)
    {
        return "{$question->sid}X{$question->gid}X{$question->qid}";
    }

    private static function generateForDummy(): Generator
    {
        yield from [];
    }

    private static function generateFor5PointChoice(Question $question, ?int $answer): Generator
    {
        yield self::makeFieldName($question) => $answer;
    }
}
