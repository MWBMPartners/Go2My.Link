<?php
/**
 * ============================================================================
 * 🍪 Go2My.Link — Cookie Consent Functions
 * ============================================================================
 *
 * Manages GDPR/CCPA cookie consent: recording, retrieving, revoking,
 * jurisdiction detection, and consent version validation.
 *
 * Consent categories: essential, analytics, functional, marketing
 * Consent is stored in tblConsentRecords with full audit trail.
 *
 * Dependencies: db_connect.php (getDB()), db_query.php, settings.php,
 *               security.php (g2ml_getClientIP())
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.7.0
 * @since      Phase 6
 *
 * 📖 References:
 *     - GDPR Art 7: https://gdpr-info.eu/art-7-gdpr/
 *     - ePrivacy:   https://eur-lex.europa.eu/eli/dir/2002/58/oj
 *     - CCPA:       https://oag.ca.gov/privacy/ccpa
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
// 📋 Get Consent Status for a Category
// ============================================================================

/**
 * Check the current consent status for a specific cookie category.
 *
 * Checks session cache first, then queries the database for the most recent
 * consent record for the current user or session.
 *
 * @param  string    $type  Consent type: 'essential', 'analytics', 'functional', 'marketing'
 * @return bool|null        true = consented, false = refused, null = no record
 */
function g2ml_getConsentStatus(string $type): ?bool
{
    $validTypes = ['essential', 'analytics', 'functional', 'marketing'];

    if (!in_array($type, $validTypes, true))
    {
        return null;
    }

    // Essential cookies are always consented
    if ($type === 'essential')
    {
        return true;
    }

    // Check session cache
    $cacheKey = '_g2ml_consent_' . $type;

    if (isset($_SESSION[$cacheKey]))
    {
        return (bool) $_SESSION[$cacheKey];
    }

    // Query database for the most recent consent record
    $db = getDB();

    if ($db === null)
    {
        return null;
    }

    $userUID   = $_SESSION['user_uid'] ?? null;
    $sessionID = session_id();

    if ($userUID !== null && (int) $userUID > 0)
    {
        $sql  = "SELECT consentGiven FROM tblConsentRecords
                 WHERE userUID = ? AND consentType = ?
                 ORDER BY createdAt DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('is', $userUID, $type);
    }
    else
    {
        $sql  = "SELECT consentGiven FROM tblConsentRecords
                 WHERE sessionID = ? AND consentType = ? AND userUID IS NULL
                 ORDER BY createdAt DESC LIMIT 1";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $sessionID, $type);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    if ($row === null)
    {
        return null;
    }

    $status = (bool) $row['consentGiven'];

    // Cache in session
    $_SESSION[$cacheKey] = $status;

    return $status;
}

// ============================================================================
// 📝 Record Consent
// ============================================================================

/**
 * Record a consent decision for a specific cookie category.
 *
 * Creates an audit-ready record in tblConsentRecords with IP, user agent,
 * jurisdiction, and consent version.
 *
 * @param  string $type    Consent type: 'essential', 'analytics', 'functional', 'marketing'
 * @param  bool   $given   true = consent given, false = consent refused
 * @param  string $method  How consent was obtained: 'banner', 'settings', 'registration'
 * @return bool            true if recorded successfully
 */
function g2ml_recordConsent(string $type, bool $given, string $method = 'banner'): bool
{
    $validTypes = ['essential', 'analytics', 'functional', 'marketing'];

    if (!in_array($type, $validTypes, true))
    {
        return false;
    }

    $db = getDB();

    if ($db === null)
    {
        return false;
    }

    $userUID        = (isset($_SESSION['user_uid']) && (int) $_SESSION['user_uid'] > 0)
                      ? (int) $_SESSION['user_uid'] : null;
    $sessionID      = session_id();
    $consentGiven   = $given ? 1 : 0;
    $ipAddress      = function_exists('g2ml_getClientIP') ? g2ml_getClientIP() : ($_SERVER['REMOTE_ADDR'] ?? null);
    $userAgent      = $_SERVER['HTTP_USER_AGENT'] ?? null;
    $jurisdiction   = function_exists('g2ml_detectJurisdiction') ? g2ml_detectJurisdiction() : null;
    $consentVersion = function_exists('getSetting') ? getSetting('compliance.consent_version', '1.0') : '1.0';
    $expiryDays     = function_exists('getSetting') ? (int) getSetting('compliance.consent_expiry_days', 365) : 365;

    // Truncate UA to column limit
    if ($userAgent !== null && strlen($userAgent) > 500)
    {
        $userAgent = substr($userAgent, 0, 500);
    }

    // Calculate expiry date
    $expiresAt = date('Y-m-d H:i:s', strtotime("+{$expiryDays} days"));

    try
    {
        $sql = "INSERT INTO tblConsentRecords
                (userUID, sessionID, consentType, consentGiven, consentMethod,
                 ipAddress, userAgent, jurisdiction, consentVersion, expiresAt)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = $db->prepare($sql);
        $stmt->bind_param(
            'ississssss',
            $userUID, $sessionID, $type, $consentGiven, $method,
            $ipAddress, $userAgent, $jurisdiction, $consentVersion, $expiresAt
        );
        $stmt->execute();
        $stmt->close();

        // Update session cache
        $_SESSION['_g2ml_consent_' . $type] = $given;

        // Log the consent action
        if (function_exists('logActivity'))
        {
            logActivity('consent_recorded', 'success', null, [
                'logData' => [
                    'consentType' => $type,
                    'consentGiven' => $given,
                    'method' => $method,
                ],
            ]);
        }

        return true;
    }
    catch (\Throwable $e)
    {
        error_log('[Go2My.Link] ERROR: g2ml_recordConsent failed: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// ❌ Revoke Consent
// ============================================================================

/**
 * Revoke consent for a specific cookie category.
 *
 * Records a new consent entry with consentGiven=0 (maintains full audit trail).
 *
 * @param  string $type  Consent type to revoke
 * @return bool          true if revocation was recorded
 */
function g2ml_revokeConsent(string $type): bool
{
    return g2ml_recordConsent($type, false, 'settings');
}

// ============================================================================
// 📊 Get Consent Summary
// ============================================================================

/**
 * Get the current consent status for all 4 cookie categories.
 *
 * @return array  Associative array: ['essential' => true, 'analytics' => null, ...]
 */
function g2ml_getConsentSummary(): array
{
    return [
        'essential'  => true, // Always consented
        'analytics'  => g2ml_getConsentStatus('analytics'),
        'functional' => g2ml_getConsentStatus('functional'),
        'marketing'  => g2ml_getConsentStatus('marketing'),
    ];
}

// ============================================================================
// ✅ Has Valid Consent
// ============================================================================

/**
 * Check whether the user has any valid consent on record AND the consent
 * version matches the current policy version.
 *
 * Used to determine whether to show the cookie consent banner.
 *
 * @return bool  true if user has made a consent choice with the current version
 */
function g2ml_hasValidConsent(): bool
{
    $db = getDB();

    if ($db === null)
    {
        return false;
    }

    $currentVersion = function_exists('getSetting')
                      ? getSetting('compliance.consent_version', '1.0') : '1.0';

    $userUID   = $_SESSION['user_uid'] ?? null;
    $sessionID = session_id();

    if ($userUID !== null && (int) $userUID > 0)
    {
        $sql  = "SELECT COUNT(*) AS cnt FROM tblConsentRecords
                 WHERE userUID = ? AND consentVersion = ?
                   AND (expiresAt IS NULL OR expiresAt > NOW())";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('is', $userUID, $currentVersion);
    }
    else
    {
        $sql  = "SELECT COUNT(*) AS cnt FROM tblConsentRecords
                 WHERE sessionID = ? AND userUID IS NULL AND consentVersion = ?
                   AND (expiresAt IS NULL OR expiresAt > NOW())";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ss', $sessionID, $currentVersion);
    }

    $stmt->execute();
    $result = $stmt->get_result();
    $row    = $result->fetch_assoc();
    $stmt->close();

    return ($row['cnt'] ?? 0) > 0;
}

// ============================================================================
// 🌍 Detect Jurisdiction
// ============================================================================

/**
 * Detect the user's likely legal jurisdiction from Accept-Language header.
 *
 * This is a best-effort heuristic — not authoritative. Used to determine
 * whether opt-in or opt-out consent model applies.
 *
 * @return string  Jurisdiction code: 'EU', 'UK', 'US', 'BR', 'KR', 'AU', 'CA', etc.
 */
function g2ml_detectJurisdiction(): string
{
    $default = function_exists('getSetting')
               ? getSetting('compliance.default_jurisdiction', 'EU') : 'EU';

    $acceptLang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';

    if ($acceptLang === '')
    {
        return $default;
    }

    // Extract primary language tag (e.g., "en-GB" from "en-GB,en;q=0.9")
    $primary = strtolower(substr($acceptLang, 0, 5));

    // Map language tags to jurisdictions
    $jurisdictionMap = [
        'en-gb' => 'UK',
        'en-us' => 'US',
        'en-au' => 'AU',
        'en-ca' => 'CA',
        'pt-br' => 'BR',
        'ko'    => 'KR',
        'ko-kr' => 'KR',
        'ja'    => 'JP',
        'ja-jp' => 'JP',
        'zh-cn' => 'CN',
        'zh-tw' => 'TW',
    ];

    // Check full tag first, then 2-char prefix
    if (isset($jurisdictionMap[$primary]))
    {
        return $jurisdictionMap[$primary];
    }

    $prefix = substr($primary, 0, 2);

    // EU language prefixes
    $euLanguages = ['de', 'fr', 'it', 'es', 'nl', 'pl', 'pt', 'sv', 'da',
                    'fi', 'el', 'cs', 'sk', 'hu', 'ro', 'bg', 'hr', 'sl',
                    'et', 'lv', 'lt', 'mt', 'ga', 'lb'];

    if (in_array($prefix, $euLanguages, true))
    {
        return 'EU';
    }

    if (isset($jurisdictionMap[$prefix]))
    {
        return $jurisdictionMap[$prefix];
    }

    return $default;
}

// ============================================================================
// ⚖️ Is Opt-In Jurisdiction
// ============================================================================

/**
 * Determine whether a jurisdiction requires opt-in consent (vs opt-out).
 *
 * Opt-in: non-essential cookies blocked until explicit consent (GDPR, ePrivacy, LGPD, PIPA).
 * Opt-out: non-essential cookies allowed by default, user can opt out (CCPA, PIPEDA).
 *
 * @param  string $jurisdiction  Jurisdiction code from g2ml_detectJurisdiction()
 * @return bool                  true = opt-in required; false = opt-out model
 */
function g2ml_isOptInJurisdiction(string $jurisdiction): bool
{
    $optInJurisdictions = ['EU', 'UK', 'BR', 'KR', 'JP'];

    return in_array($jurisdiction, $optInJurisdictions, true);
}
