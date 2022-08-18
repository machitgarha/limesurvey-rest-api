<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

class MandatoryQuestionMissingError extends UnprocessableEntityError
{
    public function __construct(int $questionId)
    {
        parent::__construct();

        $this->extraParams['question_id'] = $questionId;
    }
}
