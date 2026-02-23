<?php
/**
 * ============================================================================
 * 🔒 Go2My.Link — Delete Your Account (Admin Dashboard)
 * ============================================================================
 *
 * Account deletion request page. Allows the user to request permanent
 * deletion of their account and all associated data, as provided under
 * GDPR Article 17 (Right to Erasure) and CCPA (Right to Delete).
 *
 * Deletion is not immediate — a grace period allows cancellation. After
 * the grace period, data is anonymised and the account is deactivated.
 *
 * @package    Go2My.Link
 * @subpackage AdminDashboard
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

requireAuth();

$pageTitle = function_exists('__') ? __('delete.title') : 'Delete Your Account';
$pageDesc  = function_exists('__') ? __('delete.description') : 'Request permanent deletion of your account.';

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

// ============================================================================
// Handle cancellation of a pending deletion request (GET ?cancel=requestUID)
// ============================================================================

$actionSuccess = '';
$actionError   = '';

if (isset($_GET['cancel']) && (int) $_GET['cancel'] > 0)
{
    $cancelRequestUID = (int) $_GET['cancel'];

    // Verify the request belongs to this user and is still pending
    $cancelRequest = dbSelectOne(
        "SELECT requestUID, status FROM tblDataDeletionRequests
         WHERE requestUID = ? AND userUID = ? AND requestType = 'deletion' LIMIT 1",
        'ii',
        [$cancelRequestUID, $userUID]
    );

    if ($cancelRequest === null || $cancelRequest === false)
    {
        $actionError = function_exists('__')
            ? __('delete.error_not_found')
            : 'Deletion request not found.';
    }
    elseif ($cancelRequest['status'] !== 'pending')
    {
        $actionError = function_exists('__')
            ? __('delete.error_not_cancellable')
            : 'This request can no longer be cancelled.';
    }
    else
    {
        // Cancel the request by setting status to 'rejected'
        $updateResult = dbUpdate(
            "UPDATE tblDataDeletionRequests SET status = 'rejected', processedAt = NOW() WHERE requestUID = ?",
            'i',
            [$cancelRequestUID]
        );

        if ($updateResult !== false)
        {
            $actionSuccess = function_exists('__')
                ? __('delete.cancel_success')
                : 'Deletion request cancelled. Your account will not be deleted.';

            if (function_exists('logActivity'))
            {
                logActivity('data_deletion_cancelled', 'success', 200, [
                    'userUID' => $userUID,
                    'logData' => ['requestUID' => $cancelRequestUID],
                ]);
            }
        }
        else
        {
            $actionError = function_exists('__')
                ? __('delete.error_cancel_failed')
                : 'Failed to cancel the deletion request. Please try again.';
        }
    }
}

// ============================================================================
// Handle deletion request (POST)
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'account_delete'))
    {
        $actionError = function_exists('__')
            ? __('delete.error_csrf')
            : 'Session expired. Please try again.';
    }
    else
    {
        $password = $_POST['confirm_password'] ?? '';
        $reason   = trim(g2ml_sanitiseInput($_POST['delete_reason'] ?? ''));

        // Validate password is provided
        if ($password === '')
        {
            $actionError = function_exists('__')
                ? __('delete.error_password_required')
                : 'Please enter your password to confirm account deletion.';
        }
        else
        {
            // Fetch the user's password hash for verification
            $userData = dbSelectOne(
                "SELECT passwordHash FROM tblUsers WHERE userUID = ? LIMIT 1",
                'i',
                [$userUID]
            );

            if ($userData === null || $userData === false)
            {
                $actionError = function_exists('__')
                    ? __('delete.error_user_not_found')
                    : 'Unable to verify your identity. Please try again.';
            }
            elseif (!g2ml_verifyPassword($password, $userData['passwordHash']))
            {
                $actionError = function_exists('__')
                    ? __('delete.error_wrong_password')
                    : 'Incorrect password. Please try again.';

                if (function_exists('logActivity'))
                {
                    logActivity('data_deletion_failed', 'wrong_password', 401, [
                        'userUID' => $userUID,
                    ]);
                }
            }
            else
            {
                // Password verified — create the deletion request
                $result = g2ml_requestDataDeletion($userUID, $reason !== '' ? $reason : null);

                if ($result['success'])
                {
                    $graceDays = $result['graceDays'] ?? 30;

                    $actionSuccess = function_exists('__')
                        ? sprintf(__('delete.success_message'), $graceDays)
                        : 'Your account deletion request has been submitted. You have '
                            . $graceDays . ' days to cancel before your data is permanently removed.';
                }
                else
                {
                    $actionError = g2ml_sanitiseOutput($result['error'] ?? (function_exists('__')
                        ? __('delete.error_generic')
                        : 'Failed to submit deletion request. Please try again later.'));
                }
            }
        }
    }
}

// ============================================================================
// Check for existing pending deletion request
// ============================================================================

$allRequests       = function_exists('g2ml_getUserDataRequests') ? g2ml_getUserDataRequests($userUID) : [];
$pendingDeletion   = null;
$graceDaysSetting  = function_exists('getSetting') ? (int) getSetting('compliance.data_deletion_grace_days', 30) : 30;

foreach ($allRequests as $req)
{
    if (($req['requestType'] ?? '') === 'deletion' && ($req['status'] ?? '') === 'pending')
    {
        $pendingDeletion = $req;
        break;
    }
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="delete-heading">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/privacy"><?php echo function_exists('__') ? __('delete.breadcrumb_privacy') : 'Privacy & Data'; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo function_exists('__') ? __('delete.breadcrumb_delete') : 'Delete Account'; ?></li>
            </ol>
        </nav>

        <h1 id="delete-heading" class="h2 mb-4 text-danger">
            <i class="fas fa-trash-alt" aria-hidden="true"></i>
            <?php echo function_exists('__') ? __('delete.heading') : 'Delete Your Account'; ?>
        </h1>

        <!-- Alerts -->
        <?php if ($actionSuccess !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionSuccess); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo function_exists('__') ? __('delete.close') : 'Close'; ?>"></button>
        </div>
        <?php endif; ?>

        <?php if ($actionError !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo function_exists('__') ? __('delete.close') : 'Close'; ?>"></button>
        </div>
        <?php endif; ?>

        <?php if ($pendingDeletion !== null): ?>
        <!-- ============================================================== -->
        <!-- Pending Deletion Status                                         -->
        <!-- ============================================================== -->
        <div class="card shadow-sm border-warning mb-4">
            <div class="card-header bg-warning text-dark">
                <h2 class="h5 mb-0">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('delete.pending_heading') : 'Deletion Request Pending'; ?>
                </h2>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <?php echo function_exists('__')
                        ? __('delete.pending_message')
                        : 'Your account is scheduled for deletion. During the grace period, you can cancel this request and keep your account.'; ?>
                </p>
                <ul class="mb-3">
                    <li>
                        <strong><?php echo function_exists('__') ? __('delete.requested_on') : 'Requested on'; ?>:</strong>
                        <?php if (!empty($pendingDeletion['createdAt'])): ?>
                        <time datetime="<?php echo g2ml_sanitiseOutput($pendingDeletion['createdAt']); ?>">
                            <?php echo date('j M Y, H:i', strtotime($pendingDeletion['createdAt'])); ?>
                        </time>
                        <?php endif; ?>
                    </li>
                    <li>
                        <strong><?php echo function_exists('__') ? __('delete.grace_period') : 'Grace period'; ?>:</strong>
                        <?php echo $graceDaysSetting; ?> <?php echo function_exists('__') ? __('delete.days') : 'days'; ?>
                    </li>
                </ul>

                <a href="/privacy/delete?cancel=<?php echo (int) $pendingDeletion['requestUID']; ?>"
                   class="btn btn-warning"
                   onclick="return confirm('<?php echo function_exists('__') ? __('delete.cancel_confirm') : 'Cancel your deletion request and keep your account?'; ?>');">
                    <i class="fas fa-undo" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('delete.cancel_button') : 'Cancel Deletion Request'; ?>
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- ============================================================== -->
        <!-- Warning Card                                                    -->
        <!-- ============================================================== -->
        <div class="card shadow-sm border-danger mb-4">
            <div class="card-header bg-danger text-white">
                <h2 class="h5 mb-0">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('delete.warning_heading') : 'Warning: This Action is Permanent'; ?>
                </h2>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <?php echo function_exists('__')
                        ? __('delete.warning_intro')
                        : 'Deleting your account will result in the following:'; ?>
                </p>
                <ul class="mb-3">
                    <li>
                        <i class="fas fa-user-slash text-danger" aria-hidden="true"></i>
                        <?php echo function_exists('__')
                            ? __('delete.warning_profile')
                            : 'Your profile and personal information will be permanently removed.'; ?>
                    </li>
                    <li>
                        <i class="fas fa-unlink text-danger" aria-hidden="true"></i>
                        <?php echo function_exists('__')
                            ? __('delete.warning_links')
                            : 'All your short links will be deactivated and will no longer redirect.'; ?>
                    </li>
                    <li>
                        <i class="fas fa-chart-line text-danger" aria-hidden="true"></i>
                        <?php echo function_exists('__')
                            ? __('delete.warning_analytics')
                            : 'Click analytics and usage data associated with your account will be anonymised.'; ?>
                    </li>
                    <li>
                        <i class="fas fa-sign-out-alt text-danger" aria-hidden="true"></i>
                        <?php echo function_exists('__')
                            ? __('delete.warning_sessions')
                            : 'All active sessions will be terminated across all devices.'; ?>
                    </li>
                </ul>

                <div class="alert alert-info mb-0" role="note">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <strong><?php echo function_exists('__') ? __('delete.grace_info_title') : 'Grace Period'; ?>:</strong>
                    <?php echo function_exists('__')
                        ? sprintf(__('delete.grace_info_message'), $graceDaysSetting)
                        : 'After submitting your request, you have ' . $graceDaysSetting
                            . ' days to change your mind. During this period, you can cancel the request from this page. After the grace period, deletion is irreversible.'; ?>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- Deletion Request Form                                           -->
        <!-- ============================================================== -->
        <div class="card shadow-sm border-danger mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-key" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('delete.confirm_heading') : 'Confirm Account Deletion'; ?>
                </h2>
            </div>
            <div class="card-body">
                <p class="text-body-secondary mb-3">
                    <?php echo function_exists('__')
                        ? __('delete.confirm_intro')
                        : 'To proceed, please enter your password to confirm your identity.'; ?>
                </p>

                <form action="/privacy/delete" method="POST" novalidate
                      onsubmit="return confirm('<?php echo function_exists('__') ? __('delete.submit_confirm') : 'Are you sure you want to request account deletion? You can cancel within the grace period.'; ?>');">
                    <?php echo g2ml_csrfField('account_delete'); ?>

                    <!-- Password Confirmation -->
                    <div class="mb-3">
                        <label for="confirm-password" class="form-label">
                            <?php echo function_exists('__') ? __('delete.password_label') : 'Your Password'; ?>
                            <span class="text-danger" aria-hidden="true">*</span>
                            <span class="visually-hidden">(<?php echo function_exists('__') ? __('common.required') : 'required'; ?>)</span>
                        </label>
                        <input type="password" class="form-control" id="confirm-password"
                               name="confirm_password" required aria-required="true"
                               autocomplete="current-password"
                               aria-describedby="password-help"
                               placeholder="<?php echo function_exists('__') ? __('delete.password_placeholder') : 'Enter your current password'; ?>">
                        <div id="password-help" class="form-text">
                            <?php echo function_exists('__')
                                ? __('delete.password_help')
                                : 'Required to verify your identity before processing the deletion request.'; ?>
                        </div>
                    </div>

                    <!-- Optional Reason -->
                    <div class="mb-4">
                        <label for="delete-reason" class="form-label">
                            <?php echo function_exists('__') ? __('delete.reason_label') : 'Reason for Leaving'; ?>
                            <span class="text-body-secondary">(<?php echo function_exists('__') ? __('delete.optional') : 'optional'; ?>)</span>
                        </label>
                        <textarea class="form-control" id="delete-reason" name="delete_reason"
                                  rows="3" maxlength="1000"
                                  aria-describedby="reason-help"
                                  placeholder="<?php echo function_exists('__') ? __('delete.reason_placeholder') : 'Help us improve by sharing why you\'re leaving...'; ?>"></textarea>
                        <div id="reason-help" class="form-text">
                            <?php echo function_exists('__')
                                ? __('delete.reason_help')
                                : 'Your feedback is anonymous and helps us improve our service.'; ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('delete.submit_button') : 'Request Account Deletion'; ?>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <!-- Back link -->
        <div class="mt-4">
            <a href="/privacy" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <?php echo function_exists('__') ? __('delete.back_privacy') : 'Back to Privacy & Data'; ?>
            </a>
        </div>
    </div>
</section>
