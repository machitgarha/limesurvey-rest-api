<?php

namespace MAChitgarha\LimeSurveyRestApi\Helper\Response;

/**
 * Wraps a data inside a 'data' key.
 * @return array
 */
function data(array $innerData): array
{
    return ['data' => $innerData];
}

/**
 * Wraps an error information inside an 'errors' key with only one item.
 * @return array
 */
function error(array $errorData): array
{
    return ['errors' => [$errorData]];
}
