<?php
/**
 * ============================================================================
 * 📧 Go2My.Link — Accept Organisation Invitation (Public Page)
 * ============================================================================
 *
 * Handles the invitation acceptance flow:
 *   1. Validate token from URL
 *   2. If not logged in → redirect to login with return URL
 *   3. If logged in but already in a non-default org → show error
 *   4. If valid → call acceptInvitation(), show success
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.6.0
 * @since      Phase 5
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('invite.page_title') : 'Accept Invitation';
$pageDesc  = function_exists('__') ? __('invite.page_description') : 'Join an organisation on Go2My.Link.';

$token       = trim($_GET['token'] ?? '');
$isLoggedIn  = isset($_SESSION['user_uid']) && $_SESSION['user_uid'] > 0;
$error       = '';
$success     = false;
$orgName     = '';

// ============================================================================
// Validate token presence
// ============================================================================

if ($token === '')
{
    $error = 'No invitation token provided. Please check the link in your email.';
}

// ============================================================================
// If not logged in, redirect to login with return URL
// ============================================================================

if ($error === '' && !$isLoggedIn)
{
    $returnURL = '/invite?token=' . urlencode($token);
    header('Location: /login?redirect=' . urlencode($returnURL));
    exit;
}

// ============================================================================
// Check if user is already in a non-default organisation
// ============================================================================

if ($error === '' && $isLoggedIn)
{
    $currentUser = getCurrentUser();

    if ($currentUser === null)
    {
        $error = 'Unable to load your account. Please log in again.';
    }
    elseif ($currentUser['orgHandle'] !== '[default]')
    {
        $error = 'You are already a member of an organisation ('
               . g2ml_sanitiseOutput($currentUser['orgHandle'])
               . '). You must leave your current organisation before joining another.';
    }
}

// ============================================================================
// Attempt to accept the invitation
// ============================================================================

if ($error === '' && $isLoggedIn)
{
    $result = acceptInvitation($token);

    if ($result['success'])
    {
        $success = true;
        $orgName = $result['orgName'] ?? '';
    }
    else
    {
        $error = $result['error'] ?? 'Unable to accept invitation. The link may have expired or already been used.';
    }
}
?>

<section class="py-5" aria-labelledby="invite-heading">
    <div class="container" style="max-width:600px;">

        <?php if ($success) { ?>
        <!-- ================================================================ -->
        <!-- Success State                                                     -->
        <!-- ================================================================ -->
        <div class="card shadow-sm border-success">
            <div class="card-body text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3" aria-hidden="true"></i>
                <h1 id="invite-heading" class="h3 mb-3">You've Joined <?php echo g2ml_sanitiseOutput($orgName); ?>!</h1>
                <p class="text-body-secondary mb-4">
                    Welcome to the team. You can now access organisation resources and start creating short links.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="https://admin.go2my.link/org" class="btn btn-primary">
                        <i class="fas fa-building" aria-hidden="true"></i> Go to Organisation
                    </a>
                    <a href="https://admin.go2my.link/" class="btn btn-outline-secondary">
                        <i class="fas fa-tachometer-alt" aria-hidden="true"></i> Dashboard
                    </a>
                </div>
            </div>
        </div>

        <?php } else { ?>
        <!-- ================================================================ -->
        <!-- Error State                                                       -->
        <!-- ================================================================ -->
        <div class="card shadow-sm">
            <div class="card-body text-center py-5">
                <i class="fas fa-exclamation-triangle fa-3x text-warning mb-3" aria-hidden="true"></i>
                <h1 id="invite-heading" class="h3 mb-3">Invitation Problem</h1>
                <div class="alert alert-danger text-start" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo g2ml_sanitiseOutput($error); ?>
                </div>
                <p class="text-body-secondary mb-4">
                    If you believe this is an error, please contact the organisation administrator
                    or ask them to resend the invitation.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="/" class="btn btn-primary">
                        <i class="fas fa-home" aria-hidden="true"></i> Go Home
                    </a>
                    <?php if (!$isLoggedIn) { ?>
                    <a href="/login" class="btn btn-outline-secondary">
                        <i class="fas fa-sign-in-alt" aria-hidden="true"></i> Log In
                    </a>
                    <?php } ?>
                </div>
            </div>
        </div>
        <?php } ?>

    </div>
</section>
