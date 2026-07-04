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
 * 🧪 Unit tests — shared API responder: format negotiation + XML rendering (FG-004)
 * ============================================================================
 *
 * Pure, DB-free tests for the reusable API response layer defined in
 * web/_functions/api_response.php:
 *
 *   (1) g2ml_apiWantsXml()          — content negotiation (?format=xml or Accept).
 *   (2) g2ml_sanitiseXmlElementName — coerce array keys into valid XML names.
 *   (3) g2ml_scalarToXmlText        — escaped text for scalars/bools/null.
 *   (4) g2ml_arrayToXml             — recursive array → XML fragment.
 *   (5) g2ml_buildApiXmlDocument    — full, well-formed XML document.
 *
 * The file is safe to require here: its direct-access guard only fires when the
 * script itself is the entry point (SCRIPT_FILENAME), which under the test
 * runner is run.php, and the functions do no I/O and touch no database. The
 * impure emitter g2ml_apiRespond() is intentionally NOT exercised (it calls
 * exit()); its format decision is covered via the pure g2ml_apiWantsXml().
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      autopilot COMPLETE (FG-004)
 * ============================================================================
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/web/_functions/api_response.php';

// ============================================================================
// 🔍 g2ml_apiWantsXml — content negotiation
// ============================================================================

test('wantsXml: JSON is the default when nothing asks for XML', function (): void
{
    assert_false(g2ml_apiWantsXml([], ''), 'An empty request must default to JSON, not XML');
});

test('wantsXml: an explicit ?format=xml selects XML', function (): void
{
    assert_true(g2ml_apiWantsXml(['format' => 'xml'], ''), 'format=xml must select XML');
});

test('wantsXml: ?format is case-insensitive and trimmed', function (): void
{
    assert_true(g2ml_apiWantsXml(['format' => '  XML  '], ''), 'Padded/upper-case XML must still select XML');
});

test('wantsXml: ?format=json stays on JSON', function (): void
{
    assert_false(g2ml_apiWantsXml(['format' => 'json'], ''), 'An explicit json format must not select XML');
});

test('wantsXml: an Accept header naming application/xml selects XML', function (): void
{
    assert_true(g2ml_apiWantsXml([], 'application/xml'), 'application/xml in Accept must select XML');
});

test('wantsXml: an Accept header naming text/xml selects XML', function (): void
{
    assert_true(g2ml_apiWantsXml([], 'text/xml'), 'text/xml in Accept must select XML');
});

test('wantsXml: a realistic browser Accept header with application/xml selects XML', function (): void
{
    $accept = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8';
    assert_true(g2ml_apiWantsXml([], $accept), 'A browser Accept listing application/xml must select XML');
});

test('wantsXml: the Accept header match is case-insensitive', function (): void
{
    assert_true(g2ml_apiWantsXml([], 'APPLICATION/XML'), 'Accept matching must ignore case');
});

test('wantsXml: an Accept header for JSON stays on JSON', function (): void
{
    assert_false(g2ml_apiWantsXml([], 'application/json'), 'A JSON Accept header must not select XML');
});

// ============================================================================
// 🏷️ g2ml_sanitiseXmlElementName — valid XML element names
// ============================================================================

test('elementName: a plain key is returned unchanged', function (): void
{
    assert_same('success', g2ml_sanitiseXmlElementName('success'), 'A valid name passes through');
});

test('elementName: whitespace and colons become underscores', function (): void
{
    assert_same('short_URL', g2ml_sanitiseXmlElementName('short URL'), 'A space is not valid in a name');
    assert_same('foo_bar', g2ml_sanitiseXmlElementName('foo:bar'), 'A colon (namespace char) is neutralised');
});

test('elementName: a leading digit is prefixed with an underscore', function (): void
{
    assert_same('_123abc', g2ml_sanitiseXmlElementName('123abc'), 'Names may not begin with a digit');
});

test('elementName: a reserved xml-prefixed name is escaped', function (): void
{
    assert_same('_xmlNode', g2ml_sanitiseXmlElementName('xmlNode'), 'The xml prefix is reserved by the spec');
});

test('elementName: an empty key falls back to item', function (): void
{
    assert_same('item', g2ml_sanitiseXmlElementName(''), 'An empty key has no usable name and uses the fallback');
});

test('elementName: invalid characters are each replaced with an underscore', function (): void
{
    // Each "!" is not a valid name character, so it becomes "_"; the result
    // ("___") is itself a syntactically valid XML element name.
    assert_same('___', g2ml_sanitiseXmlElementName('!!!'), 'Invalid characters map one-to-one to underscores');
});

// ============================================================================
// 🔤 g2ml_scalarToXmlText — escaped text nodes
// ============================================================================

test('scalarText: null renders as an empty string', function (): void
{
    assert_same('', g2ml_scalarToXmlText(null), 'null carries no text content');
});

test('scalarText: booleans render as the words true/false', function (): void
{
    assert_same('true', g2ml_scalarToXmlText(true), 'Boolean true renders as the literal true');
    assert_same('false', g2ml_scalarToXmlText(false), 'Boolean false renders as the literal false');
});

test('scalarText: an integer renders as its decimal string', function (): void
{
    assert_same('42', g2ml_scalarToXmlText(42), 'Integers stringify directly');
});

test('scalarText: XML metacharacters are entity-escaped', function (): void
{
    $escaped = g2ml_scalarToXmlText('<a href="x">&\'');
    assert_contains('&lt;a', $escaped, 'A less-than sign must be escaped');
    assert_contains('&amp;', $escaped, 'An ampersand must be escaped');
    assert_contains('&quot;', $escaped, 'A double quote must be escaped');
    assert_contains('&apos;', $escaped, 'A single quote must be escaped under ENT_XML1');
    assert_false(str_contains($escaped, '<a'), 'No raw markup may survive escaping');
});

// ============================================================================
// 🧱 g2ml_arrayToXml / g2ml_buildApiXmlDocument — well-formed, escaped documents
// ============================================================================

test('document: the success-shape produces a well-formed, parseable document', function (): void
{
    $data = [
        'success'   => true,
        'shortURL'  => 'https://g2my.link/abc123',
        'shortCode' => 'abc123',
    ];

    $document = g2ml_buildApiXmlDocument($data);

    // Well-formedness: SimpleXML returns false on a parse error.
    $parsed = simplexml_load_string($document);
    assert_true($parsed !== false, 'The success document must parse as well-formed XML');
    assert_same('response', $parsed->getName(), 'The root element must be <response>');
    assert_same('true', (string) $parsed->success, 'The success flag round-trips through XML');
    assert_same('https://g2my.link/abc123', (string) $parsed->shortURL, 'The short URL round-trips through XML');
    assert_same('abc123', (string) $parsed->shortCode, 'The short code round-trips through XML');
});

test('document: it carries the xml-stylesheet processing instruction and XML declaration', function (): void
{
    $document = g2ml_buildApiXmlDocument(['success' => true]);

    assert_contains('<?xml version="1.0" encoding="UTF-8"?>', $document, 'The XML declaration must be present');
    assert_contains('<?xml-stylesheet type="text/xsl" href="/api/create/response.xsl"?>', $document, 'The stylesheet PI must point at the XSLT');
});

test('document: it also loads under the stricter DOM parser', function (): void
{
    $document = g2ml_buildApiXmlDocument([
        'success' => false,
        'error'   => 'Please enter a URL to shorten.',
    ]);

    $dom    = new DOMDocument();
    $loaded = $dom->loadXML($document);

    assert_true($loaded, 'The document must load cleanly under DOMDocument (strict well-formedness)');
    assert_same('response', $dom->documentElement->nodeName, 'DOM confirms the <response> root');
});

test('document: the error-shape round-trips its message through XML', function (): void
{
    $data = [
        'success' => false,
        'error'   => 'Rate limit exceeded. Please try again later.',
    ];

    $parsed = simplexml_load_string(g2ml_buildApiXmlDocument($data));
    assert_true($parsed !== false, 'The error document must be well-formed');
    assert_same('false', (string) $parsed->success, 'The failure flag round-trips');
    assert_same('Rate limit exceeded. Please try again later.', (string) $parsed->error, 'The error message round-trips intact');
});

test('document: a hostile value is entity-escaped, never emitted as raw markup', function (): void
{
    $payload = '<script>alert(1)</script>&"\'';
    $data    = [
        'success' => false,
        'error'   => $payload,
    ];

    $document = g2ml_buildApiXmlDocument($data);

    // 1) No raw markup leaks into the serialised document.
    assert_false(str_contains($document, '<script>'), 'A raw <script> tag must never appear in the output');
    assert_contains('&lt;script&gt;', $document, 'The angle brackets of the payload must be entity-escaped');

    // 2) It still parses, and the decoded text equals the original payload
    //    exactly — proving the escaping is lossless as well as safe.
    $parsed = simplexml_load_string($document);
    assert_true($parsed !== false, 'A document built from hostile input must remain well-formed');
    assert_same($payload, (string) $parsed->error, 'The escaped value must decode back to the exact original string');
});

test('document: nested arrays and booleans render as nested elements', function (): void
{
    $data = [
        'success' => true,
        'meta'    => [
            'cached'    => false,
            'attempts'  => 3,
        ],
        'aliases' => ['first', 'second'],
    ];

    $parsed = simplexml_load_string(g2ml_buildApiXmlDocument($data));
    assert_true($parsed !== false, 'A nested document must be well-formed');
    assert_same('false', (string) $parsed->meta->cached, 'A nested boolean renders inside its parent element');
    assert_same('3', (string) $parsed->meta->attempts, 'A nested integer renders inside its parent element');

    // Indexed (list) values become repeated <item> elements.
    $items = $parsed->aliases->item;
    assert_same(2, $items->count(), 'A two-element list yields two <item> elements');
    assert_same('first', (string) $items[0], 'The first list value is preserved in order');
    assert_same('second', (string) $items[1], 'The second list value is preserved in order');
});
