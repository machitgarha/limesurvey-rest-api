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
use LSYii_Application;
use LimeExpressionManager;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataValidator;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\CustomTwigRenderer;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\IndexOutputController;

use MAChitgarha\LimeSurveyRestApi\Helper\Permission;
use MAChitgarha\LimeSurveyRestApi\Helper\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataGenerator;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\RecordGenerator;

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
        ApiDataValidator::validate($data, $survey);

        $recordData = RecordGenerator::generate($data, $survey);
        $this->submitResponse($recordData, $surveyInfo);

        return new EmptyResponse(Response::HTTP_CREATED);
    }

    private function submitResponse(array $recordData, array $surveyInfo): void
    {
        $indexPage = self::prepareCoreSurveyIndexClass();

        /** @var Survey $survey */
        $survey = $surveyInfo['oSurvey'];

        try {
            $surveyid = $survey->sid;
            $thissurvey = $surveyInfo;
            $_SESSION['survey_' . $surveyid]['step'] = 1;
            $clienttoken = '';

            $_POST = $recordData + [
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
    }

    private static function prepareCoreSurveyIndexClass(): Index
    {
        \App()->setComponent('twigRenderer', new CustomTwigRenderer(), false);

        Yii::import('application.controllers.survey.index', true);

        return new Index(new IndexOutputController(), 'index');
    }
}

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController;

use LSETwigViewRenderer;
use InvalidArgumentException;

use MAChitgarha\LimeSurveyRestApi\Error\SurveyExpiredError;
use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;
use MAChitgarha\LimeSurveyRestApi\Error\MaintenanceModeError;
use MAChitgarha\LimeSurveyRestApi\Error\SurveyNotStartedError;

class CustomTwigRenderer extends LSETwigViewRenderer
{
    public function init()
    {
    }

    public function renderTemplateFromFile($layout, $data, $return)
    {
        $layout = \str_replace('.twig', '', $layout);

        switch ($layout) {
            case 'layout_maintenance':
                throw new MaintenanceModeError();
                // No break

            default:
                throw new InvalidArgumentException("Unhandled Twig layout '$layout'");
                // No break
        }
    }
}

class IndexOutputController
{
    public function renderExitMessage(
        int $surveyId,
        string $type,
        array $messages = [],
        string $url = null,
        array $errors = null
    ): void {
        switch ($type) {
            case 'survey-expiry':
                throw new SurveyExpiredError();
                // No break

            case 'survey-notstart':
                throw new SurveyNotStartedError();
                // No break

            default:
                throw new InvalidArgumentException("Unhandled exit type '$type'");
        }
    }

    public function createUrl(string $route, $params = [], string $ampersand = '&'): string
    {
        return '';
    }

    public function createAction(string $action)
    {
        if ($action === 'captcha') {
            throw new NotImplementedError('Captcha is not implemented yet');
        }
    }
}
