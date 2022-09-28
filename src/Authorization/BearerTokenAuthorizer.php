<?php

namespace MAChitgarha\LimeSurveyRestApi\Authorization;

use User;
use Session;
use LogicException;
use RuntimeException;

use MAChitgarha\LimeSurveyRestApi\Error\AccessTokenExpiredError;
use MAChitgarha\LimeSurveyRestApi\Error\AccessTokenInvalidError;
use MAChitgarha\LimeSurveyRestApi\Error\InvalidSecurityError;

use Symfony\Component\HttpFoundation\Request;

class BearerTokenAuthorizer implements Authorizer
{
    private const HEADER_AUTHORIZATION = 'Authorization';
    private const REGEX_HEADER_AUTHORIZATION = '/Bearer ([\w~]+)/';

    /** @var Request */
    private $request;

    /** @var Session|null */
    private $session = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /** @inheritDoc */
    public function authorize(bool $errorOnExpiration = true)
    {
        // Although redundant, but might become useful when Authorization header cannot be read
        if (!$this->request->headers->has(self::HEADER_AUTHORIZATION)) {
            throw new InvalidSecurityError();
        }

        $authorizationHeader = $this->request->headers->get(self::HEADER_AUTHORIZATION);
        $accessToken = self::extractAccessTokenIfValid($authorizationHeader);

        $this->session = Session::model()->findByPk($accessToken);
        if ($this->session === null) {
            throw new AccessTokenInvalidError();
        }

        if ($this->session->expire < \time()) {
            $this->session->delete();
            if ($errorOnExpiration) {
                throw new AccessTokenExpiredError();
            }
        }

        return $this;
    }

    /**
     * Validates the authorization header and extracts the access token value inside it.
     *
     * @return string
     */
    private static function extractAccessTokenIfValid(string $authorizationHeader): string
    {
        if (!\preg_match(self::REGEX_HEADER_AUTHORIZATION, $authorizationHeader, $matches)) {
            throw new InvalidSecurityError();
        }

        return $matches[1];
    }

    // TODO: Make all the below functions a trait
    private function getSession(): Session
    {
        if ($this->session === null) {
            throw new LogicException('authorize() must be called before this function');
        }
        return $this->session;
    }

    public function getUsername(): string
    {
        return $this->getSession()->data;
    }

    public function getAccessToken(): string
    {
        return $this->getSession()->id;
    }

    public function getId(): int
    {
        $userData = User::model()->findByAttributes([
            'users_name' => $username = $this->getUsername()
        ]);

        if ($userData === null) {
            throw new RuntimeException("Cannot find user with username '$username'");
        }

        return $userData->uid;
    }
}
