<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response;

use Survey;
use Question;
use RuntimeException;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\RecordGenerator\AnswerGenerator;

class PostDataGenerator
{
    /** @var array[] */
    private $responseData;

    /** @var array */
    private $surveyInfo;

    /** @var Survey */
    private $survey;

    public function __construct(array $responseData, array $surveyInfo)
    {
        $this->responseData = $responseData;
        $this->surveyInfo = $surveyInfo;
        $this->survey = $surveyInfo['oSurvey'];
    }

    public function generate(): array
    {
        // TODO: start_time, lastanswer?
        return [
            'sid' => $this->survey->sid,
            'ajax' => 'off',
        ]
            + $this->generateMoveAndSkipSoftMandatory()
            + $this->generateStep()
            + $this->generateAnswers()
            + $this->generateRelevances()
            + $this->generateStartEndTimes()
        ;
    }

    private function generateMoveAndSkipSoftMandatory(): array
    {
        $move = 'movenext';
        return [
            'move' => $move,
        ];
    }

    private function generateStep(): array
    {
        return ['thisstep' => $this->responseData['step']];
    }

    private function generateAnswers(): array
    {
        $answers = \iterator_to_array(
            (new AnswerGenerator($this->responseData['answers']))
                ->generateAll($this->survey)
        );

        $fieldNames = \implode('|', \array_keys($answers));

        return $answers + [
            'fieldnames' => $fieldNames,
        ];
    }

    private function generateStartEndTimes(): array
    {
        if ($this->survey->isDateStamp) {
            $currentTime = \date('Y-m-d H:i:s');

            return [
                'startdate' => $this->responseData['start_time'] ?? $currentTime,
                'datestamp' => $this->responseData['end_time'] ?? $currentTime,
            ];
        }

        return [];
    }

    private function generateRelevances(): array
    {
        $result = [];
        $relevances = $this->responseData['relevances'];

        foreach ($relevances['groups'] as $groupOrder => $relevance) {
            --$groupOrder;
            $result["relevanceG{$groupOrder}"] = $relevance ? '1' : '0';
        }

        foreach ($relevances['questions'] as $questionId => $relevance) {
            $result["relevance{$questionId}"] = $relevance ? '1' : '0';
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
        foreach ($survey->baseQuestions as $question) {
            $method = $this->questionTypeToMethodMapping[$question->type];
            $answer = $this->answersData[$question->qid] ?? null;

            if ($answer !== null) {
                yield from self::{$method}(
                    $answer,
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
            throw new NotImplementedError(
                'Sending file as response is not implemented yet'
            );
        }
    }

    private function generateList(?array $answer, string $fieldName, Question $question): Generator
    {
        if ($answer['other'] ?? null) {
            yield $fieldName => '-oth-';
            yield "{$fieldName}other" => $answer['value'] ?? '';
        } else {
            yield $fieldName => $answer['value'] ?? '';
        }
    }

    private function generateListWithComment(?array $answer, string $fieldName, Question $question): Generator
    {
        yield $fieldName => $answer['code'] ?? '';
        yield "{$fieldName}comment" => $answer['comment'] ?? '';
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
                $answer['answers'][$subQuestion->title] ?? null
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
                // TODO: Return empty string when selected is false? (Also for with comments)
                yield from $this->generateBool($subAnswer['selected'] ?? null, $fieldName);
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
                yield from $this->generateBool($subAnswer['selected'] ?? null, $fieldName);
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
                    => $answer['answers'][$yScaleSubQuestion->title]['answers'][$xScaleSubQuestion->title] ?? '';
            }
        }
    }
}
