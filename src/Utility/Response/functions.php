<?php

namespace MAChitgarha\LimeSurveyRestApi\Utility\Response;

function data(array $innerData): array
{
    return ['data' => $innerData];
}

function error($errorData): array
{
    return ['errors' => [$errorData]];
}

function errors($errorDataList): array
{
    return ['errors' => $errorDataList];
}
