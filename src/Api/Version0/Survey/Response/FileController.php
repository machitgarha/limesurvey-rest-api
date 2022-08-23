<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response;

use Yii;
use Index;
use Survey;
use Question;
use Response as SurveyResponse;
use CController;
use SurveyDynamic;
use CHttpException;
use LogicException;
use RuntimeException;
use LimeExpressionManager;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;
use MAChitgarha\LimeSurveyRestApi\Helper\Permission;
use MAChitgarha\LimeSurveyRestApi\Helper\ResponseHelper;
use MAChitgarha\LimeSurveyRestApi\Helper\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\ResponseRecordGenerator;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\AnswerFieldGenerator;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\ResponseController\AnswerValidatorBuilder;

use MAChitgarha\LimeSurveyRestApi\Api\Version0\SurveyController;

use MAChitgarha\LimeSurveyRestApi\Error\InternalServerError;
use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;
use MAChitgarha\LimeSurveyRestApi\Error\SurveyNotActiveError;
use MAChitgarha\LimeSurveyRestApi\Error\RequiredKeyMissingError;
use MAChitgarha\LimeSurveyRestApi\Error\ResourceIdNotFoundError;
use MAChitgarha\LimeSurveyRestApi\Error\InvalidPathParameterError;

use MAChitgarha\LimeSurveyRestApi\Helper\SurveyHelper;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\EmptyResponse;
use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

use MAChitgarha\LimeSurveyRestApi\Utility\ContentTypeValidator;

use Respect\Validation\Exceptions\AlnumException;
use Respect\Validation\Exceptions\ValidatorException;

use Respect\Validation\Validator;
use Respect\Validation\Validator as v;

use Symfony\Component\HttpFoundation\Response;

use function MAChitgarha\LimeSurveyRestApi\Utility\Response\data;

class FileController implements Controller
{
    use Traits\ContainerProperty;
    use Traits\AuthorizerGetter;
    use Traits\PathParameterGetter;
    use Traits\RequestGetter;
    use Traits\SerializerGetter;
    use Traits\RequestBodyDecoder;
    use Traits\RequestValidator;

    public const PATH = '/surveys/{survey_id}/responses/{response_id}/files';
    public const PATH_BY_ID = '/surveys/{survey_id}/responses/{response_id}/files/{file_id}';

    public static function makeRelativePath(int $surveyId, int $responseId, string $fileId): string
    {
        return \str_replace(
            ['{survey_id}', '{response_id}', '{file_id}'],
            [$surveyId, $responseId, $fileId],
            self::PATH_BY_ID
        );
    }

    public function get(): JsonResponse
    {
        $this->validateRequest();

        $userId = $this->authorize()->getId();

        [$surveyId, $responseId] = $this->getPathParameterListAsInt(
            'survey_id',
            'response_id',
        );
        $fileId = $this->getPathParameter('file_id');

        $survey = SurveyHelper::get($surveyId);

        PermissionChecker::assertHasSurveyPermission(
            $survey,
            Permission::READ,
            $userId,
            'responses'
        );

        $response = ResponseHelper::get($surveyId, $responseId);

        self::assertFileExistsInResponse($response, $fileId);

        $filePath = self::getFileAbsolutePath($surveyId, $fileId);
        self::assertFileExistsAndReadable($filePath);

        return new JsonResponse(
            data([
                'contents' => \base64_encode(\file_get_contents($filePath))
            ]),
        );
    }

    private function getFileIdPathParameter(): string
    {
        $param = $this->getPathParameter('file_id');

        try {
            v::alnum('_')->check($param);
        } catch (AlnumException $exception) {
            throw new InvalidPathParameterError('file_id');
        }

        return $param;
    }

    private static function assertFileExistsInResponse(SurveyResponse $response, string $fileId): void
    {
        foreach ($response->getFiles() as $fileInfo) {
            if ($fileInfo['filename'] === $fileId) {
                return;
            }
        }
        throw new ResourceIdNotFoundError('file', $fileId);
    }

    private static function getFileAbsolutePath(int $surveyId, string $fileId): string
    {
        App()->loadHelper('common');

        return get_absolute_path(
            App()->getConfig('uploaddir') . "/surveys/$surveyId/files/$fileId"
        );
    }

    private static function assertFileExistsAndReadable(string $filePath): void
    {
        if (!\file_exists($filePath)) {
            throw new LogicException("File '$filePath' not exists");
        }
        if (!\is_readable($filePath)) {
            throw new RuntimeException("File '$filePath' is not readable");
        }
    }
}
