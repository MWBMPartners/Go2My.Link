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
 * 👤 Go2My.Link — User Profile (Admin Dashboard)
 * ============================================================================
 *
 * User profile page with personal info editing and password change sections.
 * Each section has its own CSRF-protected form.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.5.0
 * @since      Phase 4
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('profile.title');
} else {
    $pageTitle = 'Profile';
}
if (function_exists('__')) {
    $pageDesc = __('profile.description');
} else {
    $pageDesc = 'Manage your account settings.';
}

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

// Fetch full user data from DB (session may not have everything)
$userData = dbSelectOne(
    "SELECT userUID, firstName, lastName, displayName, email, timezone, avatarURL,
            emailVerified, createdAt
     FROM tblUsers WHERE userUID = ? LIMIT 1",
    'i',
    [$userUID]
);

if ($userData === null || $userData === false)
{
    echo '<div class="container py-5"><div class="alert alert-danger">User data not found.</div></div>';
    return;
}

// ============================================================================
// Handle profile info update
// ============================================================================

$infoSuccess = false;
$infoError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'profile_info')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'profile_info_form'))
    {
        $infoError = 'Session expired. Please try again.';
    }
    else
    {
        $firstName   = trim(g2ml_sanitiseInput($_POST['first_name'] ?? ''));
        $lastName    = trim(g2ml_sanitiseInput($_POST['last_name'] ?? ''));
        $displayName = trim(g2ml_sanitiseInput($_POST['display_name'] ?? ''));
        $timezone    = trim(g2ml_sanitiseInput($_POST['timezone'] ?? 'UTC'));

        if ($firstName === '' || $lastName === '')
        {
            $infoError = 'First name and last name are required.';
        }
        else
        {
            if ($displayName === '')
            {
                $displayName = $firstName . ' ' . $lastName;
            }

            $result = dbUpdate(
                "UPDATE tblUsers SET firstName = ?, lastName = ?, displayName = ?, timezone = ? WHERE userUID = ?",
                'ssssi',
                [$firstName, $lastName, $displayName, $timezone, $userUID]
            );

            if ($result !== false)
            {
                // Update session
                $_SESSION['user_first_name']   = $firstName;
                $_SESSION['user_last_name']    = $lastName;
                $_SESSION['user_display_name'] = $displayName;
                $_SESSION['user_timezone']     = $timezone;

                // Refresh local data
                $userData['firstName']   = $firstName;
                $userData['lastName']    = $lastName;
                $userData['displayName'] = $displayName;
                $userData['timezone']    = $timezone;

                $infoSuccess = true;

                logActivity('update_profile', 'success', 200, ['userUID' => $userUID]);
            }
            else
            {
                $infoError = 'Failed to update profile. Please try again.';
            }
        }
    }
}

// ============================================================================
// Handle password change
// ============================================================================

$pwSuccess = false;
$pwError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['form_type']) && $_POST['form_type'] === 'change_password')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'change_password_form'))
    {
        $pwError = 'Session expired. Please try again.';
    }
    else
    {
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword     = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';

        if ($newPassword !== $confirmPassword)
        {
            $pwError = 'New passwords do not match.';
        }
        else
        {
            $result = changePassword($userUID, $currentPassword, $newPassword);

            if ($result['success'])
            {
                $pwSuccess = true;
            }
            else
            {
                $pwError = $result['error'];
            }
        }
    }
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="profile-heading">
    <div class="container">
        <h1 id="profile-heading" class="h2 mb-4">
            <i class="fas fa-user-cog" aria-hidden="true"></i>
            <?php if (function_exists('__')) { echo __('profile.heading'); } else { echo 'Profile Settings'; } ?>
        </h1>

        <div class="row g-4">
            <!-- ============================================================== -->
            <!-- Personal Information                                            -->
            <!-- ============================================================== -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-id-card" aria-hidden="true"></i> Personal Information
                        </h2>
                    </div>
                    <div class="card-body">

                        <?php if ($infoSuccess) { ?>
                        <div class="alert alert-success" role="status">
                            <i class="fas fa-check-circle" aria-hidden="true"></i> Profile updated.
                        </div>
                        <?php } ?>

                        <?php if ($infoError !== '') { ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                            <?php echo g2ml_sanitiseOutput($infoError); ?>
                        </div>
                        <?php } ?>

                        <form action="/profile" method="POST" novalidate>
                            <?php echo g2ml_csrfField('profile_info_form'); ?>
                            <input type="hidden" name="form_type" value="profile_info">

                            <div class="row">
                                <div class="col-md-6">
                                    <?php
                                    echo formField([
                                        'id'           => 'first-name',
                                        'name'         => 'first_name',
                                        'label'        => 'First Name',
                                        'type'         => 'text',
                                        'required'     => true,
                                        'autocomplete' => 'given-name',
                                        'value'        => g2ml_sanitiseOutput($userData['firstName']),
                                    ]);
                                    ?>
                                </div>
                                <div class="col-md-6">
                                    <?php
                                    echo formField([
                                        'id'           => 'last-name',
                                        'name'         => 'last_name',
                                        'label'        => 'Last Name',
                                        'type'         => 'text',
                                        'required'     => true,
                                        'autocomplete' => 'family-name',
                                        'value'        => g2ml_sanitiseOutput($userData['lastName']),
                                    ]);
                                    ?>
                                </div>
                            </div>

                            <?php
                            echo formField([
                                'id'       => 'display-name',
                                'name'     => 'display_name',
                                'label'    => 'Display Name',
                                'type'     => 'text',
                                'required' => false,
                                'helpText' => 'How your name appears to others. Defaults to First + Last name.',
                                'value'    => g2ml_sanitiseOutput($userData['displayName']),
                            ]);
                            ?>

                            <!-- Email (read-only) -->
                            <div class="mb-3">
                                <label for="email-address" class="form-label">Email Address</label>
                                <input type="email" class="form-control" id="email-address" value="<?php echo g2ml_sanitiseOutput($userData['email']); ?>" readonly disabled>
                                <?php if ((int) $userData['emailVerified']) { ?>
                                <small class="text-success"><i class="fas fa-check-circle" aria-hidden="true"></i> Verified</small>
                                <?php } else { ?>
                                <small class="text-warning"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Not verified</small>
                                <?php } ?>
                            </div>

                            <!-- Timezone -->
                            <div class="mb-3">
                                <label for="timezone" class="form-label">Timezone</label>
                                <select class="form-select" id="timezone" name="timezone">
                                    <?php
                                    $timezones = ['UTC', 'Europe/London', 'Europe/Paris', 'Europe/Berlin',
                                                  'America/New_York', 'America/Chicago', 'America/Denver',
                                                  'America/Los_Angeles', 'Asia/Tokyo', 'Asia/Shanghai',
                                                  'Australia/Sydney', 'Pacific/Auckland'];
                                    foreach ($timezones as $tz) {
                                    ?>
                                    <option value="<?php echo $tz; ?>" <?php if (($userData['timezone'] === $tz)) { echo 'selected'; } ?>>
                                        <?php echo $tz; ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save" aria-hidden="true"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <!-- ============================================================== -->
            <!-- Change Password                                                 -->
            <!-- ============================================================== -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-key" aria-hidden="true"></i> Change Password
                        </h2>
                    </div>
                    <div class="card-body">

                        <?php if ($pwSuccess) { ?>
                        <div class="alert alert-success" role="status">
                            <i class="fas fa-check-circle" aria-hidden="true"></i> Password changed. Other sessions have been signed out.
                        </div>
                        <?php } ?>

                        <?php if ($pwError !== '') { ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                            <?php echo g2ml_sanitiseOutput($pwError); ?>
                        </div>
                        <?php } ?>

                        <form action="/profile" method="POST" novalidate>
                            <?php echo g2ml_csrfField('change_password_form'); ?>
                            <input type="hidden" name="form_type" value="change_password">

                            <?php
                            echo formField([
                                'id'           => 'current-password',
                                'name'         => 'current_password',
                                'label'        => 'Current Password',
                                'type'         => 'password',
                                'required'     => true,
                                'autocomplete' => 'current-password',
                            ]);

                            echo formField([
                                'id'           => 'new-password',
                                'name'         => 'new_password',
                                'label'        => 'New Password',
                                'type'         => 'password',
                                'required'     => true,
                                'autocomplete' => 'new-password',
                                'helpText'     => 'Minimum 8 characters with at least one uppercase letter, one lowercase letter, and one number.',
                            ]);

                            echo formField([
                                'id'           => 'confirm-password',
                                'name'         => 'confirm_password',
                                'label'        => 'Confirm New Password',
                                'type'         => 'password',
                                'required'     => true,
                                'autocomplete' => 'new-password',
                            ]);
                            ?>

                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key" aria-hidden="true"></i> Change Password
                            </button>
                        </form>
                    </div>
                </div>

                <!-- Sessions Link Card -->
                <div class="card shadow-sm mt-4">
                    <div class="card-body d-flex justify-content-between align-items-center">
                        <div>
                            <h3 class="h6 mb-1"><i class="fas fa-desktop" aria-hidden="true"></i> Active Sessions</h3>
                            <p class="text-body-secondary small mb-0">View and manage devices signed in to your account.</p>
                        </div>
                        <a href="/profile/sessions" class="btn btn-outline-primary btn-sm">
                            Manage <i class="fas fa-arrow-right" aria-hidden="true"></i>
                        </a>
                    </div>
                </div>

                <!-- Account Info -->
                <div class="card shadow-sm mt-4">
                    <div class="card-body">
                        <h3 class="h6 mb-2"><i class="fas fa-info-circle" aria-hidden="true"></i> Account Info</h3>
                        <p class="text-body-secondary small mb-1">
                            <strong>Member since:</strong>
                            <time datetime="<?php echo g2ml_sanitiseOutput($userData['createdAt']); ?>">
                                <?php echo date('j M Y', strtotime($userData['createdAt'])); ?>
                            </time>
                        </p>
                        <p class="text-body-secondary small mb-0">
                            <strong>Role:</strong> <?php echo g2ml_sanitiseOutput($currentUser['role']); ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
