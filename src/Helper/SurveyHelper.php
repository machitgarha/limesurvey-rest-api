<?php

namespace MAChitgarha\LimeSurveyRestApi\Helper;

use Survey;
use Question;
use LogicException;

use MAChitgarha\LimeSurveyRestApi\Error\SurveyNotActiveError;
use MAChitgarha\LimeSurveyRestApi\Error\ResourceIdNotFoundError;

class SurveyHelper
{
    public static function getInfo(int $id): array
    {
        App()->loadHelper('common');
        $surveyInfo = \getSurveyInfo($id);

        if ($surveyInfo === false) {
            throw new ResourceIdNotFoundError('survey', $id);
        }
        return $surveyInfo;
    }

    public static function get(int $id): Survey
    {
        return self::getInfo($id)['oSurvey'];
    }

    public static function assertIsActive(Survey $survey): void
    {
        if (!\App()->db->schema->getTable($survey->responsesTableName)) {
            if ($survey->active !== 'N') {
                throw new LogicException('Survey responses table name is not created');
            } else {
                throw new SurveyNotActiveError();
            }
        }
    }

    public static function getQuestionInSurveyById(Survey $survey, int $questionId): Question
    {
        foreach ($survey->baseQuestions as $question) {
            if ((int) $question->qid === $questionId) {
                return $question;
            }
        }
        throw new ResourceIdNotFoundError('question', $questionId);
    }
}
