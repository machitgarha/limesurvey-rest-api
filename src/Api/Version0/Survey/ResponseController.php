<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use Yii;
use Index;
use Survey;
use Question;
use CController;
use SurveyDynamic;
use CHttpException;
use LogicException;
use RuntimeException;
use LimeExpressionManager;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;
use MAChitgarha\LimeSurveyRestApi\Helper\Permission;
use MAChitgarha\LimeSurveyRestApi\Helper\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataGenerator;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ResponseRecordGenerator;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\AnswerFieldGenerator;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\AnswerValidatorBuilder;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\SurveyController;

use MAChitgarha\LimeSurveyRestApi\Error\InternalServerError;
use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;
use MAChitgarha\LimeSurveyRestApi\Error\SurveyNotActiveError;
use MAChitgarha\LimeSurveyRestApi\Error\RequiredKeyMissingError;
use MAChitgarha\LimeSurveyRestApi\Error\ResourceIdNotFoundError;

use MAChitgarha\LimeSurveyRestApi\Helper\SurveyHelper;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\EmptyResponse;

use MAChitgarha\LimeSurveyRestApi\Utility\ContentTypeValidator;

use Respect\Validation\Exceptions\ValidatorException;

use Respect\Validation\Validator;
use Respect\Validation\Validator as v;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use function MAChitgarha\LimeSurveyRestApi\Utility\Response\data;

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
        ContentTypeValidator::validateIsJson($this->getRequest());

        $userId = $this->authorize()->getId();

        $survey = SurveyHelper::get(
            $surveyId = $this->getPathParameterAsInt('survey_id')
        );

        PermissionChecker::assertHasSurveyPermission(
            $survey,
            Permission::READ,
            $userId,
            'responses'
        );

        SurveyHelper::assertIsActive($survey);

        $responseRecords = \array_map(
            function (SurveyDynamic $item) use ($survey) {
                return ApiDataGenerator::generate(
                    $item->decrypt()->attributes,
                    $survey
                );
            },
            SurveyDynamic::model($surveyId)->findAll()
        );

        return new JsonResponse(
            data($responseRecords)
        );
    }

    public function new(): EmptyResponse
    {
        ContentTypeValidator::validateIsJson($this->getRequest());

        $userId = $this->authorize()->getId();

        $surveyInfo = SurveyHelper::getInfo(
            $this->getPathParameterAsInt('survey_id')
        );
        $survey = $surveyInfo['oSurvey'];

        PermissionChecker::assertHasSurveyPermission(
            $survey,
            Permission::CREATE,
            $userId,
            'responses'
        );

        $data = $this->decodeJsonRequestBodyInnerData();

        $recordData = RecordGenerator::generate($data, $survey);
        $this->validateResponseData($recordData, $surveyInfo);

        $response = $this->makeResponse($recordData, $survey);
        $response->encryptSave();

        if (!$response->id) {
            throw new RuntimeException('Cannot create response');
        }

        return new EmptyResponse(Response::HTTP_CREATED);
    }

    private function validateResponseData(array $responseData, array $surveyInfo): void
    {
        Yii::import('application.controllers.survey.index', true);
        $indexPage = new Index($this, 1);

        $survey = $surveyInfo['oSurvey'];

        try {
            $thissurvey = $surveyInfo;
            $_POST += [
                'sid' => $survey->sid,
            ];

            $indexPage->action();

        } catch (CHttpException $exception) {
            /**
             * We know the survey exists, because no exceptions thrown before. So, in this case,
             * it's an unexpected error instead.
             */
            if ($exception->statusCode === Response::HTTP_NOT_FOUND) {
                throw new InternalServerError(
                    $exception->getMessage(),
                    $exception->getCode(),
                    $exception
                );
            }
        }

        SurveyHelper::assertIsActive($survey);
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
