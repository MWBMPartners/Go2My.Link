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
 * 🌍 Go2My.Link — IP Geolocation (#43)
 * ============================================================================
 *
 * Resolves a client IP address to a country (and, for a City-tier database,
 * region/city) using a locally vendored, pure-PHP MaxMind DB reader — NO
 * ext-maxminddb C extension is required or used; this reads the binary
 * `.mmdb` format directly in plain PHP, which is required on Dreamhost
 * shared hosting where PHP extensions cannot be installed.
 *
 * ----------------------------------------------------------------------------
 * Vendored library (pinned + checksum-documented)
 * ----------------------------------------------------------------------------
 * web/_libraries/maxminddb/ vendors the `MaxMind\Db\Reader` classes from
 * maxmind/MaxMind-DB-Reader-php, pinned to tag v1.13.1 (commit
 * 2194f58d0f024ce923e685cdf92af3daf9951908), licensed Apache-2.0. This is the
 * OFFICIAL MaxMind PHP database reader — the same one the geoip2/geoip2
 * Composer package depends on — used directly instead of through Composer,
 * since Dreamhost shared hosting has no Composer/CLI. SHA-256 of each
 * vendored file, exactly as fetched from
 * https://raw.githubusercontent.com/maxmind/MaxMind-DB-Reader-php/v1.13.1/:
 *
 *   LICENSE                              cfc7749b96f63bd31c3c42b5c471bf756814053e847c10f3eb003417bc523d30
 *   src/MaxMind/Db/Reader.php             (vendored as Reader.php)
 *     504f6ca381b64599902103ac005d41bd3ee5dad22b07c608876daffd9d3efc0e
 *   src/MaxMind/Db/Reader/Decoder.php
 *     e78931e099c78a90cb6a93f2da490e33be9e012bb3deb20dd3cbf7fbaf19e267
 *   src/MaxMind/Db/Reader/InvalidDatabaseException.php
 *     579ef674f3c88e3aeec00f3ef67e61fb5d1e347754f00c8c7c8c99a1a33aa6ae
 *   src/MaxMind/Db/Reader/Metadata.php
 *     6300a504f53521ef0ec27747af36385b9c37d2711b5428a8ad73ee37666b4ad9
 *   src/MaxMind/Db/Reader/Util.php
 *     d9a2dea429dce173fba274a5de6cbf6573813374ce210d27aebabeffa8633b24
 *
 * These 5 files are kept byte-identical to upstream (no local modifications)
 * so a future re-vendor is a straight diff. Only optionally-used features
 * (128-bit integer records) need ext-gmp/ext-bcmath — the GeoLite2-Country /
 * GeoLite2-City record shapes this file reads never hit that path.
 *
 * ----------------------------------------------------------------------------
 * The .mmdb database itself is NEVER committed to git (see .gitignore —
 * *.mmdb) — it is a licensed, frequently-updated MaxMind binary, fetched at
 * DEPLOY time by scripts/fetch-geoip.sh (wired into
 * .github/workflows/sftp-deploy.yml) using the MAXMIND_LICENSE_KEY GitHub
 * Actions org secret. See that script's own docblock for the graceful-skip
 * behaviour when the licence key or a successful download is unavailable.
 * ----------------------------------------------------------------------------
 *
 * ----------------------------------------------------------------------------
 * Graceful degradation (MUST NEVER break a redirect)
 * ----------------------------------------------------------------------------
 * g2ml_geolocationAvailable() — the ONLY check the hot path
 * (web/G2My.Link/public_html/index.php) needs to make — is true only when
 * BOTH the analytics.geolocation_enabled setting is on AND the configured
 * `.mmdb` file exists and is readable. When either is false, this entire
 * feature is a TOTAL no-op: no file I/O beyond one is_file()/is_readable()
 * check, no exception, and every logged column stays NULL — a redirect with
 * geolocation off is byte-for-byte identical to one from before this file
 * existed. A missing, corrupt, or unreadable database — even if the setting
 * is on — degrades to all-null silently; the failure is error_log()'d only
 * ONCE per request via _g2ml_geoipLogErrorOnce(), never per-lookup.
 *
 * ----------------------------------------------------------------------------
 * Privacy
 * ----------------------------------------------------------------------------
 * Only country/region/city are ever derived and stored — never latitude/
 * longitude, never a raw geolocation API response, and never the IP address
 * itself any more than g2ml_getClientIP() already stores it in
 * tblActivityLog.ipAddress today. Private, loopback, link-local, and
 * otherwise reserved IPs (g2ml_isPrivateOrReservedIp(), security.php) are
 * NEVER looked up — they resolve to all-null immediately, with no database
 * read at all. The redirect hot path (index.php) only calls this when it is
 * ALSO going to log the request at all — i.e. it already respects the
 * existing DNT/GPC (analytics.respect_dnt, dnt.php) and
 * analytics.log_activity gates; geolocation adds no NEW tracking surface, it
 * only enriches a request that was already going to be logged.
 *
 * ----------------------------------------------------------------------------
 * Testability seam
 * ----------------------------------------------------------------------------
 * Mirrors the $GLOBALS['g2ml_dns_txt_lookup_override'] idiom (org.php) and
 * $GLOBALS['g2ml_entitlements_tier_lookup_override'] idiom (entitlements.php):
 * a test may set $GLOBALS['g2ml_geoip_reader_override'] to a callable of
 * shape `(string $ip): array|null` to simulate a MaxMind DB record — or the
 * absence of one — without a real `.mmdb` file or the vendored reader. Unset
 * in normal runtime, where the real Reader is opened and queried.
 *
 * Dependencies: settings.php (getSetting()), security.php
 *               (g2ml_isPrivateOrReservedIp() — optional, defensively
 *               re-validated with filter_var() if unavailable),
 *               web/_libraries/maxminddb/Reader.php (lazily required only
 *               when actually opening a real database).
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.1.0 — Phase 7 (#43)
 *
 * 📖 References:
 *     - tblActivityLog schema:  web/_sql/schema/030_analytics.sql
 *     - Settings seed:          web/_sql/seeds/017_geolocation_settings.sql
 *     - Consumer (hot path):    web/G2My.Link/public_html/index.php
 *     - Consumer (bind/insert): web/_functions/activity_logger.php
 *     - MaxMind DB format:      https://maxmind.github.io/MaxMind-DB/
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
// ⚙️ Settings — enabled flag + configured database path
// ============================================================================

/**
 * Read the analytics.geolocation_enabled setting, FAILING CLOSED.
 *
 * Returns true only when settings are available AND the operator has
 * explicitly switched geolocation on. Mirrors
 * g2ml_privateDestinationsAllowed() (security.php): when the settings layer
 * is unavailable (e.g. this file loaded in isolation under a unit test),
 * this returns false so geolocation stays off by default.
 *
 * @return bool  True when geolocation is enabled.
 */
function g2ml_geolocationEnabled(): bool
{
    if (!function_exists('getSetting'))
    {
        return false;
    }

    $value = getSetting('analytics.geolocation_enabled', '0');

    if ($value === true || $value === 1 || $value === '1')
    {
        return true;
    }

    return false;
}

/**
 * Resolve the configured `.mmdb` file path.
 *
 * Falls back to the default vendored-alongside location
 * (web/_libraries/maxminddb/GeoLite2-Country.mmdb) when the
 * analytics.geoip_db_path setting is absent, blank, or the settings layer
 * itself is unavailable. The fallback is computed as an ABSOLUTE path
 * (relative to this file's own directory) rather than a relative string, so
 * it resolves correctly regardless of the current working directory a
 * request happens to run under.
 *
 * @return string
 */
function _g2ml_geoipDbPath(): string
{
    $defaultPath = dirname(__DIR__)
        . DIRECTORY_SEPARATOR . '_libraries'
        . DIRECTORY_SEPARATOR . 'maxminddb'
        . DIRECTORY_SEPARATOR . 'GeoLite2-Country.mmdb';

    if (!function_exists('getSetting'))
    {
        return $defaultPath;
    }

    $configuredPath = getSetting('analytics.geoip_db_path', $defaultPath);

    if (!is_string($configuredPath) || trim($configuredPath) === '')
    {
        return $defaultPath;
    }

    return $configuredPath;
}

/**
 * Whether geolocation should actually run for this request: the setting is
 * on AND the configured database file exists and is readable.
 *
 * This is the ONLY check the redirect hot path needs to make. Both checks
 * are cheap (a cached in-memory settings lookup, then a single filesystem
 * stat) — when this returns false, NO further work happens anywhere in this
 * file: no exception, no log line, no database open attempt.
 *
 * @return bool
 */
function g2ml_geolocationAvailable(): bool
{
    if (g2ml_geolocationEnabled() === false)
    {
        return false;
    }

    $dbPath = _g2ml_geoipDbPath();

    return is_file($dbPath) && is_readable($dbPath);
}

// ============================================================================
// 🧯 One-per-request error logging
// ============================================================================

/**
 * Log a geolocation warning to error_log(), but only ONCE per request — a
 * corrupt or unreadable database must never spam the error log once per
 * redirect on a busy short domain.
 *
 * @param  string $message
 * @return void
 */
function _g2ml_geoipLogErrorOnce(string $message): void
{
    if (isset($GLOBALS['_g2ml_geoip_error_logged']) && $GLOBALS['_g2ml_geoip_error_logged'] === true)
    {
        return;
    }

    $GLOBALS['_g2ml_geoip_error_logged'] = true;

    error_log('[Go2My.Link] WARNING: geolocation — ' . $message);
}

// ============================================================================
// 📦 Reader — lazy vendored-library load + per-request cache
// ============================================================================

/**
 * Load the vendored MaxMind\Db\Reader classes, exactly once per process.
 *
 * Deliberately NOT required at the top of this file: including this file
 * happens on every request (page_init.php bootstraps it globally, like every
 * other function file), but the 5 vendored class files should only ever be
 * parsed when geolocation is actually enabled AND about to open a real
 * database — keeping the "feature off" hot path free of even the opcode
 * overhead of the vendored library.
 *
 * @return void
 */
function _g2ml_geoipLoadVendorLibrary(): void
{
    if (class_exists('MaxMind\\Db\\Reader', false))
    {
        return;
    }

    $libraryDirectory = dirname(__DIR__)
        . DIRECTORY_SEPARATOR . '_libraries'
        . DIRECTORY_SEPARATOR . 'maxminddb';

    require_once $libraryDirectory . DIRECTORY_SEPARATOR . 'Reader' . DIRECTORY_SEPARATOR . 'Util.php';
    require_once $libraryDirectory . DIRECTORY_SEPARATOR . 'Reader' . DIRECTORY_SEPARATOR . 'InvalidDatabaseException.php';
    require_once $libraryDirectory . DIRECTORY_SEPARATOR . 'Reader' . DIRECTORY_SEPARATOR . 'Metadata.php';
    require_once $libraryDirectory . DIRECTORY_SEPARATOR . 'Reader' . DIRECTORY_SEPARATOR . 'Decoder.php';
    require_once $libraryDirectory . DIRECTORY_SEPARATOR . 'Reader.php';
}

/**
 * Open (or return the already-opened, per-request-cached) MaxMind DB reader
 * for the configured database path.
 *
 * Caches the result — including a null "failed to open" result — in
 * $GLOBALS for the remainder of the request, so a page that calls
 * g2ml_geolocateIP() more than once never reopens the file. Any failure
 * (missing file, corrupt database, unreadable permissions) is caught,
 * logged once via _g2ml_geoipLogErrorOnce(), and reported as null — NEVER
 * thrown out of this function.
 *
 * @return object|null  The opened MaxMind\Db\Reader instance, or null.
 */
function _g2ml_geoipOpenReader(): ?object
{
    if (array_key_exists('_g2ml_geoip_reader_cache', $GLOBALS))
    {
        return $GLOBALS['_g2ml_geoip_reader_cache'];
    }

    $dbPath = _g2ml_geoipDbPath();

    if (!is_file($dbPath) || !is_readable($dbPath))
    {
        $GLOBALS['_g2ml_geoip_reader_cache'] = null;

        return null;
    }

    try
    {
        _g2ml_geoipLoadVendorLibrary();

        $reader = new \MaxMind\Db\Reader($dbPath);
    }
    catch (\Throwable $caught)
    {
        _g2ml_geoipLogErrorOnce('failed to open the GeoIP database at ' . $dbPath . ': ' . $caught->getMessage());

        $GLOBALS['_g2ml_geoip_reader_cache'] = null;

        return null;
    }

    $GLOBALS['_g2ml_geoip_reader_cache'] = $reader;

    return $reader;
}

/**
 * Look up the raw MaxMind DB record for an IP address.
 *
 * Testability seam: when $GLOBALS['g2ml_geoip_reader_override'] is set to a
 * callable, it is called with $ip and its return value used verbatim
 * instead of touching any real file — see this file's docblock. Any
 * Throwable from either the override or the real reader is caught here and
 * reported as null (never thrown further) — g2ml_geolocateIP() also has its
 * own try/catch as belt-and-braces, but this is the layer that owns the
 * actual I/O.
 *
 * @param  string $ip
 * @return array|null
 */
function _g2ml_geoipLookupRecord(string $ip): ?array
{
    if (isset($GLOBALS['g2ml_geoip_reader_override']) && is_callable($GLOBALS['g2ml_geoip_reader_override']))
    {
        $override = $GLOBALS['g2ml_geoip_reader_override'];

        $overriddenRecord = $override($ip);

        if (is_array($overriddenRecord))
        {
            return $overriddenRecord;
        }

        return null;
    }

    $reader = _g2ml_geoipOpenReader();

    if ($reader === null)
    {
        return null;
    }

    try
    {
        $record = $reader->get($ip);
    }
    catch (\Throwable $caught)
    {
        _g2ml_geoipLogErrorOnce('lookup failed for an IP address: ' . $caught->getMessage());

        return null;
    }

    if (!is_array($record))
    {
        return null;
    }

    return $record;
}

// ============================================================================
// 🌍 g2ml_geolocateIP() — the public entry point
// ============================================================================

/**
 * Geolocate an IP address to country/region/city, or all-null when unknown,
 * private/reserved, or the database is unavailable.
 *
 * This function NEVER throws — every failure mode (invalid IP, private/
 * reserved address, missing/corrupt database, a decoder exception) resolves
 * to the same all-null shape, so a caller never needs its own try/catch.
 *
 * regionCode/cityName are populated only when the underlying database
 * carries subdivision/city data (a GeoLite2-City-tier database); the
 * GeoLite2-Country edition this project's CI fetch step
 * (scripts/fetch-geoip.sh) provisions by default only ever populates
 * countryCode — regionCode and cityName stay null with that edition, which
 * is expected, not a bug.
 *
 * @param  string $ip  The IP address to geolocate (IPv4 or IPv6).
 * @return array{countryCode: string|null, regionCode: string|null, cityName: string|null}
 */
function g2ml_geolocateIP(string $ip): array
{
    $result = [
        'countryCode' => null,
        'regionCode'  => null,
        'cityName'    => null,
    ];

    $trimmedIp = trim($ip);

    if ($trimmedIp === '')
    {
        return $result;
    }

    // Never look up a private/reserved address — reuses the SAME classifier
    // the anti-SSRF destination guard uses (security.php), so the policy of
    // "what counts as private/reserved" stays in exactly one place.
    if (function_exists('g2ml_isPrivateOrReservedIp') && g2ml_isPrivateOrReservedIp($trimmedIp) === true)
    {
        return $result;
    }

    // Defensive re-validation even when the helper above was unavailable for
    // some reason (e.g. security.php not loaded) — never hand a non-IP
    // string to the decoder.
    if (filter_var($trimmedIp, FILTER_VALIDATE_IP) === false)
    {
        return $result;
    }

    try
    {
        $record = _g2ml_geoipLookupRecord($trimmedIp);
    }
    catch (\Throwable $caught)
    {
        _g2ml_geoipLogErrorOnce('unexpected error during lookup: ' . $caught->getMessage());

        return $result;
    }

    if ($record === null)
    {
        return $result;
    }

    if (isset($record['country']['iso_code']) && is_string($record['country']['iso_code']) && $record['country']['iso_code'] !== '')
    {
        $result['countryCode'] = $record['country']['iso_code'];
    }

    // GeoLite2-City-tier data only — always absent from a Country-tier
    // database, so this stays null with the default CI-fetched edition.
    if (isset($record['subdivisions'][0]['iso_code']) && is_string($record['subdivisions'][0]['iso_code']) && $record['subdivisions'][0]['iso_code'] !== '')
    {
        $result['regionCode'] = $record['subdivisions'][0]['iso_code'];
    }

    if (isset($record['city']['names']['en']) && is_string($record['city']['names']['en']) && $record['city']['names']['en'] !== '')
    {
        $result['cityName'] = $record['city']['names']['en'];
    }

    return $result;
}
