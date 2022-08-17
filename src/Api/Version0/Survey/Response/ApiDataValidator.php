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

use MAChitgarha\LimeSurveyRestApi\Helper\ResponseGeneratorHelper;

use Respect\Validation\Validator;
use Respect\Validation\Validator as v;

/**
 * @internal
 * @todo Improve function names and make them consistent.
 */
class AnswerValidatorBuilder
{
    private const METHOD_TO_QUESTION_TYPE_LIST_MAPPING = [
        'buildForBool' => [
            Question::QT_Y_YES_NO_RADIO,
        ],
        'buildForInt' => [
            Question::QT_5_POINT_CHOICE,
        ],
        'buildForFloat' => [
            Question::QT_N_NUMERICAL,
        ],
        'buildForString' => [
            Question::QT_D_DATE,
            Question::QT_G_GENDER,
            Question::QT_I_LANGUAGE,
            Question::QT_S_SHORT_FREE_TEXT,
            Question::QT_T_LONG_FREE_TEXT,
            Question::QT_U_HUGE_FREE_TEXT,
            Question::QT_ASTERISK_EQUATION,
            Question::QT_EXCLAMATION_LIST_DROPDOWN,
        ],
        'buildForRanking' => [
            Question::QT_R_RANKING,
        ],
        'buildForFile' => [
            Question::QT_VERTICAL_FILE_UPLOAD,
        ],
        'buildForList' => [
            Question::QT_L_LIST,
        ],
        'buildForListWithComment' => [
            Question::QT_O_LIST_WITH_COMMENT,
        ],
        'buildForIntSubQuestions' => [
            Question::QT_A_ARRAY_5_POINT,
            Question::QT_B_ARRAY_10_CHOICE_QUESTIONS,
        ],
        'buildForFloatSubQuestions' => [
            Question::QT_K_MULTIPLE_NUMERICAL,
        ],
        'buildForStringSubQuestions' => [
            Question::QT_C_ARRAY_YES_UNCERTAIN_NO,
            Question::QT_E_ARRAY_INC_SAME_DEC,
            Question::QT_F_ARRAY,
            Question::QT_H_ARRAY_COLUMN,
            Question::QT_Q_MULTIPLE_SHORT_TEXT,
        ],
        'buildForArrayDual' => [
            Question::QT_1_ARRAY_DUAL,
        ],
        'buildForMultipleChoice' => [
            Question::QT_M_MULTIPLE_CHOICE,
        ],
        'buildForMultipleChoiceWithComments' => [
            Question::QT_P_MULTIPLE_CHOICE_WITH_COMMENTS,
        ],
        'buildForFloatSubQuestions2d' => [
            Question::QT_COLON_ARRAY_NUMBERS,
        ],
        'buildForStringSubQuestions2d' => [
            Question::QT_SEMICOLON_ARRAY_TEXT,
        ],
    ];

    /** @var array[] */
    private $questionTypeToMethodMapping;

    /** @var bool */
    private $skipSoftMandatoryQuestions = true;

    public function __construct(bool $skipSoftMandatoryQuestions = true)
    {
        $this->questionTypeToMethodMapping = ResponseGeneratorHelper::makeQuestionTypeToMethodMap(
            self::METHOD_TO_QUESTION_TYPE_LIST_MAPPING
        );

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
        $method = $this->questionTypeToMethodMapping[$question->type];
        $keyName = "answers.$question->qid";

        /** @var Validator $validator */
        $validator = $this->$method($question);
        $validator->setName($keyName);

        return $this->isMandatory($question)
            ? $validator
            : v::nullable($validator)->setName($keyName);
    }

    private function buildForBool(): Validator
    {
        return v::create()
            ->boolType();
    }

    private function buildForInt(): Validator
    {
        return v::create()
            ->intType();
    }

    private function buildForFloat(): Validator
    {
        return v::create()
            ->floatType();
    }

    private function buildForString(Question $question): Validator
    {
        return v::create()
            ->stringType()
            ->length(1, null, true);
    }

    private function buildForRanking(Question $question): Validator
    {
        return v::create()
            ->arrayType()
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
            // TODO: Is this check time-consuming or has any value?
            ->key('contents', v::base64());
    }

    private function buildForList(Question $question): Validator
    {
        $result = $nonOtherValidator = v::create()
            ->key('value', v::stringType())
            ->key('other', v::identical(false), false);

        if ($question->other === 'Y') {
            $otherValidator = v::create()
                ->key('value', v::stringType(), false)
                ->key('other', v::identical(true));

            $result = v::oneOf($otherValidator, $nonOtherValidator);
        }

        return $result;
    }

    private function buildForListWithComment(Question $question): Validator
    {
        return v::create()
            ->key('code', v::stringType())
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

    private function buildForIntSubQuestions(Question $question): Validator
    {
        return $this->buildForSubQuestions($question, [$this, 'buildForInt']);
    }

    private function buildForFloatSubQuestions(Question $question): Validator
    {
        return $this->buildForSubQuestions($question, [$this, 'buildForFloat']);
    }

    private function buildForStringSubQuestions(Question $question): Validator
    {
        return $this->buildForSubQuestions($question, [$this, 'buildForString']);
    }

    private function buildForArrayDual(Question $question): Validator
    {
        $makeKey = function (int $keyName) use ($filterPossibleAnswers, $question) {
            return v::create()
                ->key($keyName, v::stringType(), $this->isMandatory($question));
        };

        return $this->buildForSubQuestions($question, function () use ($makeKey) {
            return v::create()
                ->arrayType()
                ->length(1, 2, true)
                ->keySet($makeKey(0), $makeKey(1));
        });
    }

    private static function buildForMultipleChoiceInnerKeys(): array
    {
        // TODO: Maybe improve this? Is improvement needed at all? (Also for the 'with comments' counterpart)
        return [
            v::key('selected', v::boolType()),
            v::key('other_value', v::nullable(v::stringType()), false),
        ];
    }

    private function buildForMultipleChoice(Question $question): Validator
    {
        // At least one choice must have been selected, but we just leave it to the internal LimeSurvey validator
        return $this->buildForSubQuestions($question, function () {
            return v::keySet(...self::buildForMultipleChoiceInnerKeys());
        });
    }

    private function buildForMultipleChoiceWithComments(Question $question): Validator
    {
        return $this->buildForSubQuestions($question, function () {
            return v::keySet(...\array_merge(
                self::buildForMultipleChoiceInnerKeys(),
                [v::key('comment', v::nullable(v::stringType()), false)]
            ));
        });
    }

    private function buildForSubQuestions2d(Question $question, callable $valueValidatorBuilder): Validator
    {
        [$yScaleSubQuestionList, $xScaleSubQuestionList] =
            ResponseGeneratorHelper::splitSubQuestionsBasedOnScale2d($question);

        return $this->buildForSubQuestions(
            $question,
            function () use ($question, $valueValidatorBuilder, $xScaleSubQuestionList) {
                return $this->buildForSubQuestions($question, $valueValidatorBuilder, $xScaleSubQuestionList);
            },
            $yScaleSubQuestionList
        );
    }

    private function buildForFloatSubQuestions2d(Question $question): Validator
    {
        return $this->buildForSubQuestions2d($question, [$this, 'buildForFloat']);
    }

    private function buildForStringSubQuestions2d(Question $question): Validator
    {
        return $this->buildForSubQuestions2d($question, [$this, 'buildForString']);
    }
}
