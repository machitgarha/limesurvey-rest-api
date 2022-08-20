<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response;

use Answer;
use Survey;
use Question;
use Generator;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataGenerator\AnswerGenerator;

class ApiDataGenerator
{
    public static function generate(array $recordData, Survey $survey): array
    {
        $result = [
            'id' => $recordData['id'],
            'submit_time' => $recordData['submitdate'],
            'answers' => \iterator_to_array(
                (new AnswerGenerator($recordData))->generateAll($survey)
            ),
        ];

        if ($survey->isDateStamp) {
            $result += [
                'start_time' => $responseData['startdata'],
                'end_time' => $responseData['datestamp'],
            ];
        }

        return $result;
    }
}

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataGenerator;

use Survey;
use Question;
use Generator;
use LogicException;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\FileController;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\FieldNameGenerator;

use MAChitgarha\LimeSurveyRestApi\Helper\ResponseGeneratorHelper;

use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * @internal
 */
class AnswerGenerator
{
    private const METHOD_TO_QUESTION_TYPE_LIST_MAPPING = [
        'generateBool' => [
            Question::QT_Y_YES_NO_RADIO,
        ],
        'generateInt' => [
            Question::QT_5_POINT_CHOICE,
        ],
        'generateFloat' => [
            Question::QT_N_NUMERICAL,
        ],
        'generateString' => [
            Question::QT_D_DATE,
            Question::QT_G_GENDER,
            Question::QT_I_LANGUAGE,
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
        'generateIntSubQuestions' => [
            Question::QT_A_ARRAY_5_POINT,
            Question::QT_B_ARRAY_10_CHOICE_QUESTIONS,
        ],
        'generateFloatSubQuestions' => [
            Question::QT_K_MULTIPLE_NUMERICAL,
        ],
        'generateStringSubQuestions' => [
            Question::QT_C_ARRAY_YES_UNCERTAIN_NO,
            Question::QT_E_ARRAY_INC_SAME_DEC,
            Question::QT_F_ARRAY,
            Question::QT_H_ARRAY_COLUMN,
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
        'generateFloatSubQuestions2d' => [
            Question::QT_COLON_ARRAY_NUMBERS,
        ],
        'generateStringSubQuestions2d' => [
            Question::QT_SEMICOLON_ARRAY_TEXT,
        ],
    ];

    /** @var array[] */
    private $questionTypeToMethodMapping;

    /** @var array[] */
    private $recordData;

    public function __construct(array $recordData)
    {
        $this->questionTypeToMethodMapping = ResponseGeneratorHelper::makeQuestionTypeToMethodMap(
            self::METHOD_TO_QUESTION_TYPE_LIST_MAPPING
        );

        $this->recordData = $recordData;
    }

    public function generateAll(Survey $survey): Generator
    {
        foreach ($survey->allQuestions as $question) {
            $method = $this->questionTypeToMethodMapping[$question->type];

            // If question has no parent question, i.e. is not a subquestion
            if ($question->parent_qid === 0) {
                yield $question->title => $this->$method(
                    $question,
                    FieldNameGenerator::generate($question)
                );
            }
        }
    }

    private function getRecordField(string $fieldName, string $type = null)
    {
        $value = $this->recordData[$fieldName];

        if ($value === '' || $value === null) {
            return null;
        }
        return $this->castTo($value, $type);
    }

    private function castTo($value, string $type = null)
    {
        if ($type === null) {
            return $value;
        } elseif ($type === 'bool') {
            return $value === 'Y';
        } elseif ($type === 'int') {
            return (int) $value;
        } elseif ($type === 'float') {
            return (float) $value;
        } elseif ($type === 'string') {
            return (string) $value;
        }

        // TODO: Improve this
        throw new \Exception();
    }

    private function generateBool(Question $question, string $fieldName): ?bool
    {
        return $this->getRecordField($fieldName, 'bool');
    }

    private function generateInt(Question $question, string $fieldName): ?int
    {
        return $this->getRecordField($fieldName, 'int');
    }

    private function generateFloat(Question $question, string $fieldName): ?float
    {
        return $this->getRecordField($fieldName, 'float');
    }

    private function generateString(Question $question, string $fieldName): ?string
    {
        return $this->getRecordField($fieldName, 'string');
    }

    private function generateRanking(Question $question, string $fieldName): array
    {
        $result = [];

        $answersCount = \count($question->answers);
        for ($ranking = 0; $ranking < $answersCount; $ranking++) {
            $result[] = $this->getRecordField("{$fieldName}{$ranking}", 'string');
        }

        return $result;
    }

    private function generateFile(Question $question, string $fieldName): array
    {
        $fileInfoEncoded = $this->getRecordField($fieldName);
        $fileCount = (int) $this->getRecordField($fieldName . '_filecount');

        if ($fileCount === 0 || $fileInfoEncoded === null) {
            return [];
        }

        $fileInfo = (new JsonEncoder())->decode($fileInfoEncoded, '');

        return [
            'title' => $fileInfo['title'],
            'comment' => $fileInfo['comment'],
            'size' => $fileInfo['size'],
            'name' => $fileInfo['name'],
            'extension' => $fileInfo['ext'],
            'uri' => FileController::makeRelativePath(
                $question->sid,
                $this->recordData['id'],
                $fileInfo['filename']
            ),
        ];
    }

    private function generateList(Question $question, string $fieldName): array
    {
        $value = $this->getRecordField($fieldName, 'string');

        if ($value !== '-oth-') {
            return [
                'other' => false,
                'value' => $value,
            ];
        } else {
            return [
                'other' => true,
                'value' => $this->getRecordField($fieldName . 'other', 'string'),
            ];
        }
    }

    private function generateListWithComment(Question $question, string $fieldName)
    {
        return [
            'code' => $this->getRecordField($fieldName, 'string'),
            'comment' => $this->getRecordField($fieldName . 'comment', 'string'),
        ];
    }

    private function generateSubQuestions(Question $question, string $fieldNameBase, callable $fn): array
    {
        $result = [];

        foreach ($question->subquestions as $subQuestion) {
            $result['answers'][$subQuestion->title] = $fn(
                // fieldName:
                $fieldNameBase . FieldNameGenerator::generateSubQuestionSuffix($subQuestion)
            );
        }

        return $result;
    }

    private function generateTypedSubQuestions(Question $question, string $fieldNameBase, string $type = null): array
    {
        return $this->generateSubQuestions($question, $fieldNameBase, function (string $fieldName) use ($type) {
            return $this->getRecordField($fieldName, $type);
        });
    }

    private function generateIntSubQuestions(Question $question, string $fieldNameBase): array
    {
        return $this->generateTypedSubQuestions($question, $fieldNameBase, 'int');
    }

    private function generateFloatSubQuestions(Question $question, string $fieldNameBase): array
    {
        return $this->generateTypedSubQuestions($question, $fieldNameBase, 'float');
    }

    private function generateStringSubQuestions(Question $question, string $fieldNameBase): array
    {
        return $this->generateTypedSubQuestions($question, $fieldNameBase, 'string');
    }

    private function generateArrayDual(Question $question, string $fieldNameBase): array
    {
        return $this->generateTypedSubQuestions($question, $fieldNameBase, function (string $fieldName): array {
            return [
                $this->getRecordField("$fieldName#0", 'string'),
                $this->getRecordField("$fieldName#1", 'string'),
            ];
        });
    }

    private function generateMultipleChoice(Question $question, string $fieldNameBase): array
    {
        $result = $this->generateSubQuestions($question, $fieldNameBase, function (string $fieldName): array {
            return [
                'selected' => $this->getRecordField($fieldName, 'bool'),
            ];
        });

        if ($question->other === 'Y') {
            $value = $this->getRecordField($fieldNameBase . 'other', 'string');
            $result['other'] = [
                'selected' => $value === null,
                'other_value' => $value,
            ];
        }

        return $result;
    }

    private function generateMultipleChoiceWithComments(Question $question, string $fieldNameBase): array
    {
        $result = $this->generateSubQuestions($question, $fieldNameBase, function (string $fieldName): array {
            return [
                'selected' => $this->getRecordField($fieldName, 'bool'),
                'comment' => $this->getRecordField($fieldName . 'comment', 'string'),
            ];
        });

        if ($question->other === 'Y') {
            $value = $this->getRecordField($fieldNameBase . 'other', 'string');

            $result['other'] = [
                'selected' => $value === null,
                'other_value' => $value,
                'comment' => $this->getRecordField($fieldNameBase . 'othercomment', 'string'),
            ];
        }

        return $result;
    }

    private function generateSubQuestions2d(Question $question, string $fieldNameBase, string $type = null): array
    {
        [$yScaleSubQuestionList, $xScaleSubQuestionList] =
            ResponseGeneratorHelper::splitSubQuestionsBasedOnScale2d($question);

        $result = [];
        foreach ($yScaleSubQuestionList as $yScaleSubQuestion) {
            foreach ($xScaleSubQuestionList as $xScaleSubQuestion) {
                $result['answers'][$yScaleSubQuestion->title]['answers'][$xScaleSubQuestion->title] =
                    $this->getRecordField(
                        $fieldNameBase . FieldNameGenerator::generateSubQuestionSuffix(
                            $yScaleSubQuestion,
                            $xScaleSubQuestion
                        ),
                        $type
                    )
                ;
            }
        }

        return $result;
    }

    private function generateFloatSubQuestions2d(Question $question, string $fieldNameBase): array
    {
        return $this->generateSubQuestions2d($question, $fieldNameBase, 'float');
    }

    private function generateStringSubQuestions2d(Question $question, string $fieldNameBase): array
    {
        return $this->generateSubQuestions2d($question, $fieldNameBase, 'string');
    }
}
