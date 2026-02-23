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
 * 🔍 Go2My.Link — JSON Schema Validator
 * ============================================================================
 *
 * Lightweight, pure-PHP JSON Schema validator for draft 2020-12 schemas.
 * No external dependencies (Composer-free for Dreamhost shared hosting).
 *
 * Supports validation of: type, required, properties, additionalProperties,
 * const, enum, pattern, minLength, maxLength, minimum, maximum, format,
 * items, oneOf, and nested object/array validation.
 *
 * Usage:
 *   $result = g2ml_validateJSON($data, G2ML_SCHEMAS . '/api/create-response.schema.json');
 *   if (!$result['valid']) {
 *       foreach ($result['errors'] as $error) { ... }
 *   }
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.6.0
 * @since      Phase 5
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

// ============================================================================
// 📁 Schema Directory Constant
// ============================================================================
if (!defined('G2ML_SCHEMAS'))
{
    define('G2ML_SCHEMAS', dirname(__DIR__) . DIRECTORY_SEPARATOR . '_schemas');
}

// ============================================================================
// 🔍 Public API
// ============================================================================

/**
 * Validate data against a JSON Schema file.
 *
 * @param  mixed  $data       The data to validate (decoded JSON)
 * @param  string $schemaPath Absolute path to the .schema.json file
 * @return array{valid: bool, errors: string[]}
 */
function g2ml_validateJSON(mixed $data, string $schemaPath): array
{
    if (!file_exists($schemaPath))
    {
        return [
            'valid'  => false,
            'errors' => ["Schema file not found: {$schemaPath}"],
        ];
    }

    $schemaJSON = file_get_contents($schemaPath);
    $schema     = json_decode($schemaJSON, true);

    if ($schema === null && json_last_error() !== JSON_ERROR_NONE)
    {
        return [
            'valid'  => false,
            'errors' => ['Invalid schema JSON: ' . json_last_error_msg()],
        ];
    }

    $errors = [];
    _g2ml_validateValue($data, $schema, '$', $errors);

    return [
        'valid'  => empty($errors),
        'errors' => $errors,
    ];
}

/**
 * Validate data against an inline schema array (no file).
 *
 * @param  mixed $data   The data to validate
 * @param  array $schema The schema as a PHP array
 * @return array{valid: bool, errors: string[]}
 */
function g2ml_validateJSONInline(mixed $data, array $schema): array
{
    $errors = [];
    _g2ml_validateValue($data, $schema, '$', $errors);

    return [
        'valid'  => empty($errors),
        'errors' => $errors,
    ];
}

// ============================================================================
// 🔧 Internal Validation Engine
// ============================================================================

/**
 * Recursively validate a value against a schema node.
 *
 * @param  mixed    $value   The value to validate
 * @param  array    $schema  The schema node
 * @param  string   $path    JSON path for error reporting (e.g., '$.shortURL')
 * @param  string[] &$errors Collected error messages (by reference)
 * @return void
 */
function _g2ml_validateValue(mixed $value, array $schema, string $path, array &$errors): void
{
    // ---- oneOf ----
    if (isset($schema['oneOf']))
    {
        $oneOfMatches = 0;

        foreach ($schema['oneOf'] as $subSchema)
        {
            $subErrors = [];
            _g2ml_validateValue($value, $subSchema, $path, $subErrors);

            if (empty($subErrors))
            {
                $oneOfMatches++;
            }
        }

        if ($oneOfMatches === 0)
        {
            $errors[] = "{$path}: value does not match any oneOf schemas";
        }
        elseif ($oneOfMatches > 1)
        {
            $errors[] = "{$path}: value matches more than one oneOf schema";
        }

        return;
    }

    // ---- type ----
    if (isset($schema['type']))
    {
        if (!_g2ml_checkType($value, $schema['type']))
        {
            $actual = gettype($value);
            $errors[] = "{$path}: expected type '{$schema['type']}', got '{$actual}'";
            return; // Stop further validation if type is wrong
        }
    }

    // ---- const ----
    if (array_key_exists('const', $schema))
    {
        if ($value !== $schema['const'])
        {
            $expected = var_export($schema['const'], true);
            $errors[] = "{$path}: expected const value {$expected}";
        }
    }

    // ---- enum ----
    if (isset($schema['enum']))
    {
        if (!in_array($value, $schema['enum'], true))
        {
            $allowed = implode(', ', array_map(fn($v) => var_export($v, true), $schema['enum']));
            $errors[] = "{$path}: value must be one of [{$allowed}]";
        }
    }

    // ---- String validations ----
    if (is_string($value))
    {
        if (isset($schema['minLength']) && mb_strlen($value) < $schema['minLength'])
        {
            $errors[] = "{$path}: string length must be >= {$schema['minLength']}";
        }

        if (isset($schema['maxLength']) && mb_strlen($value) > $schema['maxLength'])
        {
            $errors[] = "{$path}: string length must be <= {$schema['maxLength']}";
        }

        if (isset($schema['pattern']) && !preg_match('/' . $schema['pattern'] . '/', $value))
        {
            $errors[] = "{$path}: string does not match pattern '{$schema['pattern']}'";
        }

        if (isset($schema['format']))
        {
            _g2ml_validateFormat($value, $schema['format'], $path, $errors);
        }
    }

    // ---- Numeric validations ----
    if (is_int($value) || is_float($value))
    {
        if (isset($schema['minimum']) && $value < $schema['minimum'])
        {
            $errors[] = "{$path}: value must be >= {$schema['minimum']}";
        }

        if (isset($schema['maximum']) && $value > $schema['maximum'])
        {
            $errors[] = "{$path}: value must be <= {$schema['maximum']}";
        }
    }

    // ---- Object validations ----
    if (is_array($value) && !array_is_list($value))
    {
        // Required properties
        if (isset($schema['required']))
        {
            foreach ($schema['required'] as $reqProp)
            {
                if (!array_key_exists($reqProp, $value))
                {
                    $errors[] = "{$path}: missing required property '{$reqProp}'";
                }
            }
        }

        // Property-level validation
        if (isset($schema['properties']))
        {
            foreach ($schema['properties'] as $propName => $propSchema)
            {
                if (array_key_exists($propName, $value))
                {
                    _g2ml_validateValue($value[$propName], $propSchema, "{$path}.{$propName}", $errors);
                }
            }
        }

        // Additional properties check
        if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false)
        {
            $allowed = array_keys($schema['properties'] ?? []);

            foreach (array_keys($value) as $key)
            {
                if (!in_array($key, $allowed, true))
                {
                    $errors[] = "{$path}: unexpected property '{$key}'";
                }
            }
        }
    }

    // ---- Array validations ----
    if (is_array($value) && array_is_list($value))
    {
        if (isset($schema['items']))
        {
            foreach ($value as $i => $item)
            {
                _g2ml_validateValue($item, $schema['items'], "{$path}[{$i}]", $errors);
            }
        }

        if (isset($schema['minItems']) && count($value) < $schema['minItems'])
        {
            $errors[] = "{$path}: array must have >= {$schema['minItems']} items";
        }

        if (isset($schema['maxItems']) && count($value) > $schema['maxItems'])
        {
            $errors[] = "{$path}: array must have <= {$schema['maxItems']} items";
        }
    }
}

/**
 * Check if a value matches a JSON Schema type.
 *
 * @param  mixed  $value The value to check
 * @param  string $type  The expected JSON Schema type
 * @return bool
 */
function _g2ml_checkType(mixed $value, string $type): bool
{
    return match ($type)
    {
        'string'  => is_string($value),
        'integer' => is_int($value),
        'number'  => is_int($value) || is_float($value),
        'boolean' => is_bool($value),
        'array'   => is_array($value) && array_is_list($value),
        'object'  => is_array($value) && !array_is_list($value),
        'null'    => is_null($value),
        default   => true,
    };
}

/**
 * Validate a string value against a format keyword.
 *
 * @param  string   $value  The string to validate
 * @param  string   $format The format keyword (uri, date-time, email, etc.)
 * @param  string   $path   JSON path for error reporting
 * @param  string[] &$errors Collected errors
 * @return void
 */
function _g2ml_validateFormat(string $value, string $format, string $path, array &$errors): void
{
    $valid = match ($format)
    {
        'uri'       => filter_var($value, FILTER_VALIDATE_URL) !== false,
        'email'     => filter_var($value, FILTER_VALIDATE_EMAIL) !== false,
        'date-time' => (bool) strtotime($value),
        'date'      => (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value),
        'ipv4'      => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false,
        'ipv6'      => filter_var($value, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false,
        default     => true, // Unknown formats pass (per spec)
    };

    if (!$valid)
    {
        $errors[] = "{$path}: string does not match format '{$format}'";
    }
}
