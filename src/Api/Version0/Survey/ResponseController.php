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
            ->key('answers', (new AnswerValidatorBuilder())->buildAll($survey))
            ->check($responseData);
    }

    private static function generateResponseRecord(array $responseData, Survey $survey): array
    {
        $currentTime = \date('Y-m-d H:i:s');

        $result = [
            'submitdate' => $responseData['submit_time'] ?? $currentTime,
            // TODO: Add support for specifying it in the request
            'startlanguage' => $survey->language,

        ] + \iterator_to_array(
            AnswerFieldGenerator::generateAll($survey, $responseData)
        );

        if ($survey->isDateStamp) {
            $result += [
                'startdate' => $responseData['start_time'] ?? $currentTime,
                'datestamp' => $responseData['end_time'] ?? $currentTime,
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

use Answer;
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
        Question::QT_1_ARRAY_DUAL => 'buildForArrayDual',
        Question::QT_5_POINT_CHOICE => 'buildFor5PointChoice',
        Question::QT_A_ARRAY_5_POINT => 'buildForArray5PointChoice',
        Question::QT_B_ARRAY_10_CHOICE_QUESTIONS => 'buildForArray10PointChoice',
        Question::QT_C_ARRAY_YES_UNCERTAIN_NO => 'buildForArrayYesNoUncertain',
        Question::QT_E_ARRAY_INC_SAME_DEC => 'buildForArrayIncreaseSameDecrease',
        Question::QT_F_ARRAY => 'buildForArrayWithPredefinedChoices',
        Question::QT_H_ARRAY_COLUMN => 'buildForArrayWithPredefinedChoices',
        Question::QT_L_LIST => 'buildForList',
        Question::QT_M_MULTIPLE_CHOICE => 'buildForMultipleChoice',
        Question::QT_O_LIST_WITH_COMMENT => 'buildForListWithComment',
        Question::QT_P_MULTIPLE_CHOICE_WITH_COMMENTS => 'buildForMultipleChoiceWithComments',
        Question::QT_EXCLAMATION_LIST_DROPDOWN => 'buildForList',
        Question::QT_COLON_ARRAY_NUMBERS => 'buildForArray2dNumbers',
        Question::QT_SEMICOLON_ARRAY_TEXT => 'buildForArray2dTexts',
    ];

    /** @var bool */
    private $skipSoftMandatoryQuestions = true;

    public function __construct(bool $skipSoftMandatoryQuestions = true)
    {
        $this->skipSoftMandatoryQuestions = $skipSoftMandatoryQuestions;
    }

    private function isMandatory(Question $question): bool
    {
        $mandatory = $question->mandatory;

        if ($mandatory === 'Y') {
            return true;
        }
        if ($mandatory === 'S') {
            return !$this->skipSoftMandatoryQuestions;
        }
        return false;
    }

    public function buildAll(Survey $survey): Validator
    {
        return v::keySet(...\array_map(
            // TODO: How nice it would look if we were able to use arrow functions! :)
            function (Question $question): Validator {
                return v::key(
                    $question->qid,
                    $this->build($question),
                    false
                );
            },
            // Strip out sub-questions, they will be handled by their parents
            \array_filter(
                $survey->allQuestions,
                function (Question $question): bool {
                    return $question->parent_qid === 0;
                }
            )
        ));
    }

    private function build(Question $question): Validator
    {
        $method = self::BUILDER_METHOD_MAP[$question->type] ?? 'buildDummy';
        $keyName = "answers.$question->qid";

        /** @var Validator $validator */
        $validator = $this->$method($question);
        $validator->setName($keyName);

        return $this->isMandatory($question)
            ? $validator
            : v::nullable($validator)->setName($keyName);
    }

    // TODO: Get rid of it
    private function buildDummy(): Validator
    {
        return v::create();
    }

    private function buildFor5PointChoice(): Validator
    {
        return v::create()
            ->intType()
            ->between(1, 5);
    }

    private function buildForList(Question $question): Validator
    {
        return v::create()
            ->stringType()
            ->in(
                \array_column($question->answers, 'code')
            );
    }

    private function buildForListWithComment(Question $question): Validator
    {
        return v::create()
            ->key('code', $this->buildForList($question))
            ->key('comment', v::stringType(), false);
    }

    private function buildForArray(
        Question $question,
        callable $valueValidatorBuilder,
        array $subQuestions = null
    ): Validator {
        $mandatory = $this->isMandatory($question);

        return v::keySet(...\array_map(
            function (Question $subQuestion) use ($valueValidatorBuilder, $question, $mandatory) {
                $validator = $valueValidatorBuilder();
                return v::key(
                    $subQuestion->title,
                    $mandatory ? $validator : v::nullable($validator),
                    $mandatory
                );
            },
            $subQuestions ?? $question->subquestions
        ));
    }

    private function buildForArraySomePointChoice(Question $question, int $count): Validator
    {
        return $this->buildForArray($question, function () use ($count) {
            return v::create()
                ->intType()
                ->between(1, $count);
        });
    }

    private function buildForArray5PointChoice(Question $question): Validator
    {
        return $this->buildForArraySomePointChoice($question, 5);
    }

    private function buildForArray10PointChoice(Question $question): Validator
    {
        return $this->buildForArraySomePointChoice($question, 10);
    }

    /**
     * @param string[] $allowedValues
     * @return Validator
     */
    private function buildForArrayOfAllowedStrings(
        Question $question,
        array $allowedValues
    ): Validator {
        return $this->buildForArray($question, function () use ($allowedValues) {
            return v::create()
                ->stringType()
                ->in($allowedValues);
        });
    }

    private function buildForArrayIncreaseSameDecrease(Question $question): Validator
    {
        return $this->buildForArrayOfAllowedStrings($question, ['I', 'S', 'D']);
    }

    private function buildForArrayWithPredefinedChoices(Question $question): Validator
    {
        return $this->buildForArrayOfAllowedStrings(
            $question,
            \array_column($question->answers, 'code')
        );
    }

    private function buildForArrayYesNoUncertain(Question $question): Validator
    {
        return $this->buildForArrayOfAllowedStrings($question, ['Y', 'N', 'U']);
    }

    private function buildForArrayDual(Question $question): Validator
    {
        $filterPossibleAnswers = function (int $scaleId) use ($question) {
            $possibleAnswers = [];

            foreach ($question->answers as $answer) {
                if ($answer->scale_id === $scaleId) {
                    $possibleAnswers[] = $answer->code;
                }
            }

            return $possibleAnswers;
        };

        $makeKey = function (int $keyName) use ($filterPossibleAnswers, $question) {
            return v::key(
                $keyName,
                v::in($filterPossibleAnswers($keyName)),
                $this->isMandatory($question)
            );
        };

        return $this->buildForArray($question, function () use ($makeKey) {
            return v::create()
                ->arrayType()
                ->length(1, 2, true)
                ->keySet($makeKey(0), $makeKey(1));
        });
    }

    private function buildForArray2d(
        Question $question,
        callable $valueValidatorBuilder
    ): Validator {
        $filterSubQuestions = function (int $scaleId) use ($question) {
            return \array_filter(
                $question->subquestions,
                function (Question $subQuestion) use ($scaleId) {
                    return $subQuestion->scale_id === $scaleId;
                }
            );
        };

        return $this->buildForArray(
            $question,
            function () use ($question, $valueValidatorBuilder, $filterSubQuestions) {
                return $this->buildForArray(
                    $question,
                    $valueValidatorBuilder,
                    $filterSubQuestions(1)
                );
            },
            $filterSubQuestions(0)
        );
    }

    private function buildForArray2dNumbers(Question $question): Validator
    {
        return $this->buildForArray2d($question, function () {
            return v::create()
                ->intType()
                ->between(1, 10);
        });
    }

    private function buildForArray2dTexts(Question $question): Validator
    {
        return $this->buildForArray2d($question, function () {
            return v::create()
                ->stringType()
                ->length(1, null, true);
        });
    }

    private function buildForMultipleChoice(Question $question): Validator
    {
        $validator = $this->buildForArray($question, function () {
            return v::boolType();
        });

        if ($this->isMandatory($question)) {
            // At least one choice must have been selected
            $validator->contains(true);
        }

        return $validator;
    }

    private function buildForMultipleChoiceWithComments(Question $question): Validator
    {
        $validator = $this->buildForArray($question, function () {
            return v::oneOf(
                v::create()
                    ->key('is_selected', v::identical(true))
                    ->key('comment', v::stringType()),
                v::create()
                    ->key('is_selected', v::identical(false))
                    ->key('comment', v::nullType(), false)
            );
        });

        if ($this->isMandatory($question)) {
            // At least one choice must have been selected
            $validator->callback(function (array $items) {
                foreach ($items as $item) {
                    if ($item['is_selected'] ?? null === true) {
                        return true;
                    }
                }
                return false;
            });
        }

        return $validator;
    }
}

/**
 * @internal
 */
class AnswerFieldGenerator
{
    private const GENERATOR_METHOD_MAP = [
        Question::QT_1_ARRAY_DUAL => 'generateArrayDual',
        Question::QT_5_POINT_CHOICE => 'generate',
        Question::QT_A_ARRAY_5_POINT => 'generateArray',
        Question::QT_B_ARRAY_10_CHOICE_QUESTIONS => 'generateArray',
        Question::QT_C_ARRAY_YES_UNCERTAIN_NO => 'generateArray',
        Question::QT_E_ARRAY_INC_SAME_DEC => 'generateArray',
        Question::QT_F_ARRAY => 'generateArray',
        Question::QT_H_ARRAY_COLUMN => 'generateArray',
        Question::QT_L_LIST => 'generate',
        Question::QT_M_MULTIPLE_CHOICE => 'generateMultipleChoice',
        Question::QT_O_LIST_WITH_COMMENT => 'generateListWithComment',
        Question::QT_P_MULTIPLE_CHOICE_WITH_COMMENTS => 'generateMultipleChoiceWithComments',
        Question::QT_EXCLAMATION_LIST_DROPDOWN => 'generate',
        Question::QT_COLON_ARRAY_NUMBERS => 'generateArray2d',
        Question::QT_SEMICOLON_ARRAY_TEXT => 'generateArray2d',
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

    private static function generateArrayDual(Question $question, ?array $answer): Generator
    {
        foreach ($answer as $subQuestionCode => $subAnswerPair) {
            foreach ($subAnswerPair as $key => $subAnswerPairItem) {
                yield self::makeSubQuestionFieldName($question, $subQuestionCode) . "#$key"
                    => $subAnswerPairItem;
            }
        }
    }

    private static function generateArray2d(Question $question, ?array $answer): Generator
    {
        foreach ($answer as $yScaleSubQuestionCode => $yAnswer) {
            foreach ($yAnswer as $xScaleSubQuestionCode => $answerValue) {
                yield
                    self::makeSubQuestionFieldName(
                        $question,
                        $yScaleSubQuestionCode,
                        $xScaleSubQuestionCode
                    )
                    => $answerValue;
            }
        }
    }

    private static function generateMultipleChoice(Question $question, ?bool $answer): Generator
    {
        yield self::makeQuestionFieldName($question) => $answer ? 'Y' : null;
    }

    private static function generateMultipleChoiceWithComments(
        Question $question,
        ?array $answer
    ): Generator {
        yield self::generateMultipleChoice($question, $answer['is_selected']);
        yield self::makeQuestionFieldName($question) . 'comment' => $answer['comment'] ?? null;
    }
}
