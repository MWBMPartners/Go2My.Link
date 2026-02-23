<?php
/**
 * ============================================================================
 * 🏢 Go2My.Link — Organisation Management Functions
 * ============================================================================
 *
 * CRUD operations for organisations, member management, invitations,
 * custom domain verification, and short domain management.
 *
 * Dependencies: security.php (g2ml_generateToken, g2ml_sanitiseInput),
 *               db_query.php (dbSelect, dbSelectOne, dbInsert, dbUpdate, dbDelete),
 *               settings.php (getSetting),
 *               auth.php (getCurrentUser, hasMinimumRole),
 *               email.php (g2ml_sendEmail),
 *               activity_logger.php (logActivity)
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.6.0
 * @since      Phase 5
 *
 * 📖 References:
 *     - tblOrganisations schema: web/_sql/schema/012_core_organisations.sql
 *     - tblOrgInvitations schema: web/_sql/schema/014_org_invitations.sql
 *     - DNS TXT records: https://www.php.net/manual/en/function.dns-get-record.php
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
// 🔒 Permission Check
// ============================================================================

/**
 * Check if the current user can manage a given organisation.
 *
 * Returns true if the user is an Admin of the specified org or a GlobalAdmin.
 *
 * @param  string $orgHandle  Organisation handle to check against
 * @return bool
 */
function canManageOrg(string $orgHandle): bool
{
    $currentUser = getCurrentUser();

    if ($currentUser === null)
    {
        return false;
    }

    // GlobalAdmin can manage any org
    if (hasMinimumRole($currentUser['role'], 'GlobalAdmin'))
    {
        return true;
    }

    // Admin can manage their own org
    return $currentUser['orgHandle'] === $orgHandle
        && hasMinimumRole($currentUser['role'], 'Admin');
}

// ============================================================================
// 🏢 Organisation CRUD
// ============================================================================

/**
 * Create a new organisation.
 *
 * Validates the handle, creates the org record, and moves the creator
 * from the [default] org to the new org with Admin role.
 *
 * @param  string $name    Organisation display name
 * @param  string $handle  Organisation handle/slug (lowercase, alphanumeric + hyphens)
 * @param  array  $options Optional: orgURL, orgDescription
 * @return array  ['success' => bool, 'error' => string|null, 'orgHandle' => string|null]
 */
function createOrganisation(string $name, string $handle, array $options = []): array
{
    $currentUser = getCurrentUser();

    if ($currentUser === null)
    {
        return ['success' => false, 'error' => 'You must be logged in to create an organisation.'];
    }

    // Must be in [default] org (not already in another org)
    if ($currentUser['orgHandle'] !== '[default]')
    {
        return ['success' => false, 'error' => 'You are already a member of an organisation.'];
    }

    // Check if org creation is allowed
    if ((int) getSetting('org.allow_creation', '1') !== 1)
    {
        return ['success' => false, 'error' => 'Organisation creation is currently disabled.'];
    }

    // Check email verification requirement
    if ((int) getSetting('org.require_email_verified', '1') === 1 && (int) ($currentUser['emailVerified'] ?? 0) !== 1)
    {
        return ['success' => false, 'error' => 'You must verify your email address before creating an organisation.'];
    }

    // Validate handle format
    $handleValidation = _g2ml_validateOrgHandle($handle);
    if ($handleValidation !== null)
    {
        return ['success' => false, 'error' => $handleValidation];
    }

    // Check handle uniqueness
    $existing = dbSelectOne(
        "SELECT orgHandle FROM tblOrganisations WHERE orgHandle = ? LIMIT 1",
        's',
        [$handle]
    );
    if ($existing !== null && $existing !== false)
    {
        return ['success' => false, 'error' => 'This handle is already taken. Please choose another.'];
    }

    // Create the organisation
    $orgURL     = isset($options['orgURL']) ? g2ml_sanitiseURL($options['orgURL']) : null;
    $orgDesc    = $options['orgDescription'] ?? null;

    $insertResult = dbInsert(
        "INSERT INTO tblOrganisations (orgHandle, orgName, orgURL, orgDescription, tierID, isVerified, isActive)
         VALUES (?, ?, ?, ?, 'free', 0, 1)",
        'ssss',
        [$handle, $name, $orgURL ?: null, $orgDesc]
    );

    if ($insertResult === false)
    {
        return ['success' => false, 'error' => 'Failed to create organisation. Please try again.'];
    }

    // Move creator to the new org and promote to Admin
    $moveResult = dbUpdate(
        "UPDATE tblUsers SET orgHandle = ?, role = 'Admin' WHERE userUID = ?",
        'si',
        [$handle, $currentUser['userUID']]
    );

    if ($moveResult === false)
    {
        return ['success' => false, 'error' => 'Organisation created but failed to assign you as admin.'];
    }

    // Update session data
    $_SESSION['user_org_handle'] = $handle;
    $_SESSION['user_role']       = 'Admin';

    logActivity('create_org', 'success', 201, [
        'userUID' => $currentUser['userUID'],
        'logData' => ['orgHandle' => $handle, 'orgName' => $name],
    ]);

    return ['success' => true, 'orgHandle' => $handle];
}

/**
 * Get organisation details with tier information.
 *
 * @param  string     $orgHandle
 * @return array|null  Org data array or null if not found
 */
function getOrganisation(string $orgHandle): ?array
{
    return dbSelectOne(
        "SELECT o.*, t.tierName, t.maxLinks, t.maxCustomDomains, t.maxLinksPages
         FROM tblOrganisations o
         LEFT JOIN tblSubscriptionTiers t ON o.tierID = t.tierID
         WHERE o.orgHandle = ? AND o.isActive = 1
         LIMIT 1",
        's',
        [$orgHandle]
    );
}

/**
 * Update organisation details.
 *
 * @param  string $orgHandle
 * @param  array  $data  Associative array of fields to update
 * @return array  ['success' => bool, 'error' => string|null]
 */
function updateOrganisation(string $orgHandle, array $data): array
{
    if (!canManageOrg($orgHandle))
    {
        return ['success' => false, 'error' => 'You do not have permission to manage this organisation.'];
    }

    $allowedFields = ['orgName', 'orgURL', 'orgDescription', 'orgFallbackURL'];
    $currentUser   = getCurrentUser();

    // GlobalAdmin can also edit admin-only fields
    if (hasMinimumRole($currentUser['role'], 'GlobalAdmin'))
    {
        $allowedFields = array_merge($allowedFields, ['tierID', 'isVerified', 'isActive', 'orgNotes']);
    }

    $setClauses = [];
    $params     = [];
    $types      = '';

    foreach ($data as $field => $value)
    {
        if (!in_array($field, $allowedFields, true))
        {
            continue;
        }

        // URL fields need sanitisation
        if (in_array($field, ['orgURL', 'orgFallbackURL'], true) && $value !== null && $value !== '')
        {
            $value = g2ml_sanitiseURL($value);
            if ($value === false)
            {
                return ['success' => false, 'error' => "Invalid URL provided for {$field}."];
            }
        }

        $setClauses[] = "`{$field}` = ?";
        $params[]     = $value;
        $types       .= is_int($value) ? 'i' : 's';
    }

    if (empty($setClauses))
    {
        return ['success' => false, 'error' => 'No valid fields to update.'];
    }

    $params[] = $orgHandle;
    $types   .= 's';

    $result = dbUpdate(
        "UPDATE tblOrganisations SET " . implode(', ', $setClauses) . " WHERE orgHandle = ?",
        $types,
        $params
    );

    if ($result === false)
    {
        return ['success' => false, 'error' => 'Failed to update organisation.'];
    }

    logActivity('update_org', 'success', 200, [
        'userUID' => $currentUser['userUID'],
        'logData' => ['orgHandle' => $orgHandle, 'fields' => array_keys($data)],
    ]);

    return ['success' => true];
}

// ============================================================================
// 👥 Member Management
// ============================================================================

/**
 * Get all members of an organisation.
 *
 * @param  string $orgHandle
 * @return array  Array of user rows
 */
function getOrgMembers(string $orgHandle): array
{
    $rows = dbSelect(
        "SELECT userUID, username, email, firstName, lastName, displayName,
                role, lastLoginAt, createdAt, isActive, isSuspended
         FROM tblUsers
         WHERE orgHandle = ? AND orgHandle != '[default]'
         ORDER BY role DESC, displayName ASC",
        's',
        [$orgHandle]
    );

    return $rows ?: [];
}

/**
 * Count active members in an organisation.
 *
 * @param  string $orgHandle
 * @return int
 */
function getOrgMemberCount(string $orgHandle): int
{
    $row = dbSelectOne(
        "SELECT COUNT(*) AS cnt FROM tblUsers WHERE orgHandle = ? AND isActive = 1",
        's',
        [$orgHandle]
    );

    return (int) ($row['cnt'] ?? 0);
}

/**
 * Remove a member from an organisation (move back to [default], set role to User).
 *
 * @param  string $orgHandle
 * @param  int    $userUID
 * @return array  ['success' => bool, 'error' => string|null]
 */
function removeMember(string $orgHandle, int $userUID): array
{
    if (!canManageOrg($orgHandle))
    {
        return ['success' => false, 'error' => 'You do not have permission to manage this organisation.'];
    }

    $currentUser = getCurrentUser();

    // Can't remove yourself
    if ($currentUser['userUID'] === $userUID)
    {
        return ['success' => false, 'error' => 'You cannot remove yourself from the organisation.'];
    }

    // Verify user is actually in this org
    $member = dbSelectOne(
        "SELECT userUID, role FROM tblUsers WHERE userUID = ? AND orgHandle = ? LIMIT 1",
        'is',
        [$userUID, $orgHandle]
    );

    if ($member === null || $member === false)
    {
        return ['success' => false, 'error' => 'User is not a member of this organisation.'];
    }

    // Non-GlobalAdmin can't remove other Admins
    if (!hasMinimumRole($currentUser['role'], 'GlobalAdmin') && $member['role'] === 'Admin')
    {
        return ['success' => false, 'error' => 'Only a GlobalAdmin can remove other administrators.'];
    }

    $result = dbUpdate(
        "UPDATE tblUsers SET orgHandle = '[default]', role = 'User' WHERE userUID = ? AND orgHandle = ?",
        'is',
        [$userUID, $orgHandle]
    );

    if ($result === false)
    {
        return ['success' => false, 'error' => 'Failed to remove member.'];
    }

    logActivity('remove_org_member', 'success', 200, [
        'userUID' => $currentUser['userUID'],
        'logData' => ['orgHandle' => $orgHandle, 'removedUserUID' => $userUID],
    ]);

    return ['success' => true];
}

/**
 * Change a member's role within an organisation.
 *
 * @param  string $orgHandle
 * @param  int    $userUID
 * @param  string $newRole   'User' or 'Admin'
 * @return array  ['success' => bool, 'error' => string|null]
 */
function changeMemberRole(string $orgHandle, int $userUID, string $newRole): array
{
    if (!canManageOrg($orgHandle))
    {
        return ['success' => false, 'error' => 'You do not have permission to manage this organisation.'];
    }

    if (!in_array($newRole, ['User', 'Admin'], true))
    {
        return ['success' => false, 'error' => 'Invalid role. Must be User or Admin.'];
    }

    $currentUser = getCurrentUser();

    // Can't change own role (prevents admin lockout)
    if ($currentUser['userUID'] === $userUID)
    {
        return ['success' => false, 'error' => 'You cannot change your own role.'];
    }

    // Verify user is in this org
    $member = dbSelectOne(
        "SELECT userUID, role FROM tblUsers WHERE userUID = ? AND orgHandle = ? LIMIT 1",
        'is',
        [$userUID, $orgHandle]
    );

    if ($member === null || $member === false)
    {
        return ['success' => false, 'error' => 'User is not a member of this organisation.'];
    }

    $result = dbUpdate(
        "UPDATE tblUsers SET role = ? WHERE userUID = ? AND orgHandle = ?",
        'sis',
        [$newRole, $userUID, $orgHandle]
    );

    if ($result === false)
    {
        return ['success' => false, 'error' => 'Failed to change member role.'];
    }

    logActivity('change_org_member_role', 'success', 200, [
        'userUID' => $currentUser['userUID'],
        'logData' => ['orgHandle' => $orgHandle, 'targetUserUID' => $userUID, 'newRole' => $newRole],
    ]);

    return ['success' => true];
}

// ============================================================================
// 📧 Invitations
// ============================================================================

/**
 * Invite a user to join an organisation.
 *
 * Generates a tokenised invitation, stores the SHA-256 hash, and sends
 * an email with the accept link.
 *
 * @param  string $orgHandle
 * @param  string $email     Invitee email address
 * @param  string $role      Role to assign on acceptance ('User' or 'Admin')
 * @return array  ['success' => bool, 'error' => string|null]
 */
function inviteMember(string $orgHandle, string $email, string $role = 'User'): array
{
    if (!canManageOrg($orgHandle))
    {
        return ['success' => false, 'error' => 'You do not have permission to invite members.'];
    }

    if (!in_array($role, ['User', 'Admin'], true))
    {
        return ['success' => false, 'error' => 'Invalid role. Must be User or Admin.'];
    }

    $email = strtolower(trim($email));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL))
    {
        return ['success' => false, 'error' => 'Invalid email address.'];
    }

    // Check if user is already in this org
    $existingMember = dbSelectOne(
        "SELECT userUID FROM tblUsers WHERE email = ? AND orgHandle = ? LIMIT 1",
        'ss',
        [$email, $orgHandle]
    );

    if ($existingMember !== null && $existingMember !== false)
    {
        return ['success' => false, 'error' => 'This user is already a member of your organisation.'];
    }

    // Check for existing pending invitation
    $existingInvite = dbSelectOne(
        "SELECT invitationUID FROM tblOrgInvitations
         WHERE orgHandle = ? AND email = ? AND status = 'pending' AND expiresAt > NOW()
         LIMIT 1",
        'ss',
        [$orgHandle, $email]
    );

    if ($existingInvite !== null && $existingInvite !== false)
    {
        return ['success' => false, 'error' => 'A pending invitation already exists for this email address.'];
    }

    // Check max pending invitations
    $maxPending = (int) getSetting('org.max_pending_invitations', '50');
    $pendingCount = dbSelectOne(
        "SELECT COUNT(*) AS cnt FROM tblOrgInvitations
         WHERE orgHandle = ? AND status = 'pending' AND expiresAt > NOW()",
        's',
        [$orgHandle]
    );

    if ((int) ($pendingCount['cnt'] ?? 0) >= $maxPending)
    {
        return ['success' => false, 'error' => 'Maximum number of pending invitations reached.'];
    }

    // Generate invitation token
    $token     = g2ml_generateToken(32);
    $tokenHash = hash('sha256', $token);
    $expiry    = date('Y-m-d H:i:s', time() + (int) getSetting('org.invitation_expiry', 604800));

    $currentUser = getCurrentUser();

    // Expire any previous pending invitations for this email+org
    dbUpdate(
        "UPDATE tblOrgInvitations SET status = 'expired'
         WHERE orgHandle = ? AND email = ? AND status = 'pending'",
        'ss',
        [$orgHandle, $email]
    );

    // Create invitation record
    $insertResult = dbInsert(
        "INSERT INTO tblOrgInvitations (orgHandle, email, role, invitationToken, invitedByUserUID, expiresAt)
         VALUES (?, ?, ?, ?, ?, ?)",
        'ssssss',
        [$orgHandle, $email, $role, $tokenHash, $currentUser['userUID'], $expiry]
    );

    if ($insertResult === false)
    {
        return ['success' => false, 'error' => 'Failed to create invitation.'];
    }

    // Get org name for email
    $org = getOrganisation($orgHandle);
    $orgName = $org['orgName'] ?? $orgHandle;

    // Send invitation email
    $acceptURL = 'https://go2my.link/invite?token=' . urlencode($token);
    $subject   = str_replace('{orgName}', $orgName, getSetting(
        'org.invitation_email_subject',
        "You've been invited to join {orgName} on Go2My.Link"
    ));

    g2ml_sendEmail($email, $subject, 'org_invitation', [
        'orgName'      => $orgName,
        'inviterName'  => $currentUser['displayName'] ?? ($currentUser['firstName'] . ' ' . $currentUser['lastName']),
        'inviterEmail' => $currentUser['email'] ?? '',
        'role'         => $role,
        'acceptURL'    => $acceptURL,
        'expiryDays'   => round((int) getSetting('org.invitation_expiry', 604800) / 86400),
    ]);

    logActivity('invite_org_member', 'success', 201, [
        'userUID' => $currentUser['userUID'],
        'logData' => ['orgHandle' => $orgHandle, 'email' => $email, 'role' => $role],
    ]);

    return ['success' => true];
}

/**
 * Accept an organisation invitation.
 *
 * Validates the token, moves the user to the org, and updates the invitation status.
 *
 * @param  string $token  Plaintext token from email link
 * @return array  ['success' => bool, 'error' => string|null, 'orgHandle' => string|null]
 */
function acceptInvitation(string $token): array
{
    $currentUser = getCurrentUser();

    if ($currentUser === null)
    {
        return ['success' => false, 'error' => 'You must be logged in to accept an invitation.'];
    }

    // User must be in [default] org
    if ($currentUser['orgHandle'] !== '[default]')
    {
        return ['success' => false, 'error' => 'You are already a member of another organisation. You must leave your current organisation first.'];
    }

    $tokenHash = hash('sha256', $token);

    // Look up invitation
    $invitation = dbSelectOne(
        "SELECT i.*, o.orgName
         FROM tblOrgInvitations i
         JOIN tblOrganisations o ON i.orgHandle = o.orgHandle
         WHERE i.invitationToken = ? AND i.status = 'pending' AND i.expiresAt > NOW()
         LIMIT 1",
        's',
        [$tokenHash]
    );

    if ($invitation === null || $invitation === false)
    {
        return ['success' => false, 'error' => 'This invitation is invalid, has expired, or has already been used.'];
    }

    // Check email matches
    if (strtolower($currentUser['email']) !== strtolower($invitation['email']))
    {
        return ['success' => false, 'error' => 'This invitation was sent to a different email address.'];
    }

    // Move user to the org with assigned role
    $moveResult = dbUpdate(
        "UPDATE tblUsers SET orgHandle = ?, role = ? WHERE userUID = ?",
        'ssi',
        [$invitation['orgHandle'], $invitation['role'], $currentUser['userUID']]
    );

    if ($moveResult === false)
    {
        return ['success' => false, 'error' => 'Failed to join organisation.'];
    }

    // Mark invitation as accepted
    dbUpdate(
        "UPDATE tblOrgInvitations SET status = 'accepted', acceptedAt = NOW()
         WHERE invitationUID = ?",
        'i',
        [$invitation['invitationUID']]
    );

    // Update session
    $_SESSION['user_org_handle'] = $invitation['orgHandle'];
    $_SESSION['user_role']       = $invitation['role'];

    logActivity('accept_org_invitation', 'success', 200, [
        'userUID' => $currentUser['userUID'],
        'logData' => [
            'orgHandle' => $invitation['orgHandle'],
            'role'      => $invitation['role'],
        ],
    ]);

    return [
        'success'   => true,
        'orgHandle' => $invitation['orgHandle'],
        'orgName'   => $invitation['orgName'],
    ];
}

/**
 * Cancel a pending invitation.
 *
 * @param  int    $invitationUID
 * @param  string $orgHandle
 * @return array  ['success' => bool, 'error' => string|null]
 */
function cancelInvitation(int $invitationUID, string $orgHandle): array
{
    if (!canManageOrg($orgHandle))
    {
        return ['success' => false, 'error' => 'You do not have permission to manage invitations.'];
    }

    $result = dbUpdate(
        "UPDATE tblOrgInvitations SET status = 'cancelled'
         WHERE invitationUID = ? AND orgHandle = ? AND status = 'pending'",
        'is',
        [$invitationUID, $orgHandle]
    );

    if ($result === false || $result === 0)
    {
        return ['success' => false, 'error' => 'Invitation not found or already processed.'];
    }

    $currentUser = getCurrentUser();

    logActivity('cancel_org_invitation', 'success', 200, [
        'userUID' => $currentUser['userUID'],
        'logData' => ['orgHandle' => $orgHandle, 'invitationUID' => $invitationUID],
    ]);

    return ['success' => true];
}

/**
 * Get pending invitations for an organisation.
 *
 * @param  string $orgHandle
 * @return array  Array of invitation rows
 */
function getPendingInvitations(string $orgHandle): array
{
    $rows = dbSelect(
        "SELECT i.invitationUID, i.email, i.role, i.expiresAt, i.createdAt,
                u.displayName AS inviterName
         FROM tblOrgInvitations i
         LEFT JOIN tblUsers u ON i.invitedByUserUID = u.userUID
         WHERE i.orgHandle = ? AND i.status = 'pending' AND i.expiresAt > NOW()
         ORDER BY i.createdAt DESC",
        's',
        [$orgHandle]
    );

    return $rows ?: [];
}

// ============================================================================
// 🌐 Custom Domain Management
// ============================================================================

/**
 * Add a custom domain to an organisation with a DNS verification token.
 *
 * @param  string $orgHandle
 * @param  string $domain     Domain name (e.g., "example.com")
 * @param  string $type       Domain type: 'primary', 'redirect', or 'linkspage'
 * @return array  ['success' => bool, 'error' => string|null, 'verificationToken' => string|null]
 */
function addOrgDomain(string $orgHandle, string $domain, string $type = 'primary'): array
{
    if (!canManageOrg($orgHandle))
    {
        return ['success' => false, 'error' => 'You do not have permission to manage domains.'];
    }

    if (!in_array($type, ['primary', 'redirect', 'linkspage'], true))
    {
        return ['success' => false, 'error' => 'Invalid domain type.'];
    }

    // Basic domain validation
    $domain = strtolower(trim($domain));
    if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain))
    {
        return ['success' => false, 'error' => 'Invalid domain name format.'];
    }

    // Check uniqueness
    $existing = dbSelectOne(
        "SELECT domainUID FROM tblOrgDomains WHERE domainName = ? LIMIT 1",
        's',
        [$domain]
    );

    if ($existing !== null && $existing !== false)
    {
        return ['success' => false, 'error' => 'This domain is already registered.'];
    }

    // Check tier limits
    $org = getOrganisation($orgHandle);
    $maxDomains = (int) getSetting('org.max_custom_domains', '0');
    if ($maxDomains === 0)
    {
        $maxDomains = (int) ($org['maxCustomDomains'] ?? 0);
    }

    if ($maxDomains > 0)
    {
        $currentCount = dbSelectOne(
            "SELECT COUNT(*) AS cnt FROM tblOrgDomains WHERE orgHandle = ? AND isActive = 1",
            's',
            [$orgHandle]
        );
        if ((int) ($currentCount['cnt'] ?? 0) >= $maxDomains)
        {
            return ['success' => false, 'error' => "Your plan allows a maximum of {$maxDomains} custom domains."];
        }
    }

    // Generate verification token
    $verifyToken = g2ml_generateToken(16);

    $insertResult = dbInsert(
        "INSERT INTO tblOrgDomains (orgHandle, domainName, domainType, verificationToken, verificationStatus)
         VALUES (?, ?, ?, ?, 'pending')",
        'ssss',
        [$orgHandle, $domain, $type, $verifyToken]
    );

    if ($insertResult === false)
    {
        return ['success' => false, 'error' => 'Failed to add domain.'];
    }

    $currentUser = getCurrentUser();
    logActivity('add_org_domain', 'success', 201, [
        'userUID' => $currentUser['userUID'],
        'logData' => ['orgHandle' => $orgHandle, 'domain' => $domain, 'type' => $type],
    ]);

    return ['success' => true, 'verificationToken' => $verifyToken];
}

/**
 * Verify a custom domain via DNS TXT record lookup.
 *
 * Checks for a TXT record at _g2ml-verify.{domain} matching the stored token.
 *
 * @param  int    $domainUID
 * @param  string $orgHandle
 * @return array  ['success' => bool, 'verified' => bool, 'error' => string|null]
 *
 * 📖 Reference: https://www.php.net/manual/en/function.dns-get-record.php
 */
function verifyDomain(int $domainUID, string $orgHandle): array
{
    if (!canManageOrg($orgHandle))
    {
        return ['success' => false, 'verified' => false, 'error' => 'You do not have permission to verify domains.'];
    }

    $domain = dbSelectOne(
        "SELECT * FROM tblOrgDomains WHERE domainUID = ? AND orgHandle = ? LIMIT 1",
        'is',
        [$domainUID, $orgHandle]
    );

    if ($domain === null || $domain === false)
    {
        return ['success' => false, 'verified' => false, 'error' => 'Domain not found.'];
    }

    $prefix    = getSetting('org.dns_verify_prefix', '_g2ml-verify');
    $lookupHost = $prefix . '.' . $domain['domainName'];

    // Perform DNS TXT lookup
    $records = @dns_get_record($lookupHost, DNS_TXT);

    if ($records === false || empty($records))
    {
        // Update last checked timestamp
        dbUpdate(
            "UPDATE tblOrgDomains SET lastCheckedAt = NOW(), verificationStatus = 'pending' WHERE domainUID = ?",
            'i',
            [$domainUID]
        );

        return ['success' => true, 'verified' => false, 'error' => 'No TXT record found. Please add the DNS record and try again.'];
    }

    // Check if any TXT record matches the verification token
    $verified = false;
    foreach ($records as $record)
    {
        if (isset($record['txt']) && $record['txt'] === $domain['verificationToken'])
        {
            $verified = true;
            break;
        }
    }

    if ($verified)
    {
        dbUpdate(
            "UPDATE tblOrgDomains SET verificationStatus = 'verified', verifiedAt = NOW(), lastCheckedAt = NOW()
             WHERE domainUID = ?",
            'i',
            [$domainUID]
        );

        $currentUser = getCurrentUser();
        logActivity('verify_org_domain', 'success', 200, [
            'userUID' => $currentUser['userUID'],
            'logData' => ['orgHandle' => $orgHandle, 'domain' => $domain['domainName']],
        ]);

        return ['success' => true, 'verified' => true];
    }

    // Token mismatch
    dbUpdate(
        "UPDATE tblOrgDomains SET lastCheckedAt = NOW(), verificationStatus = 'failed' WHERE domainUID = ?",
        'i',
        [$domainUID]
    );

    return ['success' => true, 'verified' => false, 'error' => 'TXT record found but the value does not match. Please check the verification token.'];
}

/**
 * Remove a custom domain from an organisation.
 *
 * @param  int    $domainUID
 * @param  string $orgHandle
 * @return array  ['success' => bool, 'error' => string|null]
 */
function removeOrgDomain(int $domainUID, string $orgHandle): array
{
    if (!canManageOrg($orgHandle))
    {
        return ['success' => false, 'error' => 'You do not have permission to manage domains.'];
    }

    $result = dbDelete(
        "DELETE FROM tblOrgDomains WHERE domainUID = ? AND orgHandle = ?",
        'is',
        [$domainUID, $orgHandle]
    );

    if ($result === false || $result === 0)
    {
        return ['success' => false, 'error' => 'Domain not found.'];
    }

    $currentUser = getCurrentUser();
    logActivity('remove_org_domain', 'success', 200, [
        'userUID' => $currentUser['userUID'],
        'logData' => ['orgHandle' => $orgHandle, 'domainUID' => $domainUID],
    ]);

    return ['success' => true];
}

/**
 * Get all custom domains for an organisation.
 *
 * @param  string $orgHandle
 * @return array
 */
function getOrgDomains(string $orgHandle): array
{
    $rows = dbSelect(
        "SELECT * FROM tblOrgDomains WHERE orgHandle = ? ORDER BY createdAt ASC",
        's',
        [$orgHandle]
    );

    return $rows ?: [];
}

// ============================================================================
// 🔗 Short Domain Management
// ============================================================================

/**
 * Add a custom short domain to an organisation.
 *
 * @param  string $orgHandle
 * @param  string $domain     Short domain (e.g., "camsda.link")
 * @return array  ['success' => bool, 'error' => string|null]
 */
function addOrgShortDomain(string $orgHandle, string $domain): array
{
    if (!canManageOrg($orgHandle))
    {
        return ['success' => false, 'error' => 'You do not have permission to manage short domains.'];
    }

    $domain = strtolower(trim($domain));
    if (!preg_match('/^[a-z0-9]([a-z0-9-]*[a-z0-9])?(\.[a-z0-9]([a-z0-9-]*[a-z0-9])?)+$/', $domain))
    {
        return ['success' => false, 'error' => 'Invalid domain name format.'];
    }

    // Check uniqueness
    $existing = dbSelectOne(
        "SELECT shortDomainUID FROM tblOrgShortDomains WHERE shortDomain = ? LIMIT 1",
        's',
        [$domain]
    );

    if ($existing !== null && $existing !== false)
    {
        return ['success' => false, 'error' => 'This short domain is already registered.'];
    }

    // Check if this is the first short domain (make it default)
    $currentCount = dbSelectOne(
        "SELECT COUNT(*) AS cnt FROM tblOrgShortDomains WHERE orgHandle = ? AND isActive = 1",
        's',
        [$orgHandle]
    );
    $isDefault = ((int) ($currentCount['cnt'] ?? 0) === 0) ? 1 : 0;

    $insertResult = dbInsert(
        "INSERT INTO tblOrgShortDomains (orgHandle, shortDomain, isDefault, isActive)
         VALUES (?, ?, ?, 1)",
        'ssi',
        [$orgHandle, $domain, $isDefault]
    );

    if ($insertResult === false)
    {
        return ['success' => false, 'error' => 'Failed to add short domain.'];
    }

    $currentUser = getCurrentUser();
    logActivity('add_org_short_domain', 'success', 201, [
        'userUID' => $currentUser['userUID'],
        'logData' => ['orgHandle' => $orgHandle, 'shortDomain' => $domain],
    ]);

    return ['success' => true];
}

/**
 * Remove a custom short domain.
 *
 * @param  int    $shortDomainUID
 * @param  string $orgHandle
 * @return array  ['success' => bool, 'error' => string|null]
 */
function removeOrgShortDomain(int $shortDomainUID, string $orgHandle): array
{
    if (!canManageOrg($orgHandle))
    {
        return ['success' => false, 'error' => 'You do not have permission to manage short domains.'];
    }

    // Can't remove the default short domain
    $domain = dbSelectOne(
        "SELECT * FROM tblOrgShortDomains WHERE shortDomainUID = ? AND orgHandle = ? LIMIT 1",
        'is',
        [$shortDomainUID, $orgHandle]
    );

    if ($domain === null || $domain === false)
    {
        return ['success' => false, 'error' => 'Short domain not found.'];
    }

    if ((int) $domain['isDefault'] === 1)
    {
        return ['success' => false, 'error' => 'Cannot remove the default short domain. Set another domain as default first.'];
    }

    $result = dbDelete(
        "DELETE FROM tblOrgShortDomains WHERE shortDomainUID = ? AND orgHandle = ?",
        'is',
        [$shortDomainUID, $orgHandle]
    );

    if ($result === false || $result === 0)
    {
        return ['success' => false, 'error' => 'Failed to remove short domain.'];
    }

    $currentUser = getCurrentUser();
    logActivity('remove_org_short_domain', 'success', 200, [
        'userUID' => $currentUser['userUID'],
        'logData' => ['orgHandle' => $orgHandle, 'shortDomain' => $domain['shortDomain']],
    ]);

    return ['success' => true];
}

/**
 * Set a short domain as the default for an organisation.
 *
 * @param  int    $shortDomainUID
 * @param  string $orgHandle
 * @return array  ['success' => bool, 'error' => string|null]
 */
function setDefaultShortDomain(int $shortDomainUID, string $orgHandle): array
{
    if (!canManageOrg($orgHandle))
    {
        return ['success' => false, 'error' => 'You do not have permission to manage short domains.'];
    }

    // Verify domain belongs to this org
    $domain = dbSelectOne(
        "SELECT * FROM tblOrgShortDomains WHERE shortDomainUID = ? AND orgHandle = ? LIMIT 1",
        'is',
        [$shortDomainUID, $orgHandle]
    );

    if ($domain === null || $domain === false)
    {
        return ['success' => false, 'error' => 'Short domain not found.'];
    }

    // Unset all defaults for this org
    dbUpdate(
        "UPDATE tblOrgShortDomains SET isDefault = 0 WHERE orgHandle = ?",
        's',
        [$orgHandle]
    );

    // Set new default
    dbUpdate(
        "UPDATE tblOrgShortDomains SET isDefault = 1 WHERE shortDomainUID = ?",
        'i',
        [$shortDomainUID]
    );

    $currentUser = getCurrentUser();
    logActivity('set_default_short_domain', 'success', 200, [
        'userUID' => $currentUser['userUID'],
        'logData' => ['orgHandle' => $orgHandle, 'shortDomain' => $domain['shortDomain']],
    ]);

    return ['success' => true];
}

/**
 * Get all short domains for an organisation.
 *
 * @param  string $orgHandle
 * @return array
 */
function getOrgShortDomains(string $orgHandle): array
{
    $rows = dbSelect(
        "SELECT * FROM tblOrgShortDomains WHERE orgHandle = ? AND isActive = 1 ORDER BY isDefault DESC, createdAt ASC",
        's',
        [$orgHandle]
    );

    return $rows ?: [];
}

// ============================================================================
// 🔧 Internal Helpers
// ============================================================================

/**
 * Validate an organisation handle/slug.
 *
 * @param  string      $handle
 * @return string|null  Error message, or null if valid
 */
function _g2ml_validateOrgHandle(string $handle): ?string
{
    $minLen = (int) getSetting('org.handle_min_length', '3');
    $maxLen = (int) getSetting('org.handle_max_length', '50');

    if (strlen($handle) < $minLen)
    {
        return "Handle must be at least {$minLen} characters.";
    }

    if (strlen($handle) > $maxLen)
    {
        return "Handle must be no more than {$maxLen} characters.";
    }

    // Lowercase alphanumeric + hyphens, must start and end with alphanumeric
    if (!preg_match('/^[a-z0-9][a-z0-9-]*[a-z0-9]$/', $handle) && strlen($handle) >= 2)
    {
        return 'Handle must contain only lowercase letters, numbers, and hyphens, and must start and end with a letter or number.';
    }

    if (strlen($handle) === 1 && !preg_match('/^[a-z0-9]$/', $handle))
    {
        return 'Handle must contain only lowercase letters and numbers.';
    }

    // Check reserved words
    $reserved = array_map('trim', explode(',', getSetting('org.reserved_handles', 'admin,api,app,default,system,support,help,www,mail,ftp,test,demo,null,undefined')));
    if (in_array($handle, $reserved, true))
    {
        return 'This handle is reserved. Please choose another.';
    }

    // Disallow [default] handle
    if ($handle === '[default]')
    {
        return 'This handle is reserved.';
    }

    return null;
}
