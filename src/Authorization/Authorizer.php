<?php

namespace MAChitgarha\LimeSurveyRestApi\Authorization;

interface Authorizer
{
    /**
     * @return $this
     */
    public function authorize(): self;

    public function getAccessToken(): string;
}
