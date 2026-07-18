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
 * 📤 Go2My.Link — Shared API Response Emitter (JSON + optional XML/XSLT)
 * ============================================================================
 *
 * A single, reusable place for API endpoints to emit a structured response.
 * Centralises what used to be a dozen near-identical
 *   http_response_code(X); header('Content-Type: application/json…'); echo …
 * blocks scattered through the create endpoint (FG-004 dedup).
 *
 * Content negotiation:
 *   - JSON is the DEFAULT (and the format used by the existing AJAX path).
 *   - XML is emitted only when the caller explicitly asks for it, either via
 *     the "?format=xml" query parameter or an "Accept" header that names
 *     "application/xml" or "text/xml". The XML document carries an
 *     xml-stylesheet processing instruction so a browser renders it via the
 *     accompanying XSLT stylesheet (api/create/response.xsl).
 *
 * Every value placed into the XML document is escaped with htmlspecialchars()
 * using the ENT_XML1 flag, and every element name is sanitised to a valid XML
 * name, so untrusted content can never break well-formedness or inject markup.
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      autopilot COMPLETE (FG-004)
 *
 * 📖 References:
 *     - ENT_XML1:      https://www.php.net/manual/en/function.htmlspecialchars.php
 *     - Accept header: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/Accept
 *     - xml-stylesheet: https://www.w3.org/TR/xml-stylesheet/
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// This file only defines functions; it must never be requested directly.
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 🔍 Format negotiation (pure, testable)
// ============================================================================

/**
 * Decide whether the caller has asked for an XML response.
 *
 * Kept deliberately pure (no superglobals, no side effects) so it can be
 * exercised directly by unit tests. XML is opt-in: JSON remains the default
 * for every request that does not explicitly request XML.
 *
 * @param  array<string, mixed> $get           The query parameters (usually $_GET).
 * @param  string               $acceptHeader  The raw Accept request header value.
 * @return bool                                 True when XML should be emitted.
 */
function g2ml_apiWantsXml(array $get, string $acceptHeader): bool
{
    // 1) An explicit "?format=xml" wins outright.
    $formatParameter = '';

    if (isset($get['format']) && is_string($get['format']))
    {
        $formatParameter = strtolower(trim($get['format']));
    }

    if ($formatParameter === 'xml')
    {
        return true;
    }

    // 2) Otherwise honour an Accept header that names an XML media type.
    $normalisedAccept = strtolower($acceptHeader);

    if (str_contains($normalisedAccept, 'application/xml'))
    {
        return true;
    }

    if (str_contains($normalisedAccept, 'text/xml'))
    {
        return true;
    }

    return false;
}

// ============================================================================
// 🧱 XML construction (pure, testable)
// ============================================================================

/**
 * Coerce an arbitrary array key into a valid XML element name.
 *
 * XML element names must begin with a letter or underscore and may then
 * contain letters, digits, hyphens, full stops and underscores. Anything
 * else is replaced with an underscore. Integer (list) keys have no meaningful
 * name, so callers pass a fallback such as "item" instead.
 *
 * @param  string $key  The raw associative-array key.
 * @return string       A syntactically valid XML element name.
 */
function g2ml_sanitiseXmlElementName(string $key): string
{
    $sanitised = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $key);

    if ($sanitised === null || $sanitised === '')
    {
        return 'item';
    }

    // The name must not start with a digit, hyphen or full stop.
    $firstCharacter = substr($sanitised, 0, 1);

    if (preg_match('/[A-Za-z_]/', $firstCharacter) !== 1)
    {
        $sanitised = '_' . $sanitised;
    }

    // Names beginning with "xml" (any case) are reserved by the spec.
    if (strtolower(substr($sanitised, 0, 3)) === 'xml')
    {
        $sanitised = '_' . $sanitised;
    }

    return $sanitised;
}

/**
 * Render a single scalar (or null) value as escaped XML text content.
 *
 * @param  mixed $value  A string, int, float, bool or null.
 * @return string        The XML-escaped text (empty string for null).
 */
function g2ml_scalarToXmlText(mixed $value): string
{
    if ($value === null)
    {
        return '';
    }

    if (is_bool($value))
    {
        if ($value === true)
        {
            return 'true';
        }

        return 'false';
    }

    if (is_string($value))
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    // int or float — cast, then escape defensively (no markup, but consistent).
    return htmlspecialchars((string) $value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
}

/**
 * Recursively render an associative/indexed array into XML element markup.
 *
 * Nested arrays become nested elements; scalars/bools/null become escaped
 * text nodes. Integer keys (list items) are emitted as <item> elements.
 *
 * @param  array<int|string, mixed> $data         The data to render.
 * @param  int                      $indentLevel  Indentation depth (for readability).
 * @return string                                 The XML fragment (no root element).
 */
function g2ml_arrayToXml(array $data, int $indentLevel = 1): string
{
    $xml    = '';
    $indent = str_repeat('    ', $indentLevel);

    foreach ($data as $key => $value)
    {
        if (is_int($key))
        {
            $elementName = 'item';
        }
        else
        {
            $elementName = g2ml_sanitiseXmlElementName((string) $key);
        }

        if (is_array($value))
        {
            $xml = $xml . $indent . '<' . $elementName . '>' . "\n";
            $xml = $xml . g2ml_arrayToXml($value, $indentLevel + 1);
            $xml = $xml . $indent . '</' . $elementName . '>' . "\n";
        }
        else
        {
            $xml = $xml . $indent . '<' . $elementName . '>'
                . g2ml_scalarToXmlText($value)
                . '</' . $elementName . '>' . "\n";
        }
    }

    return $xml;
}

/**
 * Build a complete, well-formed XML document for an API response payload.
 *
 * The document carries an xml-stylesheet processing instruction pointing at
 * the create endpoint's XSLT so browsers render a friendly HTML view, while
 * programmatic clients can consume the raw XML.
 *
 * @param  array<int|string, mixed> $data  The response payload.
 * @return string                          A UTF-8 XML document string.
 */
function g2ml_buildApiXmlDocument(array $data): string
{
    $document  = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    $document .= '<?xml-stylesheet type="text/xsl" href="/api/create/response.xsl"?>' . "\n";
    $document .= '<response>' . "\n";
    $document .= g2ml_arrayToXml($data, 1);
    $document .= '</response>' . "\n";

    return $document;
}

// ============================================================================
// 📮 Emitter (impure — sets status, headers, echoes, and exits)
// ============================================================================

/**
 * Emit a structured API response and terminate the request.
 *
 * Chooses XML or JSON via g2ml_apiWantsXml() (JSON is the default), sets the
 * status code and matching Content-Type, writes the body, then exits — mirroring
 * the historical behaviour of the create endpoint's inline response blocks.
 *
 * Endpoint-specific headers (for example "Allow" or "Retry-After") should be
 * set by the caller BEFORE calling this function; they will still be sent.
 *
 * @param  array<int|string, mixed> $data        The response payload.
 * @param  int                      $statusCode  The HTTP status code to send.
 * @return never                                 This function never returns (it exits).
 */
function g2ml_apiRespond(array $data, int $statusCode = 200): never
{
    $acceptHeader = '';

    if (isset($_SERVER['HTTP_ACCEPT']) && is_string($_SERVER['HTTP_ACCEPT']))
    {
        $acceptHeader = $_SERVER['HTTP_ACCEPT'];
    }

    if (g2ml_apiWantsXml($_GET, $acceptHeader))
    {
        http_response_code($statusCode);
        header('Content-Type: application/xml; charset=UTF-8');
        echo g2ml_buildApiXmlDocument($data);
    }
    else
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode($data);
    }

    exit;
}

// ============================================================================
// 📦 /api/v1 envelope — {status, data, meta} / {status, error} (#38)
// ============================================================================
//
// The versioned public API (/api/v1/*) publishes a documented envelope shape
// that differs from the website's own internal flat {success:...} responses
// (which api/create and api/consent keep using unchanged, so existing callers
// never break). These builders sit ON TOP of g2ml_apiRespond() — they never
// re-implement JSON/XML negotiation, so /api/v1 gets XML parity for free.
// ============================================================================

/**
 * Build a success envelope for the /api/v1 response shape.
 *
 * @param  mixed                 $data  The success payload (endpoint-specific).
 * @param  array<string, mixed>  $meta  Extra meta fields (e.g. 'rateLimit' => [...]).
 *                                       Overrides the auto-generated 'timestamp'
 *                                       and 'requestId' defaults if supplied.
 * @return array<string, mixed>          {"status":"success","data":...,"meta":{...}}
 *
 * Usage example:
 *   g2ml_apiRespond(g2ml_apiEnvelope(['pong' => true], ['requestId' => $requestId]), 200);
 */
function g2ml_apiEnvelope(mixed $data, array $meta = []): array
{
    $baseMeta = [
        'timestamp' => gmdate('Y-m-d\TH:i:s\Z'),
        'requestId' => g2ml_generateToken(8),
    ];

    $mergedMeta = array_merge($baseMeta, $meta);

    return [
        'status' => 'success',
        'data'   => $data,
        'meta'   => $mergedMeta,
    ];
}

/**
 * Build an error envelope for the /api/v1 response shape.
 *
 * @param  int         $httpCode  The HTTP status code being returned (echoed into error.code).
 * @param  string      $message   A generic, non-leaking human-readable message.
 * @param  string|null $field     Optional offending field name, for validation errors.
 * @return array<string, mixed>    {"status":"error","error":{"code":...,"message":...,"field":...}}
 *
 * Usage example:
 *   g2ml_apiRespond(g2ml_apiErrorBody(401, 'Invalid or expired API key.'), 401);
 */
function g2ml_apiErrorBody(int $httpCode, string $message, ?string $field = null): array
{
    return [
        'status' => 'error',
        'error'  => [
            'code'    => $httpCode,
            'message' => $message,
            'field'   => $field,
        ],
    ];
}

// ============================================================================
// 🚦 /api/v1 handler-thrown client errors (#39)
// ============================================================================
//
// #38 shipped two scope-free/always-succeed exerciser endpoints, so the front
// controller's try/catch only ever needed one outcome: success, or an
// unexpected Throwable mapped generically to 500 (never leaking the cause).
// #39 adds real CRUD endpoints whose handlers legitimately need to signal an
// EXPECTED client-facing outcome — 404 not found, 409 alias-taken, 422
// validation — with a specific message and optional field name, while still
// leaving every UNEXPECTED failure (a DB error, a null-pointer bug, etc.) to
// fall through to the existing generic 500 path untouched. This exception
// type is the single, minimal seam that makes that possible without handlers
// ever calling g2ml_apiRespond()/http_response_code() themselves.
// ============================================================================

/**
 * Thrown by an /api/v1 handler to signal a specific, expected client-facing
 * error (400/404/409/422/etc.) — never for an unexpected failure, which
 * handlers should simply let escape as a generic Throwable (or explicitly
 * throw RuntimeException for) so the front controller's existing 500 path
 * keeps hiding the underlying cause.
 *
 * Usage example:
 *   throw new G2mlApiHandlerException(404, 'No such short URL in this account.');
 *   throw new G2mlApiHandlerException(422, 'destination_url is required.', 'destination_url');
 */
class G2mlApiHandlerException extends RuntimeException
{
    private int $httpStatusCode;
    private ?string $field;

    /**
     * @param int         $httpStatusCode  The HTTP status code to send (e.g. 404, 409, 422).
     * @param string      $message         A generic, non-leaking human-readable message.
     * @param string|null $field           Optional offending field name, for validation errors.
     */
    public function __construct(int $httpStatusCode, string $message, ?string $field = null)
    {
        parent::__construct($message);

        $this->httpStatusCode = $httpStatusCode;
        $this->field           = $field;
    }

    /**
     * @return int  The HTTP status code this error should be sent with.
     */
    public function getHttpStatusCode(): int
    {
        return $this->httpStatusCode;
    }

    /**
     * @return string|null  The offending field name, or null when not field-specific.
     */
    public function getField(): ?string
    {
        return $this->field;
    }
}

// ============================================================================
// 🗺️ /api/v1 parameterised route matching — "METHOD <prefix>/{code}" (#39, extended #41)
// ============================================================================

/**
 * The whitelist of first-path-segment prefixes this matcher will even
 * consider for a two-segment "<prefix>/<code>" shape. This is NOT the thing
 * that decides whether a route actually exists — $paramRouteTable membership
 * still governs that — it only bounds which prefixes are worth building a
 * paramRouteKey for at all, so an arbitrary "foo/bar"-shaped path can never
 * accidentally probe an unrelated table entry.
 *
 * @return array<int, string>
 */
function g2ml_apiParamRoutePrefixes(): array
{
    return [
        'urls',
        'analytics',
    ];
}

/**
 * Attempt to match a parameterised "METHOD <prefix>/{code}" route, where
 * <prefix> is one of g2ml_apiParamRoutePrefixes() (currently 'urls' and
 * 'analytics').
 *
 * Pure and DB-free (no superglobals, no side effects) so it is directly
 * unit-testable. The front controller's route table stays a tiny, explicit
 * map of EXACT "METHOD path" strings (see web/Go2My.Link/public_html/api/v1/index.php)
 * for every literal route (ping, account, org, urls, urls/bulk, analytics);
 * this function is the single, narrow extension that additionally recognises
 * a two-segment "<prefix>/<code>" path whose second segment looks like a
 * short code, WITHOUT turning the dispatcher into a general-purpose router —
 * only prefixes on the fixed whitelist above are ever considered, and even
 * then only when the resulting "METHOD <prefix>/{code}" key actually exists
 * in the caller-supplied $paramRouteTable.
 *
 * A candidate code is re-validated here against the same charset used for
 * both generated (sp_generateShortCode — alphanumeric) and custom
 * (G2ML_CUSTOM_CODE_PATTERN — alphanumeric + hyphen/underscore) short codes,
 * bounded to the tblShortURLs.shortCode VARCHAR(50) column width, even though
 * g2ml_apiSanitiseRoute() already constrained the wider route string — this
 * function must never trust an unvalidated segment even if reused elsewhere.
 *
 * @param  string $httpMethod       The HTTP method, e.g. 'GET', 'PUT', 'DELETE'.
 * @param  string $sanitisedRoute   The route AFTER g2ml_apiSanitiseRoute() has
 *                                   already accepted it (never null here — a
 *                                   null/rejected route never reaches this call).
 * @param  array  $paramRouteTable  Table keyed 'METHOD <prefix>/{code}' => ['scope'=>..., 'handler'=>...].
 * @return array{route: array, code: string}|null  The matched route + extracted
 *                                                   code, or null when this is
 *                                                   not a recognised parameterised route.
 *
 * Usage example:
 *   $match = g2ml_apiMatchParamRoute('GET', 'urls/abc123', $paramRouteTable);
 *   if ($match !== null) { $code = $match['code']; $route = $match['route']; }
 *   $match = g2ml_apiMatchParamRoute('GET', 'analytics/abc123', $paramRouteTable);
 */
function g2ml_apiMatchParamRoute(string $httpMethod, string $sanitisedRoute, array $paramRouteTable): ?array
{
    $segments = explode('/', $sanitisedRoute);

    if (count($segments) !== 2)
    {
        return null;
    }

    $routePrefix = $segments[0];

    if (!in_array($routePrefix, g2ml_apiParamRoutePrefixes(), true))
    {
        return null;
    }

    $candidateCode = $segments[1];

    if (preg_match('/^[A-Za-z0-9_-]{1,50}$/', $candidateCode) !== 1)
    {
        return null;
    }

    $paramRouteKey = $httpMethod . ' ' . $routePrefix . '/{code}';

    if (!isset($paramRouteTable[$paramRouteKey]))
    {
        return null;
    }

    return [
        'route' => $paramRouteTable[$paramRouteKey],
        'code'  => $candidateCode,
    ];
}

/**
 * Sanitise a raw "_apiroute" value to a safe, traversal-free route string.
 *
 * Whitelists letters, digits, forward slash, underscore and hyphen — matching
 * the .htaccess rewrite contract for /api/v1/*. No '.' is permitted at all,
 * so a directory-traversal segment ("..") can never survive this filter.
 * Leading/trailing slashes are trimmed so 'ping', '/ping' and 'ping/' all
 * normalise to the same route key.
 *
 * @param  string $rawRoute  The raw, attacker-controlled route segment.
 * @return string|null        The normalised route (possibly ''), or null if
 *                             it contains any character outside the whitelist.
 */
function g2ml_apiSanitiseRoute(string $rawRoute): ?string
{
    $trimmedRoute = trim($rawRoute, '/');

    if ($trimmedRoute === '')
    {
        return '';
    }

    if (preg_match('/^[A-Za-z0-9\/_-]+$/', $trimmedRoute) !== 1)
    {
        return null;
    }

    return $trimmedRoute;
}
