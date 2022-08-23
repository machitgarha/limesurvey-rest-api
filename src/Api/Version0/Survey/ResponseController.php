<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey;

use SurveyDynamic;
use ReflectionProperty;
use LimeExpressionManager;

use League\OpenAPIValidation\PSR7\Exception\Validation\InvalidBody;

use League\OpenAPIValidation\Schema\Exception\NotEnoughValidSchemas;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataValidator;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\PostDataGenerator;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\CoreSurveyIndexInvoker;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\SurveyResponseIdHolder;

use MAChitgarha\LimeSurveyRestApi\Error\TypeMismatchError;
use MAChitgarha\LimeSurveyRestApi\Error\InternalServerError;
use MAChitgarha\LimeSurveyRestApi\Error\ResponseCompletedError;
use MAChitgarha\LimeSurveyRestApi\Error\ResourceIdNotFoundError;

use MAChitgarha\LimeSurveyRestApi\Helper\Permission;
use MAChitgarha\LimeSurveyRestApi\Helper\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ApiDataGenerator;

use MAChitgarha\LimeSurveyRestApi\Helper\SurveyHelper;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\EmptyResponse;
use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

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
        $this->validateRequest();

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
            SurveyDynamic::model((string) $surveyId)->findAll()
        );

        return new JsonResponse(
            data($responseRecords)
        );
    }

    public function new(): EmptyResponse
    {
        $this->validateRequest();

        [$apiData, $surveyInfo] = $this->prepareNewOrUpdate(Permission::CREATE);
        $surveyId = $surveyInfo['sid'];

        try {
            // This must not return
            // TODO: Maybe move the first invoke() arg move to PostDataGenerator?
            (new CoreSurveyIndexInvoker([], $surveyInfo))->invoke([
                'sid' => $surveyId,
                'move' => 'movenext',
                'fieldnames' => '',
                'start_time' => $apiData['start_time']
            ], [
                // TODO: startingValues.seed
            ], true);
        } catch (SurveyResponseIdHolder $error) {
            return new EmptyResponse(
                Response::HTTP_CREATED,
                ['Location' => self::makeNewResponseUri(
                    $surveyId,
                    $error->getResponseId()
                )]
            );
        }

        throw new InternalServerError();
    }

    public function update(): EmptyResponse
    {
        $this->validateUpdateRequest();

        [$apiData, $surveyInfo] = $this->prepareNewOrUpdate(Permission::UPDATE);
        [$surveyId, $responseId] = $this->getPathParameterListAsInt(
            'survey_id',
            'response_id'
        );

        $response = SurveyDynamic::model((string) $surveyId)
            ->findByPk($responseId);

        if ($response === null) {
            throw new ResourceIdNotFoundError('response', $responseId);
        }
        if ($response->isCompleted($responseId)) {
            throw new ResponseCompletedError();
        }

        // TODO: Disallow backward navigation if needed

        (new ApiDataValidator($apiData, $surveyInfo))->validate();
        $postData = (new PostDataGenerator($apiData, $surveyInfo))->generate();

        // TODO: maxstep, datestamp?
        $sessionData = [];
        $sessionData["survey_$surveyId"] = [
            'step' => $postData['thisstep'],
            'totalsteps' => $postData['thisstep'],
            'maxstep' => $postData['thisstep'],
            'srid' => $responseId,
        ];

        $this->prepareLimeExpressionManager($surveyInfo, $postData, $sessionData, $response);

        (new CoreSurveyIndexInvoker($apiData, $surveyInfo))
            ->invoke($postData, $sessionData, false);

        $response->updateByPk($responseId, [
            'lastpage' => $postData['thisstep']
        ]);

        return new EmptyResponse(Response::HTTP_OK);
    }

    private function prepareLimeExpressionManager(
        array $surveyInfo,
        array $postData,
        array $sessionData,
        SurveyDynamic $response
    ): void {
        $surveyId = $surveyInfo['sid'];
        $surveyMode = [
            'A' => 'survey',
            'G' => 'group',
            'S' => 'question',
        ][$surveyInfo['format']];

        $surveyOptions = [
            'active' => $surveyInfo['active'] === 'Y',
            'allowsave' => $surveyInfo['allowsave'] === 'Y',
            'anonymized' => $surveyInfo['anonymized'] !== 'N',
            'assessments' => $surveyInfo['assessments'] === 'Y',
            'datestamp' => $surveyInfo['datestamp'] === 'Y',
            'deletenonvalues' => \App()->getConfig('deletenonvalues'),
            'hyperlinkSyntaxHighlighting' => false,
            'ipaddr' => $surveyInfo['ipaddr'] === 'Y',
            'radix' => \getRadixPointData($surveyInfo['surveyls_numberformat'])['separator'],
            'refurl' => '',
            'savetimings' => $surveyInfo['savetimings'] === "Y",
            'surveyls_dateformat' => $surveyInfo['surveyls_dateformat'] ?? 1,
            'startlanguage' => \App()->language ?? $surveyInfo['language'],
            'target' => \App()->getConfig('uploaddir')
                . DIRECTORY_SEPARATOR . 'surveys'
                . DIRECTORY_SEPARATOR . $surveyId
                . DIRECTORY_SEPARATOR . 'files'
                . DIRECTORY_SEPARATOR,
            'tempdir' => \App()->getConfig('tempdir') . DIRECTORY_SEPARATOR,
            'timeadjust' => \App()->getConfig("timeadjust"),
            'token' => '',
        ];

        $_SESSION = $sessionData;

        LimeExpressionManager::StartSurvey($surveyId, $surveyMode, $surveyOptions, true);

        $expressionManager = LimeExpressionManager::singleton();
        $currentSequence = ($response->lastpage ?? 0) - 1;

        foreach ([
            'currentGroupSeq' => $currentSequence,
            'currentQuestionSeq' => $currentSequence,
        ] as $propertyName => $propertyValue) {
            $reflection = new ReflectionProperty(
                $expressionManager,
                $propertyName
            );
            $reflection->setAccessible(true);
            $reflection->setValue($expressionManager, $propertyValue);
        }

        /*
         * We don't process POST here, because we want the validations and
         * other steps done in Index class and SurveyRuntimeHelper.
         */
        LimeExpressionManager::JumpTo($postData['thisstep'], false, false);
    }

    private function prepareNewOrUpdate(string $permission): array
    {
        $userId = $this->authorize()->getId();

        $surveyInfo = SurveyHelper::getInfo(
            $this->getPathParameterAsInt('survey_id')
        );
        $survey = $surveyInfo['oSurvey'];

        PermissionChecker::assertHasSurveyPermission(
            $survey,
            $permission,
            $userId,
            'responses'
        );

        SurveyHelper::assertIsActive($survey);

        return [$this->decodeJsonRequestBodyInnerData(), $surveyInfo];
    }

    private function validateUpdateRequest(): void
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

use Yii;
use Index;
use Exception;
use CController;
use LSETwigViewRenderer;
use LimeExpressionManager;
use InvalidArgumentException;

use MAChitgarha\LimeSurveyRestApi\Error\ErrorBucket;
use MAChitgarha\LimeSurveyRestApi\Error\InvalidAnswerError;
use MAChitgarha\LimeSurveyRestApi\Error\SurveyExpiredError;
use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;
use MAChitgarha\LimeSurveyRestApi\Error\MaintenanceModeError;
use MAChitgarha\LimeSurveyRestApi\Error\SurveyNotStartedError;
use MAChitgarha\LimeSurveyRestApi\Error\MandatoryQuestionMissingError;

class CoreSurveyIndexInvoker
{
    /** @var array */
    private $apiData;

    /** @var array */
    private $surveyInfo;

    public function __construct(array $apiData, array $surveyInfo)
    {
        $this->apiData = $apiData;
        $this->surveyInfo = $surveyInfo;
    }

    public function invoke(array $postData, array $sessionData, bool $isPostRequest): void
    {
        $indexPage = $this->prepareCoreSurveyIndexClass($isPostRequest);

        $_POST = $postData;
        $_SESSION = $sessionData;

        $thissurvey = $this->surveyInfo;
        $clienttoken = '';

        // See CustomTwigRenderer::renderTemplateFromFile() for more info
        $indexPage->action();
    }

    private function prepareCoreSurveyIndexClass(bool $isPostRequest): Index
    {
        \App()->setComponent(
            'twigRenderer',
            new CustomTwigRenderer(
                $isPostRequest,
                $this->apiData['skip_soft_mandatory'] ?? false
            ),
            false
        );

        Yii::import('application.controllers.survey.index', true);

        $controller = new IndexOutputController();
        \App()->setController($controller);

        return new Index($controller, 'index');
    }
}

class CustomTwigRenderer extends LSETwigViewRenderer
{
    /** @var ErrorBucket */
    private $errorBucket;

    /** @var bool */
    private $isPostRequest;

    /** @var string[] Mandatory tip message for each question */
    private $mandatoryTips = [];

    /**
     * EM tips for each question. Mapping of question IDs to an array of tips,
     * in which, the keys are the tips and values are just true. This is for
     * preventing from message duplication.
     * @var true[][]
     */
    private $expressionManagerTips = [];

    /** @var bool */
    private $skipSoftMandatory;

    public function __construct(bool $isPostRequest, bool $skipSoftMandatory)
    {
        $this->errorBucket = new ErrorBucket();
        $this->isPostRequest = $isPostRequest;
        $this->skipSoftMandatory = $skipSoftMandatory;
    }

    public function init()
    {
    }

    public function renderPartial($twigPath, $data)
    {
        $twigName = \str_replace([
            '/survey/questions/question_help/',
            '/subviews/privacy/',
            '.twig'
        ], '', $twigPath);

        $questionId = $data['qid'] ?? $data['qInfo']['qid'];

        switch ($twigName) {
            case 'error_tip':
                $this->errorBucket->addItem(new InvalidAnswerError(
                    $questionId,
                    self::normalizeMessage($data['vtip'])
                ));
                break;

            case 'mandatory_tip':
                $this->mandatoryTips[$questionId] = $data['sMandatoryText'];
                break;

            case 'em_tip':
                $this->expressionManagerTips[$questionId][$data['vtip']] = true;
                break;

            case 'privacy_datasecurity_notice_label':
                // Nothing to do
                break;

            default:
                throw new InvalidArgumentException(
                    "Unhandled Twig path '$twigPath'"
                );
        }

        return '';
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
                $this->handleGlobalLayoutRenderRequest($data, $return);
                break;

            default:
                throw new InvalidArgumentException(
                    "Unhandled Twig layout '$layout'"
                );
                // No break
        }

        return '';
    }

    private function handleGlobalLayoutRenderRequest(array $data, bool $return): void
    {
        $surveyInfo = $data['aSurveyInfo'];
        $surveyId = $surveyInfo['sid'];
        $surveySession = $_SESSION["survey_$surveyId"];

        if ($this->isPostRequest) {
            /*
             * We cannot do anything but throwing an exception, because
             * otherwise the program control returns to the core (e.g.
             * to the display_first_page() function in frontend helper),
             * not what it expected (because $return in this case is false,
             * so the app should end).
             */
            throw new SurveyResponseIdHolder($surveySession['srid']);
        }

        $lastMoveResult = LimeExpressionManager::GetLastMoveResult();

        if ($lastMoveResult !== null) {
            if ($lastMoveResult['mandViolation']) {
                $this->handleMandatoryViolations($lastMoveResult);
            }
            if (!$lastMoveResult['valid']) {
                $this->handleInvalidAnswers($lastMoveResult);
            }
        } else {
            // TODO: Right to leave without any actions?
        }

        if (!$this->errorBucket->isEmpty()) {
            throw $this->errorBucket;
        }
    }

    private static function splitPipeSeparatedQuestionFieldNames(string $fieldNames)
    {
        if (empty($fieldNames)) {
            return [];
        }
        return \explode('|', $fieldNames);
    }

    private static function isQuestionFieldNameSameAsQuestionId(string $questionFieldName, int $questionId): bool
    {
        [,, $questionIdConcatOtherNonSense] = \explode('X', $questionFieldName);

        return \str_starts_with($questionIdConcatOtherNonSense, (string) $questionId);
    }

    private function handleLastMoveResultError(string $pipeSeparatedQuestionFieldNameList, callable $fn): void
    {
        $questionFieldNameList = self::splitPipeSeparatedQuestionFieldNames(
            $pipeSeparatedQuestionFieldNameList
        );
        $questionIndexInfoList = LimeExpressionManager::GetQuestionIndexInfo();

        foreach ($questionFieldNameList as $questionFieldName) {
            foreach ($questionIndexInfoList as $questionIndexInfo) {
                if (self::isQuestionFieldNameSameAsQuestionId(
                    $questionFieldName,
                    $questionId = (int) $questionIndexInfo['qid']
                )) {
                    $fn($questionId, $questionIndexInfo);
                }
            }
        }
    }


    private function handleMandatoryViolations(array $lastMoveResult): void
    {
        $isSoft = $lastMoveResult['mandNonSoft'] ?: $lastMoveResult['mandSoft'];
        if ($isSoft && $this->skipSoftMandatory) {
            return;
        }

        $addError = function (int $questionId) {
            $this->errorBucket->addItem(
                new MandatoryQuestionMissingError(
                    $questionId,
                    $this->mandatoryTips[$questionId]
                )
            );
        };

        // TODO: Fix all passed questions being checked
        $fn = function (int $questionId, array $questionIndexInfo) use ($addError) {
            $isSoft = $questionIndexInfo['mandNonSoft'] ?: $questionIndexInfo['mandSoft'];

            if ($isSoft) {
                if (!$this->skipSoftMandatory) {
                    $addError($questionId);
                }
            } else {
                $addError($questionId);
            }
        };

        $this->handleLastMoveResultError($lastMoveResult['unansweredSQs'], $fn);
    }

    private function handleInvalidAnswers(array $lastMoveResult): void
    {
        $addError = function (int $questionId) {
            foreach ($this->expressionManagerTips[$questionId] as $tip => $true) {
                $this->errorBucket->addItem(
                    new InvalidAnswerError($questionId, $tip)
                );
            }
        };

        $fn = function (int $questionId, array $questionIndexInfo) use ($addError) {
            if (!$questionIndexInfo['valid']) {
                $addError($questionId);
            }
        };

        $this->handleLastMoveResultError($lastMoveResult['invalidSQs'], $fn);
    }

    private function addExpressionManagerTipsAsErrors(int $questionId): void
    {
        foreach ($this->expressionManagerTips[$questionId] as $tip => $true) {
            $this->errorBucket->addItem(
                new InvalidAnswerError($questionId, $tip)
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

class IndexOutputController extends CController
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

    public function createAbsoluteUrl($a, $b = [], $c = '', $d = '&'): string
    {
        return '';
    }

    public function createUrl($a, $b = [], $c = '&'): string
    {
        return '';
    }

    public function createAction($actionId)
    {
        if ($actionId === 'captcha') {
            throw new NotImplementedError('Captcha is not implemented yet');
        }
        return parent::createAction($actionId);
    }

    public function recordCachingAction($a, $b, $c)
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
