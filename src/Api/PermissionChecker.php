<?php

namespace MAChitgarha\LimeSurveyRestApi\Api;

use Survey;
use Permission;

use MAChitgarha\LimeSurveyRestApi\Error\PermissionDeniedError;

class PermissionChecker
{
    public static function assertHasSurveyPermission(
        Survey $survey,
        string $operation,
        int $userId,
        string $target = 'survey'
    ): void {
        if (!$survey->hasPermission($target, $operation, $userId)) {
            throw new PermissionDeniedError(
                "No $operation permission on survey with id '$survey->sid'"
            );
        }
    }
}
