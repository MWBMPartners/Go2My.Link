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
 * 🧪 Unit tests — the stricter link check when LinksPage addresses are saved
 *                  (issue #308, piece F1c)
 * ============================================================================
 *
 * Every place in web/_functions/linkspage_manage.php that saves an address
 * must run g2ml_destinationProblem() on it:
 *   - social links
 *   - the page avatar
 *   - a manual item address, on add and on edit
 *   - an item icon, on add and on edit
 *
 * Each save function is run for real in a child process (so the stand-in
 * database functions never leak into the shared test process). The database
 * functions are stand-ins that answer just enough for the save to reach the
 * address check and, when it passes, the "store" step. Nothing here needs a
 * database.
 *
 * A refusal must look exactly like the place's existing refusal for an
 * invalid address (same error, or the social link silently skipped).
 *
 * @package    Go2My.Link
 * @subpackage Tests
 * @since      v1.2.0 — #308 (F1c)
 * ============================================================================
 */

declare(strict_types=1);

/**
 * Run one script in a clean child process, with stand-ins for __() and the
 * database functions already defined, and return what it printed.
 *
 * @param  string $scriptBody  PHP code (no opening tag) to run after the stand-ins.
 * @return string              Trimmed output, or '' when the child could not run.
 */
function g2ml_lpdc_run_child(string $scriptBody): string
{
    $securityPath = dirname(__DIR__, 2) . '/web/_functions/security.php';
    $managePath   = dirname(__DIR__, 2) . '/web/_functions/linkspage_manage.php';

    $script = <<<'PHP'
<?php
declare(strict_types=1);

// Stand-in for __(): returns the key, so a test can see which error was used.
function __(string $key, array $replacements = []): string
{
    return $key;
}

$GLOBALS['stored'] = [];

// Stand-in database functions: just enough for the item saves to run.
function dbSelectOne(string $sql, string $types = '', array $params = []): array|null|false
{
    if (str_contains($sql, 'COUNT(*)'))
    {
        return ['itemCount' => 0];
    }

    if (str_contains($sql, 'MAX(sortOrder)'))
    {
        return ['maxSort' => null];
    }

    if (str_contains($sql, 'FROM tblLinksPageItems i'))
    {
        return ['itemUID' => 5, 'pageUID' => 3, 'urlUID' => null, 'itemTitle' => 'Old', 'itemURL' => 'https://example.com/old',
                'itemDescription' => null, 'itemIcon' => null, 'requiresAgeGate' => 0, 'sortOrder' => 0, 'isActive' => 1];
    }

    return ['pageUID' => 3, 'userUID' => 1, 'orgHandle' => 'x', 'slug' => 'x', 'pageTitle' => 'X'];
}

function dbInsert(string $sql, string $types = '', array $params = []): int|bool
{
    $GLOBALS['stored'] = $params;

    return 7;
}

function dbUpdate(string $sql, string $types = '', array $params = []): int|false
{
    $GLOBALS['stored'] = $params;

    return 1;
}

PHP;

    $script .= 'require ' . var_export($securityPath, true) . ';' . "\n";
    $script .= 'require ' . var_export($managePath, true) . ';' . "\n";
    $script .= $scriptBody;

    $tempFile = tempnam(sys_get_temp_dir(), 'g2ml_lpdc_');

    if ($tempFile === false)
    {
        return '';
    }

    file_put_contents($tempFile, $script);

    $output = shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tempFile) . ' 2>&1');

    unlink($tempFile);

    if ($output === null || $output === false)
    {
        return '';
    }

    return trim($output);
}

/**
 * Run a body that ends by setting $result, and decode it as JSON.
 *
 * @param  string $scriptBody  PHP code that sets $result.
 * @return array<string, mixed>
 */
function g2ml_lpdc_run_json(string $scriptBody): array
{
    $output  = g2ml_lpdc_run_child($scriptBody . "\n" . 'echo json_encode($result);' . "\n");
    $decoded = json_decode($output, true);

    if (!is_array($decoded))
    {
        assert_true(false, 'The child process did not return JSON. It printed: ' . $output);

        return [];
    }

    return $decoded;
}

/** The addresses the stricter check must refuse. */
function g2ml_lpdc_refused_addresses(): array
{
    return [
        'http://1572395022/',
        'http://0x7f.1/',
        'http://example.com:0x50/',
        'https://xn--googlecom-075n.xn--tda.de/',
        'https://xn--pple-43d.xn--tda.de/',
    ];
}

/** The same addresses with a path, used for items and icons. */
function g2ml_lpdc_refused_https_addresses(): array
{
    // Icons and avatars must be https, so these are the https ones only.
    return [
        'https://xn--googlecom-075n.xn--tda.de/a.png',
        'https://xn--pple-43d.xn--tda.de/a.png',
        'https://example.com:0x50/a.png',
        'https://1572395022/a.png',
        'https://0x7f.1/a.png',
    ];
}

// ============================================================================
// 🔗 Social links
// ============================================================================

test('linkspage save check: social links — a normal address is kept, a refused one is skipped', function (): void
{
    $body  = '$keys = array_keys(G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS);' . "\n";
    $body .= '$bad = ' . var_export(g2ml_lpdc_refused_addresses(), true) . ';' . "\n";
    $body .= '$in = [$keys[0] => "https://example.com/me"];' . "\n";
    $body .= 'foreach ($bad as $index => $address) { $in[$keys[$index + 1]] = $address; }' . "\n";
    $body .= '$in[$keys[6]] = "https://bücher.de/straße";' . "\n";
    $body .= '$result = ["out" => g2ml_linkspageManageValidateSocialLinks($in), "keys" => $keys];' . "\n";

    $result = g2ml_lpdc_run_json($body);
    $keys   = $result['keys'];
    $out    = $result['out'];

    assert_true(count($keys) >= 7, 'The test needs at least seven social networks');
    assert_same('https://example.com/me', $out[$keys[0]], 'A normal https address must be kept');

    foreach ([1, 2, 3, 4, 5] as $index)
    {
        assert_false(isset($out[$keys[$index]]), 'Refused address number ' . $index . ' must be skipped, not stored');
    }

    assert_same('https://xn--bcher-kva.de/stra%C3%9Fe', $out[$keys[6]], 'A non-English address must be kept in its converted ASCII form');
});

// ============================================================================
// 🖼️ Page avatar
// ============================================================================

test('linkspage save check: avatar — normal and non-English https accepted, refused addresses get the usual avatar error', function (): void
{
    $body  = '$refused = ' . var_export(g2ml_lpdc_refused_https_addresses(), true) . ';' . "\n";
    $body .= '$result = ["refused" => [], "good" => null, "idn" => null];' . "\n";
    $body .= 'foreach ($refused as $address) { $result["refused"][] = _g2ml_linkspageManageValidateFields(["slug" => "avatar-check", "pageTitle" => "T", "avatarPath" => $address]); }' . "\n";
    $body .= '$result["good"] = _g2ml_linkspageManageValidateFields(["slug" => "avatar-check", "pageTitle" => "T", "avatarPath" => "https://example.com/me.png"]);' . "\n";
    $body .= '$result["idn"] = _g2ml_linkspageManageValidateFields(["slug" => "avatar-check", "pageTitle" => "T", "avatarPath" => "https://bücher.de/straße"]);' . "\n";

    $result = g2ml_lpdc_run_json($body);

    assert_true($result['good']['ok'], 'A normal https avatar must still be accepted');
    assert_true($result['idn']['ok'], 'A non-English https avatar address must be accepted');
    assert_same('https://xn--bcher-kva.de/stra%C3%9Fe', $result['idn']['fields']['avatarPath'], 'It must be stored in its converted ASCII form');

    foreach ($result['refused'] as $index => $one)
    {
        assert_false($one['ok'], 'Refused avatar address number ' . $index . ' must be refused');
        assert_same('linkspage.avatar_url_https_error', $one['error'], 'It must use the existing avatar error');
        assert_same('validation', $one['errorCode'], 'It must be a validation error');
    }
});

// ============================================================================
// 📎 Manual item address — add and edit
// ============================================================================

test('linkspage save check: manual item address on add — refused ones get the usual invalid-address error', function (): void
{
    $body  = '$refused = ' . var_export(g2ml_lpdc_refused_addresses(), true) . ';' . "\n";
    $body .= '$result = ["refused" => [], "good" => null, "idn" => null];' . "\n";
    $body .= 'foreach ($refused as $address) { $result["refused"][] = g2ml_linkspageManageAddItem(1, 3, ["source" => "manual", "itemTitle" => "T", "manualURL" => $address]); }' . "\n";
    $body .= '$result["good"] = g2ml_linkspageManageAddItem(1, 3, ["source" => "manual", "itemTitle" => "T", "manualURL" => "https://example.com/page"]);' . "\n";
    $body .= '$result["goodStored"] = $GLOBALS["stored"][3];' . "\n";
    $body .= '$result["idn"] = g2ml_linkspageManageAddItem(1, 3, ["source" => "manual", "itemTitle" => "T", "manualURL" => "https://bücher.de/straße"]);' . "\n";
    $body .= '$result["idnStored"] = $GLOBALS["stored"][3];' . "\n";

    $result = g2ml_lpdc_run_json($body);

    assert_true($result['good']['success'], 'A normal https address must still be accepted');
    assert_same('https://example.com/page', $result['goodStored'], 'It must be stored unchanged');
    assert_true($result['idn']['success'], 'A non-English address must be accepted');
    assert_same('https://xn--bcher-kva.de/stra%C3%9Fe', $result['idnStored'], 'It must be stored in its converted ASCII form');

    foreach ($result['refused'] as $index => $one)
    {
        assert_false($one['success'], 'Refused item address number ' . $index . ' must be refused');
        assert_same('linkspage.error.url_invalid', $one['error'], 'It must use the existing invalid-address error');
    }
});

test('linkspage save check: manual item address on edit — refused ones get the usual invalid-address error', function (): void
{
    $body  = '$refused = ' . var_export(g2ml_lpdc_refused_addresses(), true) . ';' . "\n";
    $body .= '$result = ["refused" => [], "good" => null, "idn" => null];' . "\n";
    $body .= 'foreach ($refused as $address) { $result["refused"][] = g2ml_linkspageManageUpdateItem(1, 5, ["itemTitle" => "T", "manualURL" => $address]); }' . "\n";
    $body .= '$result["good"] = g2ml_linkspageManageUpdateItem(1, 5, ["itemTitle" => "T", "manualURL" => "https://example.com/page"]);' . "\n";
    $body .= '$result["goodStored"] = $GLOBALS["stored"][1];' . "\n";
    $body .= '$result["idn"] = g2ml_linkspageManageUpdateItem(1, 5, ["itemTitle" => "T", "manualURL" => "https://bücher.de/straße"]);' . "\n";
    $body .= '$result["idnStored"] = $GLOBALS["stored"][1];' . "\n";

    $result = g2ml_lpdc_run_json($body);

    assert_true($result['good']['success'], 'A normal https address must still be accepted');
    assert_same('https://example.com/page', $result['goodStored'], 'It must be stored unchanged');
    assert_true($result['idn']['success'], 'A non-English address must be accepted');
    assert_same('https://xn--bcher-kva.de/stra%C3%9Fe', $result['idnStored'], 'It must be stored in its converted ASCII form');

    foreach ($result['refused'] as $index => $one)
    {
        assert_false($one['success'], 'Refused item address number ' . $index . ' must be refused');
        assert_same('linkspage.error.url_invalid', $one['error'], 'It must use the existing invalid-address error');
    }
});

// ============================================================================
// 🖼️ Item icon — add and edit
// ============================================================================

test('linkspage save check: item icon on add — refused ones get the usual icon error', function (): void
{
    $body  = '$refused = ' . var_export(g2ml_lpdc_refused_https_addresses(), true) . ';' . "\n";
    $body .= '$result = ["refused" => [], "good" => null, "idn" => null];' . "\n";
    $body .= '$base = ["source" => "manual", "itemTitle" => "T", "manualURL" => "https://example.com/page"];' . "\n";
    $body .= 'foreach ($refused as $address) { $result["refused"][] = g2ml_linkspageManageAddItem(1, 3, $base + ["itemIcon" => $address]); }' . "\n";
    $body .= '$result["good"] = g2ml_linkspageManageAddItem(1, 3, $base + ["itemIcon" => "https://example.com/i.png"]);' . "\n";
    $body .= '$result["goodStored"] = $GLOBALS["stored"][5];' . "\n";
    $body .= '$result["idn"] = g2ml_linkspageManageAddItem(1, 3, $base + ["itemIcon" => "https://bücher.de/straße"]);' . "\n";
    $body .= '$result["idnStored"] = $GLOBALS["stored"][5];' . "\n";

    $result = g2ml_lpdc_run_json($body);

    assert_true($result['good']['success'], 'A normal https icon must still be accepted');
    assert_same('https://example.com/i.png', $result['goodStored'], 'It must be stored unchanged');
    assert_true($result['idn']['success'], 'A non-English icon address must be accepted');
    assert_same('https://xn--bcher-kva.de/stra%C3%9Fe', $result['idnStored'], 'It must be stored in its converted ASCII form');

    foreach ($result['refused'] as $index => $one)
    {
        assert_false($one['success'], 'Refused icon address number ' . $index . ' must be refused');
        assert_same('linkspage.item_icon_https_error', $one['error'], 'It must use the existing icon error');
    }
});

test('linkspage save check: item icon on edit — refused ones get the usual icon error', function (): void
{
    $body  = '$refused = ' . var_export(g2ml_lpdc_refused_https_addresses(), true) . ';' . "\n";
    $body .= '$result = ["refused" => [], "good" => null, "idn" => null];' . "\n";
    $body .= '$base = ["itemTitle" => "T", "manualURL" => "https://example.com/page"];' . "\n";
    $body .= 'foreach ($refused as $address) { $result["refused"][] = g2ml_linkspageManageUpdateItem(1, 5, $base + ["itemIcon" => $address]); }' . "\n";
    $body .= '$result["good"] = g2ml_linkspageManageUpdateItem(1, 5, $base + ["itemIcon" => "https://example.com/i.png"]);' . "\n";
    $body .= '$result["goodStored"] = $GLOBALS["stored"][3];' . "\n";
    $body .= '$result["idn"] = g2ml_linkspageManageUpdateItem(1, 5, $base + ["itemIcon" => "https://bücher.de/straße"]);' . "\n";
    $body .= '$result["idnStored"] = $GLOBALS["stored"][3];' . "\n";

    $result = g2ml_lpdc_run_json($body);

    assert_true($result['good']['success'], 'A normal https icon must still be accepted');
    assert_same('https://example.com/i.png', $result['goodStored'], 'It must be stored unchanged');
    assert_true($result['idn']['success'], 'A non-English icon address must be accepted');
    assert_same('https://xn--bcher-kva.de/stra%C3%9Fe', $result['idnStored'], 'It must be stored in its converted ASCII form');

    foreach ($result['refused'] as $index => $one)
    {
        assert_false($one['success'], 'Refused icon address number ' . $index . ' must be refused');
        assert_same('linkspage.item_icon_https_error', $one['error'], 'It must use the existing icon error');
    }
});
