<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Login;

use AuthPluginBase;
use LSUserIdentity as UserIdentity;
use PluginEvent;
use Session;

use MAChitgarha\LimeSurveyRestApi\Api\Controller;

use MAChitgarha\LimeSurveyRestApi\Error\AccessTokenExpiredError;
use MAChitgarha\LimeSurveyRestApi\Error\InvalidCredentialsError;
use MAChitgarha\LimeSurveyRestApi\Error\TooManyAuthenticationFailuresError;

use MAChitgarha\LimeSurveyRestApi\Utility\Response\EmptyResponse;
use MAChitgarha\LimeSurveyRestApi\Utility\Response\JsonResponse;

use function MAChitgarha\LimeSurveyRestApi\Utility\Response\data;

class BearerTokenController extends Controller
{
    public const PATH = '/login/bearer_token';

    public function new(): JsonResponse
    {
        $this->validateRequest();

        $data = $this->decodeJsonRequestBodyInnerData();

        $this->login(
            $data['username'],
            $data['password']
        );

        $session = $this->makeNewSession(
            $data['username'],
            App()->securityManager->generateRandomString(32)
        );
        $session->save();

        return new JsonResponse(
            data([
                'access_token' => $session->id,
                'expire_time' => $session->expire,
            ]),
            JsonResponse::HTTP_CREATED
        );
    }

    private function login(string $username, string $password): void
    {
        $authPlugin = 'Authdb';

        $identity = new UserIdentity($username, $password);
        $identity->setPlugin($authPlugin);

        $this->dispatchRemoteControlLoginEventToAuthPlugin(
            $identity,
            $username,
            $password,
            $authPlugin
        );

        if (!$identity->authenticate()) {
            switch ($identity->errorCode) {
                case AuthPluginBase::ERROR_IP_LOCKED_OUT:
                    throw new TooManyAuthenticationFailuresError($identity->errorMessage);
                    // No break

                case AuthPluginBase::ERROR_USERNAME_INVALID:
                case AuthPluginBase::ERROR_PASSWORD_INVALID:
                case AuthPluginBase::ERROR_UNKNOWN_IDENTITY:
                    throw new InvalidCredentialsError();
                    // No break

                default:
                    throw new \Exception(
                        "$identity->errorMessage (code: $identity->errorCode)"
                    );
            }
        }
    }

    private function dispatchRemoteControlLoginEventToAuthPlugin(
        UserIdentity $identity,
        string $username,
        string $password,
        string $authPlugin
    ): void {
        $event = new PluginEvent('remoteControlLogin');
        $event->set('identity', $identity);
        $event->set('plugin', $authPlugin);
        $event->set('username', $username);
        $event->set('password', $password);

        App()->getPluginManager()->dispatchEvent($event, [$authPlugin]);
    }

    private function makeNewSession(string $username, string $sessionKey): Session
    {
        $session = new Session();
        $session->id = $sessionKey;
        $session->expire = \time() + (int) App()->getConfig(
            'iSessionExpirationTime',
            \ini_get('session.gc_maxlifetime')
        );
        $session->data = $username;

        return $session;
    }

    public function delete(): EmptyResponse
    {
        $this->validateRequest();

        try {
            $accessToken = $this->getAuthorizer()->authorize(false)->getAccessToken();
            Session::model()->deleteByPk($accessToken);
        } catch (AccessTokenExpiredError $error) {
            // Deletion doesn't care about expiration
        }

        return new EmptyResponse();
    }
}
