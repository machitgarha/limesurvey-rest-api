<?php

namespace MAChitgarha\LimeSurveyRestApi\Helper\Response;

/**
 * Wraps a data inside a 'data' key.
 */
function data(array $innerData): array
{
    return ['data' => $innerData];
}

/**
 * Wraps an error information inside an 'errors' key with only one item.
 */
function error($errorData): array
{
    return ['errors' => [$errorData]];
}
