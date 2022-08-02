<?php

namespace MAChitgarha\LimeSurveyRestApi\Api;

use Survey;
use Permission;

use MAChitgarha\LimeSurveyRestApi\Error\PermissionDeniedError;

class PermissionChecker
{
    public static function assertHasSurveyPermission(
        Survey $survey,
        string $permission,
        int $userId
    ): void {
        if (!$survey->hasPermission('survey', $permission, $userId)) {
            throw new PermissionDeniedError(
                "No $permission permission on survey with id '$survey->sid'"
            );
        }
    }
}
