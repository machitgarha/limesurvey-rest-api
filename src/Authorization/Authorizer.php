<?php

namespace MAChitgarha\LimeSurveyRestApi\Authorization;

use Session;

interface Authorizer
{
    /**
     * Authorizes the requester using Authorization header.
     *
     * @param bool $errorOnExpiration Whether to throw a AccessTokenExpiredError when the access
     * token is expired.
     * @return static
     */
    public function authorize(bool $errorOnExpiration = true);

    public function getUsername(): string;
    public function getAccessToken(): string;
}
