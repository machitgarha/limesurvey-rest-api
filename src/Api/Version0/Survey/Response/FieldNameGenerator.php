<?php

namespace MAChitgarha\LimeSurveyRestApi\Api\Version0\Survey\Response;

use Question;

class FieldNameGenerator
{
    public static function generate(Question $question): string
    {
        return "{$question->sid}X{$question->gid}X{$question->qid}";
    }

    public static function generateSubQuestionSuffixByCode(string ...$subQuestionCodes): string
    {
        return \implode('_', $subQuestionCodes);
    }

    public static function generateSubQuestionSuffix(Question ...$subQuestions): string
    {
        return self::generateSubQuestionSuffixByCode(...\array_column($subQuestions, 'title'));
    }
}
