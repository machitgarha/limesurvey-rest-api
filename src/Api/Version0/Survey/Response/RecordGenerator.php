<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response;

use Survey;
use Question;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\RecordGenerator\AnswerGenerator;

class RecordGenerator
{
    public static function generate(array $responseData, Survey $survey): array
    {
        $currentTime = \date('Y-m-d H:i:s');

        $result = [
            'submitdate' => $responseData['submit_time'] ?? $currentTime,
            // TODO: Add support for specifying it in the request
            'startlanguage' => $survey->language,

        ] + \iterator_to_array(
            (new AnswerGenerator($responseData['answers']))->generateAll($survey)
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

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\RecordGenerator;

use Survey;
use Question;
use Generator;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\FieldNameGenerator;

use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;

use MAChitgarha\LimeSurveyRestApi\Helper\ResponseGeneratorHelper;

/**
 * @internal
 */
class AnswerGenerator
{
    private const METHOD_TO_QUESTION_TYPE_LIST_MAPPING = [
        'generateBool' => [
            Question::QT_Y_YES_NO_RADIO,
        ],
        'generateString' => [
            Question::QT_5_POINT_CHOICE,
            Question::QT_D_DATE,
            Question::QT_G_GENDER,
            Question::QT_I_LANGUAGE,
            Question::QT_N_NUMERICAL,
            Question::QT_S_SHORT_FREE_TEXT,
            Question::QT_T_LONG_FREE_TEXT,
            Question::QT_U_HUGE_FREE_TEXT,
            Question::QT_ASTERISK_EQUATION,
            Question::QT_EXCLAMATION_LIST_DROPDOWN,
        ],
        'generateRanking' => [
            Question::QT_R_RANKING,
        ],
        'generateFile' => [
            Question::QT_VERTICAL_FILE_UPLOAD,
        ],
        'generateList' => [
            Question::QT_L_LIST,
        ],
        'generateListWithComment' => [
            Question::QT_O_LIST_WITH_COMMENT,
        ],
        'generateStringSubQuestions' => [
            Question::QT_A_ARRAY_5_POINT,
            Question::QT_B_ARRAY_10_CHOICE_QUESTIONS,
            Question::QT_C_ARRAY_YES_UNCERTAIN_NO,
            Question::QT_E_ARRAY_INC_SAME_DEC,
            Question::QT_F_ARRAY,
            Question::QT_H_ARRAY_COLUMN,
            Question::QT_K_MULTIPLE_NUMERICAL,
            Question::QT_Q_MULTIPLE_SHORT_TEXT,
        ],
        'generateArrayDual' => [
            Question::QT_1_ARRAY_DUAL,
        ],
        'generateMultipleChoice' => [
            Question::QT_M_MULTIPLE_CHOICE,
        ],
        'generateMultipleChoiceWithComments' => [
            Question::QT_P_MULTIPLE_CHOICE_WITH_COMMENTS,
        ],
        'generateStringSubQuestions2d' => [
            Question::QT_COLON_ARRAY_NUMBERS,
            Question::QT_SEMICOLON_ARRAY_TEXT,
        ],
    ];

    /** @var array[] */
    private $questionTypeToMethodMapping;

    /** @var array */
    private $answersData;

    public function __construct(array $answersData)
    {
        $this->questionTypeToMethodMapping = ResponseGeneratorHelper::makeQuestionTypeToMethodMap(
            self::METHOD_TO_QUESTION_TYPE_LIST_MAPPING
        );

        $this->answersData = $answersData;
    }

    public function generateAll(Survey $survey): Generator
    {
        foreach ($survey->allQuestions as $question) {
            $method = $this->questionTypeToMethodMapping[$question->type];

            if ($question->parent_qid === 0) {
                yield from self::{$method}(
                    $this->answersData[$question->qid] ?? null,
                    FieldNameGenerator::generate($question),
                    $question
                );
            }
        }
    }

    private function generateBool(?bool $answer, string $fieldName): Generator
    {
        // NOTE: Don't remove the parantheses for PHP 8.x compatibility
        yield $fieldName => $answer === null ? '' : ($answer ? 'Y' : 'N');
    }

    private function generateString(?string $answer, string $fieldName): Generator
    {
        yield $fieldName => $answer ?? '';
    }

    private function generateRanking(?array $answer, string $fieldName, Question $question): Generator
    {
        $answersCount = \count($question->answers);
        for ($ranking = 0; $ranking < $answersCount; $ranking++) {
            yield "{$fieldName}{$ranking}" => $answer[$ranking] ?? '';
        }
    }

    private function generateFile(?array $answer, string $fieldName, Question $question): Generator
    {
        if (empty($answer)) {
            yield $fieldName => '';
            yield "{$fieldName}_filecount" => 0;
        } else {
            // TODO
            throw new NotImplementedError();
        }
    }

    private function generateList(?array $answer, string $fieldName, Question $question): Generator
    {
        if ($answer['other'] ?? null) {
            yield $fieldName => '-oth-';
            yield "{$fieldName}other" => $answer['value'] ?? '';
        } else {
            yield $fieldName => $answer['value'];
        }
    }

    private function generateListWithComment(?array $answer, string $fieldName, Question $question): Generator
    {
        yield $fieldName => $answer['code'];
        yield "{$fieldName}comment" => $answer['comment'];
    }

    private function generateSubQuestions(
        ?array $answer,
        string $fieldNameBase,
        Question $question,
        callable $fn
    ): Generator {
        foreach ($question->subquestions as $subQuestion) {
            yield from $fn(
                $fieldNameBase . FieldNameGenerator::generateSubQuestionSuffix($subQuestion),
                $answer[$subQuestion->title] ?? null
            );
        }
    }

    private function generateStringSubQuestions(?array $answer, string $fieldNameBase, Question $question): Generator
    {
        yield from $this->generateSubQuestions(
            $answer,
            $fieldNameBase,
            $question,
            /** @param int|float|string|null $subAnswer */
            function (string $fieldName, $subAnswer): Generator {
                yield $fieldName => $subAnswer ?? '';
            }
        );
    }

    private function generateArrayDual(?array $answer, string $fieldNameBase, Question $question): Generator
    {
        yield from $this->generateSubQuestions(
            $answer,
            $fieldNameBase,
            $question,
            function (string $fieldName, ?string $subAnswer): Generator {
                yield "$subQuestionFieldName#0" => $subAnswer[0] ?? '';
                yield "$subQuestionFieldName#1" => $subAnswer[1] ?? '';
            }
        );
    }

    private function generateMultipleChoice(?array $answer, string $fieldNameBase, Question $question): Generator
    {
        yield from $this->generateSubQuestions(
            $answer,
            $fieldNameBase,
            $question,
            function (string $fieldName, ?array $subAnswer): Generator {
                // TODO: Return empty string when selected is false?
                yield from $this->generateBool($subAnswer['selected'], $fieldName);
            }
        );

        if ($question->other === 'Y') {
            yield "{$fieldNameBase}other" => $answer['other']['other_value'] ?? '';
        }
    }

    private function generateMultipleChoiceWithComments(
        ?array $answer,
        string $fieldNameBase,
        Question $question
    ): Generator {
        yield from $this->generateSubQuestions(
            $answer,
            $fieldNameBase,
            $question,
            function (string $fieldName, ?array $subAnswer): Generator {
                // TODO: Return empty string when selected is false?
                yield from $this->generateBool($subAnswer['selected'], $fieldName);
                yield "{$fieldName}comment" => $subAnswer['comment'] ?? '';
            }
        );

        if ($question->other === 'Y') {
            yield "{$fieldNameBase}other" => $answer['other']['other_value'] ?? '';
            yield "{$fieldNameBase}othercomment" => $answer['other']['comment'] ?? '';
        }
    }

    private function generateStringSubQuestions2d(?array $answer, string $fieldNameBase, Question $question): Generator
    {
        [$yScaleSubQuestionList, $xScaleSubQuestionList] =
            ResponseGeneratorHelper::splitSubQuestionsBasedOnScale2d($question);

        foreach ($yScaleSubQuestionList as $yScaleSubQuestion) {
            foreach ($xScaleSubQuestionList as $xScaleSubQuestion) {
                yield
                    $fieldNameBase . FieldNameGenerator::generateSubQuestionSuffix(
                        $yScaleSubQuestion,
                        $xScaleSubQuestion
                    )
                    => $answer[$yScaleSubQuestion->title][$xScaleSubQuestion->title] ?? '';
            }
        }
    }
}
