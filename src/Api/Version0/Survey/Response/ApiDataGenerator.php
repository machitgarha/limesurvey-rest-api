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
            'submit_date' => $recordData['submitdate'],
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

use Symfony\Component\Serializer\Encoder\JsonEncoder;

/**
 * @internal
 */
class AnswerGenerator
{
    private const GENERATOR_METHOD_MAP = [
        Question::QT_1_ARRAY_DUAL => 'generateArrayDual',
        Question::QT_5_POINT_CHOICE => 'generateInteger',
        Question::QT_A_ARRAY_5_POINT => 'generateIntegerSubQuestions',
        Question::QT_B_ARRAY_10_CHOICE_QUESTIONS => 'generateIntegerSubQuestions',
        Question::QT_C_ARRAY_YES_UNCERTAIN_NO => 'generateStringSubQuestions',
        Question::QT_D_DATE => 'generateString',
        Question::QT_E_ARRAY_INC_SAME_DEC => 'generateStringSubQuestions',
        Question::QT_F_ARRAY => 'generateStringSubQuestions',
        Question::QT_G_GENDER => 'generateString',
        Question::QT_H_ARRAY_COLUMN => 'generateStringSubQuestions',
        Question::QT_I_LANGUAGE => 'generateString',
        Question::QT_K_MULTIPLE_NUMERICAL => 'generateNumericalSubQuestions',
        Question::QT_L_LIST => 'generateString',
        Question::QT_M_MULTIPLE_CHOICE => 'generateBooleanSubQuestions',
        Question::QT_N_NUMERICAL => 'generateNumerical',
        Question::QT_O_LIST_WITH_COMMENT => 'generateListWithComment',
        Question::QT_P_MULTIPLE_CHOICE_WITH_COMMENTS => 'generateMultipleChoiceWithComments',
        Question::QT_Q_MULTIPLE_SHORT_TEXT => 'generateSubQuestions',
        Question::QT_R_RANKING => 'generateRanking',
        Question::QT_S_SHORT_FREE_TEXT => 'generateString',
        Question::QT_T_LONG_FREE_TEXT => 'generateString',
        Question::QT_U_HUGE_FREE_TEXT => 'generateString',
        Question::QT_Y_YES_NO_RADIO => 'generateBoolean',
        Question::QT_ASTERISK_EQUATION => 'generateString',
        Question::QT_EXCLAMATION_LIST_DROPDOWN => 'generateString',
        Question::QT_COLON_ARRAY_NUMBERS => 'generateIntegerSubQuestions2d',
        Question::QT_SEMICOLON_ARRAY_TEXT => 'generateStringSubQuestions2d',
        Question::QT_VERTICAL_FILE_UPLOAD => 'generateFile',
    ];
    /** @var array[] */
    private $recordData;

    public function __construct(array $recordData)
    {
        // TODO: Rename all these to attribute, to be consistent with the core
        $this->recordData = $recordData;
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
        } elseif ($type === 'string') {
            return (string) $value;
        } elseif ($type === 'int') {
            return (int) $value;
        } elseif ($type === 'float') {
            return (float) $value;
        } elseif ($type === 'bool') {
            return $value === 'Y';
        }

        // TODO: Improve this
        throw new \Exception();
    }

    public function generateAll(Survey $survey): Generator
    {
        /** @var Question $question */
        foreach ($survey->allQuestions as $question) {
            $method = self::GENERATOR_METHOD_MAP[$question->type];

            // If question has no parent question, i.e. is not a subquestion
            if ($question->parent_qid === 0) {
                yield $question->title => $this->$method($question, $recordData);
            }
        }
    }

    private function generateInteger(Question $question): ?int
    {
        return $this->getRecordField(FieldNameGenerator::generate($question), 'int');
    }

    private function generateBoolean(Question $question): ?bool
    {
        return $this->getRecordField(FieldNameGenerator::generate($question), 'bool');
    }

    private function generateNumerical(Question $question): ?float
    {
        return $this->getRecordField(FieldNameGenerator::generate($question), 'float');
    }

    private function generateString(Question $question)
    {
        return $this->getRecordField(FieldNameGenerator::generate($question), 'string');
    }

    private function generateRanking(Question $question): array
    {
        $result = [];

        $answersCount = \count($question->answers);
        for ($ranking = 0; $ranking < $answersCount; $ranking++) {
            $result[] = $this->getRecordField(
                FieldNameGenerator::generate($question) . "$ranking",
                'string'
            );
        }

        return $result;
    }

    private function generateFile(Question $question): array
    {
        $fieldName = FieldNameGenerator::generate($question);

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

    private function generateListWithComment(Question $question)
    {
        $fieldName = FieldNameGenerator::generate($question);
        return [
            'code' => $this->getRecordField($fieldName, 'string'),
            'comment' => $this->getRecordField($fieldName, 'string'),
        ];
    }

    private function generateSubQuestions(Question $question, string $type = null): array
    {
        $result = [];

        foreach ($question->subquestions as $subQuestion) {
            $result[$subQuestion->title] = $this->getRecordField(
                FieldNameGenerator::generateForSubQuestion($question, $subQuestion->title),
                $type
            );
        }

        return $result;
    }

    private function generateIntegerSubQuestions(Question $question): array
    {
        return $this->generateSubQuestions($question, 'int');
    }

    private function generateStringSubQuestions(Question $question): array
    {
        return $this->generateSubQuestions($question, 'string');
    }

    private function generateNumericalSubQuestions(Question $question): array
    {
        return $this->generateSubQuestions($question, 'float');
    }

    private function generateBooleanSubQuestions(Question $question): array
    {
        return $this->generateSubQuestions($question, 'bool');
    }

    private function generateCustomSubQuestions(Question $question, callable $fn): array
    {
        $result = [];

        foreach ($question->subquestions as $subQuestion) {
            $fieldName = FieldNameGenerator::generateForSubQuestion(
                $question,
                $subQuestion->title
            );
            $result[$subQuestion->title] = $fn($fieldName);
        }

        return $result;
    }

    private function generateArrayDual(Question $question): array
    {
        return $this->generateCustomSubQuestions($question, function (string $fieldName): array {
            return \array_map(
                function (int $key) use ($fieldName): string {
                    return $this->getRecordField("$fieldName#$key", 'string');
                },
                [0, 1]
            );
        });
    }

    private function generateMultipleChoiceWithComments(Question $question): array
    {
        return $this->generateCustomSubQuestions($question, function (string $fieldName): array {
            $isSelected = $this->getRecordField($fieldName, 'bool') === true;

            return [
                'is_selected' => $isSelected,
                'comment' => $isSelected
                    ? ($this->getRecordField($fieldName . 'comment', 'string') ?? '')
                    : null,
            ];
        });
    }

    private function generateSubQuestions2d(Question $question, string $type = null): array
    {
        [$yScaleSubQuestions, $xScaleSubQuestions] =
            self::splitSubQuestionsBasedOnScale2d($question);

        $result = [];
        foreach ($yScaleSubQuestions as $yScaleSubQuestion) {
            foreach ($xScaleSubQuestions as $xScaleSubQuestion) {
                $result[$yScaleSubQuestion->title][$xScaleSubQuestion->title] =
                    $this->getRecordField(
                        FieldNameGenerator::generateForSubQuestion(
                            $question,
                            $yScaleSubQuestion->title,
                            $xScaleSubQuestion->title
                        ),
                        $type
                    );
            }
        }

        return $result;
    }

    /**
     * @return array{0:Question[],1:Question[]}
     */
    private static function splitSubQuestionsBasedOnScale2d(Question $question): array
    {
        $yScaleSubQuestions = [];
        $xScaleSubQuestions = [];

        foreach ($question->subquestions as $subQuestion) {
            if ((int) $subQuestion->scale_id === 0) {
                $yScaleSubQuestions[] = $subQuestion;
            } elseif ((int) $subQuestion->scale_id === 1) {
                $xScaleSubQuestions[] = $subQuestion;
            } else {
                throw new LogicException(
                    "Invalid scale_id for subquestion with ID $subQuestion->qid"
                );
            }
        }

        return [$yScaleSubQuestions, $xScaleSubQuestions];
    }

    private function generateIntegerSubQuestions2d(Question $question): array
    {
        return $this->generateSubQuestions2d($question, 'int');
    }

    private function generateStringSubQuestions2d(Question $question): array
    {
        return $this->generateSubQuestions2d($question, 'string');
    }
}
