<?php
/**
 * ============================================================================
 * ⚙️ Go2My.Link — Settings Manager
 * ============================================================================
 *
 * Reads and writes application settings from tblSettings with scope cascade:
 *   User > Organisation > System > Default
 *
 * Settings are cached in memory after first load. Default + System scopes are
 * loaded eagerly during bootstrap; Organisation and User scopes are loaded
 * lazily on first request.
 *
 * Sensitive settings (isSensitive = 1) are decrypted at load time using
 * g2ml_decrypt() from security.php.
 *
 * Dependencies: db_connect.php (getDB()), db_query.php (dbSelect(), dbInsert(),
 *               dbUpdate()), security.php (g2ml_encrypt(), g2ml_decrypt())
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - tblSettings schema: web/_sql/schema/010_core_settings.sql
 *     - Seed data: web/_sql/seeds/003_default_settings.sql
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
// 💾 Settings Cache
// ============================================================================
// Settings are cached per-scope in a global array. The structure is:
//   $_g2ml_settings_cache['Default']['site.name'] = 'Go2My.Link'
//   $_g2ml_settings_cache['System']['site.name'] = 'Custom Name'
//   $_g2ml_settings_cache['Organisation:myorg']['site.name'] = 'Org Custom'
//   $_g2ml_settings_cache['User:42']['site.name'] = 'User Pref'
//
// The '_loaded' key tracks which scopes have been loaded from DB.
// ============================================================================

$GLOBALS['_g2ml_settings_cache'] = [];
$GLOBALS['_g2ml_settings_loaded'] = [];

/**
 * Load Default and System settings into cache.
 *
 * Called during bootstrap (page_init.php). Loads all settings at Default and
 * System scope in a single query. Sensitive values are decrypted.
 *
 * @return void
 */
function loadSettingsCache(): void
{
    $rows = dbSelect(
        "SELECT settingID, settingScope, settingScopeRef, settingValue, settingDefault,
                settingDataType, isSensitive
         FROM tblSettings
         WHERE settingScope IN ('Default', 'System')
         ORDER BY settingScope ASC",
        '',
        []
    );

    if ($rows === false)
    {
        error_log('[Go2My.Link] WARNING: Failed to load settings cache from database.');
        return;
    }

    foreach ($rows as $row)
    {
        $cacheKey = $row['settingScope'];
        $value    = $row['settingValue'] ?? $row['settingDefault'];

        // Decrypt sensitive values
        if ((int) $row['isSensitive'] === 1 && $value !== null && $value !== '')
        {
            $decrypted = g2ml_decrypt($value);

            if ($decrypted !== false)
            {
                $value = $decrypted;
            }
            else
            {
                error_log('[Go2My.Link] WARNING: Failed to decrypt setting: ' . $row['settingID']);
            }
        }

        // Cast the value to the appropriate PHP type
        $value = _g2ml_castSettingValue($value, $row['settingDataType']);

        $GLOBALS['_g2ml_settings_cache'][$cacheKey][$row['settingID']] = $value;
    }

    $GLOBALS['_g2ml_settings_loaded']['Default'] = true;
    $GLOBALS['_g2ml_settings_loaded']['System']  = true;
}

/**
 * Load Organisation-scope settings into cache (lazy).
 *
 * Called on first getSetting() request for an org scope.
 *
 * @param  string $orgHandle  The organisation handle
 * @return void
 */
function _g2ml_loadOrgSettings(string $orgHandle): void
{
    $cacheKey = 'Organisation:' . $orgHandle;

    if (isset($GLOBALS['_g2ml_settings_loaded'][$cacheKey]))
    {
        return; // Already loaded
    }

    $rows = dbSelect(
        "SELECT settingID, settingValue, settingDefault, settingDataType, isSensitive
         FROM tblSettings
         WHERE settingScope = 'Organisation' AND settingScopeRef = ?",
        's',
        [$orgHandle]
    );

    if ($rows === false)
    {
        return;
    }

    foreach ($rows as $row)
    {
        $value = $row['settingValue'] ?? $row['settingDefault'];

        if ((int) $row['isSensitive'] === 1 && $value !== null && $value !== '')
        {
            $decrypted = g2ml_decrypt($value);

            if ($decrypted !== false)
            {
                $value = $decrypted;
            }
        }

        $value = _g2ml_castSettingValue($value, $row['settingDataType']);

        $GLOBALS['_g2ml_settings_cache'][$cacheKey][$row['settingID']] = $value;
    }

    $GLOBALS['_g2ml_settings_loaded'][$cacheKey] = true;
}

/**
 * Load User-scope settings into cache (lazy).
 *
 * Called on first getSetting() request for a user scope.
 *
 * @param  int $userUID  The user's UID
 * @return void
 */
function _g2ml_loadUserSettings(int $userUID): void
{
    $cacheKey = 'User:' . $userUID;

    if (isset($GLOBALS['_g2ml_settings_loaded'][$cacheKey]))
    {
        return; // Already loaded
    }

    $rows = dbSelect(
        "SELECT settingID, settingValue, settingDefault, settingDataType, isSensitive
         FROM tblSettings
         WHERE settingScope = 'User' AND settingScopeRef = ?",
        's',
        [(string) $userUID]
    );

    if ($rows === false)
    {
        return;
    }

    foreach ($rows as $row)
    {
        $value = $row['settingValue'] ?? $row['settingDefault'];

        if ((int) $row['isSensitive'] === 1 && $value !== null && $value !== '')
        {
            $decrypted = g2ml_decrypt($value);

            if ($decrypted !== false)
            {
                $value = $decrypted;
            }
        }

        $value = _g2ml_castSettingValue($value, $row['settingDataType']);

        $GLOBALS['_g2ml_settings_cache'][$cacheKey][$row['settingID']] = $value;
    }

    $GLOBALS['_g2ml_settings_loaded'][$cacheKey] = true;
}

// ============================================================================
// 📖 Get Setting
// ============================================================================

/**
 * Retrieve a setting value with scope cascade.
 *
 * Checks scopes in priority order: User > Organisation > System > Default.
 * Returns the first found value. If no value exists in any scope, returns
 * the provided default.
 *
 * @param  string      $settingID   The setting key (e.g., 'site.name', 'redirect.max_hops')
 * @param  mixed       $default     Default value if not found in any scope
 * @param  string|null $orgHandle   Organisation handle for org-scope lookup (optional)
 * @param  int|null    $userUID     User UID for user-scope lookup (optional)
 * @return mixed                    The setting value, cast to its declared data type
 *
 * Usage example:
 *   $siteName = getSetting('site.name');
 *   $maxHops  = getSetting('redirect.max_hops', 3);
 *   $orgName  = getSetting('org.display_name', null, 'myorg');
 */
function getSetting(string $settingID, mixed $default = null, ?string $orgHandle = null, ?int $userUID = null): mixed
{
    // Check User scope first (highest priority)
    if ($userUID !== null)
    {
        _g2ml_loadUserSettings($userUID);
        $cacheKey = 'User:' . $userUID;

        if (isset($GLOBALS['_g2ml_settings_cache'][$cacheKey][$settingID]))
        {
            return $GLOBALS['_g2ml_settings_cache'][$cacheKey][$settingID];
        }
    }

    // Check Organisation scope
    if ($orgHandle !== null)
    {
        _g2ml_loadOrgSettings($orgHandle);
        $cacheKey = 'Organisation:' . $orgHandle;

        if (isset($GLOBALS['_g2ml_settings_cache'][$cacheKey][$settingID]))
        {
            return $GLOBALS['_g2ml_settings_cache'][$cacheKey][$settingID];
        }
    }

    // Check System scope
    if (isset($GLOBALS['_g2ml_settings_cache']['System'][$settingID]))
    {
        return $GLOBALS['_g2ml_settings_cache']['System'][$settingID];
    }

    // Check Default scope
    if (isset($GLOBALS['_g2ml_settings_cache']['Default'][$settingID]))
    {
        return $GLOBALS['_g2ml_settings_cache']['Default'][$settingID];
    }

    // No value found in any scope — return the provided default
    return $default;
}

// ============================================================================
// ✏️ Set Setting
// ============================================================================

/**
 * Write a setting value to the database and update the cache.
 *
 * If a row already exists for this settingID + scope + scopeRef, it updates
 * the value. Otherwise, it inserts a new row.
 *
 * Sensitive settings are encrypted before storage.
 *
 * @param  string      $settingID     The setting key
 * @param  mixed       $value         The value to store
 * @param  string      $scope         Target scope: Default, System, Organisation, or User
 * @param  string|null $scopeRef      Scope reference: orgHandle or userUID
 * @param  bool        $isSensitive   Whether to encrypt the value
 * @return bool                       True on success, false on failure
 *
 * Usage example:
 *   setSetting('site.name', 'My Custom Name', 'System');
 *   setSetting('org.welcome_message', 'Hello!', 'Organisation', 'myorg');
 *   setSetting('user.theme', 'dark', 'User', '42');
 */
function setSetting(string $settingID, mixed $value, string $scope = 'System', ?string $scopeRef = null, bool $isSensitive = false): bool
{
    // Validate scope
    $validScopes = ['Default', 'System', 'Organisation', 'User'];

    if (!in_array($scope, $validScopes, true))
    {
        error_log('[Go2My.Link] ERROR: setSetting called with invalid scope: ' . $scope);
        return false;
    }

    // Convert value to string for storage
    $stringValue = _g2ml_settingToString($value);

    // Encrypt if sensitive
    if ($isSensitive && $stringValue !== null && $stringValue !== '')
    {
        $encrypted = g2ml_encrypt($stringValue);

        if ($encrypted === false)
        {
            error_log('[Go2My.Link] ERROR: Failed to encrypt setting: ' . $settingID);
            return false;
        }

        $stringValue = $encrypted;
    }

    // Check if this setting already exists at this scope
    $existing = dbSelectOne(
        "SELECT settingUID FROM tblSettings
         WHERE settingID = ? AND settingScope = ? AND (settingScopeRef = ? OR (settingScopeRef IS NULL AND ? IS NULL))",
        'ssss',
        [$settingID, $scope, $scopeRef, $scopeRef]
    );

    if ($existing === false)
    {
        return false; // Query error
    }

    if ($existing !== null)
    {
        // Update existing row
        $result = dbUpdate(
            "UPDATE tblSettings SET settingValue = ?, isSensitive = ? WHERE settingUID = ?",
            'sii',
            [$stringValue, $isSensitive ? 1 : 0, (int) $existing['settingUID']]
        );
    }
    else
    {
        // Insert new row
        $result = dbInsert(
            "INSERT INTO tblSettings (settingID, settingScope, settingScopeRef, settingValue, isSensitive)
             VALUES (?, ?, ?, ?, ?)",
            'ssssi',
            [$settingID, $scope, $scopeRef, $stringValue, $isSensitive ? 1 : 0]
        );
    }

    if ($result === false)
    {
        return false;
    }

    // Update the cache with the unencrypted value
    $cacheKey = $scope;

    if ($scope === 'Organisation' && $scopeRef !== null)
    {
        $cacheKey = 'Organisation:' . $scopeRef;
    }
    elseif ($scope === 'User' && $scopeRef !== null)
    {
        $cacheKey = 'User:' . $scopeRef;
    }

    $GLOBALS['_g2ml_settings_cache'][$cacheKey][$settingID] = $value;

    return true;
}

// ============================================================================
// 🔧 Type Casting Helpers
// ============================================================================

/**
 * Cast a raw setting value string to its declared PHP type.
 *
 * @param  mixed       $value     The raw value (usually a string from the DB)
 * @param  string|null $dataType  The declared type: string, integer, float, boolean, json, url, email
 * @return mixed                  The cast value
 */
function _g2ml_castSettingValue(mixed $value, ?string $dataType): mixed
{
    if ($value === null)
    {
        return null;
    }

    return match ($dataType)
    {
        'integer' => (int) $value,
        'float'   => (float) $value,
        'boolean' => in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true),
        'json'    => json_decode((string) $value, true),
        default   => (string) $value, // string, url, email — all stored as strings
    };
}

/**
 * Convert a PHP value to a string for database storage.
 *
 * @param  mixed       $value  The PHP value to convert
 * @return string|null         The string representation
 */
function _g2ml_settingToString(mixed $value): ?string
{
    if ($value === null)
    {
        return null;
    }

    if (is_bool($value))
    {
        return $value ? '1' : '0';
    }

    if (is_array($value) || is_object($value))
    {
        return json_encode($value);
    }

    return (string) $value;
}

/**
 * Get all settings for a specific scope (for admin panels, export, etc.).
 *
 * @param  string      $scope     The scope to retrieve (Default, System, Organisation, User)
 * @param  string|null $scopeRef  Scope reference (orgHandle or userUID)
 * @return array                  Associative array of settingID => value
 */
function getAllSettings(string $scope = 'Default', ?string $scopeRef = null): array
{
    $cacheKey = $scope;

    if ($scope === 'Organisation' && $scopeRef !== null)
    {
        $cacheKey = 'Organisation:' . $scopeRef;
        _g2ml_loadOrgSettings($scopeRef);
    }
    elseif ($scope === 'User' && $scopeRef !== null)
    {
        $cacheKey = 'User:' . $scopeRef;
        _g2ml_loadUserSettings((int) $scopeRef);
    }

    return $GLOBALS['_g2ml_settings_cache'][$cacheKey] ?? [];
}
