<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0;

use Yii;
use User;
use Survey;
use Permission;
use RuntimeException;
use SurveyLanguageSetting;

use MAChitgarha\LimeSurveyRestApi\Api\Interfaces\Controller;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Error\Error;
use MAChitgarha\LimeSurveyRestApi\Error\InternalServerError;
use MAChitgarha\LimeSurveyRestApi\Error\NotImplementedError;

use MAChitgarha\LimeSurveyRestApi\Utility\ContentTypeValidator;

use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;

use function MAChitgarha\LimeSurveyRestApi\Helper\Response\data;

class SurveyController implements Controller
{
    use Traits\ContainerProperty;
    use Traits\AuthorizerGetter;
    use Traits\RequestGetter;
    use Traits\SerializerGetter;

    public const PATH = '/surveys';
    public const PATH_BY_ID = '/surveys/{survey_id}';

    public function list(): JsonResponse
    {
        ContentTypeValidator::validateIsJson($this->getRequest());

        $username = $this->getAuthorizer()->authorize()->getUsername();

        $survey = new Survey();

        $this->setSurveyPermissionByUsername($survey, $username);
        $userSurveys = $survey->findAll();

        $data = [];
        foreach ($userSurveys as $survey) {
            $data[] = [
                'id' => $survey->sid,
                'is_active' => $survey->active !== 'N',
                'creation_time' => \strtotime($survey->datecreated),
                'owner_id' => $survey->owner_id,
                'l10n' => [
                    'title' => $survey->languagesettings[$survey->language]->surveyls_title ?? ''
                ],
            ];
        }

        return new JsonResponse(
            data($data),
            JsonResponse::HTTP_OK
        );
    }

    private function setSurveyPermissionByUsername(Survey $survey, string $username): void
    {
        $userData = User::model()->findByAttributes(['users_name' => $username]);
        if ($userData === null) {
            throw new RuntimeException("Cannot find user with username '$username'");
        }
        $survey->permission($userData->uid);
    }

    public function new(): Response
    {
        throw new NotImplementedError();
    }

    public function get(): JsonResponse
    {
        throw new NotImplementedError();
    }

    public function update(): Response
    {
        throw new NotImplementedError();
    }

    public function delete(): Response
    {
        throw new NotImplementedError();
    }
}
