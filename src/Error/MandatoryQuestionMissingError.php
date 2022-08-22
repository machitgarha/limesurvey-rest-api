<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

class MandatoryQuestionMissingError extends UnprocessableEntityError
{
    public function __construct(int $questionId, string $message = null)
    {
        parent::__construct($message);

        $this->extraParams['question_id'] = $questionId;
    }
}
