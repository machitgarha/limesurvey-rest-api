<?php

namespace MAChitgarha\LimeSurveyRestApi\Error;

class InvalidAnswerError extends UnprocessableEntityError
{
    public function __construct(int $questionId, string $message)
    {
        parent::__construct($message);

        $this->extraParams['question_id'] = $questionId;
    }
}
