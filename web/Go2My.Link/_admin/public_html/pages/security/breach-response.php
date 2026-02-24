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
 * 🚨 Go2My.Link — Admin: Breach Response
 * ============================================================================
 *
 * GlobalAdmin-only page to trigger a mass credential reset in the event
 * of a security breach. Invalidates all passwords, revokes all sessions,
 * optionally rotates ENCRYPTION_SALT, and sends notification emails.
 *
 * @package    Go2My.Link
 * @subpackage Admin
 * @version    1.0.0
 * @since      Phase 7
 * ============================================================================
 */

// 🔒 Require GlobalAdmin role
requireAuth('GlobalAdmin');

// 🛡️ Prevent caching of this sensitive page (salt may be displayed)
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

// ============================================================================
// 📝 Page metadata
// ============================================================================
$pageTitle       = 'Breach Response';
$pageDescription = 'Emergency mass credential reset for security incidents.';

// ============================================================================
// 📋 Process form submission
// ============================================================================
$formSuccess = false;
$formError   = '';
$formStats   = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'breach_response')
{
    // Validate CSRF token
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!function_exists('g2ml_validateCSRFToken') || !g2ml_validateCSRFToken($csrfToken, 'breach_response_form'))
    {
        $formError = 'Session expired. Please try again.';
    }
    else
    {
        // Validate confirmation checkbox
        $confirmed = isset($_POST['confirm_breach']);

        if (!$confirmed)
        {
            $formError = 'You must confirm that you understand the consequences of this action.';
        }
        else
        {
            // Get form inputs
            $reason     = trim($_POST['breach_reason'] ?? '');
            $rotateSalt = isset($_POST['rotate_salt']);
            $newSalt    = trim($_POST['new_salt'] ?? '');

            // 🛡️ Enforce length limit on reason (prevent abuse via mass emails)
            if (mb_strlen($reason) > 500)
            {
                $reason = mb_substr($reason, 0, 500);
            }

            // 🛡️ Sanitise reason (strip HTML tags for defense-in-depth)
            if (function_exists('g2ml_sanitiseInput'))
            {
                $reason = g2ml_sanitiseInput($reason);
            }
            else
            {
                $reason = strip_tags($reason);
            }

            if ($reason === '')
            {
                $formError = 'A reason for the breach response is required.';
            }
            else
            {
                // Auto-generate new salt if rotation requested but no salt provided
                if ($rotateSalt && $newSalt === '')
                {
                    $newSalt = bin2hex(random_bytes(32));
                }

                // Validate salt format if provided
                if ($rotateSalt && !preg_match('/^[0-9a-fA-F]{64}$/', $newSalt))
                {
                    $formError = 'The new ENCRYPTION_SALT must be exactly 64 hexadecimal characters.';
                }
                else
                {
                    $currentUser = getCurrentUser();
                    $adminUID    = $currentUser['userUID'];

                    // Execute the breach response
                    $result = g2ml_breachResponse(
                        $adminUID,
                        $reason,
                        $rotateSalt ? $newSalt : null
                    );

                    if ($result['success'])
                    {
                        $formSuccess = true;
                        $formStats   = $result['stats'];

                        // Store the new salt for display (admin must update auth_creds.php)
                        if ($rotateSalt)
                        {
                            $formStats['new_salt'] = $newSalt;
                        }
                    }
                    else
                    {
                        $formError = $result['error'];
                    }
                }
            }
        }
    }
}
?>

<section class="py-4" aria-labelledby="breach-response-heading">
    <div class="container">
        <h1 id="breach-response-heading" class="h2 mb-2">
            <i class="fas fa-shield-alt text-danger me-2" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($pageTitle); ?>
        </h1>
        <p class="text-body-secondary mb-4">
            <?php echo g2ml_sanitiseOutput($pageDescription); ?>
        </p>

        <?php if ($formSuccess && $formStats !== null): ?>
        <!-- ============================================================ -->
        <!-- ✅ Success Result -->
        <!-- ============================================================ -->
        <div class="alert alert-success" role="status">
            <h5 class="alert-heading">
                <i class="fas fa-check-circle me-2" aria-hidden="true"></i>
                Breach Response Completed
            </h5>
            <hr>
            <div class="row">
                <div class="col-md-6">
                    <p class="mb-1"><strong>Passwords invalidated:</strong> <?php echo (int) $formStats['passwords_invalidated']; ?></p>
                    <p class="mb-1"><strong>Sessions revoked:</strong> <?php echo (int) $formStats['sessions_revoked']; ?></p>
                    <p class="mb-1"><strong>Emails sent:</strong> <?php echo (int) $formStats['emails_sent']; ?></p>
                    <p class="mb-1"><strong>Emails failed:</strong> <?php echo (int) $formStats['emails_failed']; ?></p>
                </div>
                <div class="col-md-6">
                    <p class="mb-1"><strong>SALT rotated:</strong> <?php echo $formStats['salt_rotated'] ? 'Yes' : 'No'; ?></p>
                    <p class="mb-1"><strong>Started:</strong> <?php echo g2ml_sanitiseOutput($formStats['started_at']); ?></p>
                    <p class="mb-1"><strong>Completed:</strong> <?php echo g2ml_sanitiseOutput($formStats['completed_at']); ?></p>
                </div>
            </div>

            <?php if (!empty($formStats['new_salt'])): ?>
            <hr>
            <div class="alert alert-warning mb-0">
                <h6 class="alert-heading">
                    <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                    Action Required: Update auth_creds.php
                </h6>
                <p class="mb-1">
                    The ENCRYPTION_SALT has been rotated. You <strong>must</strong> update the
                    <code>ENCRYPTION_SALT</code> constant in your <code>auth_creds.php</code> file
                    to the new value below, or encrypted settings will become unreadable on the
                    next server restart.
                </p>
                <p class="mb-0">
                    <strong>New ENCRYPTION_SALT:</strong>
                    <code class="user-select-all"><?php echo g2ml_sanitiseOutput($formStats['new_salt']); ?></code>
                </p>
            </div>
            <?php endif; ?>
        </div>

        <div class="alert alert-info">
            <i class="fas fa-info-circle me-2" aria-hidden="true"></i>
            <strong>Note:</strong> Your own session has been invalidated. You will need to log in
            again with a new password. A reset email has been sent to your email address.
        </div>

        <?php else: ?>
        <!-- ============================================================ -->
        <!-- 📋 Breach Response Form -->
        <!-- ============================================================ -->

        <?php if ($formError !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle me-2" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($formError); ?>
        </div>
        <?php endif; ?>

        <div class="alert alert-danger border-danger">
            <h5 class="alert-heading">
                <i class="fas fa-exclamation-triangle me-2" aria-hidden="true"></i>
                Warning: Destructive Action
            </h5>
            <p class="mb-1">This action will:</p>
            <ul class="mb-0">
                <li><strong>Invalidate ALL user passwords</strong> — every user will be forced to reset</li>
                <li><strong>Terminate ALL active sessions</strong> — every user will be logged out immediately</li>
                <li><strong>Send mass notification emails</strong> — every active user receives a reset link</li>
                <li>Optionally <strong>rotate the ENCRYPTION_SALT</strong> — requires manual auth_creds.php update</li>
            </ul>
        </div>

        <div class="row">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-danger text-white">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-shield-alt me-2" aria-hidden="true"></i>
                            Trigger Breach Response
                        </h2>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="breach-response-form">
                            <?php if (function_exists('g2ml_csrfField')) { echo g2ml_csrfField('breach_response_form'); } ?>
                            <input type="hidden" name="form_type" value="breach_response">

                            <!-- Reason -->
                            <div class="mb-3">
                                <label for="breach_reason" class="form-label">
                                    <strong>Reason for Breach Response</strong> <span class="text-danger">*</span>
                                </label>
                                <textarea
                                    class="form-control"
                                    id="breach_reason"
                                    name="breach_reason"
                                    rows="3"
                                    required
                                    maxlength="500"
                                    placeholder="e.g., Suspected unauthorised access to user database..."
                                ></textarea>
                                <div class="form-text">
                                    This reason will be included in the notification email sent to all users.
                                </div>
                            </div>

                            <!-- SALT Rotation -->
                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="rotate_salt" name="rotate_salt">
                                    <label class="form-check-label" for="rotate_salt">
                                        Rotate ENCRYPTION_SALT (re-encrypt all sensitive settings)
                                    </label>
                                </div>
                            </div>

                            <div class="mb-3" id="new-salt-group" style="display:none;">
                                <label for="new_salt" class="form-label">
                                    New ENCRYPTION_SALT <small class="text-body-secondary">(leave empty to auto-generate)</small>
                                </label>
                                <input
                                    type="text"
                                    class="form-control font-monospace"
                                    id="new_salt"
                                    name="new_salt"
                                    maxlength="64"
                                    pattern="[0-9a-fA-F]{64}"
                                    placeholder="64 hexadecimal characters (auto-generated if empty)"
                                >
                                <div class="form-text">
                                    Must be exactly 64 hexadecimal characters. After rotation, you must
                                    manually update <code>auth_creds.php</code> with the new value.
                                </div>
                            </div>

                            <hr>

                            <!-- Confirmation -->
                            <div class="mb-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" id="confirm_breach" name="confirm_breach" required>
                                    <label class="form-check-label text-danger fw-bold" for="confirm_breach">
                                        I understand this will invalidate ALL user passwords, terminate ALL sessions,
                                        and send mass notification emails. This action cannot be undone.
                                    </label>
                                </div>
                            </div>

                            <button type="submit" class="btn btn-danger btn-lg" id="submit-btn" disabled>
                                <i class="fas fa-shield-alt me-2" aria-hidden="true"></i>
                                Execute Breach Response
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <script>
        // Toggle SALT input visibility
        document.getElementById('rotate_salt').addEventListener('change', function() {
            document.getElementById('new-salt-group').style.display = this.checked ? 'block' : 'none';
        });
        // Enable submit only when confirmation is checked
        document.getElementById('confirm_breach').addEventListener('change', function() {
            document.getElementById('submit-btn').disabled = !this.checked;
        });
        </script>
        <?php endif; ?>
    </div>
</section>
