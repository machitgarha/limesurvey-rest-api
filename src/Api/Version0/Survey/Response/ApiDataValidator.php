<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response;

use Survey;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataValidator\{
    AnswerValidatorBuilder
};

use Respect\Validation\Validator as v;

class ApiDataValidator
{
    /**
     * Validates the structure of the API data.
     */
    public static function validate(
        array $responseData,
        Survey $survey,
        bool $skipSoftMandatoryQuestions = true
    ): void {
        $dateTimeFormat = 'Y-m-d H:i:s';

        // TODO: Use a custom key rule to generate full indexes in exception messages
        v::create()
            ->key('submit_time', v::dateTime($dateTimeFormat), false)
            ->key('start_time', v::dateTime($dateTimeFormat), false)
            ->key('end_time', v::dateTime($dateTimeFormat), false)
            ->key(
                'answers',
                (new AnswerValidatorBuilder($skipSoftMandatoryQuestions))
                    ->buildAll($survey)
            )
            ->check($responseData);
    }
}

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataValidator;

use Survey;
use Question;

use Respect\Validation\Validator;
use Respect\Validation\Validator as v;

/**
 * @internal
 * @todo Improve function names and make them consistent.
 */
class AnswerValidatorBuilder
{
    private const BUILDER_METHOD_MAP = [
        Question::QT_1_ARRAY_DUAL => 'buildForArrayDual',
        Question::QT_5_POINT_CHOICE => 'buildFor5PointChoice',
        Question::QT_A_ARRAY_5_POINT => 'buildForArray5PointChoice',
        Question::QT_B_ARRAY_10_CHOICE_QUESTIONS => 'buildForArray10PointChoice',
        Question::QT_C_ARRAY_YES_UNCERTAIN_NO => 'buildForArrayYesNoUncertain',
        Question::QT_D_DATE => 'buildForText',
        Question::QT_E_ARRAY_INC_SAME_DEC => 'buildForArrayIncreaseSameDecrease',
        Question::QT_F_ARRAY => 'buildForArrayWithPredefinedChoices',
        Question::QT_G_GENDER => 'buildForText',
        Question::QT_H_ARRAY_COLUMN => 'buildForArrayWithPredefinedChoices',
        Question::QT_I_LANGUAGE => 'buildForText',
        Question::QT_K_MULTIPLE_NUMERICAL => 'buildForNumericalSubQuestions',
        Question::QT_L_LIST => 'buildForList',
        Question::QT_M_MULTIPLE_CHOICE => 'buildForMultipleChoice',
        Question::QT_N_NUMERICAL => 'buildForNumber',
        Question::QT_O_LIST_WITH_COMMENT => 'buildForListWithComment',
        Question::QT_P_MULTIPLE_CHOICE_WITH_COMMENTS => 'buildForMultipleChoiceWithComments',
        Question::QT_Q_MULTIPLE_SHORT_TEXT => 'buildForMultipleTexts',
        Question::QT_R_RANKING => 'buildForRanking',
        Question::QT_S_SHORT_FREE_TEXT => 'buildForText',
        Question::QT_T_LONG_FREE_TEXT => 'buildForText',
        Question::QT_U_HUGE_FREE_TEXT => 'buildForText',
        Question::QT_Y_YES_NO_RADIO => 'buildForYesNo',
        Question::QT_ASTERISK_EQUATION => 'buildForText',
        Question::QT_EXCLAMATION_LIST_DROPDOWN => 'buildForList',
        Question::QT_COLON_ARRAY_NUMBERS => 'buildForArray2dNumbers',
        Question::QT_SEMICOLON_ARRAY_TEXT => 'buildForArray2dTexts',
        Question::QT_VERTICAL_FILE_UPLOAD => 'buildForFile',
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
        $method = self::BUILDER_METHOD_MAP[$question->type];
        $keyName = "answers.$question->qid";

        /** @var Validator $validator */
        $validator = $this->$method($question);
        $validator->setName($keyName);

        return $this->isMandatory($question)
            ? $validator
            : v::nullable($validator)->setName($keyName);
    }

    private function buildForNumber(): Validator
    {
        return v::create()
            ->floatType();
    }

    private function buildFor5PointChoice(): Validator
    {
        return v::create()
            ->intType()
            ->between(1, 5);
    }

    private function buildForYesNo(): Validator
    {
        return v::create()
            ->boolType();
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

    private function buildForSubQuestions(
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
        return $this->buildForSubQuestions($question, function () use ($count) {
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
        return $this->buildForSubQuestions($question, function () use ($allowedValues) {
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

        return $this->buildForSubQuestions($question, function () use ($makeKey) {
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

        return $this->buildForSubQuestions(
            $question,
            function () use ($question, $valueValidatorBuilder, $filterSubQuestions) {
                return $this->buildForSubQuestions(
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
        $validator = $this->buildForSubQuestions($question, function () {
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
        $validator = $this->buildForSubQuestions($question, function () {
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

    private function buildForText(Question $question): Validator
    {
        return v::create()
            ->stringType()
            ->length(1, null, true);
    }

    private function buildForMultipleTexts(Question $question): Validator
    {
        return $this->buildForSubQuestions($question, function () use ($question) {
            return $this->buildForText($question);
        });
    }

    private function buildForNumericalSubQuestions(Question $question): Validator
    {
        return $this->buildForSubQuestions($question, function () use ($question) {
            return $this->buildForNumber();
        });
    }

    private function buildForRanking(Question $question): Validator
    {
        return v::create()
            ->each(v::stringType());
    }

    private function buildForFile(Question $question): Validator
    {
        return v::create()
            ->key('title', v::stringType())
            ->key('comment', v::stringType())
            ->key('size', v::floatType()->positive())
            ->key('name', v::stringType())
            ->key('extension', v::stringType())
            ->key('contents', v::base64());
    }
}
