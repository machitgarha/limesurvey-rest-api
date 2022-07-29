<?php

namespace MAChitgarha\LimeSurveyRestApi\Authorization;

use Session;
use LogicException;

use MAChitgarha\LimeSurveyRestApi\Error\AccessTokenExpiredError;
use MAChitgarha\LimeSurveyRestApi\Error\AccessTokenInvalidError;
use MAChitgarha\LimeSurveyRestApi\Error\AuthorizationHeaderMissingError;
use MAChitgarha\LimeSurveyRestApi\Error\AuthorizationHeaderUnsupportedError;

use Symfony\Component\HttpFoundation\Request;

class BearerTokenAuthorizer implements Authorizer
{
    private const HEADER_AUTHORIZATION = 'Authorization';
    private const REGEX_HEADER_AUTHORIZATION = '/Bearer ([\w~]+)/';

    /** @var Request */
    private $request;

    /** @var ?string */
    private $accessToken = null;

    public function __construct(Request $request)
    {
        $this->request = $request;
    }

    /**
     * @inheritDoc
     */
    public function authorize(bool $errorOnExpiration = true): self
    {
        if (!$this->request->headers->has(self::HEADER_AUTHORIZATION)) {
            throw new AuthorizationHeaderMissingError();
        }

        $authorizationHeader = $this->request->headers->get(self::HEADER_AUTHORIZATION);
        $this->accessToken = self::extractAccessTokenIfValid($authorizationHeader);

        /** @var Session */
        $session = Session::model()->findByPk($this->accessToken);
        if ($session === null) {
            throw new AccessTokenInvalidError();
        }

        if ($session->expire > \time()) {
            $session->delete();
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
            throw new AuthorizationHeaderUnsupportedError();
        }

        return $matches[1];
    }

    /**
     * @inheritDoc
     */
    public function getAccessToken(): string
    {
        if ($this->accessToken === null) {
            throw new LogicException(
                'authorize() must be called before this function to extract access token'
            );
        }
        return $this->accessToken;
    }
}
