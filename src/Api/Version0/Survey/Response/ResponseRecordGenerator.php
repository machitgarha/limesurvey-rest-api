<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response;

use Answer;
use Survey;
use Question;
use Generator;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ResponseRecordGenerator\{
    AnswerFieldGenerator
};

class ResponseRecordGenerator
{
    public static function generate(array $responseData, Survey $survey)
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
}

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ResponseRecordGenerator;

use Question;

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
        Question::QT_D_DATE => 'generate',
        Question::QT_E_ARRAY_INC_SAME_DEC => 'generateSubQuestions',
        Question::QT_F_ARRAY => 'generateSubQuestions',
        Question::QT_G_GENDER => 'generate',
        Question::QT_H_ARRAY_COLUMN => 'generateSubQuestions',
        Question::QT_I_LANGUAGE => 'generate',
        Question::QT_K_MULTIPLE_NUMERICAL => 'generateSubQuestions',
        Question::QT_L_LIST => 'generate',
        Question::QT_M_MULTIPLE_CHOICE => 'generateMultipleChoice',
        Question::QT_N_NUMERICAL => 'generate',
        Question::QT_O_LIST_WITH_COMMENT => 'generateListWithComment',
        Question::QT_P_MULTIPLE_CHOICE_WITH_COMMENTS => 'generateMultipleChoiceWithComments',
        Question::QT_Q_MULTIPLE_SHORT_TEXT => 'generateSubQuestions',
        Question::QT_R_RANKING => 'generateRanking',
        Question::QT_S_SHORT_FREE_TEXT => 'generate',
        Question::QT_T_LONG_FREE_TEXT => 'generate',
        Question::QT_U_HUGE_FREE_TEXT => 'generate',
        Question::QT_Y_YES_NO_RADIO => 'generate',
        Question::QT_ASTERISK_EQUATION => 'generate',
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

    private static function generateRanking(Question $question, ?array $answer): Generator
    {
        foreach ($answer as $ranking => $answerItem) {
            $fieldName = self::makeQuestionFieldName($question) . $ranking;
            yield $fieldName => $answerItem;
        }
    }
}
