<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Login;

use Session;
use LSUserIdentity as UserIdentity;

use MAChitgarha\LimeSurveyRestApi\Api\Traits;

use MAChitgarha\LimeSurveyRestApi\Error\InvalidCredentialsError;
use MAChitgarha\LimeSurveyRestApi\Error\TooManyAuthenticationFailuresError;

use MAChitgarha\LimeSurveyRestApi\Plugin;

use MAChitgarha\LimeSurveyRestApi\Utility\ContentTypeValidator;

use Respect\Validation\Validator as v;

use Symfony\Component\HttpFoundation\JsonResponse;

use Symfony\Component\Serializer\Serializer;

class BearerTokenController
{
    use Traits\RequestProperty;
    use Traits\SerializerProperty;
    use Traits\RequestBodyDecoder;

    public const PATH = '/login/bearer_token';

    public function new(): JsonResponse
    {
        ContentTypeValidator::validateIsJson($this->getRequest());

        $data = $this->decodeJsonRequestBodyInnerData();
        $this->validateDataForNew($data);

        $this->login(
            $data['username'],
            $data['password']
        );

        $session = $this->makeNewSession(
            $data['username'],
            App()->securityManager->generateRandomString(32),
        );
        $session->save();

        return new JsonResponse(
            [
                'access_token' => $session->id,
                'expire_time' => $session->expire,
            ],
            JsonResponse::HTTP_CREATED
        );
    }

    private function validateDataForNew(array $bodyData): void
    {
        v::
            key('username', v::stringType())->
            key('password', v::stringType())->
            check($bodyData);
    }

    private function login(string $username, string $password): void
    {
        $identity = new UserIdentity($username, $password);
        $identity->setPlugin(Plugin::getName());

        // Dispatching remoteControlLogin is redundant unlike _doLogin()

        if (!$identity->authenticate()) {
            switch ($identity->errorCode) {
                case UserIdentity::ERROR_IP_LOCKED_OUT:
                    throw new TooManyAuthenticationFailuresError();
                    // No break

                case UserIdentity::ERROR_UNKNOWN_IDENTITY:
                    throw new InvalidCredentialsError();
                    // No break

                default:
                    // TODO: Error message
                    throw new \Exception();
            }
        }
    }

    private function makeNewSession(string $username, string $sessionKey): Session
    {
        $session = new Session();
        $session->id = $sessionKey;
        $session->expire = \time() + (int) Yii::app()->getConfig(
            'iSessionExpirationTime',
            \ini_get('session.gc_maxlifetime')
        );
        $session->data = $username;

        return $session;
    }

    public function delete(): string
    {
        ContentTypeValidator::validateIsJson($this->getRequest());

        return '{}';
    }
}
