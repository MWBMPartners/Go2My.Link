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
 * 🏷️ Go2My.Link — Account Type Management Functions
 * ============================================================================
 *
 * Functions for managing multiple account types per user, org-scoped.
 * Works alongside the existing role system (tblUsers.role + hasMinimumRole)
 * for full backward compatibility.
 *
 * Architecture:
 *   - tblAccountTypes: reference table defining available types
 *   - tblUserAccountTypes: junction table (user ↔ type, org-scoped)
 *   - tblUsers.role: cached "effective role" = highest-privilege type held
 *   - syncEffectiveRole(): keeps tblUsers.role in sync after any change
 *
 * Dependencies: db_query.php (dbSelect, dbSelectOne, dbInsert, dbUpdate),
 *               auth.php (hasMinimumRole, getCurrentUser),
 *               activity_logger.php (logActivity)
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.1.0
 * @since      Phase 7
 *
 * 📖 References:
 *     - tblAccountTypes schema: web/_sql/schema/015_account_types.sql
 *     - tblUserAccountTypes schema: web/_sql/schema/015_account_types.sql
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
// 🔍 Query Functions
// ============================================================================

/**
 * Get all active account types assigned to a user.
 *
 * Returns an array of account type records with metadata from the reference
 * table (display name, role level, etc.). Filters out expired and inactive
 * assignments.
 *
 * @param  int         $userUID    The user's UID
 * @param  string|null $orgHandle  If provided, filter to this org only; NULL = all orgs
 * @return array                   Array of account type assignment records
 */
function getUserAccountTypes(int $userUID, ?string $orgHandle = null): array
{
    if ($orgHandle !== null)
    {
        $rows = dbSelect(
            "SELECT uat.userAccountTypeUID, uat.accountTypeID, uat.orgHandle,
                    uat.grantedByUserUID, uat.grantedAt, uat.expiresAt, uat.isActive,
                    at.accountTypeName, at.accountTypeDescription, at.roleLevel, at.roleName,
                    at.isSystemType, at.sortOrder
             FROM tblUserAccountTypes uat
             JOIN tblAccountTypes at ON uat.accountTypeID = at.accountTypeID
             WHERE uat.userUID = ? AND uat.orgHandle = ?
               AND uat.isActive = 1
               AND (uat.expiresAt IS NULL OR uat.expiresAt > NOW())
               AND at.isActive = 1
             ORDER BY at.sortOrder ASC, at.accountTypeName ASC",
            'is',
            [$userUID, $orgHandle]
        );
    }
    else
    {
        $rows = dbSelect(
            "SELECT uat.userAccountTypeUID, uat.accountTypeID, uat.orgHandle,
                    uat.grantedByUserUID, uat.grantedAt, uat.expiresAt, uat.isActive,
                    at.accountTypeName, at.accountTypeDescription, at.roleLevel, at.roleName,
                    at.isSystemType, at.sortOrder
             FROM tblUserAccountTypes uat
             JOIN tblAccountTypes at ON uat.accountTypeID = at.accountTypeID
             WHERE uat.userUID = ?
               AND uat.isActive = 1
               AND (uat.expiresAt IS NULL OR uat.expiresAt > NOW())
               AND at.isActive = 1
             ORDER BY at.sortOrder ASC, at.accountTypeName ASC",
            'i',
            [$userUID]
        );
    }

    return $rows ?: [];
}

/**
 * Check if a user has a specific account type (active and non-expired).
 *
 * @param  int         $userUID        The user's UID
 * @param  string      $accountTypeID  The account type slug to check
 * @param  string|null $orgHandle      If provided, check within this org only
 * @return bool
 */
function hasAccountType(int $userUID, string $accountTypeID, ?string $orgHandle = null): bool
{
    if ($orgHandle !== null)
    {
        $row = dbSelectOne(
            "SELECT uat.userAccountTypeUID
             FROM tblUserAccountTypes uat
             JOIN tblAccountTypes at ON uat.accountTypeID = at.accountTypeID
             WHERE uat.userUID = ? AND uat.accountTypeID = ? AND uat.orgHandle = ?
               AND uat.isActive = 1
               AND (uat.expiresAt IS NULL OR uat.expiresAt > NOW())
               AND at.isActive = 1
             LIMIT 1",
            'iss',
            [$userUID, $accountTypeID, $orgHandle]
        );
    }
    else
    {
        $row = dbSelectOne(
            "SELECT uat.userAccountTypeUID
             FROM tblUserAccountTypes uat
             JOIN tblAccountTypes at ON uat.accountTypeID = at.accountTypeID
             WHERE uat.userUID = ? AND uat.accountTypeID = ?
               AND uat.isActive = 1
               AND (uat.expiresAt IS NULL OR uat.expiresAt > NOW())
               AND at.isActive = 1
             LIMIT 1",
            'is',
            [$userUID, $accountTypeID]
        );
    }

    return ($row !== null && $row !== false);
}

/**
 * Determine the effective (highest-privilege) role for a user.
 *
 * Queries the junction table for the account type with the highest roleLevel
 * and returns the corresponding legacy roleName. Falls back to 'User' if
 * no active account types are found.
 *
 * @param  int         $userUID    The user's UID
 * @param  string|null $orgHandle  If provided, consider types in this org only
 * @return string                  Legacy role name: 'GlobalAdmin', 'Admin', 'User', or 'Anonymous'
 */
function getEffectiveRole(int $userUID, ?string $orgHandle = null): string
{
    if ($orgHandle !== null)
    {
        $row = dbSelectOne(
            "SELECT at.roleName
             FROM tblUserAccountTypes uat
             JOIN tblAccountTypes at ON uat.accountTypeID = at.accountTypeID
             WHERE uat.userUID = ? AND uat.orgHandle = ?
               AND uat.isActive = 1
               AND (uat.expiresAt IS NULL OR uat.expiresAt > NOW())
               AND at.isActive = 1
             ORDER BY at.roleLevel DESC
             LIMIT 1",
            'is',
            [$userUID, $orgHandle]
        );
    }
    else
    {
        $row = dbSelectOne(
            "SELECT at.roleName
             FROM tblUserAccountTypes uat
             JOIN tblAccountTypes at ON uat.accountTypeID = at.accountTypeID
             WHERE uat.userUID = ?
               AND uat.isActive = 1
               AND (uat.expiresAt IS NULL OR uat.expiresAt > NOW())
               AND at.isActive = 1
             ORDER BY at.roleLevel DESC
             LIMIT 1",
            'i',
            [$userUID]
        );
    }

    if ($row !== null && $row !== false)
    {
        return $row['roleName'];
    }

    return 'User';
}

// ============================================================================
// 🔧 Management Functions
// ============================================================================

/**
 * Assign an account type to a user within an org context.
 *
 * If the assignment already exists but was previously revoked (isActive = 0),
 * it is re-activated. After assignment, syncEffectiveRole() is called to
 * update the cached tblUsers.role column.
 *
 * @param  int         $userUID          The user's UID
 * @param  string      $accountTypeID    Account type slug to assign
 * @param  string      $orgHandle        Organisation context
 * @param  int|null    $grantedByUserUID Who is assigning this type (NULL = system)
 * @param  string|null $expiresAt        Optional expiry datetime (NULL = permanent)
 * @return array                         ['success' => bool, 'error' => string|null]
 */
function assignAccountType(
    int $userUID,
    string $accountTypeID,
    string $orgHandle,
    ?int $grantedByUserUID = null,
    ?string $expiresAt = null
): array {
    // Validate the account type exists and is active
    $accountType = getAccountType($accountTypeID);
    if ($accountType === null)
    {
        return ['success' => false, 'error' => 'Account type not found or inactive.'];
    }

    // Check if an assignment already exists (active or inactive)
    $existing = dbSelectOne(
        "SELECT userAccountTypeUID, isActive
         FROM tblUserAccountTypes
         WHERE userUID = ? AND orgHandle = ? AND accountTypeID = ?
         LIMIT 1",
        'iss',
        [$userUID, $orgHandle, $accountTypeID]
    );

    if ($existing !== null && $existing !== false)
    {
        if ((int) $existing['isActive'] === 1)
        {
            // Already assigned and active — nothing to do
            return ['success' => true, 'error' => null];
        }

        // Re-activate the existing assignment
        $result = dbUpdate(
            "UPDATE tblUserAccountTypes
             SET isActive = 1, grantedByUserUID = ?, grantedAt = NOW(), expiresAt = ?
             WHERE userAccountTypeUID = ?",
            'isi',
            [$grantedByUserUID, $expiresAt, $existing['userAccountTypeUID']]
        );
    }
    else
    {
        // Create new assignment
        $result = dbInsert(
            "INSERT INTO tblUserAccountTypes
                (userUID, orgHandle, accountTypeID, grantedByUserUID, grantedAt, expiresAt, isActive)
             VALUES (?, ?, ?, ?, NOW(), ?, 1)",
            'issis',
            [$userUID, $orgHandle, $accountTypeID, $grantedByUserUID, $expiresAt]
        );
    }

    if ($result === false)
    {
        return ['success' => false, 'error' => 'Failed to assign account type.'];
    }

    // Sync the effective role
    syncEffectiveRole($userUID);

    // Log activity
    logActivity('assign_account_type', 'success', 200, [
        'userUID' => $grantedByUserUID ?? 0,
        'logData' => [
            'targetUserUID'  => $userUID,
            'accountTypeID'  => $accountTypeID,
            'orgHandle'      => $orgHandle,
        ],
    ]);

    return ['success' => true, 'error' => null];
}

/**
 * Revoke an account type from a user (soft-delete via isActive = 0).
 *
 * Prevents revoking the last account type — a user must always retain
 * at least the base 'user' type. After revocation, syncEffectiveRole()
 * is called to update the cached tblUsers.role column.
 *
 * @param  int    $userUID        The user's UID
 * @param  string $accountTypeID  Account type slug to revoke
 * @param  string $orgHandle      Organisation context
 * @return array                  ['success' => bool, 'error' => string|null]
 */
function revokeAccountType(int $userUID, string $accountTypeID, string $orgHandle): array
{
    // Check the assignment exists and is active
    $existing = dbSelectOne(
        "SELECT userAccountTypeUID
         FROM tblUserAccountTypes
         WHERE userUID = ? AND orgHandle = ? AND accountTypeID = ? AND isActive = 1
         LIMIT 1",
        'iss',
        [$userUID, $orgHandle, $accountTypeID]
    );

    if ($existing === null || $existing === false)
    {
        return ['success' => false, 'error' => 'Account type assignment not found.'];
    }

    // Count remaining active types for this user across all orgs
    $activeCount = dbSelectOne(
        "SELECT COUNT(*) AS cnt
         FROM tblUserAccountTypes
         WHERE userUID = ? AND isActive = 1
           AND (expiresAt IS NULL OR expiresAt > NOW())",
        'i',
        [$userUID]
    );

    if ((int) ($activeCount['cnt'] ?? 0) <= 1)
    {
        return ['success' => false, 'error' => 'Cannot revoke the last account type. A user must always have at least one active type.'];
    }

    // Soft-delete the assignment
    $result = dbUpdate(
        "UPDATE tblUserAccountTypes SET isActive = 0 WHERE userAccountTypeUID = ?",
        'i',
        [$existing['userAccountTypeUID']]
    );

    if ($result === false)
    {
        return ['success' => false, 'error' => 'Failed to revoke account type.'];
    }

    // Sync the effective role
    syncEffectiveRole($userUID);

    // Log activity
    $currentUser = getCurrentUser();
    logActivity('revoke_account_type', 'success', 200, [
        'userUID' => ($currentUser !== null) ? $currentUser['userUID'] : 0,
        'logData' => [
            'targetUserUID'  => $userUID,
            'accountTypeID'  => $accountTypeID,
            'orgHandle'      => $orgHandle,
        ],
    ]);

    return ['success' => true, 'error' => null];
}

// ============================================================================
// 🔄 Sync Functions
// ============================================================================

/**
 * Recalculate and update the cached effective role in tblUsers.role.
 *
 * Queries the junction table for the user's highest-privilege account type
 * (by roleLevel) and writes the corresponding roleName to tblUsers.role.
 * Also updates $_SESSION['user_role'] if this is the current user.
 *
 * This function is called automatically by assignAccountType() and
 * revokeAccountType(). It ensures that hasMinimumRole() continues to
 * work without any changes.
 *
 * @param  int  $userUID  The user's UID
 * @return bool           True on success, false on failure
 */
function syncEffectiveRole(int $userUID): bool
{
    $effectiveRole = getEffectiveRole($userUID);

    $updated = dbUpdate(
        "UPDATE tblUsers SET role = ? WHERE userUID = ?",
        'si',
        [$effectiveRole, $userUID]
    );

    // If this user is currently logged in, update their session
    if (isset($_SESSION['user_uid']) && (int) $_SESSION['user_uid'] === $userUID)
    {
        $_SESSION['user_role'] = $effectiveRole;
    }

    return ($updated !== false);
}

/**
 * Refresh the account types stored in the current user's session.
 *
 * Should be called after any account type change that affects the current user
 * (or the user identified by $userUID).
 *
 * @param  int    $userUID    The user's UID
 * @param  string $orgHandle  Organisation context
 * @return void
 */
function refreshSessionAccountTypes(int $userUID, string $orgHandle): void
{
    if (isset($_SESSION['user_uid']) && (int) $_SESSION['user_uid'] === $userUID)
    {
        $_SESSION['user_account_types'] = getUserAccountTypes($userUID, $orgHandle);
    }
}

// ============================================================================
// 📋 Reference Table Functions
// ============================================================================

/**
 * Get all account types from the reference table.
 *
 * @param  bool  $activeOnly  If true, only return active types (default: true)
 * @return array               Array of account type records
 */
function getAllAccountTypes(bool $activeOnly = true): array
{
    if ($activeOnly)
    {
        $rows = dbSelect(
            "SELECT accountTypeUID, accountTypeID, accountTypeName, accountTypeDescription,
                    roleLevel, roleName, isSystemType, sortOrder, isActive,
                    createdAt, updatedAt
             FROM tblAccountTypes
             WHERE isActive = 1
             ORDER BY sortOrder ASC, accountTypeName ASC",
            '',
            []
        );
    }
    else
    {
        $rows = dbSelect(
            "SELECT accountTypeUID, accountTypeID, accountTypeName, accountTypeDescription,
                    roleLevel, roleName, isSystemType, sortOrder, isActive,
                    createdAt, updatedAt
             FROM tblAccountTypes
             ORDER BY sortOrder ASC, accountTypeName ASC",
            '',
            []
        );
    }

    return $rows ?: [];
}

/**
 * Get a single account type by its slug ID.
 *
 * @param  string     $accountTypeID  The account type slug
 * @return array|null                  Account type record, or null if not found/inactive
 */
function getAccountType(string $accountTypeID): ?array
{
    $row = dbSelectOne(
        "SELECT accountTypeUID, accountTypeID, accountTypeName, accountTypeDescription,
                roleLevel, roleName, isSystemType, sortOrder, isActive,
                createdAt, updatedAt
         FROM tblAccountTypes
         WHERE accountTypeID = ? AND isActive = 1
         LIMIT 1",
        's',
        [$accountTypeID]
    );

    if ($row === null || $row === false)
    {
        return null;
    }

    return $row;
}

// ============================================================================
// 🔍 Session Convenience Functions
// ============================================================================

/**
 * Check if the current session user has a specific account type.
 *
 * This is a fast check against the session-cached account types array,
 * avoiding a database query. The session is populated during login.
 *
 * @param  string $accountTypeID  The account type slug to check
 * @return bool
 */
function hasAccountTypeInSession(string $accountTypeID): bool
{
    $types = $_SESSION['user_account_types'] ?? [];

    foreach ($types as $type)
    {
        if (isset($type['accountTypeID']) && $type['accountTypeID'] === $accountTypeID)
        {
            return true;
        }
    }

    return false;
}
