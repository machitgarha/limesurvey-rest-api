<?php

namespace MAChitgarha\LimeSurveyRestApi\Helper;

use Question;

class ResponseGeneratorHelper
{
    public static function makeQuestionTypeToMethodMap(
        array $methodToQuestionTypeListMapping
    ): array {
        $result = [];

        foreach ($methodToQuestionTypeListMapping as $methodName => $questionTypeList) {
            foreach ($questionTypeList as $questionType) {
                $result[$questionType] = $methodName;
            }
        }

        return $result;
    }

    /**
     * @return array{0:Question[],1:Question[]}
     */
    public static function splitSubQuestionsBasedOnScale2d(Question $question): array
    {
        $yScaleSubQuestionList = [];
        $xScaleSubQuestionList = [];

        foreach ($question->subquestions as $subQuestion) {
            if ((int) $subQuestion->scale_id === 0) {
                $yScaleSubQuestionList[] = $subQuestion;
            } elseif ((int) $subQuestion->scale_id === 1) {
                $xScaleSubQuestionList[] = $subQuestion;
            } else {
                throw new LogicException(
                    "Invalid scale_id for subquestion with ID $subQuestion->qid"
                );
            }
        }

        return [$yScaleSubQuestionList, $xScaleSubQuestionList];
    }
}
