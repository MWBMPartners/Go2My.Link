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

if (function_exists('__')) {
    $pageTitle = __('delete.title');
} else {
    $pageTitle = 'Delete Your Account';
}
if (function_exists('__')) {
    $pageDesc = __('delete.description');
} else {
    $pageDesc = 'Request permanent deletion of your account.';
}

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
        if (function_exists('__')) {
            $actionError = __('delete.error_not_found');
        } else {
            $actionError = 'Deletion request not found.';
        }
    }
    elseif ($cancelRequest['status'] !== 'pending')
    {
        if (function_exists('__')) {
            $actionError = __('delete.error_not_cancellable');
        } else {
            $actionError = 'This request can no longer be cancelled.';
        }
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
            if (function_exists('__')) {
                $actionSuccess = __('delete.cancel_success');
            } else {
                $actionSuccess = 'Deletion request cancelled. Your account will not be deleted.';
            }

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
            if (function_exists('__')) {
                $actionError = __('delete.error_cancel_failed');
            } else {
                $actionError = 'Failed to cancel the deletion request. Please try again.';
            }
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
        if (function_exists('__')) {
            $actionError = __('delete.error_csrf');
        } else {
            $actionError = 'Session expired. Please try again.';
        }
    }
    else
    {
        $password = $_POST['confirm_password'] ?? '';
        $reason   = trim(g2ml_sanitiseInput($_POST['delete_reason'] ?? ''));

        // Validate password is provided
        if ($password === '')
        {
            if (function_exists('__')) {
                $actionError = __('delete.error_password_required');
            } else {
                $actionError = 'Please enter your password to confirm account deletion.';
            }
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
                if (function_exists('__')) {
                    $actionError = __('delete.error_user_not_found');
                } else {
                    $actionError = 'Unable to verify your identity. Please try again.';
                }
            }
            elseif (!g2ml_verifyPassword($password, $userData['passwordHash']))
            {
                if (function_exists('__')) {
                    $actionError = __('delete.error_wrong_password');
                } else {
                    $actionError = 'Incorrect password. Please try again.';
                }

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
                if ($reason !== '') {
                    $reasonParam = $reason;
                } else {
                    $reasonParam = null;
                }
                $result = g2ml_requestDataDeletion($userUID, $reasonParam);

                if ($result['success'])
                {
                    $graceDays = $result['graceDays'] ?? 30;

                    if (function_exists('__')) {
                        $actionSuccess = sprintf(__('delete.success_message'), $graceDays);
                    } else {
                        $actionSuccess = 'Your account deletion request has been submitted. You have ' . $graceDays . ' days to cancel before your data is permanently removed.';
                    }
                }
                else
                {
                    if (function_exists('__')) {
                        $errorMsg = __('delete.error_generic');
                    } else {
                        $errorMsg = 'Failed to submit deletion request. Please try again later.';
                    }
                    $actionError = g2ml_sanitiseOutput($result['error'] ?? $errorMsg);
                }
            }
        }
    }
}

// ============================================================================
// Check for existing pending deletion request
// ============================================================================

if (function_exists('g2ml_getUserDataRequests')) {
    $allRequests = g2ml_getUserDataRequests($userUID);
} else {
    $allRequests = [];
}
$pendingDeletion   = null;
if (function_exists('getSetting')) {
    $graceDaysSetting = (int) getSetting('compliance.data_deletion_grace_days', 30);
} else {
    $graceDaysSetting = 30;
}

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
                <li class="breadcrumb-item"><a href="/privacy"><?php if (function_exists('__')) { echo __('delete.breadcrumb_privacy'); } else { echo 'Privacy & Data'; } ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php if (function_exists('__')) { echo __('delete.breadcrumb_delete'); } else { echo 'Delete Account'; } ?></li>
            </ol>
        </nav>

        <h1 id="delete-heading" class="h2 mb-4 text-danger">
            <i class="fas fa-trash-alt" aria-hidden="true"></i>
            <?php if (function_exists('__')) { echo __('delete.heading'); } else { echo 'Delete Your Account'; } ?>
        </h1>

        <!-- Alerts -->
        <?php if ($actionSuccess !== '') { ?>
        <div class="alert alert-success alert-dismissible fade show" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionSuccess); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php if (function_exists('__')) { echo __('delete.close'); } else { echo 'Close'; } ?>"></button>
        </div>
        <?php } ?>

        <?php if ($actionError !== '') { ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php if (function_exists('__')) { echo __('delete.close'); } else { echo 'Close'; } ?>"></button>
        </div>
        <?php } ?>

        <?php if ($pendingDeletion !== null) { ?>
        <!-- ============================================================== -->
        <!-- Pending Deletion Status                                         -->
        <!-- ============================================================== -->
        <div class="card shadow-sm border-warning mb-4">
            <div class="card-header bg-warning text-dark">
                <h2 class="h5 mb-0">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('delete.pending_heading'); } else { echo 'Deletion Request Pending'; } ?>
                </h2>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <?php if (function_exists('__')) { echo __('delete.pending_message'); } else { echo 'Your account is scheduled for deletion. During the grace period, you can cancel this request and keep your account.'; } ?>
                </p>
                <ul class="mb-3">
                    <li>
                        <strong><?php if (function_exists('__')) { echo __('delete.requested_on'); } else { echo 'Requested on'; } ?>:</strong>
                        <?php if (!empty($pendingDeletion['createdAt'])) { ?>
                        <time datetime="<?php echo g2ml_sanitiseOutput($pendingDeletion['createdAt']); ?>">
                            <?php echo date('j M Y, H:i', strtotime($pendingDeletion['createdAt'])); ?>
                        </time>
                        <?php } ?>
                    </li>
                    <li>
                        <strong><?php if (function_exists('__')) { echo __('delete.grace_period'); } else { echo 'Grace period'; } ?>:</strong>
                        <?php echo $graceDaysSetting; ?> <?php if (function_exists('__')) { echo __('delete.days'); } else { echo 'days'; } ?>
                    </li>
                </ul>

                <a href="/privacy/delete?cancel=<?php echo (int) $pendingDeletion['requestUID']; ?>"
                   class="btn btn-warning"
                   onclick="return confirm('<?php if (function_exists('__')) { echo __('delete.cancel_confirm'); } else { echo 'Cancel your deletion request and keep your account?'; } ?>');">
                    <i class="fas fa-undo" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('delete.cancel_button'); } else { echo 'Cancel Deletion Request'; } ?>
                </a>
            </div>
        </div>

        <?php } else { ?>
        <!-- ============================================================== -->
        <!-- Warning Card                                                    -->
        <!-- ============================================================== -->
        <div class="card shadow-sm border-danger mb-4">
            <div class="card-header bg-danger text-white">
                <h2 class="h5 mb-0">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('delete.warning_heading'); } else { echo 'Warning: This Action is Permanent'; } ?>
                </h2>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    <?php if (function_exists('__')) { echo __('delete.warning_intro'); } else { echo 'Deleting your account will result in the following:'; } ?>
                </p>
                <ul class="mb-3">
                    <li>
                        <i class="fas fa-user-slash text-danger" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('delete.warning_profile'); } else { echo 'Your profile and personal information will be permanently removed.'; } ?>
                    </li>
                    <li>
                        <i class="fas fa-unlink text-danger" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('delete.warning_links'); } else { echo 'All your short links will be deactivated and will no longer redirect.'; } ?>
                    </li>
                    <li>
                        <i class="fas fa-chart-line text-danger" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('delete.warning_analytics'); } else { echo 'Click analytics and usage data associated with your account will be anonymised.'; } ?>
                    </li>
                    <li>
                        <i class="fas fa-sign-out-alt text-danger" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('delete.warning_sessions'); } else { echo 'All active sessions will be terminated across all devices.'; } ?>
                    </li>
                </ul>

                <div class="alert alert-info mb-0" role="note">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <strong><?php if (function_exists('__')) { echo __('delete.grace_info_title'); } else { echo 'Grace Period'; } ?>:</strong>
                    <?php if (function_exists('__')) { echo sprintf(__('delete.grace_info_message'), $graceDaysSetting); } else { echo 'After submitting your request, you have ' . $graceDaysSetting . ' days to change your mind. During this period, you can cancel the request from this page. After the grace period, deletion is irreversible.'; } ?>
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
                    <?php if (function_exists('__')) { echo __('delete.confirm_heading'); } else { echo 'Confirm Account Deletion'; } ?>
                </h2>
            </div>
            <div class="card-body">
                <p class="text-body-secondary mb-3">
                    <?php if (function_exists('__')) { echo __('delete.confirm_intro'); } else { echo 'To proceed, please enter your password to confirm your identity.'; } ?>
                </p>

                <form action="/privacy/delete" method="POST" novalidate
                      onsubmit="return confirm('<?php if (function_exists('__')) { echo __('delete.submit_confirm'); } else { echo 'Are you sure you want to request account deletion? You can cancel within the grace period.'; } ?>');">
                    <?php echo g2ml_csrfField('account_delete'); ?>

                    <!-- Password Confirmation -->
                    <div class="mb-3">
                        <label for="confirm-password" class="form-label">
                            <?php if (function_exists('__')) { echo __('delete.password_label'); } else { echo 'Your Password'; } ?>
                            <span class="text-danger" aria-hidden="true">*</span>
                            <span class="visually-hidden">(<?php if (function_exists('__')) { echo __('common.required'); } else { echo 'required'; } ?>)</span>
                        </label>
                        <input type="password" class="form-control" id="confirm-password"
                               name="confirm_password" required aria-required="true"
                               autocomplete="current-password"
                               aria-describedby="password-help"
                               placeholder="<?php if (function_exists('__')) { echo __('delete.password_placeholder'); } else { echo 'Enter your current password'; } ?>">
                        <div id="password-help" class="form-text">
                            <?php if (function_exists('__')) { echo __('delete.password_help'); } else { echo 'Required to verify your identity before processing the deletion request.'; } ?>
                        </div>
                    </div>

                    <!-- Optional Reason -->
                    <div class="mb-4">
                        <label for="delete-reason" class="form-label">
                            <?php if (function_exists('__')) { echo __('delete.reason_label'); } else { echo 'Reason for Leaving'; } ?>
                            <span class="text-body-secondary">(<?php if (function_exists('__')) { echo __('delete.optional'); } else { echo 'optional'; } ?>)</span>
                        </label>
                        <textarea class="form-control" id="delete-reason" name="delete_reason"
                                  rows="3" maxlength="1000"
                                  aria-describedby="reason-help"
                                  placeholder="<?php if (function_exists('__')) { echo __('delete.reason_placeholder'); } else { echo 'Help us improve by sharing why you\'re leaving...'; } ?>"></textarea>
                        <div id="reason-help" class="form-text">
                            <?php if (function_exists('__')) { echo __('delete.reason_help'); } else { echo 'Your feedback is anonymous and helps us improve our service.'; } ?>
                        </div>
                    </div>

                    <button type="submit" class="btn btn-danger">
                        <i class="fas fa-trash-alt" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('delete.submit_button'); } else { echo 'Request Account Deletion'; } ?>
                    </button>
                </form>
            </div>
        </div>
        <?php } ?>

        <!-- Back link -->
        <div class="mt-4">
            <a href="/privacy" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <?php if (function_exists('__')) { echo __('delete.back_privacy'); } else { echo 'Back to Privacy & Data'; } ?>
            </a>
        </div>
    </div>
</section>
