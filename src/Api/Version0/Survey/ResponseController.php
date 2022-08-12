<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use Yii;
use Index;
use Survey;
use Question;
use CController;
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
        $recordData = $this->generateResponseRecord($data, $survey);

        $this->validateResponseData($recordData, $survey);

        $response = $this->makeResponse($recordData, $survey);
        $response->encryptSave();

        if (!$response->id) {
            throw new RuntimeException('Cannot create response');
        }

        return new Response('', Response::HTTP_CREATED);
    }

    private function validateResponseData(array $responseData, Survey $survey): void
    {
        Yii::import('application.controllers.survey.index', true);

        $indexPage = new Index($this, 1);
        $indexPage->action();

        if (!App()->db->schema->getTable($survey->responsesTableName)) {
            if ($survey->active !== 'N') {
                throw new LogicException('Survey responses table name is not created');
            } else {
                throw new SurveyNotActiveError();
            }
        }
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

/**
 * @internal
 */
class AnswerFieldGenerator
{
    private const GENERATOR_METHOD_MAP = [
        Question::QT_1_ARRAY_DUAL => 'generateArrayDual',
        Question::QT_5_POINT_CHOICE => 'generate',
        Question::QT_A_ARRAY_5_POINT => 'generateSubQuestions',
        Question::QT_B_ARRAY_10_CHOICE_QUESTIONS => 'generateSubQuestions',
        Question::QT_C_ARRAY_YES_UNCERTAIN_NO => 'generateSubQuestions',
        Question::QT_E_ARRAY_INC_SAME_DEC => 'generateSubQuestions',
        Question::QT_F_ARRAY => 'generateSubQuestions',
        Question::QT_H_ARRAY_COLUMN => 'generateSubQuestions',
        Question::QT_L_LIST => 'generate',
        Question::QT_M_MULTIPLE_CHOICE => 'generateMultipleChoice',
        Question::QT_O_LIST_WITH_COMMENT => 'generateListWithComment',
        Question::QT_P_MULTIPLE_CHOICE_WITH_COMMENTS => 'generateMultipleChoiceWithComments',
        Question::QT_Q_MULTIPLE_SHORT_TEXT => 'generateSubQuestions',
        Question::QT_S_SHORT_FREE_TEXT => 'generate',
        Question::QT_T_LONG_FREE_TEXT => 'generate',
        Question::QT_U_HUGE_FREE_TEXT => 'generate',
        Question::QT_EXCLAMATION_LIST_DROPDOWN => 'generate',
        Question::QT_COLON_ARRAY_NUMBERS => 'generateArray2d',
        Question::QT_SEMICOLON_ARRAY_TEXT => 'generateArray2d',
    ];

    public static function generateAll(Survey $survey, array $answersData): Generator
    {
        foreach ($survey->allQuestions as $question) {
            $method = self::GENERATOR_METHOD_MAP[$question->type] ?? 'generateDummy';

            if ($question->parent_qid === 0) {
                yield from self::{$method}(
                    $question,
                    $answersData['answers'][$question->qid]
                );
            }
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

    private static function generateSubQuestions(Question $question, ?array $answer): Generator
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

    private static function generateMultipleChoice(Question $question, ?array $answer): Generator
    {
        foreach ($answer as $subQuestionCode => $answerValue) {
            yield
                self::makeSubQuestionFieldName($question, $subQuestionCode)
                => $answerValue ? 'Y' : null;
        }
    }

    private static function generateMultipleChoiceWithComments(
        Question $question,
        ?array $answer
    ): Generator {
        foreach ($answer as $subQuestionCode => $answerValue) {
            $subQuestionFieldName = self::makeSubQuestionFieldName($question, $subQuestionCode);

            yield $subQuestionFieldName => $answerValue['is_selected'] ? 'Y' : null;
            yield $subQuestionFieldName . 'comment' => $answerValue['comment'] ?? null;
        }
    }
}
