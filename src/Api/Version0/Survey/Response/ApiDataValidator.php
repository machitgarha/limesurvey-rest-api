<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response;

use Survey;
use SurveysGroups;
use RuntimeException;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataValidator\{
    AnswerValidatorBuilder
};

use Respect\Validation\Validator as v;
use Respect\Validation\Validator;

class ApiDataValidator
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

    /**
     * Validates the response data.
     *
     * This method validates:
     *
     * - each answer againts its related question type.
     * - have one of question_group_id or question_id (or none) wrt survey format.
     */
    public function validate(): void
    {
        $validator = v::create();

        $this
            ->addAnswersValidator($validator)
            ->addSurveyFormatValidator($validator);

        $validator->check($this->responseData);
    }

    private function addAnswersValidator(Validator $validator): self
    {
        $validator->key(
            'answers',
            (new AnswerValidatorBuilder())->buildAll($this->survey)
        );

        return $this;
    }

    private function addSurveyFormatValidator(Validator $validator): self
    {
        $format = $this->surveyInfo['format'];
        switch ($format) {
            case 'A':
                break;

            case 'G':
                $validator->key(
                    'question_group_id',
                    v::intType()->in(
                        \array_column($this->survey->groups, 'gid'),
                        true
                    )
                );
                break;

            case 'S':
                $validator->key(
                    'question_id',
                    v::intType()->in(
                        \array_column($this->survey->baseQuestions, 'qid'),
                        true
                    )
                );
                break;

            default:
                throw new RuntimeException(
                    "Unknown survey format '$format'"
                );
        }

        return $this;
    }
}

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataValidator;

use Survey;
use Question;

use MAChitgarha\LimeSurveyRestApi\Helper\ResponseGeneratorHelper;

use Respect\Validation\Validator;
use Respect\Validation\Validator as v;

/**
 * The methods try to build minimal validator, leaving the main validations to
 * be done by the core. As a simple example, for 5 point choice questions, it
 * only checks the value to be an integer and the required range (i.e. [1, 5])
 * is ignored.
 *
 * @internal
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

    public function __construct()
    {
        $this->questionTypeToMethodMapping = ResponseGeneratorHelper::makeQuestionTypeToMethodMap(
            self::METHOD_TO_QUESTION_TYPE_LIST_MAPPING
        );
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
            $survey->baseQuestions
        ));
    }

    private function build(Question $question): Validator
    {
        $method = $this->questionTypeToMethodMapping[$question->type];
        $keyName = "answers.$question->qid";

        /** @var Validator $validator */
        $validator = $this->$method($question);
        $validator->setName($keyName);

        return v::nullable($validator);
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

    private function buildForString(): Validator
    {
        return v::create()
            ->stringType();
    }

    private function buildForRanking(): Validator
    {
        return v::create()
            ->arrayType()
            ->each(v::stringType());
    }

    private function buildForFile(): Validator
    {
        return v::create()
            ->key('title', v::stringType())
            ->key('comment', v::stringType())
            ->key('size', v::floatType())
            ->key('name', v::stringType())
            ->key('extension', v::stringType())
            // TODO: Performance?
            ->key('contents', v::base64());
    }

    private function buildForList(Question $question): Validator
    {
        return $nonOtherValidator = v::create()
            ->key('value', v::stringType())
            ->key('other', v::boolType(), false);
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
        return v::keySet(...\array_map(
            function (Question $subQuestion) use ($valueValidatorBuilder, $question) {
                return v::key($subQuestion->title, v::nullable($valueValidatorBuilder()), false);
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
        return $this->buildForSubQuestions($question, function () {
            return v::create()
                ->arrayType()
                ->length(1, 2, true)
                ->key(0, v::stringType(), false)
                ->key(1, v::stringType(), false);
        });
    }

    private static function buildForMultipleChoiceInnerKeys(): array
    {
        return v::create()
            ->key('selected', v::boolType())
            ->key('other_value', v::nullable(v::stringType()), false);
    }

    private function buildForMultipleChoice(Question $question): Validator
    {
        return $this->buildForSubQuestions($question, function () {
            return self::buildForMultipleChoiceInnerKeys();
        });
    }

    private function buildForMultipleChoiceWithComments(Question $question): Validator
    {
        return $this->buildForSubQuestions($question, function () {
            return self::buildForMultipleChoiceInnerKeys()
                ->key('comment', v::nullable(v::stringType()), false);
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
