<?php
/**
 * Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
 * All rights reserved.
 *
 * This source code is proprietary and confidential.
 * Unauthorised copying, modification, or distribution is strictly prohibited.
 */

/**
 * ============================================================================
 * 📦 Go2My.Link — API v1 Handler: POST /api/v1/urls/bulk (#39)
 * ============================================================================
 *
 * Batch-creates up to G2ML_API_BULK_MAX_ITEMS short URLs in one request,
 * reusing createShortURL() per row exactly like g2ml_apiHandleUrlsCreate()
 * (same identity binding: the verified key's own org/user/key, never a
 * client-supplied value). This is deliberately a plain sequential loop, NOT a
 * single all-or-nothing database transaction — the whole point of a bulk
 * endpoint's contract is PARTIAL success: one row's failure (a taken alias, a
 * blocked destination) must never roll back the rows that already succeeded.
 * Each row's outcome is reported independently in the `results` array.
 *
 * @package    Go2My.Link
 * @subpackage API
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#39)
 *
 * 📖 References:
 *     - web/Go2My.Link/_functions/shorturl_create.php — createShortURL()
 *     - web/Go2My.Link/public_html/api/v1/handlers/urls.php — g2ml_apiHandleUrlsCreate() (single-row sibling)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

if (!defined('G2ML_API_BULK_MAX_ITEMS'))
{
    define('G2ML_API_BULK_MAX_ITEMS', 100);
}

/**
 * Handle POST /api/v1/urls/bulk.
 *
 * Request body: {"items": [ {destination_url, custom_code?, title?, tags?}, ... ]}
 * (up to G2ML_API_BULK_MAX_ITEMS entries).
 *
 * @param  array $keyRow   The verified API key row.
 * @param  array $context  Request context; 'body' carries the decoded JSON.
 * @return array            {results: array<int, array{input_index:int, success:bool, short_code?:string, short_url?:string, error?:string}>}
 *
 * @throws G2mlApiHandlerException  422 when 'items' is missing/malformed,
 *                                   empty, or over the per-request cap. A
 *                                   per-row validation/create failure is
 *                                   NEVER thrown — it is captured in that
 *                                   row's own result entry so sibling rows
 *                                   still get a chance to succeed.
 */
function g2ml_apiHandleUrlsBulkCreate(array $keyRow, array $context): array
{
    $body = [];

    if (isset($context['body']) && is_array($context['body']))
    {
        $body = $context['body'];
    }

    if (!isset($body['items']) || !is_array($body['items']) || !array_is_list($body['items']))
    {
        throw new G2mlApiHandlerException(422, 'items must be an array of URL objects.', 'items');
    }

    $items = $body['items'];

    if (count($items) === 0)
    {
        throw new G2mlApiHandlerException(422, 'items must contain at least one entry.', 'items');
    }

    if (count($items) > G2ML_API_BULK_MAX_ITEMS)
    {
        throw new G2mlApiHandlerException(422, 'items must contain no more than ' . G2ML_API_BULK_MAX_ITEMS . ' entries.', 'items');
    }

    $orgHandle = (string) $keyRow['orgHandle'];
    $userUID   = (int) $keyRow['userUID'];
    $apiKeyUID = (int) $keyRow['apiKeyUID'];

    $results = [];

    foreach ($items as $inputIndex => $item)
    {
        $results[] = _g2ml_apiUrlsBulkCreateOne((int) $inputIndex, $item, $orgHandle, $userUID, $apiKeyUID);
    }

    return ['results' => $results];
}

/**
 * Validate and attempt one row of a bulk-create request, reusing
 * createShortURL(). Every failure path (bad shape, missing field, create
 * rejection) is captured into the returned row rather than thrown, so a
 * single bad row can never abort the sibling rows already processed or still
 * to come.
 *
 * @param  int    $inputIndex  The row's position in the submitted items array.
 * @param  mixed  $item        The raw (pre-validation) item value.
 * @param  string $orgHandle   The verified key's own org (never client-supplied).
 * @param  int    $userUID     The verified key's own owning user.
 * @param  int    $apiKeyUID   The verified key's own apiKeyUID.
 * @return array                {input_index, success, short_code?, short_url?, error?}
 */
function _g2ml_apiUrlsBulkCreateOne(int $inputIndex, mixed $item, string $orgHandle, int $userUID, int $apiKeyUID): array
{
    if (!is_array($item))
    {
        return [
            'input_index' => $inputIndex,
            'success'     => false,
            'error'       => 'Each item must be an object.',
        ];
    }

    if (!isset($item['destination_url']) || !is_string($item['destination_url']) || trim($item['destination_url']) === '')
    {
        return [
            'input_index' => $inputIndex,
            'success'     => false,
            'error'       => 'destination_url is required and must be a non-empty string.',
        ];
    }

    $itemTitle = null;

    if (isset($item['title']))
    {
        if (!is_string($item['title']))
        {
            return [
                'input_index' => $inputIndex,
                'success'     => false,
                'error'       => 'title must be a string.',
            ];
        }

        $itemTitleTrimmed = trim($item['title']);

        if ($itemTitleTrimmed !== '')
        {
            $itemTitle = $itemTitleTrimmed;
        }
    }

    $itemCustomCode = null;

    if (isset($item['custom_code']))
    {
        if (!is_string($item['custom_code']))
        {
            return [
                'input_index' => $inputIndex,
                'success'     => false,
                'error'       => 'custom_code must be a string.',
            ];
        }

        $itemCustomCode = trim($item['custom_code']);
    }

    $itemTags = null;

    if (isset($item['tags']))
    {
        if (!is_string($item['tags']) && !is_array($item['tags']))
        {
            return [
                'input_index' => $inputIndex,
                'success'     => false,
                'error'       => 'tags must be a string or an array of strings.',
            ];
        }

        $itemTags = $item['tags'];
    }

    $createOptions = [
        'orgHandle'           => $orgHandle,
        'userUID'             => $userUID,
        'title'               => $itemTitle,
        'createdVia'          => 'api',
        'createdViaAPIKeyUID' => $apiKeyUID,
    ];

    if ($itemCustomCode !== null && $itemCustomCode !== '')
    {
        $createOptions['customCode'] = $itemCustomCode;
    }

    if ($itemTags !== null)
    {
        $createOptions['tags'] = $itemTags;
    }

    $created = createShortURL(trim($item['destination_url']), $createOptions);

    if ($created['success'] === true)
    {
        return [
            'input_index' => $inputIndex,
            'success'     => true,
            'short_code'  => $created['shortCode'],
            'short_url'   => $created['shortURL'],
        ];
    }

    $errorMessage = 'Failed to create the short URL.';

    if (isset($created['error']) && is_string($created['error']))
    {
        $errorMessage = $created['error'];
    }

    return [
        'input_index' => $inputIndex,
        'success'     => false,
        'error'       => $errorMessage,
    ];
}
