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
 * 👥 Go2My.Link — Member Management (Admin Dashboard)
 * ============================================================================
 *
 * List org members, change roles, remove members, and view pending invitations.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.6.0
 * @since      Phase 5
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('org.members_title');
} else {
    $pageTitle = 'Members';
}
if (function_exists('__')) {
    $pageDesc = __('org.members_description');
} else {
    $pageDesc = 'Manage your organisation members.';
}

$currentUser = getCurrentUser();
$orgHandle   = $currentUser['orgHandle'];

if ($orgHandle === '[default]' || !canManageOrg($orgHandle))
{
    echo '<div class="container py-5"><div class="alert alert-danger">';
    echo '<i class="fas fa-lock" aria-hidden="true"></i> ';
    echo 'You do not have permission to manage members.';
    echo '</div></div>';
    return;
}

$org = getOrganisation($orgHandle);
$actionSuccess = '';
$actionError   = '';

// ============================================================================
// Handle POST actions (role change, remove, cancel invitation)
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken  = $_POST['_csrf_token'] ?? '';
    $actionType = $_POST['action_type'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'org_members_form'))
    {
        $actionError = 'Session expired. Please try again.';
    }
    else
    {
        switch ($actionType)
        {
            case 'change_role':
                $targetUID = (int) ($_POST['user_uid'] ?? 0);
                $newRole   = $_POST['new_role'] ?? '';
                $result    = changeMemberRole($orgHandle, $targetUID, $newRole);
                if ($result['success']) { $actionSuccess = 'Member role updated.'; }
                else { $actionError = $result['error']; }
                break;

            case 'remove_member':
                $targetUID = (int) ($_POST['user_uid'] ?? 0);
                $result    = removeMember($orgHandle, $targetUID);
                if ($result['success']) { $actionSuccess = 'Member removed from organisation.'; }
                else { $actionError = $result['error']; }
                break;

            case 'cancel_invitation':
                $invUID = (int) ($_POST['invitation_uid'] ?? 0);
                $result = cancelInvitation($invUID, $orgHandle);
                if ($result['success']) { $actionSuccess = 'Invitation cancelled.'; }
                else { $actionError = $result['error']; }
                break;
        }
    }
}

// Load data
$members     = getOrgMembers($orgHandle);
$invitations = getPendingInvitations($orgHandle);
?>

<section class="py-4" aria-labelledby="members-heading">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/org">Organisation</a></li>
                <li class="breadcrumb-item active" aria-current="page">Members</li>
            </ol>
        </nav>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 id="members-heading" class="h2 mb-0">
                <i class="fas fa-users" aria-hidden="true"></i> Members
            </h1>
            <a href="/org/members/invite" class="btn btn-primary">
                <i class="fas fa-user-plus" aria-hidden="true"></i> Invite Member
            </a>
        </div>

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

        <!-- ================================================================ -->
        <!-- Members Table                                                     -->
        <!-- ================================================================ -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-user-friends" aria-hidden="true"></i>
                    Active Members (<?php echo count($members); ?>)
                </h2>
            </div>

            <?php if (empty($members)) { ?>
            <div class="card-body text-center text-body-secondary py-4">
                <i class="fas fa-user-slash fa-2x mb-2" aria-hidden="true"></i>
                <p class="mb-0">No members found.</p>
            </div>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Name</th>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Last Login</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($members as $member) { ?>
                        <tr>
                            <td>
                                <?php echo g2ml_sanitiseOutput($member['displayName'] ?? ($member['firstName'] . ' ' . $member['lastName'])); ?>
                                <?php if ((int) $member['userUID'] === $currentUser['userUID']) { ?>
                                <span class="badge bg-info">You</span>
                                <?php } ?>
                                <?php if ((int) $member['isSuspended']) { ?>
                                <span class="badge bg-danger">Suspended</span>
                                <?php } ?>
                            </td>
                            <td><?php echo g2ml_sanitiseOutput($member['email']); ?></td>
                            <td>
                                <?php
                                $roleBadge = match($member['role']) {
                                    'GlobalAdmin' => 'bg-danger',
                                    'Admin'       => 'bg-warning text-dark',
                                    default       => 'bg-secondary',
                                };
                                ?>
                                <span class="badge <?php echo $roleBadge; ?>"><?php echo g2ml_sanitiseOutput($member['role']); ?></span>
                            </td>
                            <td>
                                <?php if ($member['lastLoginAt']) { ?>
                                <time datetime="<?php echo g2ml_sanitiseOutput($member['lastLoginAt']); ?>">
                                    <?php echo date('j M Y', strtotime($member['lastLoginAt'])); ?>
                                </time>
                                <?php } else { ?>
                                <span class="text-body-secondary">Never</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ((int) $member['userUID'] !== $currentUser['userUID'] && $member['role'] !== 'GlobalAdmin') { ?>
                                <div class="d-flex gap-1">
                                    <!-- Role Change -->
                                    <form action="/org/members" method="POST" class="d-inline">
                                        <?php echo g2ml_csrfField('org_members_form'); ?>
                                        <input type="hidden" name="action_type" value="change_role">
                                        <input type="hidden" name="user_uid" value="<?php echo (int) $member['userUID']; ?>">
                                        <select name="new_role" class="form-select form-select-sm d-inline-block" style="width:auto;"
                                                onchange="this.form.submit()" aria-label="Change role">
                                            <option value="User" <?php if (($member['role'] === 'User')) { echo 'selected'; } ?>>User</option>
                                            <option value="Admin" <?php if (($member['role'] === 'Admin')) { echo 'selected'; } ?>>Admin</option>
                                        </select>
                                    </form>

                                    <!-- Remove -->
                                    <form action="/org/members" method="POST" class="d-inline"
                                          onsubmit="return confirm('Remove this member from the organisation?');">
                                        <?php echo g2ml_csrfField('org_members_form'); ?>
                                        <input type="hidden" name="action_type" value="remove_member">
                                        <input type="hidden" name="user_uid" value="<?php echo (int) $member['userUID']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                title="Remove member"
                                                aria-label="Remove <?php echo g2ml_sanitiseOutput($member['displayName'] ?? $member['firstName']); ?>">
                                            <i class="fas fa-user-minus" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                                <?php } else { ?>
                                <span class="text-body-secondary">—</span>
                                <?php } ?>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>

        <!-- ================================================================ -->
        <!-- Pending Invitations                                               -->
        <!-- ================================================================ -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-envelope-open-text" aria-hidden="true"></i>
                    Pending Invitations (<?php echo count($invitations); ?>)
                </h2>
            </div>

            <?php if (empty($invitations)) { ?>
            <div class="card-body text-center text-body-secondary py-4">
                <p class="mb-0">No pending invitations.</p>
            </div>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Email</th>
                            <th scope="col">Role</th>
                            <th scope="col">Invited By</th>
                            <th scope="col">Expires</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($invitations as $inv) { ?>
                        <tr>
                            <td><?php echo g2ml_sanitiseOutput($inv['email']); ?></td>
                            <td><span class="badge bg-secondary"><?php echo g2ml_sanitiseOutput($inv['role']); ?></span></td>
                            <td><?php echo g2ml_sanitiseOutput($inv['inviterName'] ?? 'Unknown'); ?></td>
                            <td>
                                <time datetime="<?php echo g2ml_sanitiseOutput($inv['expiresAt']); ?>">
                                    <?php echo date('j M Y', strtotime($inv['expiresAt'])); ?>
                                </time>
                            </td>
                            <td>
                                <form action="/org/members" method="POST" class="d-inline"
                                      onsubmit="return confirm('Cancel this invitation?');">
                                    <?php echo g2ml_csrfField('org_members_form'); ?>
                                    <input type="hidden" name="action_type" value="cancel_invitation">
                                    <input type="hidden" name="invitation_uid" value="<?php echo (int) $inv['invitationUID']; ?>">
                                    <button type="submit" class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-times" aria-hidden="true"></i> Cancel
                                    </button>
                                </form>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>

    </div>
</section>
