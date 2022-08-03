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

        v::create()
            ->key('submit_time', v::dateTime($dateTimeFormat), false)
            ->key('start_time', v::dateTime($dateTimeFormat), false)
            ->key('end_time', v::dateTime($dateTimeFormat), false)
            ->key('answers', AnswerValidatorBuilder::buildAll($survey))
            ->check($responseData);
    }

    private static function generateResponseRecord(array $responseData, Survey $survey): array
    {
        $result = [
            'submitdate' => $responseData['submit_time'],
            // TODO: Add support for specifying it in the request
            'startlanguage' => $survey->language,

        ] + \iterator_to_array(
            AnswerFieldGenerator::generateAll($survey, $responseData)
        );

        if ($survey->isDateStamp) {
            $result += [
                'startdate' => $responseData['start_time'],
                'datestamp' => $responseData['end_time'],
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
        Question::QT_5_POINT_CHOICE => 'build5PointChoice',
        Question::QT_L_LIST => 'buildList',
        Question::QT_EXCLAMATION_LIST_DROPDOWN => 'buildList',
    ];

    public static function buildAll(Survey $survey): Validator
    {
        $validator = v::create();

        foreach ($survey->allQuestions as $question) {
            $validator->key(
                $question->qid,
                self::build($question),
                false
            );
        }

        return $validator;
    }

    private static function build(Question $question): Validator
    {
        $method = self::BUILDER_METHOD_MAP[$question->type] ?? 'buildDummy';
        $keyName = "answers.$question->qid";

        /** @var Validator $validator */
        $validator = self::{$method}();
        $validator->setName($keyName);

        return $question->mandatory === 'Y'
            ? $validator
            : v::nullable($validator)->setName($keyName);
    }

    // TODO: Get rid of it
    private static function buildDummy(): Validator
    {
        return v::create();
    }

    private static function build5PointChoice(): Validator
    {
        return v::create()
            ->intType()
            ->between(1, 5);
    }

    private static function buildList(Question $question): Validator
    {
        $answersCode = \array_column($question, 'code');

        return v::create()
            ->stringType()
            ->in($answersCode);
    }
}

/**
 * @internal
 */
class AnswerFieldGenerator
{
    private const GENERATOR_METHOD_MAP = [
        Question::QT_5_POINT_CHOICE => 'generate',
        Question::QT_L_LIST => 'generate',
        Question::QT_EXCLAMATION_LIST_DROPDOWN => 'generate',
    ];

    public static function generateAll(Survey $survey, array $answersData): Generator
    {
        foreach ($survey->allQuestions as $question) {
            $method = self::GENERATOR_METHOD_MAP[$question->type] ?? 'generate';

            yield from self::{$method}(
                $question,
                $answersData['answers'][$question->qid]
            );
        }
    }

    private static function makeFieldName(Question $question)
    {
        return "{$question->sid}X{$question->gid}X{$question->qid}";
    }

    private static function generate(Question $question, $answer): Generator
    {
        yield self::makeFieldName($question) => $answer;
    }
}
