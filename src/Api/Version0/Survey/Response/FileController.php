<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response;

use Survey;
use Response as SurveyResponse;
use SplFileObject;
use CHttpException;
use LogicException;
use RuntimeException;
use UploaderController;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;
use MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\FileController\EndRequestPreventer;

use MAChitgarha\LimeSurveyRestApi\Error\ResourceIdNotFoundError;
use MAChitgarha\LimeSurveyRestApi\Error\UnprocessableEntityError;

use MAChitgarha\LimeSurveyRestApi\Helper\Permission;
use MAChitgarha\LimeSurveyRestApi\Helper\ResponseHelper;
use MAChitgarha\LimeSurveyRestApi\Helper\PermissionChecker;

use MAChitgarha\LimeSurveyRestApi\Helper\SurveyHelper;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

use Symfony\Component\HttpFoundation\Response;

use Symfony\Component\Serializer\Encoder\JsonDecode;

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

    public function new(): JsonResponse
    {
        $this->validateRequest();

        $userId = $this->authorize()->getId();

        [$surveyId, $responseId] = $this->getPathParameterListAsInt(
            'survey_id',
            'response_id',
        );

        $survey = SurveyHelper::get($surveyId);

        PermissionChecker::assertHasSurveyPermission(
            $survey,
            Permission::CREATE,
            $userId,
            'responses'
        );

        SurveyHelper::assertIsActive($survey);

        $data = $this->decodeJsonRequestBodyInnerData();

        $tmpFile = $this->makeTempFile($data['extension']);
        $tmpFile->fwrite(
            $fileContents = \base64_decode($data['contents'])
        );
        $tmpFilePath = $tmpFile->getPathname();

        try {
            self::validateUsingCoreUploader($data['question_id'], $survey, $tmpFilePath, $fileContents);
        } catch (UnprocessableEntityError $error) {
            \unlink($tmpFilePath);
            throw $error;
        }

        return new JsonResponse(
            data([
                'tmp_name' => $tmpFile->getBasename()
            ]),
            Response::HTTP_CREATED
        );
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

    private static function makeTempFile(string $extension): SplFileObject
    {
        $tempUploadDir = \App()->getConfig('tempdir') . '/upload/';
        $filename = 'fupratmp_' . \randomChars(15);

        return new SplFileObject("$tempUploadDir/$filename.$extension", "w");
    }

    private static function validateUsingCoreUploader(
        int $questionId,
        Survey $survey,
        string $tmpFilePath,
        string $fileContents
    ): void {
        $_POST = [
            'mode' => 'upload',
            'fieldname' => FieldNameGenerator::generate(
                SurveyHelper::getQuestionInSurveyById($survey, $questionId)
            ),
        ];

        $_FILES['uploadfile'] = [
            'tmp_name' => $tmpFilePath,
            'name' => $tmpFilePath,
            'size' => \strlen($fileContents),
            'error' => 0,
        ];

        \App()->session['LEMsid'] = $survey->sid;

        \App()->attachEventHandler('onEndRequest', function () {
            throw new EndRequestPreventer();
        });

        \ob_start();
        try {
            (new UploaderController('dummy'))->run(0);
        } catch (CHttpException $exception) {
            // TODO: Maybe the error message is useful?
        } catch (EndRequestPreventer $e) {
        }
        $output = \ob_get_clean();

        $result = (new JsonDecode())->decode($output, '', [
            JsonDecode::ASSOCIATIVE => true,
        ]);

        if (!$result['success'] && $result['msg'] !== gT('An unknown error occured')) {
            throw new UnprocessableEntityError($result['msg']);
        }
    }
}

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response\FileController;

use Exception;

class EndRequestPreventer extends Exception
{
}
