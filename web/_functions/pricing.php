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
 * 💷 Go2My.Link — Flexible Pricing & Entitlement Engine Resolver (scaffold)
 * ============================================================================
 *
 * Data-driven replacement for the HARD-CODED tblSubscriptionTiers has* and
 * max* columns, built on top of web/_sql/schema/036_pricing_engine.sql. This file
 * is DISABLED BY DEFAULT: every public function first checks
 * g2ml_pricingEngineEnabled(), which reads tblSettings
 * 'billing.pricing_engine_enabled' — seeded '0' by
 * web/_sql/seeds/019_pricing_settings.sql. While that setting is '0', this
 * file is loaded (see web/_includes/page_init.php) but produces NO output
 * that anything reads: the single guarded hook in
 * web/_functions/entitlements.php's _g2ml_resolveOrgTier() only calls into
 * this file when the flag is '1', so entitlements.php's behaviour is
 * byte-for-byte unchanged until an operator flips that switch.
 *
 * Like entitlements.php, this file only DEFINES functions/constants at
 * include time — no top-level side effects, no queries run merely by
 * requiring it — so it is safe to require standalone (tests do exactly
 * that).
 *
 * PUBLIC API PRESERVED: this file NEVER changes the signatures or behaviour
 * of entitlements.php's g2ml_getOrgTier(), g2ml_canUseFeature(),
 * g2ml_checkLimit(), or their two whitelist constants. It offers a SEPARATE,
 * ADDITIVE set of functions for new call sites once the engine is live:
 *
 *   - g2ml_pricingEngineEnabled()    — is the master switch on? (request-cached)
 *   - g2ml_pricingResolveOrgTier()   — the legacy-shaped resolver entitlements.php
 *                                      hooks into (plus a 'features' sub-array
 *                                      of every granular slug for new callers)
 *   - g2ml_pricingCanUse()           — granular boolean feature check
 *   - g2ml_pricingGetLimit()         — granular limit/quota check
 *   - g2ml_pricingMeterUsage()       — records metered usage (no-op unless
 *                                      billing.usage_metering_enabled='1')
 *   - g2ml_clearPricingCache()       — invalidate this file's request caches
 *
 * FAIL-OPEN CONTRACT (non-negotiable, mirrors entitlements.php): every DB
 * read here is guarded so a query/system failure resolves to `false`
 * (resolver) or an "allowed"/no-op result (granular checks/metering) rather
 * than throwing or blocking a legitimate action. g2ml_pricingResolveOrgTier()
 * returning anything other than an array tells the caller (entitlements.php)
 * to fall through to the LEGACY resolution path, which itself fails open to
 * the unlimited sentinel — so a bug in this file can never newly block
 * create/redirect/login.
 *
 * MERGE ORDER (safe-floor default → tier value → org override):
 *   1. tblFeatures.default* — the safe floor used when a tier has no row at
 *      all for a feature (defaults are "off"/0/not-unlimited, so a forgotten
 *      mapping can never accidentally grant a premium capability).
 *   2. tblTierFeatures — the tier's own currently-effective value (latest
 *      effectiveFrom <= NOW(), effectiveUntil NULL or > NOW()).
 *   3. tblOrgFeatureOverrides — applied IN overrideUID ORDER so multiple
 *      'adjust' rows accumulate deterministically: 'deny' forces off,
 *      'grant' forces on, 'set' replaces the value (incl. isUnlimited),
 *      'adjust' adds adjustDelta to the numeric value, floored at 0.
 *
 * Dependencies: db_query.php (dbSelect/dbInsert), settings.php (getSetting) —
 * both loaded earlier in page_init.php's Layer 2. Also REUSES (at CALL time,
 * not include time) entitlements.php's private org→tier fetchers
 * (_g2ml_fetchOrgTierRow()/_g2ml_fetchFallbackTierRow()) so org/tier/
 * fallback/GlobalAdmin resolution semantics stay byte-identical between the
 * legacy and pricing-engine paths — including their test-only lookup-override
 * globals. page_init.php loads this file immediately BEFORE entitlements.php,
 * but that only affects include ORDER, not availability: by the time any
 * caller actually INVOKES g2ml_pricingResolveOrgTier(), page_init.php has
 * finished requiring both files, so both function sets already exist.
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.6.0 — Pricing Engine phase (scaffold — inert until
 *             billing.pricing_engine_enabled is switched on)
 *
 * 📖 References:
 *     - Tables:               web/_sql/schema/036_pricing_engine.sql
 *     - Feature/backfill seed: web/_sql/seeds/018_pricing_feature_registry.sql
 *     - Engine-switch seed:   web/_sql/seeds/019_pricing_settings.sql
 *     - Legacy API preserved: web/_functions/entitlements.php
 *     - Guarded hook:         entitlements.php's _g2ml_resolveOrgTier()
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
// 🔑 Constants
// ============================================================================
if (!defined('G2ML_PRICING_SETTING_MASTER'))
{
    define('G2ML_PRICING_SETTING_MASTER', 'billing.pricing_engine_enabled');
}

// ============================================================================
// 🗄️ Test-only lookup overrides — mirror entitlements.php's
// $GLOBALS['g2ml_entitlements_tier_lookup_override'] idiom. Each override, when
// set to a callable, is checked BEFORE the real DB call, so tests never touch
// a live database. Unset in normal runtime.
// ============================================================================

/**
 * Fetch every ACTIVE tblFeatures row (request-cached by the caller).
 *
 * Test-only override: set $GLOBALS['g2ml_pricing_features_override'] to a
 * callable of shape `(): array|false` to simulate a result — including a
 * system failure (`false`) — deterministically.
 *
 * @return array|false  List of feature rows, or false on a DB/query system error.
 */
function _g2ml_pricingFetchFeatures(): array|false
{
    if (isset($GLOBALS['g2ml_pricing_features_override'])
        && is_callable($GLOBALS['g2ml_pricing_features_override']))
    {
        $override = $GLOBALS['g2ml_pricing_features_override'];

        return $override();
    }

    return dbSelect(
        "SELECT featureUID, featureSlug, featureName, valueType, valueUnit, quotaPeriod,
                defaultValueBoolean, defaultValueInt, defaultValueString, defaultValueJSON,
                defaultIsUnlimited, category, isMeterable, legacyColumn, sortOrder
         FROM tblFeatures
         WHERE isActive = 1
         ORDER BY sortOrder ASC",
        '',
        []
    );
}

/**
 * Fetch every tblTierFeatures row for a tier (effective-dating is applied by
 * the WHERE clause; picking the LATEST effectiveFrom per feature is done by
 * the caller in PHP — see _g2ml_pricingReduceLatestEffective()).
 *
 * Test-only override: set $GLOBALS['g2ml_pricing_tier_features_override'] to a
 * callable of shape `(string $tierID): array|false`.
 *
 * @param  string $tierID
 * @return array|false  List of currently-effective tier-feature rows, or
 *                        false on a DB/query system error.
 */
function _g2ml_pricingFetchTierFeatures(string $tierID): array|false
{
    if (isset($GLOBALS['g2ml_pricing_tier_features_override'])
        && is_callable($GLOBALS['g2ml_pricing_tier_features_override']))
    {
        $override = $GLOBALS['g2ml_pricing_tier_features_override'];

        return $override($tierID);
    }

    return dbSelect(
        "SELECT tierFeatureUID, tierID, featureUID, valueBoolean, valueInt, valueString, valueJSON,
                isUnlimited, effectiveFrom, effectiveUntil
         FROM tblTierFeatures
         WHERE tierID = ?
           AND (effectiveFrom IS NULL OR effectiveFrom <= NOW())
           AND (effectiveUntil IS NULL OR effectiveUntil > NOW())",
        's',
        [$tierID]
    );
}

/**
 * Fetch every ACTIVE, currently-effective tblOrgFeatureOverrides row for an
 * org, ordered by overrideUID so multiple 'adjust' rows accumulate
 * deterministically.
 *
 * Test-only override: set $GLOBALS['g2ml_pricing_org_overrides_override'] to a
 * callable of shape `(string $orgHandle): array|false`.
 *
 * @param  string $orgHandle
 * @return array|false  List of override rows in overrideUID order, or false
 *                        on a DB/query system error.
 */
function _g2ml_pricingFetchOrgOverrides(string $orgHandle): array|false
{
    if (isset($GLOBALS['g2ml_pricing_org_overrides_override'])
        && is_callable($GLOBALS['g2ml_pricing_org_overrides_override']))
    {
        $override = $GLOBALS['g2ml_pricing_org_overrides_override'];

        return $override($orgHandle);
    }

    return dbSelect(
        "SELECT overrideUID, orgHandle, featureUID, overrideMode, valueBoolean, valueInt,
                valueString, valueJSON, isUnlimited, adjustDelta, effectiveFrom, effectiveUntil
         FROM tblOrgFeatureOverrides
         WHERE orgHandle = ?
           AND isActive = 1
           AND (effectiveFrom IS NULL OR effectiveFrom <= NOW())
           AND (effectiveUntil IS NULL OR effectiveUntil > NOW())
         ORDER BY overrideUID ASC",
        's',
        [$orgHandle]
    );
}

// ============================================================================
// 🔧 Internal helpers — merge maths, JSON decoding, request caches
// ============================================================================

/**
 * Read one tblSettings value through getSetting(), with a test-only override
 * seam — mirrors the fetch-override idiom above so tests can drive
 * engine/metering switches deterministically without loading settings.php
 * (and without redefining the global getSetting() function itself, which
 * would leak into every other unit test file sharing this process).
 *
 * Test-only override: set $GLOBALS['g2ml_pricing_setting_override'] to a
 * callable of shape `(string $settingID, mixed $default): mixed`.
 *
 * @param  string $settingID
 * @param  mixed  $default
 * @return mixed
 */
function _g2ml_pricingReadSetting(string $settingID, mixed $default): mixed
{
    if (isset($GLOBALS['g2ml_pricing_setting_override'])
        && is_callable($GLOBALS['g2ml_pricing_setting_override']))
    {
        $override = $GLOBALS['g2ml_pricing_setting_override'];

        return $override($settingID, $default);
    }

    if (function_exists('getSetting'))
    {
        return getSetting($settingID, $default);
    }

    return $default;
}

/**
 * Decode a JSON column value that may already be an array (test fixtures),
 * a JSON string (real DB rows), or null. Invalid JSON degrades to null
 * (logged) rather than throwing.
 *
 * @param  mixed $rawValue
 * @return mixed  The decoded value (array/scalar), or null.
 */
function _g2ml_pricingDecodeJSON(mixed $rawValue): mixed
{
    if ($rawValue === null)
    {
        return null;
    }

    if (is_array($rawValue))
    {
        return $rawValue;
    }

    if (!is_string($rawValue) || $rawValue === '')
    {
        return null;
    }

    $decoded = json_decode($rawValue, true);

    if (json_last_error() !== JSON_ERROR_NONE)
    {
        error_log('[Go2My.Link] WARNING: pricing — invalid JSON value encountered during resolution: ' . json_last_error_msg());

        return null;
    }

    return $decoded;
}

/**
 * Reduce a list of currently-effective tblTierFeatures rows to one row per
 * featureUID — the row with the LATEST effectiveFrom (NULL = oldest, so a
 * dated row always wins over an undated one).
 *
 * @param  array $tierFeatureRows
 * @return array  Keyed by featureUID (as a string) => the winning row.
 */
function _g2ml_pricingReduceLatestEffective(array $tierFeatureRows): array
{
    $winners = [];

    foreach ($tierFeatureRows as $row)
    {
        $featureUIDKey = (string) $row['featureUID'];

        if (!isset($winners[$featureUIDKey]))
        {
            $winners[$featureUIDKey] = $row;
            continue;
        }

        $candidateEffectiveFrom = $row['effectiveFrom'] ?? null;
        $currentEffectiveFrom   = $winners[$featureUIDKey]['effectiveFrom'] ?? null;

        if ($candidateEffectiveFrom === null)
        {
            // An undated row never beats an already-stored row (NULL = oldest).
            continue;
        }

        if ($currentEffectiveFrom === null)
        {
            $winners[$featureUIDKey] = $row;
            continue;
        }

        if (strtotime($candidateEffectiveFrom) > strtotime($currentEffectiveFrom))
        {
            $winners[$featureUIDKey] = $row;
        }
    }

    return $winners;
}

/**
 * Resolve one feature's final value by applying the merge order (default →
 * tier → ordered org overrides) for the feature's own valueType.
 *
 * @param  array      $featureRow       The tblFeatures row (defaults, valueType).
 * @param  array|null $tierFeatureRow   The tier's winning tblTierFeatures row, or null.
 * @param  array      $orgOverrideRows  This feature's tblOrgFeatureOverrides rows, in overrideUID order.
 * @return array{valueType: string, value: mixed, isUnlimited: bool}
 */
function _g2ml_pricingResolveFeatureValue(array $featureRow, ?array $tierFeatureRow, array $orgOverrideRows): array
{
    $valueType   = (string) $featureRow['valueType'];
    $isUnlimited = false;
    $value       = null;

    // -------------------------------------------------------------------
    // 1. Safe-floor default.
    // -------------------------------------------------------------------
    if ($valueType === 'boolean')
    {
        $value = ((int) ($featureRow['defaultValueBoolean'] ?? 0) === 1);
    }
    elseif ($valueType === 'limit' || $valueType === 'quota')
    {
        $isUnlimited = ((int) ($featureRow['defaultIsUnlimited'] ?? 0) === 1);

        if ($isUnlimited === true)
        {
            $value = null;
        }
        elseif ($featureRow['defaultValueInt'] !== null)
        {
            $value = (int) $featureRow['defaultValueInt'];
        }
        else
        {
            $value = null;
        }
    }
    elseif ($valueType === 'enum')
    {
        $value = $featureRow['defaultValueString'] ?? null;
    }
    else
    {
        // 'config'
        $value = _g2ml_pricingDecodeJSON($featureRow['defaultValueJSON'] ?? null);
    }

    // -------------------------------------------------------------------
    // 2. Tier value (when the tier has its own currently-effective row).
    // -------------------------------------------------------------------
    if ($tierFeatureRow !== null)
    {
        if ($valueType === 'boolean')
        {
            $value = ((int) ($tierFeatureRow['valueBoolean'] ?? 0) === 1);
        }
        elseif ($valueType === 'limit' || $valueType === 'quota')
        {
            $isUnlimited = ((int) ($tierFeatureRow['isUnlimited'] ?? 0) === 1);

            if ($isUnlimited === true)
            {
                $value = null;
            }
            elseif ($tierFeatureRow['valueInt'] !== null)
            {
                $value = (int) $tierFeatureRow['valueInt'];
            }
            else
            {
                $value = null;
            }
        }
        elseif ($valueType === 'enum')
        {
            $value = $tierFeatureRow['valueString'] ?? null;
        }
        else
        {
            $value = _g2ml_pricingDecodeJSON($tierFeatureRow['valueJSON'] ?? null);
        }
    }

    // -------------------------------------------------------------------
    // 3. Org overrides, applied in overrideUID order.
    // -------------------------------------------------------------------
    foreach ($orgOverrideRows as $overrideRow)
    {
        $mode = (string) ($overrideRow['overrideMode'] ?? 'set');

        if ($mode === 'deny')
        {
            if ($valueType === 'boolean')
            {
                $value = false;
            }
            elseif ($valueType === 'limit' || $valueType === 'quota')
            {
                $isUnlimited = false;
                $value       = 0;
            }
            else
            {
                $value = null;
            }

            continue;
        }

        if ($mode === 'grant')
        {
            if ($valueType === 'boolean')
            {
                $value = true;
            }
            elseif ($valueType === 'limit' || $valueType === 'quota')
            {
                $isUnlimited = true;
                $value       = null;
            }

            continue;
        }

        if ($mode === 'set')
        {
            if ($valueType === 'boolean')
            {
                $value = ((int) ($overrideRow['valueBoolean'] ?? 0) === 1);
            }
            elseif ($valueType === 'limit' || $valueType === 'quota')
            {
                $isUnlimited = ((int) ($overrideRow['isUnlimited'] ?? 0) === 1);

                if ($isUnlimited === true)
                {
                    $value = null;
                }
                elseif ($overrideRow['valueInt'] !== null)
                {
                    $value = (int) $overrideRow['valueInt'];
                }
                else
                {
                    $value = null;
                }
            }
            elseif ($valueType === 'enum')
            {
                $value = $overrideRow['valueString'] ?? null;
            }
            else
            {
                $value = _g2ml_pricingDecodeJSON($overrideRow['valueJSON'] ?? null);
            }

            continue;
        }

        if ($mode === 'adjust')
        {
            if (($valueType === 'limit' || $valueType === 'quota') && $isUnlimited === false)
            {
                $delta        = (int) ($overrideRow['adjustDelta'] ?? 0);
                $currentValue = 0;

                if ($value !== null)
                {
                    $currentValue = (int) $value;
                }

                $adjustedValue = $currentValue + $delta;

                if ($adjustedValue < 0)
                {
                    $adjustedValue = 0;
                }

                $value = $adjustedValue;
            }

            continue;
        }
    }

    return [
        'valueType'   => $valueType,
        'value'       => $value,
        'isUnlimited' => $isUnlimited,
    ];
}

// ============================================================================
// 🌐 Public API
// ============================================================================

/**
 * Is the flexible pricing engine's MASTER switch on?
 *
 * Reads tblSettings 'billing.pricing_engine_enabled' via getSetting()
 * (through _g2ml_pricingReadSetting()), which is seeded '0' by
 * web/_sql/seeds/019_pricing_settings.sql — so this returns false on every
 * install until an operator explicitly flips it. Request-cached so repeated
 * calls within one request never re-hit the settings cache.
 *
 * @return bool
 */
function g2ml_pricingEngineEnabled(): bool
{
    if (isset($GLOBALS['g2ml_pricing_engine_enabled_cache'])
        && is_bool($GLOBALS['g2ml_pricing_engine_enabled_cache']))
    {
        return $GLOBALS['g2ml_pricing_engine_enabled_cache'];
    }

    $enabled = false;

    try
    {
        $enabled = (_g2ml_pricingReadSetting(G2ML_PRICING_SETTING_MASTER, '0') === '1');
    }
    catch (Throwable $unexpectedError)
    {
        error_log('[Go2My.Link] ERROR: pricing — unexpected exception reading the master switch: ' . $unexpectedError->getMessage() . ' — treating the engine as OFF.');
        $enabled = false;
    }

    $GLOBALS['g2ml_pricing_engine_enabled_cache'] = $enabled;

    return $enabled;
}

/**
 * Resolve an organisation's effective entitlement tier via the flexible
 * pricing engine, emitted in the SAME legacy shape entitlements.php's
 * _g2ml_entitlementsNormaliseTierRow() produces (tierID, tierName, max*
 * limits, has* flags, unlimited, source), PLUS a 'features' sub-array keyed
 * by every ACTIVE feature slug for new granular callers.
 *
 * Does NOT itself check g2ml_pricingEngineEnabled() — the caller
 * (entitlements.php's guarded hook) already does that; this function always
 * attempts a full resolution when called directly, which is what the unit
 * tests and g2ml_pricingCanUse()/g2ml_pricingGetLimit() need.
 *
 * FAIL-OPEN: any DB/system error at any step returns false so the caller
 * falls back to the legacy resolver (which itself fails open to the
 * unlimited sentinel) — this function must never throw.
 *
 * @param  string $orgHandle
 * @return array|false
 */
function g2ml_pricingResolveOrgTier(string $orgHandle): array|false
{
    try
    {
        if (!function_exists('_g2ml_fetchOrgTierRow') || !function_exists('_g2ml_fetchFallbackTierRow'))
        {
            error_log('[Go2My.Link] ERROR: pricing — entitlements.php helpers are unavailable; cannot resolve org "' . $orgHandle . '" — falling back to legacy resolution.');

            return false;
        }

        // ---------------------------------------------------------------
        // 1. Resolve org → tierID (identical semantics to the legacy path,
        //    including the lowest-sortOrder ACTIVE fallback).
        // ---------------------------------------------------------------
        $tierRow = _g2ml_fetchOrgTierRow($orgHandle);

        if ($tierRow === false)
        {
            error_log('[Go2My.Link] ERROR: pricing — org tier lookup failed for org "' . $orgHandle . '" — falling back to legacy resolution.');

            return false;
        }

        if ($tierRow !== null && (int) ($tierRow['isActive'] ?? 0) === 1)
        {
            $resolvedTierID   = (string) $tierRow['tierID'];
            $resolvedTierName = (string) $tierRow['tierName'];
        }
        else
        {
            $fallbackRow = _g2ml_fetchFallbackTierRow();

            if ($fallbackRow === false || $fallbackRow === null)
            {
                error_log('[Go2My.Link] ERROR: pricing — fallback tier lookup failed/empty resolving org "' . $orgHandle . '" — falling back to legacy resolution.');

                return false;
            }

            $resolvedTierID   = (string) $fallbackRow['tierID'];
            $resolvedTierName = (string) $fallbackRow['tierName'];
        }

        // ---------------------------------------------------------------
        // 2. Load ACTIVE features.
        // ---------------------------------------------------------------
        $features = _g2ml_pricingFetchFeatures();

        if ($features === false)
        {
            error_log('[Go2My.Link] ERROR: pricing — feature registry lookup failed resolving org "' . $orgHandle . '" — falling back to legacy resolution.');

            return false;
        }

        // ---------------------------------------------------------------
        // 3. Load the tier's currently-effective tblTierFeatures rows,
        //    reduced to one (latest-effectiveFrom) row per feature.
        // ---------------------------------------------------------------
        $tierFeatureRows = _g2ml_pricingFetchTierFeatures($resolvedTierID);

        if ($tierFeatureRows === false)
        {
            error_log('[Go2My.Link] ERROR: pricing — tier-feature lookup failed for tier "' . $resolvedTierID . '" — falling back to legacy resolution.');

            return false;
        }

        $tierFeatureByFeatureUID = _g2ml_pricingReduceLatestEffective($tierFeatureRows);

        // ---------------------------------------------------------------
        // 4. Load the org's ACTIVE, currently-effective overrides.
        // ---------------------------------------------------------------
        $orgOverrideRows = _g2ml_pricingFetchOrgOverrides($orgHandle);

        if ($orgOverrideRows === false)
        {
            error_log('[Go2My.Link] ERROR: pricing — org-override lookup failed for org "' . $orgHandle . '" — falling back to legacy resolution.');

            return false;
        }

        $orgOverridesByFeatureUID = [];

        foreach ($orgOverrideRows as $overrideRow)
        {
            $featureUIDKey = (string) $overrideRow['featureUID'];

            if (!isset($orgOverridesByFeatureUID[$featureUIDKey]))
            {
                $orgOverridesByFeatureUID[$featureUIDKey] = [];
            }

            $orgOverridesByFeatureUID[$featureUIDKey][] = $overrideRow;
        }

        // ---------------------------------------------------------------
        // 5. Merge per feature.
        // ---------------------------------------------------------------
        $resolvedFeatures = [];

        foreach ($features as $featureRow)
        {
            $featureUIDKey = (string) $featureRow['featureUID'];
            $slug          = (string) $featureRow['featureSlug'];

            $tierFeatureRow  = $tierFeatureByFeatureUID[$featureUIDKey] ?? null;
            $featureOverrides = $orgOverridesByFeatureUID[$featureUIDKey] ?? [];

            $resolvedFeatures[$slug] = _g2ml_pricingResolveFeatureValue($featureRow, $tierFeatureRow, $featureOverrides);
        }

        // ---------------------------------------------------------------
        // 6. Umbrella rule: hasAdvancedRedirects reports ON when the
        //    umbrella row itself is ON OR any granular redirects.* flag
        //    resolved true.
        // ---------------------------------------------------------------
        if (isset($resolvedFeatures['redirects.advanced']))
        {
            $anyGranularRedirectOn = false;

            foreach ($resolvedFeatures as $slug => $resolvedValue)
            {
                if ($slug === 'redirects.advanced')
                {
                    continue;
                }

                if (str_starts_with($slug, 'redirects.') && $resolvedValue['value'] === true)
                {
                    $anyGranularRedirectOn = true;
                }
            }

            if ($anyGranularRedirectOn === true)
            {
                $resolvedFeatures['redirects.advanced']['value'] = true;
            }
        }

        // ---------------------------------------------------------------
        // 7. Emit the legacy-shaped array via legacyColumn mapping.
        // ---------------------------------------------------------------
        $legacyShaped = [
            'tierID'               => $resolvedTierID,
            'tierName'             => $resolvedTierName,
            'maxLinks'             => null,
            'maxCustomDomains'     => null,
            'maxAPIRequestsPerDay' => null,
            'maxLinksPages'        => null,
            'hasAdvancedRedirects' => false,
            'hasAnalytics'         => false,
            'hasQRCodes'           => false,
            'hasAPIAccess'         => false,
            'hasPrioritySupport'   => false,
            'hasCustomHTML'        => false,
        ];

        foreach ($features as $featureRow)
        {
            $legacyColumn = $featureRow['legacyColumn'] ?? null;

            if ($legacyColumn === null || $legacyColumn === '')
            {
                continue;
            }

            $slug     = (string) $featureRow['featureSlug'];
            $resolved = $resolvedFeatures[$slug] ?? null;

            if ($resolved === null)
            {
                continue;
            }

            if ($resolved['isUnlimited'] === true)
            {
                $legacyShaped[$legacyColumn] = null;
            }
            elseif ($resolved['valueType'] === 'boolean')
            {
                $legacyShaped[$legacyColumn] = ($resolved['value'] === true);
            }
            elseif ($resolved['value'] === null)
            {
                $legacyShaped[$legacyColumn] = null;
            }
            else
            {
                $legacyShaped[$legacyColumn] = (int) $resolved['value'];
            }
        }

        $legacyShaped['unlimited'] = false;
        $legacyShaped['source']    = 'pricing_engine';

        // ---------------------------------------------------------------
        // 8. Additionally expose every granular slug for new callers.
        // ---------------------------------------------------------------
        $featuresOut = [];

        foreach ($resolvedFeatures as $slug => $resolvedValue)
        {
            $featuresOut[$slug] = $resolvedValue['value'];
        }

        $legacyShaped['features'] = $featuresOut;

        return $legacyShaped;
    }
    catch (Throwable $unexpectedError)
    {
        error_log('[Go2My.Link] ERROR: pricing — unexpected exception resolving org "' . $orgHandle . '": ' . $unexpectedError->getMessage() . ' — falling back to legacy resolution.');

        return false;
    }
}

/**
 * Granular boolean feature check for NEW call sites (uses the full dot-
 * namespaced featureSlug registry, not the legacy has* whitelist).
 *
 * Returns false whenever the engine is OFF — existing callers must keep
 * using g2ml_canUseFeature() until an operator switches the engine on; this
 * function is for code written AFTER that migration.
 *
 * @param  string $orgHandle
 * @param  string $featureSlug
 * @return bool
 */
function g2ml_pricingCanUse(string $orgHandle, string $featureSlug): bool
{
    if (g2ml_pricingEngineEnabled() !== true)
    {
        return false;
    }

    $tier = g2ml_pricingResolveOrgTier($orgHandle);

    if (!is_array($tier) || !isset($tier['features']) || !is_array($tier['features']))
    {
        error_log('[Go2My.Link] WARNING: pricing — g2ml_pricingCanUse() could not resolve org "' . $orgHandle . '" — denying.');

        return false;
    }

    if (!array_key_exists($featureSlug, $tier['features']))
    {
        error_log('[Go2My.Link] WARNING: pricing — g2ml_pricingCanUse() called with an unrecognised/inactive feature slug "' . $featureSlug . '" — denying.');

        return false;
    }

    if ($tier['features'][$featureSlug] === true)
    {
        return true;
    }

    return false;
}

/**
 * Granular limit/quota check for NEW call sites, same contract shape as
 * g2ml_checkLimit(): fail-OPEN (allowed, unlimited) on an unrecognised slug
 * or a system error, since a caller-supplied typo or an engine outage is an
 * entitlement-SYSTEM defect, not a genuine over-limit condition.
 *
 * @param  string $orgHandle
 * @param  string $featureSlug
 * @param  int    $currentCount  The caller's current usage count (negative
 *                                values are treated as 0).
 * @return array  ['allowed' => bool, 'limit' => int|null, 'remaining' => int|null]
 */
function g2ml_pricingGetLimit(string $orgHandle, string $featureSlug, int $currentCount): array
{
    $failOpen = [
        'allowed'   => true,
        'limit'     => null,
        'remaining' => null,
    ];

    if (g2ml_pricingEngineEnabled() !== true)
    {
        return $failOpen;
    }

    $tier = g2ml_pricingResolveOrgTier($orgHandle);

    if (!is_array($tier) || !isset($tier['features']) || !is_array($tier['features']))
    {
        error_log('[Go2My.Link] WARNING: pricing — g2ml_pricingGetLimit() could not resolve org "' . $orgHandle . '" — failing OPEN (allowed, unlimited).');

        return $failOpen;
    }

    if (!array_key_exists($featureSlug, $tier['features']))
    {
        error_log('[Go2My.Link] WARNING: pricing — g2ml_pricingGetLimit() called with an unrecognised/inactive feature slug "' . $featureSlug . '" — failing OPEN (allowed, unlimited).');

        return $failOpen;
    }

    $limitValue = $tier['features'][$featureSlug];

    if ($limitValue === null)
    {
        return $failOpen;
    }

    $limitValueInt = (int) $limitValue;

    if ($currentCount < 0)
    {
        $currentCountSafe = 0;
    }
    else
    {
        $currentCountSafe = $currentCount;
    }

    if ($currentCountSafe < $limitValueInt)
    {
        $allowed = true;
    }
    else
    {
        $allowed = false;
    }

    $remaining = $limitValueInt - $currentCountSafe;

    if ($remaining < 0)
    {
        $remaining = 0;
    }

    return [
        'allowed'   => $allowed,
        'limit'     => $limitValueInt,
        'remaining' => $remaining,
    ];
}

/**
 * Probabilistic prune of old tblUsageEvents rows (1-in-100 request sweep),
 * mirroring tblAPIRequestLog's retention pattern (api_ratelimit.php) — no
 * cron dependency on Dreamhost shared hosting.
 *
 * @return void
 */
function _g2ml_pricingPruneUsageEvents(): void
{
    $retentionDays = (int) _g2ml_pricingReadSetting('billing.usage_event_retention_days', 90);

    if ($retentionDays <= 0)
    {
        return;
    }

    dbDelete(
        'DELETE FROM tblUsageEvents WHERE createdAt < DATE_SUB(NOW(), INTERVAL ? DAY)',
        'i',
        [$retentionDays]
    );
}

/**
 * Record metered usage for a meterable feature. A NO-OP (returns true
 * without touching the database) unless 'billing.usage_metering_enabled' is
 * '1' — so this is safe to sprinkle into call sites ahead of the engine (or
 * even ahead of PAYG) going live.
 *
 * Metering failures are LOGGED and return false but NEVER throw — the
 * caller must never let a metering failure block the underlying action
 * (creating a link, serving a redirect, etc.).
 *
 * @param  string      $orgHandle
 * @param  string      $featureSlug   Must be an ACTIVE, isMeterable=1 feature.
 * @param  int         $quantity      Units consumed by this event (default 1).
 * @param  string|null $eventRef      Optional idempotency reference for the
 *                                     optional fine-grained event ledger.
 * @return bool
 */
function g2ml_pricingMeterUsage(string $orgHandle, string $featureSlug, int $quantity = 1, ?string $eventRef = null): bool
{
    try
    {
        $meteringEnabled = (_g2ml_pricingReadSetting('billing.usage_metering_enabled', '0') === '1');

        if ($meteringEnabled !== true)
        {
            return true;
        }

        $features = _g2ml_pricingFetchFeatures();

        if ($features === false)
        {
            error_log('[Go2My.Link] ERROR: pricing — feature registry lookup failed metering "' . $featureSlug . '" for org "' . $orgHandle . '" — usage not recorded.');

            return false;
        }

        $featureUID  = null;
        $isMeterable = false;
        $quotaPeriod = null;

        foreach ($features as $featureRow)
        {
            if ((string) $featureRow['featureSlug'] === $featureSlug)
            {
                $featureUID  = (int) $featureRow['featureUID'];
                $isMeterable = ((int) ($featureRow['isMeterable'] ?? 0) === 1);
                $quotaPeriod = $featureRow['quotaPeriod'] ?? null;
                break;
            }
        }

        if ($featureUID === null || $isMeterable !== true)
        {
            error_log('[Go2My.Link] WARNING: pricing — g2ml_pricingMeterUsage() called for an unknown/non-meterable feature slug "' . $featureSlug . '" — usage not recorded.');

            return false;
        }

        if ($quotaPeriod === 'day')
        {
            $periodType  = 'day';
            $periodStart = date('Y-m-d');
        }
        elseif ($quotaPeriod === 'billing_period')
        {
            // Aligning to a subscription's own anniversary is left for the
            // billing-integration phase; a calendar-month bucket is a safe,
            // conservative stand-in until then.
            $periodType  = 'billing_period';
            $periodStart = date('Y-m-01');
        }
        else
        {
            $periodType  = 'month';
            $periodStart = date('Y-m-01');
        }

        $counterUpserted = dbInsert(
            'INSERT INTO tblUsageCounters (orgHandle, featureUID, periodType, periodStart, usedCount, lastEventAt) '
            . 'VALUES (?, ?, ?, ?, ?, NOW()) '
            . 'ON DUPLICATE KEY UPDATE usedCount = usedCount + VALUES(usedCount), lastEventAt = VALUES(lastEventAt)',
            'sissi',
            [$orgHandle, $featureUID, $periodType, $periodStart, $quantity]
        );

        if ($counterUpserted === false)
        {
            error_log('[Go2My.Link] ERROR: pricing — usage counter UPSERT failed for org "' . $orgHandle . '", feature "' . $featureSlug . '" — usage not recorded (the underlying action was NOT blocked by this).');

            return false;
        }

        $eventLogEnabled = (_g2ml_pricingReadSetting('billing.usage_event_log_enabled', '0') === '1');

        if ($eventLogEnabled === true)
        {
            $eventInserted = dbInsert(
                'INSERT INTO tblUsageEvents (orgHandle, featureUID, quantity, eventRef) VALUES (?, ?, ?, ?)',
                'siis',
                [$orgHandle, $featureUID, $quantity, $eventRef]
            );

            if ($eventInserted === false)
            {
                error_log('[Go2My.Link] WARNING: pricing — usage EVENT log insert failed for org "' . $orgHandle . '", feature "' . $featureSlug . '" — the counter update above still succeeded.');
            }

            if (mt_rand(1, 100) === 1)
            {
                _g2ml_pricingPruneUsageEvents();
            }
        }

        return true;
    }
    catch (Throwable $unexpectedError)
    {
        error_log('[Go2My.Link] ERROR: pricing — unexpected exception metering "' . $featureSlug . '" for org "' . $orgHandle . '": ' . $unexpectedError->getMessage() . ' — usage not recorded (never blocks the action).');

        return false;
    }
}

/**
 * Clear this file's request-scoped caches (currently just the engine-enabled
 * flag). Mirrors entitlements.php's g2ml_clearOrgTierCache() shape (an
 * optional orgHandle, ignored here since the engine-enabled flag is global —
 * kept for signature symmetry and future per-org caching). Also invalidates
 * entitlements.php's own tier cache (function_exists-guarded) so a caller
 * that changes pricing-engine data mid-request cannot see a stale legacy-path
 * tier either.
 *
 * @param  string|null $orgHandle  Present for signature symmetry with
 *                                  g2ml_clearOrgTierCache(); unused because
 *                                  the only cache here (the engine flag) is
 *                                  global, not per-org.
 * @return void
 */
function g2ml_clearPricingCache(?string $orgHandle = null): void
{
    unset($GLOBALS['g2ml_pricing_engine_enabled_cache']);

    if (function_exists('g2ml_clearOrgTierCache'))
    {
        g2ml_clearOrgTierCache($orgHandle);
    }
}
