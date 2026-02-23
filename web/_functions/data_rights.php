<?php
/**
 * ============================================================================
 * 📋 Go2My.Link — Data Subject Rights Functions
 * ============================================================================
 *
 * Implements GDPR/CCPA data subject rights:
 *   - Data export (Article 20 / CCPA right to know)
 *   - Data deletion (Article 17 / CCPA right to delete)
 *   - Data anonymisation
 *   - Consent history
 *
 * Dependencies: db_connect.php, db_query.php, settings.php, email.php,
 *               security.php, auth.php
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.7.0
 * @since      Phase 6
 *
 * 📖 References:
 *     - GDPR Art 17: https://gdpr-info.eu/art-17-gdpr/
 *     - GDPR Art 20: https://gdpr-info.eu/art-20-gdpr/
 *     - CCPA:        https://oag.ca.gov/privacy/ccpa
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
// 📦 Request Data Export
// ============================================================================

/**
 * Gather all user data and create a downloadable JSON export file.
 *
 * Collects data from: tblUsers, tblShortURLs, tblConsentRecords,
 * tblUserSessions, tblActivityLog (user's own entries).
 *
 * @param  int   $userUID  The user requesting the export
 * @return array           ['success' => bool, 'requestUID' => int|null, 'error' => string|null]
 */
function g2ml_requestDataExport(int $userUID): array
{
    $db = getDB();

    if ($db === null)
    {
        return ['success' => false, 'error' => 'Database unavailable'];
    }

    // Check for pending/processing export request
    $sql  = "SELECT requestUID FROM tblDataDeletionRequests
             WHERE userUID = ? AND requestType = 'export'
               AND status IN ('pending', 'processing')
             LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $userUID);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing !== null)
    {
        return ['success' => false, 'error' => 'An export request is already in progress'];
    }

    try
    {
        // Gather user profile data
        $userData = [];

        $sql  = "SELECT userUID, email, firstName, lastName, displayName, timezone,
                        role, isActive, emailVerified, createdAt, updatedAt
                 FROM tblUsers WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $userData['profile'] = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        // Gather short URLs
        $sql  = "SELECT shortCode, destinationURL, title, isActive, clickCount,
                        createdAt, updatedAt, expiresAt
                 FROM tblShortURLs WHERE createdByUserUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $userData['short_urls'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Gather consent records
        $sql  = "SELECT consentType, consentGiven, consentMethod, jurisdiction,
                        consentVersion, createdAt, expiresAt
                 FROM tblConsentRecords WHERE userUID = ? ORDER BY createdAt DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $userData['consent_records'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Gather sessions (exclude tokens for security)
        $sql  = "SELECT sessionUID, deviceType, browserName, osName, ipAddress,
                        isActive, createdAt, lastActivityAt, expiresAt
                 FROM tblUserSessions WHERE userUID = ? ORDER BY createdAt DESC";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $userData['sessions'] = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        // Write JSON export
        $exportDir = G2ML_UPLOADS . DIRECTORY_SEPARATOR . 'exports';

        if (!is_dir($exportDir))
        {
            mkdir($exportDir, 0750, true);
        }

        $filename = 'export_' . $userUID . '_' . date('Ymd_His') . '.json';
        $filepath = $exportDir . DIRECTORY_SEPARATOR . $filename;

        $jsonContent = json_encode([
            'export_date'    => date('Y-m-d\TH:i:s\Z'),
            'export_version' => '1.0',
            'service'        => 'Go2My.Link',
            'user_uid'       => $userUID,
            'data'           => $userData,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        file_put_contents($filepath, $jsonContent);

        // Calculate expiry
        $expiryHours = function_exists('getSetting')
                       ? (int) getSetting('compliance.data_export_expiry_hours', 48) : 48;
        $expiresAt   = date('Y-m-d H:i:s', strtotime("+{$expiryHours} hours"));

        // Create request record
        $sql = "INSERT INTO tblDataDeletionRequests
                (userUID, requestType, status, exportFilePath, exportExpiresAt)
                VALUES (?, 'export', 'completed', ?, ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('iss', $userUID, $filepath, $expiresAt);
        $stmt->execute();
        $requestUID = $stmt->insert_id;
        $stmt->close();

        // Update processedAt
        $sql = "UPDATE tblDataDeletionRequests SET processedAt = NOW(), status = 'completed'
                WHERE requestUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $requestUID);
        $stmt->execute();
        $stmt->close();

        // Send email notification
        if (function_exists('sendEmail') && $userData['profile'] !== null)
        {
            $email = $userData['profile']['email'];
            sendEmail($email, 'Your Data Export is Ready', 'data_export_ready', [
                'displayName' => $userData['profile']['displayName'] ?? $userData['profile']['firstName'],
                'downloadURL' => 'https://admin.go2my.link/privacy/export?download=' . $requestUID,
                'expiryHours' => $expiryHours,
            ]);
        }

        if (function_exists('logActivity'))
        {
            logActivity('data_export_requested', 'success', null, [
                'userUID' => $userUID,
                'logData' => ['requestUID' => $requestUID],
            ]);
        }

        return ['success' => true, 'requestUID' => $requestUID];
    }
    catch (\Throwable $e)
    {
        error_log('[Go2My.Link] ERROR: g2ml_requestDataExport failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Export failed. Please try again later.'];
    }
}

// ============================================================================
// 🗑️ Request Data Deletion
// ============================================================================

/**
 * Create a pending data deletion request with a grace period.
 *
 * The deletion is NOT executed immediately — it enters a grace period
 * during which the user can cancel. After the grace period, an admin
 * (or automated process) executes the deletion.
 *
 * @param  int         $userUID  The user requesting deletion
 * @param  string|null $reason   Optional reason for the request
 * @return array                 ['success' => bool, 'requestUID' => int|null, 'graceDays' => int, 'error' => string|null]
 */
function g2ml_requestDataDeletion(int $userUID, ?string $reason = null): array
{
    $db = getDB();

    if ($db === null)
    {
        return ['success' => false, 'error' => 'Database unavailable'];
    }

    // Check for existing pending deletion request
    $sql  = "SELECT requestUID FROM tblDataDeletionRequests
             WHERE userUID = ? AND requestType = 'deletion'
               AND status IN ('pending', 'processing')
             LIMIT 1";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $userUID);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($existing !== null)
    {
        return ['success' => false, 'error' => 'A deletion request is already pending'];
    }

    $graceDays = function_exists('getSetting')
                 ? (int) getSetting('compliance.data_deletion_grace_days', 30) : 30;

    try
    {
        $sql = "INSERT INTO tblDataDeletionRequests
                (userUID, requestType, status, requestReason)
                VALUES (?, 'deletion', 'pending', ?)";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('is', $userUID, $reason);
        $stmt->execute();
        $requestUID = $stmt->insert_id;
        $stmt->close();

        // Send confirmation email
        if (function_exists('sendEmail') && function_exists('getCurrentUser'))
        {
            $user = getCurrentUser();

            if ($user !== null)
            {
                sendEmail($user['email'], 'Data Deletion Request Received', 'data_deletion_requested', [
                    'displayName' => $user['displayName'] ?? $user['firstName'],
                    'graceDays'   => $graceDays,
                    'cancelURL'   => 'https://admin.go2my.link/privacy/delete?cancel=' . $requestUID,
                ]);
            }
        }

        if (function_exists('logActivity'))
        {
            logActivity('data_deletion_requested', 'success', null, [
                'userUID' => $userUID,
                'logData' => ['requestUID' => $requestUID, 'graceDays' => $graceDays],
            ]);
        }

        return ['success' => true, 'requestUID' => $requestUID, 'graceDays' => $graceDays];
    }
    catch (\Throwable $e)
    {
        error_log('[Go2My.Link] ERROR: g2ml_requestDataDeletion failed: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Request failed. Please try again later.'];
    }
}

// ============================================================================
// 🔒 Anonymise User Data
// ============================================================================

/**
 * Replace all PII with anonymised placeholders across all tables.
 *
 * This is a destructive, irreversible operation. Used after grace period
 * for deletion requests. Wraps all updates in a transaction.
 *
 * @param  int  $userUID  The user to anonymise
 * @return bool           true if anonymisation completed
 */
function g2ml_anonymiseUserData(int $userUID): bool
{
    $db = getDB();

    if ($db === null)
    {
        return false;
    }

    $placeholder = '[DELETED]';
    $anonEmail   = 'deleted_' . $userUID . '@anonymised.go2my.link';

    try
    {
        $db->begin_transaction();

        // Anonymise user profile
        $sql = "UPDATE tblUsers SET
                    email = ?, firstName = ?, lastName = ?, displayName = ?,
                    passwordHash = '', avatarURL = NULL, isActive = 0,
                    emailVerified = 0, updatedAt = NOW()
                WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('ssssi', $anonEmail, $placeholder, $placeholder, $placeholder, $userUID);
        $stmt->execute();
        $stmt->close();

        // Revoke all sessions
        $sql  = "UPDATE tblUserSessions SET isActive = 0 WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $stmt->close();

        // Anonymise IP addresses in activity log
        $sql  = "UPDATE tblActivityLog SET ipAddress = '0.0.0.0' WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $stmt->close();

        // Anonymise consent records (keep structure for legal compliance)
        $sql  = "UPDATE tblConsentRecords SET ipAddress = '0.0.0.0', userAgent = NULL WHERE userUID = ?";
        $stmt = $db->prepare($sql);
        $stmt->bind_param('i', $userUID);
        $stmt->execute();
        $stmt->close();

        $db->commit();

        return true;
    }
    catch (\Throwable $e)
    {
        $db->rollback();
        error_log('[Go2My.Link] ERROR: g2ml_anonymiseUserData failed: ' . $e->getMessage());
        return false;
    }
}

// ============================================================================
// ⚙️ Process Data Deletion (Admin Action)
// ============================================================================

/**
 * Execute a pending data deletion request (after grace period).
 *
 * @param  int  $requestUID         The deletion request to process
 * @param  int  $processedByUserUID Admin user processing the request
 * @return bool                     true if processed successfully
 */
function g2ml_processDataDeletion(int $requestUID, int $processedByUserUID): bool
{
    $db = getDB();

    if ($db === null)
    {
        return false;
    }

    // Get request details
    $sql  = "SELECT userUID, requestType, status FROM tblDataDeletionRequests WHERE requestUID = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $requestUID);
    $stmt->execute();
    $request = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($request === null || $request['status'] !== 'pending')
    {
        return false;
    }

    // Anonymise the user's data
    $success = g2ml_anonymiseUserData($request['userUID']);

    // Update request status
    $newStatus = $success ? 'completed' : 'rejected';
    $sql  = "UPDATE tblDataDeletionRequests
             SET status = ?, processedByUserUID = ?, processedAt = NOW()
             WHERE requestUID = ?";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('sii', $newStatus, $processedByUserUID, $requestUID);
    $stmt->execute();
    $stmt->close();

    return $success;
}

// ============================================================================
// 📜 Get Consent History
// ============================================================================

/**
 * Retrieve the full consent history for a user.
 *
 * @param  int   $userUID  The user to query
 * @return array           Array of consent records sorted by date desc
 */
function g2ml_getConsentHistory(int $userUID): array
{
    $db = getDB();

    if ($db === null)
    {
        return [];
    }

    $sql  = "SELECT consentUID, consentType, consentGiven, consentMethod,
                    jurisdiction, consentVersion, createdAt, expiresAt
             FROM tblConsentRecords WHERE userUID = ?
             ORDER BY createdAt DESC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $userUID);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $result;
}

// ============================================================================
// 📋 Get User Data Requests
// ============================================================================

/**
 * Retrieve all data subject requests for a user (exports, deletions, etc.).
 *
 * @param  int   $userUID  The user to query
 * @return array           Array of request records sorted by date desc
 */
function g2ml_getUserDataRequests(int $userUID): array
{
    $db = getDB();

    if ($db === null)
    {
        return [];
    }

    $sql  = "SELECT requestUID, requestType, status, requestReason,
                    exportFilePath, exportExpiresAt, createdAt, processedAt
             FROM tblDataDeletionRequests WHERE userUID = ?
             ORDER BY createdAt DESC";
    $stmt = $db->prepare($sql);
    $stmt->bind_param('i', $userUID);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();

    return $result;
}
