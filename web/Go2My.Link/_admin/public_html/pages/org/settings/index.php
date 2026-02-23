<?php
/**
 * ============================================================================
 * ⚙️ Go2My.Link — Organisation Settings (Admin Dashboard)
 * ============================================================================
 *
 * Edit organisation details. Basic settings for Admin+, admin-only settings
 * (tier, verification, status) for GlobalAdmin.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.6.0
 * @since      Phase 5
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('org.settings_title') : 'Organisation Settings';
$pageDesc  = function_exists('__') ? __('org.settings_description') : 'Update your organisation details.';

$currentUser = getCurrentUser();
$orgHandle   = $currentUser['orgHandle'];

// Must not be in [default] org
if ($orgHandle === '[default]')
{
    echo '<div class="container py-5"><div class="alert alert-warning">';
    echo '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i> ';
    echo 'You must be part of an organisation to access settings. <a href="/org/create">Create one</a>.';
    echo '</div></div>';
    return;
}

// Must be Admin or GlobalAdmin
if (!canManageOrg($orgHandle))
{
    echo '<div class="container py-5"><div class="alert alert-danger">';
    echo '<i class="fas fa-lock" aria-hidden="true"></i> ';
    echo 'You do not have permission to manage organisation settings.';
    echo '</div></div>';
    return;
}

$org = getOrganisation($orgHandle);
if ($org === null)
{
    echo '<div class="container py-5"><div class="alert alert-danger">Organisation not found.</div></div>';
    return;
}

$isGlobalAdmin = hasMinimumRole($currentUser['role'], 'GlobalAdmin');

// ============================================================================
// Handle basic settings update
// ============================================================================

$basicSuccess = false;
$basicError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'basic_settings')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'org_basic_settings'))
    {
        $basicError = 'Session expired. Please try again.';
    }
    else
    {
        $updateData = [
            'orgName'        => trim(g2ml_sanitiseInput($_POST['org_name'] ?? '')),
            'orgURL'         => trim($_POST['org_url'] ?? ''),
            'orgDescription' => trim(g2ml_sanitiseInput($_POST['org_description'] ?? '')),
            'orgFallbackURL' => trim($_POST['org_fallback_url'] ?? ''),
        ];

        if ($updateData['orgName'] === '')
        {
            $basicError = 'Organisation name is required.';
        }
        else
        {
            // Empty strings → null for optional fields
            if ($updateData['orgURL'] === '') $updateData['orgURL'] = null;
            if ($updateData['orgDescription'] === '') $updateData['orgDescription'] = null;
            if ($updateData['orgFallbackURL'] === '') $updateData['orgFallbackURL'] = null;

            $result = updateOrganisation($orgHandle, $updateData);

            if ($result['success'])
            {
                $basicSuccess = true;
                // Refresh org data
                $org = getOrganisation($orgHandle);
            }
            else
            {
                $basicError = $result['error'];
            }
        }
    }
}

// ============================================================================
// Handle admin settings update (GlobalAdmin only)
// ============================================================================

$adminSuccess = false;
$adminError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form_type'] ?? '') === 'admin_settings' && $isGlobalAdmin)
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'org_admin_settings'))
    {
        $adminError = 'Session expired. Please try again.';
    }
    else
    {
        $adminData = [
            'tierID'     => trim(g2ml_sanitiseInput($_POST['tier_id'] ?? '')),
            'isVerified' => isset($_POST['is_verified']) ? 1 : 0,
            'isActive'   => isset($_POST['is_active']) ? 1 : 0,
            'orgNotes'   => trim(g2ml_sanitiseInput($_POST['org_notes'] ?? '')),
        ];

        if ($adminData['orgNotes'] === '') $adminData['orgNotes'] = null;

        $result = updateOrganisation($orgHandle, $adminData);

        if ($result['success'])
        {
            $adminSuccess = true;
            $org = getOrganisation($orgHandle);
        }
        else
        {
            $adminError = $result['error'];
        }
    }
}

// Load subscription tiers for dropdown
$tiers = [];
if ($isGlobalAdmin)
{
    $tiers = dbSelect("SELECT tierID, tierName FROM tblSubscriptionTiers ORDER BY tierUID ASC", '', []) ?: [];
}
?>

<section class="py-4" aria-labelledby="org-settings-heading">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/org">Organisation</a></li>
                <li class="breadcrumb-item active" aria-current="page">Settings</li>
            </ol>
        </nav>

        <h1 id="org-settings-heading" class="h2 mb-4">
            <i class="fas fa-cog" aria-hidden="true"></i>
            <?php echo function_exists('__') ? __('org.settings_heading') : 'Organisation Settings'; ?>
        </h1>

        <div class="row g-4">
            <!-- ============================================================ -->
            <!-- Basic Settings                                                -->
            <!-- ============================================================ -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-id-card" aria-hidden="true"></i> Basic Information
                        </h2>
                    </div>
                    <div class="card-body">

                        <?php if ($basicSuccess): ?>
                        <div class="alert alert-success" role="status">
                            <i class="fas fa-check-circle" aria-hidden="true"></i> Organisation updated.
                        </div>
                        <?php endif; ?>

                        <?php if ($basicError !== ''): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                            <?php echo g2ml_sanitiseOutput($basicError); ?>
                        </div>
                        <?php endif; ?>

                        <form action="/org/settings" method="POST" novalidate>
                            <?php echo g2ml_csrfField('org_basic_settings'); ?>
                            <input type="hidden" name="form_type" value="basic_settings">

                            <!-- Handle (read-only) -->
                            <div class="mb-3">
                                <label class="form-label">Handle</label>
                                <input type="text" class="form-control" value="<?php echo g2ml_sanitiseOutput($org['orgHandle']); ?>" readonly disabled>
                            </div>

                            <?php
                            echo formField([
                                'id'       => 'org-name',
                                'name'     => 'org_name',
                                'label'    => 'Organisation Name',
                                'type'     => 'text',
                                'required' => true,
                                'value'    => g2ml_sanitiseOutput($org['orgName']),
                            ]);

                            echo formField([
                                'id'       => 'org-url',
                                'name'     => 'org_url',
                                'label'    => 'Website URL',
                                'type'     => 'url',
                                'required' => false,
                                'value'    => g2ml_sanitiseOutput($org['orgURL'] ?? ''),
                            ]);

                            echo formField([
                                'id'       => 'org-description',
                                'name'     => 'org_description',
                                'label'    => 'Description',
                                'type'     => 'textarea',
                                'required' => false,
                                'value'    => g2ml_sanitiseOutput($org['orgDescription'] ?? ''),
                            ]);

                            echo formField([
                                'id'       => 'org-fallback-url',
                                'name'     => 'org_fallback_url',
                                'label'    => 'Fallback URL',
                                'type'     => 'url',
                                'required' => false,
                                'value'    => g2ml_sanitiseOutput($org['orgFallbackURL'] ?? ''),
                                'helpText' => 'URL to redirect to when a short link is not found or has expired.',
                            ]);
                            ?>

                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save" aria-hidden="true"></i> Save Changes
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <?php if ($isGlobalAdmin): ?>
            <!-- ============================================================ -->
            <!-- Admin Settings (GlobalAdmin only)                             -->
            <!-- ============================================================ -->
            <div class="col-lg-6">
                <div class="card shadow-sm">
                    <div class="card-header bg-warning-subtle">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-shield-alt" aria-hidden="true"></i> Admin Settings
                            <span class="badge bg-warning text-dark ms-2">GlobalAdmin</span>
                        </h2>
                    </div>
                    <div class="card-body">

                        <?php if ($adminSuccess): ?>
                        <div class="alert alert-success" role="status">
                            <i class="fas fa-check-circle" aria-hidden="true"></i> Admin settings updated.
                        </div>
                        <?php endif; ?>

                        <?php if ($adminError !== ''): ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                            <?php echo g2ml_sanitiseOutput($adminError); ?>
                        </div>
                        <?php endif; ?>

                        <form action="/org/settings" method="POST" novalidate>
                            <?php echo g2ml_csrfField('org_admin_settings'); ?>
                            <input type="hidden" name="form_type" value="admin_settings">

                            <!-- Tier -->
                            <div class="mb-3">
                                <label for="tier-id" class="form-label">Subscription Tier</label>
                                <select class="form-select" id="tier-id" name="tier_id">
                                    <?php foreach ($tiers as $tier): ?>
                                    <option value="<?php echo g2ml_sanitiseOutput($tier['tierID']); ?>"
                                        <?php echo ($org['tierID'] === $tier['tierID']) ? 'selected' : ''; ?>>
                                        <?php echo g2ml_sanitiseOutput($tier['tierName']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Verified -->
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="is-verified" name="is_verified" value="1"
                                       <?php echo ((int) $org['isVerified']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is-verified">Verified Organisation</label>
                            </div>

                            <!-- Active -->
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="is-active" name="is_active" value="1"
                                       <?php echo ((int) $org['isActive']) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is-active">Active</label>
                            </div>

                            <?php
                            echo formField([
                                'id'       => 'org-notes',
                                'name'     => 'org_notes',
                                'label'    => 'Admin Notes',
                                'type'     => 'textarea',
                                'required' => false,
                                'value'    => g2ml_sanitiseOutput($org['orgNotes'] ?? ''),
                                'helpText' => 'Internal notes (not visible to org members).',
                            ]);
                            ?>

                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-shield-alt" aria-hidden="true"></i> Update Admin Settings
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</section>
