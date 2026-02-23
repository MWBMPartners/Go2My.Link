<?php
/**
 * ============================================================================
 * 🖥️ Go2My.Link — Active Sessions (Admin Dashboard)
 * ============================================================================
 *
 * Session management page. Lists all active sessions with device info, IP,
 * and last activity. Users can revoke individual sessions or all other sessions.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.5.0
 * @since      Phase 4
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('sessions.title');
} else {
    $pageTitle = 'Active Sessions';
}
if (function_exists('__')) {
    $pageDesc = __('sessions.description');
} else {
    $pageDesc = 'View and manage your active sessions.';
}

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

// ============================================================================
// Handle session revocation (POST)
// ============================================================================

$actionSuccess = '';
$actionError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';
    $action    = $_POST['action'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'sessions_form'))
    {
        $actionError = 'Session expired. Please try again.';
    }
    else
    {
        // --------------------------------------------------------------------
        // Revoke a single session
        // --------------------------------------------------------------------
        if ($action === 'revoke_one')
        {
            $sessionUID = (int) ($_POST['session_uid'] ?? 0);

            if ($sessionUID <= 0)
            {
                $actionError = 'Invalid session.';
            }
            else
            {
                $revoked = revokeSession($sessionUID, $userUID);

                if ($revoked)
                {
                    $actionSuccess = 'Session revoked successfully.';

                    logActivity('revoke_session', 'success', 200, [
                        'userUID' => $userUID,
                        'logData' => ['sessionUID' => $sessionUID],
                    ]);
                }
                else
                {
                    $actionError = 'Failed to revoke session. It may have already expired.';
                }
            }
        }

        // --------------------------------------------------------------------
        // Revoke all other sessions
        // --------------------------------------------------------------------
        elseif ($action === 'revoke_all_others')
        {
            $currentToken = $_SESSION['session_token'] ?? '';

            if ($currentToken === '')
            {
                $actionError = 'Unable to identify current session.';
            }
            else
            {
                $count = revokeAllOtherSessions($userUID, $currentToken);

                if (($count > 0)) {
                    $actionSuccess = $count . ' other session(s) revoked.';
                } else {
                    $actionSuccess = 'No other active sessions to revoke.';
                }

                logActivity('revoke_all_sessions', 'success', 200, [
                    'userUID' => $userUID,
                    'logData' => ['revoked_count' => $count],
                ]);
            }
        }
        else
        {
            $actionError = 'Unknown action.';
        }
    }
}

// ============================================================================
// Fetch active sessions
// ============================================================================

$sessions = listUserSessions($userUID);
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="sessions-heading">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/profile">Profile</a></li>
                <li class="breadcrumb-item active" aria-current="page">Sessions</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 id="sessions-heading" class="h2 mb-0">
                <i class="fas fa-desktop" aria-hidden="true"></i>
                <?php if (function_exists('__')) { echo __('sessions.heading'); } else { echo 'Active Sessions'; } ?>
            </h1>

            <?php if (count($sessions) > 1) { ?>
            <!-- Revoke All Others button -->
            <form action="/profile/sessions" method="POST" class="d-inline"
                  onsubmit="return confirm('Sign out of all other devices? You will remain signed in on this device.');">
                <?php echo g2ml_csrfField('sessions_form'); ?>
                <input type="hidden" name="action" value="revoke_all_others">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                    <i class="fas fa-sign-out-alt" aria-hidden="true"></i> Sign Out All Others
                </button>
            </form>
            <?php } ?>
        </div>

        <p class="text-body-secondary mb-4">
            <?php if (function_exists('__')) { echo __('sessions.intro'); } else { echo 'These are the devices currently signed in to your account. If you see a device you don\'t recognise, revoke it and change your password.'; } ?>
        </p>

        <!-- Alerts -->
        <?php if ($actionSuccess !== '') { ?>
        <div class="alert alert-success" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionSuccess); ?>
        </div>
        <?php } ?>

        <?php if ($actionError !== '') { ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionError); ?>
        </div>
        <?php } ?>

        <!-- ============================================================== -->
        <!-- Sessions List                                                   -->
        <!-- ============================================================== -->

        <?php if (empty($sessions)) { ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle" aria-hidden="true"></i> No active sessions found.
        </div>

        <?php } else { ?>
        <div class="row g-3">
            <?php foreach ($sessions as $session) { ?>
            <div class="col-12">
                <div class="card shadow-sm <?php if ($session['isCurrent']) { echo 'border-primary'; } ?>">
                    <div class="card-body">
                        <div class="row align-items-center">

                            <!-- Device icon + info -->
                            <div class="col-md-6">
                                <div class="d-flex align-items-start">
                                    <div class="me-3 text-body-secondary" style="font-size: 1.5rem;">
                                        <?php
                                        // Choose icon based on device info
                                        $deviceInfo = $session['deviceInfo'] ?? '';
                                        $deviceIcon = 'fa-desktop';

                                        if (stripos($deviceInfo, 'iOS') !== false || stripos($deviceInfo, 'Android') !== false)
                                        {
                                            $deviceIcon = 'fa-mobile-alt';
                                        }
                                        elseif (stripos($deviceInfo, 'iPad') !== false || stripos($deviceInfo, 'Tablet') !== false)
                                        {
                                            $deviceIcon = 'fa-tablet-alt';
                                        }
                                        ?>
                                        <i class="fas <?php echo $deviceIcon; ?>" aria-hidden="true"></i>
                                    </div>
                                    <div>
                                        <h2 class="h6 mb-1">
                                            <?php echo g2ml_sanitiseOutput($deviceInfo ?: 'Unknown device'); ?>
                                            <?php if ($session['isCurrent']) { ?>
                                            <span class="badge bg-primary ms-1">This device</span>
                                            <?php } ?>
                                        </h2>
                                        <p class="text-body-secondary small mb-0">
                                            <i class="fas fa-map-marker-alt fa-fw" aria-hidden="true"></i>
                                            IP: <?php echo g2ml_sanitiseOutput($session['ipMasked']); ?>
                                        </p>
                                    </div>
                                </div>
                            </div>

                            <!-- Activity info -->
                            <div class="col-md-3">
                                <p class="small text-body-secondary mb-1">
                                    <strong>Last active:</strong><br>
                                    <?php
                                    if ($session['lastActivityAt'])
                                    {
                                        $lastActive = strtotime($session['lastActivityAt']);
                                        $diff = time() - $lastActive;

                                        if ($diff < 60)
                                        {
                                            echo 'Just now';
                                        }
                                        elseif ($diff < 3600)
                                        {
                                            echo floor($diff / 60) . ' min ago';
                                        }
                                        elseif ($diff < 86400)
                                        {
                                            echo floor($diff / 3600) . ' hr ago';
                                        }
                                        else
                                        {
                                            echo date('j M Y, H:i', $lastActive);
                                        }
                                    }
                                    else
                                    {
                                        echo 'Unknown';
                                    }
                                    ?>
                                </p>
                                <p class="small text-body-secondary mb-0">
                                    <strong>Signed in:</strong><br>
                                    <?php if ($session['createdAt']) { echo date('j M Y, H:i', strtotime($session['createdAt'])); } else { echo 'Unknown'; } ?>
                                </p>
                            </div>

                            <!-- Actions -->
                            <div class="col-md-3 text-md-end mt-2 mt-md-0">
                                <?php if ($session['isCurrent']) { ?>
                                <span class="text-body-secondary small">
                                    <i class="fas fa-check-circle text-success" aria-hidden="true"></i> Current session
                                </span>
                                <?php } else { ?>
                                <form action="/profile/sessions" method="POST" class="d-inline"
                                      onsubmit="return confirm('Revoke this session? The device will be signed out.');">
                                    <?php echo g2ml_csrfField('sessions_form'); ?>
                                    <input type="hidden" name="action" value="revoke_one">
                                    <input type="hidden" name="session_uid" value="<?php echo (int) $session['sessionUID']; ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-times" aria-hidden="true"></i> Revoke
                                    </button>
                                </form>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php } ?>
        </div>

        <p class="text-body-secondary small mt-3">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            Showing <?php echo count($sessions); ?> active session(s).
            Sessions expire automatically after inactivity.
        </p>
        <?php } ?>

        <!-- Back to profile -->
        <div class="mt-4">
            <a href="/profile" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Profile
            </a>
        </div>
    </div>
</section>
