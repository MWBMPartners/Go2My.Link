<?php
/**
 * ============================================================================
 * 📧 Go2My.Link — Invite Member (Admin Dashboard)
 * ============================================================================
 *
 * Form to invite a user to join the organisation via email.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.6.0
 * @since      Phase 5
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('org.invite_title');
} else {
    $pageTitle = 'Invite Member';
}
if (function_exists('__')) {
    $pageDesc = __('org.invite_description');
} else {
    $pageDesc = 'Send an invitation to join your organisation.';
}

$currentUser = getCurrentUser();
$orgHandle   = $currentUser['orgHandle'];

if ($orgHandle === '[default]' || !canManageOrg($orgHandle))
{
    echo '<div class="container py-5"><div class="alert alert-danger">';
    echo '<i class="fas fa-lock" aria-hidden="true"></i> ';
    echo 'You do not have permission to invite members.';
    echo '</div></div>';
    return;
}

$org = getOrganisation($orgHandle);

$formSuccess = false;
$formError   = '';

// ============================================================================
// Handle form submission
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'invite_member_form'))
    {
        $formError = 'Session expired. Please try again.';
    }
    else
    {
        $email = strtolower(trim(g2ml_sanitiseInput($_POST['invite_email'] ?? '')));
        $role  = $_POST['invite_role'] ?? 'User';

        if ($email === '')
        {
            $formError = 'Email address is required.';
        }
        else
        {
            $result = inviteMember($orgHandle, $email, $role);

            if ($result['success'])
            {
                $formSuccess = true;
            }
            else
            {
                $formError = $result['error'];
            }
        }
    }
}
?>

<section class="py-4" aria-labelledby="invite-heading">
    <div class="container" style="max-width:600px;">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/org">Organisation</a></li>
                <li class="breadcrumb-item"><a href="/org/members">Members</a></li>
                <li class="breadcrumb-item active" aria-current="page">Invite</li>
            </ol>
        </nav>

        <h1 id="invite-heading" class="h2 mb-4">
            <i class="fas fa-user-plus" aria-hidden="true"></i>
            Invite Member
        </h1>

        <?php if ($formSuccess) { ?>
        <!-- Success -->
        <div class="card shadow-sm border-success">
            <div class="card-body text-center py-5">
                <i class="fas fa-envelope-circle-check fa-3x text-success mb-3" aria-hidden="true"></i>
                <h2 class="h4">Invitation Sent!</h2>
                <p class="text-body-secondary">
                    An invitation email has been sent. They'll receive a link to join
                    <strong><?php echo g2ml_sanitiseOutput($org['orgName']); ?></strong>.
                </p>
                <div class="d-flex justify-content-center gap-2">
                    <a href="/org/members/invite" class="btn btn-primary">
                        <i class="fas fa-user-plus" aria-hidden="true"></i> Invite Another
                    </a>
                    <a href="/org/members" class="btn btn-outline-secondary">
                        <i class="fas fa-users" aria-hidden="true"></i> View Members
                    </a>
                </div>
            </div>
        </div>

        <?php } else { ?>
        <!-- Form -->
        <div class="card shadow-sm">
            <div class="card-body">

                <p class="text-body-secondary mb-4">
                    Send an invitation to join <strong><?php echo g2ml_sanitiseOutput($org['orgName']); ?></strong>.
                    The invitee will receive an email with a link to accept.
                </p>

                <?php if ($formError !== '') { ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo g2ml_sanitiseOutput($formError); ?>
                </div>
                <?php } ?>

                <form action="/org/members/invite" method="POST" novalidate>
                    <?php echo g2ml_csrfField('invite_member_form'); ?>

                    <?php
                    echo formField([
                        'id'           => 'invite-email',
                        'name'         => 'invite_email',
                        'label'        => 'Email Address',
                        'type'         => 'email',
                        'required'     => true,
                        'autocomplete' => 'email',
                        'value'        => g2ml_sanitiseOutput($_POST['invite_email'] ?? ''),
                        'helpText'     => 'The email address of the person you want to invite.',
                    ]);
                    ?>

                    <div class="mb-3">
                        <label for="invite-role" class="form-label">Role</label>
                        <select class="form-select" id="invite-role" name="invite_role">
                            <option value="User" <?php if ((($_POST['invite_role'] ?? 'User') === 'User')) { echo 'selected'; } ?>>
                                User — Can create and manage their own short links
                            </option>
                            <option value="Admin" <?php if ((($_POST['invite_role'] ?? '') === 'Admin')) { echo 'selected'; } ?>>
                                Admin — Can manage organisation settings, members, and domains
                            </option>
                        </select>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-paper-plane" aria-hidden="true"></i> Send Invitation
                        </button>
                        <a href="/org/members" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php } ?>

    </div>
</section>
