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
        $response->encryptSave();

        if (!$response->id) {
            throw new RuntimeException('Cannot create response');
        }

        return new Response('', Response::HTTP_CREATED);
    }

    private function validateResponseData(array $responseData, Survey $survey): void
    {
        $dateTimeFormat = 'Y-m-d H:i:s';

        // TODO: Use a custom key rule to generate full indexes in exception messages
        v::create()
            ->key('submit_time', v::dateTime($dateTimeFormat), false)
            ->key('start_time', v::dateTime($dateTimeFormat), false)
            ->key('end_time', v::dateTime($dateTimeFormat), false)
            ->key('answers', AnswerValidatorBuilder::buildAll($survey))
            ->check($responseData);
    }

    private static function generateResponseRecord(array $responseData, Survey $survey): array
    {
        $result = [
            'submitdate' => $responseData['submit_time'],
            // TODO: Add support for specifying it in the request
            'startlanguage' => $survey->language,

        ] + \iterator_to_array(
            AnswerFieldGenerator::generateAll($survey, $responseData)
        );

        if ($survey->isDateStamp) {
            $result += [
                'startdate' => $responseData['start_time'],
                'datestamp' => $responseData['end_time'],
            ];
        }

        return $result;
    }

    private static function makeResponse(array $recordData, Survey $survey): SurveyDynamic
    {
        SurveyDynamic::sid($survey->sid);

        $response = new SurveyDynamic();

        /*
         * Make sure the data doesn't have extra attributes (i.e. fields). Having less attributes
         * than expected should not be a problem, as it finally will be caught by the active
         * record itself if answer for a mandatory question isn't provided.
         */
        \assert(
            [] === \array_diff(
                \array_keys($recordData),
                $response->tableSchema->columnNames
            )
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
        Question::QT_A_ARRAY_5_POINT => 'buildForArray5PointChoice',
        Question::QT_B_ARRAY_10_CHOICE_QUESTIONS => 'buildForArray10PointChoice',
        Question::QT_E_ARRAY_INC_SAME_DEC => 'buildForArrayIncreaseSameDecrease',
        Question::QT_L_LIST => 'buildForList',
        Question::QT_O_LIST_WITH_COMMENT => 'buildForListWithComment',
        Question::QT_EXCLAMATION_LIST_DROPDOWN => 'buildForList',
    ];

    public static function buildAll(Survey $survey): Validator
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
        $method = self::BUILDER_METHOD_MAP[$question->type] ?? 'buildDummy';
        $keyName = "answers.$question->qid";

        /** @var Validator $validator */
        $validator = self::{$method}($question);
        $validator->setName($keyName);

        return $question->mandatory === 'Y'
            ? $validator
            : v::nullable($validator)->setName($keyName);
    }

    // TODO: Get rid of it
    private static function buildDummy(): Validator
    {
        return v::create();
    }

    private static function buildFor5PointChoice(): Validator
    {
        return v::create()
            ->intType()
            ->between(1, 5);
    }

    private static function buildForList(Question $question): Validator
    {
        $answersCode = \array_column($question->answers, 'code');

        return v::create()
            ->stringType()
            ->in($answersCode);
    }

    private static function buildForListWithComment(Question $question): Validator
    {
        return v::create()
            ->key('code', self::buildForList($question))
            ->key('comment', v::stringType());
    }

    private static function buildForArray(
        Question $question,
        callable $valueValidatorBuilder
    ): Validator {
        $validator = v::create()->arrayType();

        foreach ($question->subquestions as $subQuestion) {
            $validator->key(
                $subQuestion->title,
                $valueValidatorBuilder(),
                // TODO: Add a case for soft mandatories (e.g. query parameter to bypass it)
                $question->mandatory === 'Y'
            );
        }

        return $validator;
    }

    private static function buildForArraySomePointChoice(Question $question, int $count): Validator
    {
        return self::buildForArray($question, function () use ($count) {
            return v::create()
                ->intType()
                ->between(1, $count);
        });
    }

    private static function buildForArray5PointChoice(Question $question): Validator
    {
        return self::buildForArraySomePointChoice($question, 5);
    }

    private static function buildForArray10PointChoice(Question $question): Validator
    {
        return self::buildForArraySomePointChoice($question, 10);
    }

    private static function buildForArrayIncreaseSameDecrease(Question $question): Validator
    {
        return self::buildForArray($question, function () use ($count) {
            return v::create()
                ->stringType()
                ->in(['I', 'S', 'D']);
        });
    }
}

/**
 * @internal
 */
class AnswerFieldGenerator
{
    private const GENERATOR_METHOD_MAP = [
        Question::QT_5_POINT_CHOICE => 'generate',
        Question::QT_A_ARRAY_5_POINT => 'generateArray',
        Question::QT_B_ARRAY_10_CHOICE_QUESTIONS => 'generateArray',
        Question::QT_E_ARRAY_INC_SAME_DEC => 'generateArray',
        Question::QT_L_LIST => 'generate',
        Question::QT_O_LIST_WITH_COMMENT => 'generateListWithComment',
        Question::QT_EXCLAMATION_LIST_DROPDOWN => 'generate',
    ];

    public static function generateAll(Survey $survey, array $answersData): Generator
    {
        foreach ($survey->allQuestions as $question) {
            $method = self::GENERATOR_METHOD_MAP[$question->type] ?? 'generateDummy';

            yield from self::{$method}(
                $question,
                $answersData['answers'][$question->qid]
            );
        }
    }

    private static function makeQuestionFieldName(Question $question): string
    {
        return "{$question->sid}X{$question->gid}X{$question->qid}";
    }

    private static function makeSubQuestionFieldName(
        Question $question,
        string ...$subQuestionCodes
    ): string {
        return self::makeQuestionFieldName($question) .
            \implode('_', $subQuestionCodes);
    }

    private static function generateDummy(Question $question): Generator
    {
        yield from [];
    }

    private static function generate(Question $question, $answer): Generator
    {
        yield self::makeQuestionFieldName($question) => $answer;
    }

    private static function generateListWithComment(Question $question, ?array $answer): Generator
    {
        yield from self::generate($question, $answer['code'] ?? null);
        yield self::makeQuestionFieldName($question) . 'comment' => $answer['comment'] ?? null;
    }

    private static function generateArray(Question $question, ?array $answer): Generator
    {
        foreach ($answer as $subQuestionCode => $subAnswer) {
            yield self::makeSubQuestionFieldName($question, $subQuestionCode) => $subAnswer;
        }
    }
}
