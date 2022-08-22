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

use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidBody;

use League\OpenAPIValidation\Schema\Exception\TooManyValidSchemas;
use League\OpenAPIValidation\Schema\Exception\NotEnoughValidSchemas;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\AnswersValidator;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataValidator;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\PostDataGenerator;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\CustomTwigRenderer;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\IndexOutputController;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\SurveyResponseIdHolder;

use MAChitgarha\LimeSurveyRestApi\Error\Error;
use MAChitgarha\LimeSurveyRestApi\Error\TypeMismatchError;
use MAChitgarha\LimeSurveyRestApi\Error\ResourceIdNotFoundError;

use MAChitgarha\LimeSurveyRestApi\Helper\Permission;
use MAChitgarha\LimeSurveyRestApi\Helper\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataGenerator;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\RecordGenerator;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\AnswerFieldGenerator;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\AnswerValidatorBuilder;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\SurveyController;

use MAChitgarha\LimeSurveyRestApi\Helper\SurveyHelper;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\EmptyResponse;
use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

use MAChitgarha\LimeSurveyRestApi\Utility\ContentTypeValidator;

use Respect\Validation\Exceptions\ValidatorException;

use Respect\Validation\Validator;
use Respect\Validation\Validator as v;

use Symfony\Component\HttpFoundation\Response;

use function MAChitgarha\LimeSurveyRestApi\Utility\Response\data;

class ResponseController implements Controller
{
    use Traits\ContainerProperty;
    use Traits\AuthorizerGetter;
    use Traits\PathParameterGetter;
    use Traits\RequestGetter;
    use Traits\SerializerGetter;
    use Traits\RequestBodyDecoder;
    use Traits\RequestValidator;

    public const PATH = '/surveys/{survey_id}/responses';
    public const PATH_BY_ID = '/surveys/{survey_id}/responses/{response_id}';

    public function list(): JsonResponse
    {
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
        [$data, $surveyInfo] = $this->prepareNewOrUpdate(__FUNCTION__);

        $responseUri = $this->submitResponse($data, $surveyInfo);

        return new EmptyResponse(
            Response::HTTP_CREATED,
            ['Location' => $responseUri]
        );
    }

    public function update(): EmptyResponse
    {
        [$data, $surveyInfo] = $this->prepareNewOrUpdate(__FUNCTION__);
        $responseId = $this->getPathParameterAsInt('response_id');

        $response = SurveyDynamic::model($surveyInfo['sid'])
            ->findByPk($responseId);

        if ($response === null) {
            throw new ResourceIdNotFoundError('response', $responseId);
        }

        // TODO: Disallow backward navigation if needed

        $this->submitResponse($data, $surveyInfo, $responseId);

        return new EmptyResponse(Response::HTTP_OK);
    }

    private function prepareNewOrUpdate(string $type): array
    {
        $this->validateNewOrUpdate();

        $userId = $this->authorize()->getId();

        $surveyInfo = SurveyHelper::getInfo(
            $this->getPathParameterAsInt('survey_id')
        );

        PermissionChecker::assertHasSurveyPermission(
            $surveyInfo['oSurvey'],
            $type === 'new' ? Permission::CREATE : Permission::UPDATE,
            $userId,
            'responses'
        );

        $data = $this->decodeJsonRequestBodyInnerData();
        (new ApiDataValidator($data, $surveyInfo))->validate();

        return [$data, $surveyInfo];
    }

    private function validateNewOrUpdate(): void
    {
        try {
            $this->validateRequest();
        } catch (InvalidBody $exception) {
            $previous = $exception->getPrevious();

            if ($previous instanceof NotEnoughValidSchemas) {
                throw new TypeMismatchError(
                    'Cannot indicate the type of one of the answers (i.e. is probably out of valid answer types)'
                );
            }

            throw $exception;
        }
    }

    private function submitResponse(array $apiResponseData, array $surveyInfo, int $responseId = null): string
    {
        $indexPage = self::prepareCoreSurveyIndexClass();

        /** @var Survey $survey */
        $survey = $surveyInfo['oSurvey'];

        try {
            $_POST = (new PostDataGenerator($apiResponseData, $surveyInfo))
                ->generate();

            $surveyid = $survey->sid;
            $thissurvey = $surveyInfo;
            $clienttoken = '';

            // TODO: maxstep, datestamp, startingValues.seed?
            $_SESSION["survey_$surveyid"]['step'] = $_POST['thisstep'];
            $_SESSION["survey_$surveyid"]['totalsteps'] = $_POST['thisstep'];
            if ($responseId !== null) {
                $_SESSION["survey_$surveyid"]['srid'] = $responseId;
            }

            // See CustomTwigRenderer::renderTemplateFromFile() for more info
            try {
                $indexPage->action();
            } catch (SurveyResponseIdHolder $holder) {
                return self::makeNewResponseUri(
                    $surveyid,
                    $holder->getResponseId()
                );
            }
        } catch (CHttpException $exception) {
            /**
             * We know the survey exists, because no exceptions thrown before.
             * So, in this case, it's an unexpected error instead.
             */
            if ($exception->statusCode === Response::HTTP_NOT_FOUND) {
                throw new InternalServerError(
                    $exception->getMessage(),
                    $exception->getCode(),
                    $exception
                );
            }

            if ($exception->statusCode === Response::HTTP_UNAUTHORIZED) {
                SurveyHelper::assertIsActive($survey);
            }

            throw $exception;
        }
    }

    private static function prepareCoreSurveyIndexClass(): Index
    {
        \App()->setComponent('twigRenderer', new CustomTwigRenderer(), false);

        Yii::import('application.controllers.survey.index', true);

        $controller = new IndexOutputController();
        \App()->setController($controller);

        return new Index($controller, 'index');
    }

    private static function makeNewResponseUri(int $surveyId, int $responseId): string
    {
        return \str_replace(
            ['{survey_id}', '{response_id}'],
            [$surveyId, $responseId],
            self::PATH_BY_ID
        );
    }
}

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController;

use Exception;
use CComponent;
use LSETwigViewRenderer;
use SurveyRuntimeHelper;
use LimeExpressionManager;
use InvalidArgumentException;

use MAChitgarha\LimeSurveyRestApi\Error\ErrorBucket;
use MAChitgarha\LimeSurveyRestApi\Error\InvalidAnswerError;
use MAChitgarha\LimeSurveyRestApi\Error\SurveyExpiredError;
use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;
use MAChitgarha\LimeSurveyRestApi\Error\MaintenanceModeError;
use MAChitgarha\LimeSurveyRestApi\Error\SurveyNotStartedError;
use MAChitgarha\LimeSurveyRestApi\Error\MandatoryQuestionMissingError;

class CustomTwigRenderer extends LSETwigViewRenderer
{
    /** @var ErrorBucket */
    private $errorBucket;

    /** @var true[] Used map for faster checks */
    private $missingMandatoryQuestionIds = [];

    public function __construct()
    {
        $this->errorBucket = new ErrorBucket();
    }

    public function init()
    {
    }

    public function renderTemplateFromFile($layout, $data, $return)
    {
        $layoutName = \str_replace('.twig', '', $layout);

        // TODO: Handle surveyls_policy_error and datasecurity_error
        switch ($layoutName) {
            case 'layout_maintenance':
                throw new MaintenanceModeError();
                // No break

            case 'layout_global':
                // This means the move is 'movesubmit'
                if ($return) {
                    return '';
                }

                if ($this->errorBucket->isEmpty()) {
                    $surveyId = $data['aSurveyInfo']['sid'];

                    /*
                     * As the parent implementation of current method ends the
                     * app (using App()->end()), to prevent any problems and/or
                     * inconsistencies, we should return here too. However,
                     * returning a value is not possible, because the program
                     * control returns to SurveyRuntimeHelper instead of our
                     * dedicated controller. So we need to throw an special
                     * (one-time) exception as a workaround.
                     */
                    throw new SurveyResponseIdHolder(
                        $_SESSION["survey_$surveyId"]['srid']
                    );
                } else {
                    throw $this->errorBucket;
                }
                break;

            default:
                throw new InvalidArgumentException(
                    "Unhandled Twig layout '$layout'"
                );
                // No break
        }
    }

    public function renderPartial($twigPath, $data)
    {
        $twigName = \str_replace([
            '/survey/questions/question_help/',
            '/subviews/privacy/',
            '.twig'
        ], '', $twigPath);

        switch ($twigName) {
            case 'mandatory_tip':
                // TODO: Support for skipping soft mandatory questions
                $questionId = (int) $data['qInfo']['qid'];

                if (!isset($this->missingMandatoryQuestionIds[$questionId])) {
                    $this->errorBucket->addItem(
                        new MandatoryQuestionMissingError(
                            $questionId,
                            $data['sMandatoryText']
                        )
                    );
                    $this->missingMandatoryQuestionIds[$questionId] = true;
                }

                break;

            case 'em_tip':
                // TODO: What to do here?
                break;

            case 'error_tip':
                $this->errorBucket->addItem(new InvalidAnswerError(
                    $data['qid'],
                    self::normalizeMessage($data['vtip'])
                ));
                break;

            case 'privacy_datasecurity_notice_label':
                // Nothing to do
                break;

            default:
                throw new InvalidArgumentException(
                    "Unhandled Twig path '$twigPath'"
                );
        }
    }

    private static function normalizeMessage(string $message): string
    {
        return \strip_tags($message);
    }

    public function renderHtmlPage($html, $template): void
    {
    }

    public function renderQuestion($view, $render)
    {
        return '';
    }
}

class IndexOutputController
{
    public function renderExitMessage(
        int $surveyId,
        string $type,
        array $messages = [],
        array $url = null,
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
                throw new InvalidArgumentException(
                    "Unhandled exit type '$type'"
                );
        }
    }

    public function createAbsoluteUrl(): string
    {
        return '';
    }

    public function createUrl(): string
    {
        return '';
    }

    public function createAction(string $action)
    {
        if ($action === 'captcha') {
            throw new NotImplementedError('Captcha is not implemented yet');
        }
    }

    public function recordCachingAction()
    {
    }
}

class SurveyResponseIdHolder extends Exception
{
    /** @var int */
    private $responseId;

    public function __construct(int $responseId)
    {
        parent::__construct();

        $this->responseId = $responseId;
    }

    public function getResponseId(): int
    {
        return $this->responseId;
    }
}
