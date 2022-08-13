<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response;

class FieldNameGenerator
{
    public static function generate(Question $question): string
    {
        return "{$question->sid}X{$question->gid}X{$question->qid}";
    }

    public static function generateForSubQuestion(
        Question $question,
        string ...$subQuestionCodes
    ): string {
        return self::generateForQuestion($question) . \implode('_', $subQuestionCodes);
    }
}
