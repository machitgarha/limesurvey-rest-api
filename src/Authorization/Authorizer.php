<?php

namespace MAChitgarha\LimeSurveyRestApi\Authorization;

interface Authorizer
{
    /**
     * @return static
     */
    public function authorize();

    public function getAccessToken(): string;
}
