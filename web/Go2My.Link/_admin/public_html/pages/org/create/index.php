<?php
/**
 * ============================================================================
 * 🏢 Go2My.Link — Create Organisation (Admin Dashboard)
 * ============================================================================
 *
 * Form for creating a new organisation. Only available to users currently
 * in the [default] organisation.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.6.0
 * @since      Phase 5
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('org.create_title') : 'Create Organisation';
$pageDesc  = function_exists('__') ? __('org.create_description') : 'Set up a new organisation for your team.';

$currentUser = getCurrentUser();

// Must be in [default] org
if ($currentUser['orgHandle'] !== '[default]')
{
    echo '<div class="container py-5"><div class="alert alert-warning">';
    echo '<i class="fas fa-exclamation-triangle" aria-hidden="true"></i> ';
    echo 'You are already a member of an organisation. <a href="/org">View your organisation</a>.';
    echo '</div></div>';
    return;
}

$formSuccess = false;
$formError   = '';
$newOrgHandle = '';

// ============================================================================
// Handle form submission
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'create_org_form'))
    {
        $formError = 'Session expired. Please try again.';
    }
    else
    {
        $orgName = trim(g2ml_sanitiseInput($_POST['org_name'] ?? ''));
        $orgHandle = strtolower(trim(g2ml_sanitiseInput($_POST['org_handle'] ?? '')));
        $orgURL = trim($_POST['org_url'] ?? '');
        $orgDesc = trim(g2ml_sanitiseInput($_POST['org_description'] ?? ''));

        if ($orgName === '')
        {
            $formError = 'Organisation name is required.';
        }
        elseif ($orgHandle === '')
        {
            $formError = 'Organisation handle is required.';
        }
        else
        {
            $result = createOrganisation($orgName, $orgHandle, [
                'orgURL'         => $orgURL !== '' ? $orgURL : null,
                'orgDescription' => $orgDesc !== '' ? $orgDesc : null,
            ]);

            if ($result['success'])
            {
                $formSuccess  = true;
                $newOrgHandle = $result['orgHandle'];
            }
            else
            {
                $formError = $result['error'];
            }
        }
    }
}
?>

<section class="py-4" aria-labelledby="create-org-heading">
    <div class="container" style="max-width:700px;">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/org">Organisation</a></li>
                <li class="breadcrumb-item active" aria-current="page">Create</li>
            </ol>
        </nav>

        <h1 id="create-org-heading" class="h2 mb-4">
            <i class="fas fa-plus-circle" aria-hidden="true"></i>
            <?php echo function_exists('__') ? __('org.create_heading') : 'Create Organisation'; ?>
        </h1>

        <?php if ($formSuccess) { ?>
        <!-- Success -->
        <div class="card shadow-sm border-success">
            <div class="card-body text-center py-5">
                <i class="fas fa-check-circle fa-3x text-success mb-3" aria-hidden="true"></i>
                <h2 class="h4">Organisation Created!</h2>
                <p class="text-body-secondary">
                    Your organisation has been set up and you've been assigned as Admin.
                </p>
                <a href="/org" class="btn btn-primary">
                    <i class="fas fa-building" aria-hidden="true"></i> View Organisation
                </a>
            </div>
        </div>

        <?php } else { ?>
        <!-- Form -->
        <div class="card shadow-sm">
            <div class="card-body">

                <?php if ($formError !== '') { ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo g2ml_sanitiseOutput($formError); ?>
                </div>
                <?php } ?>

                <form action="/org/create" method="POST" novalidate>
                    <?php echo g2ml_csrfField('create_org_form'); ?>

                    <?php
                    echo formField([
                        'id'       => 'org-name',
                        'name'     => 'org_name',
                        'label'    => 'Organisation Name',
                        'type'     => 'text',
                        'required' => true,
                        'value'    => g2ml_sanitiseOutput($_POST['org_name'] ?? ''),
                        'helpText' => 'The display name for your organisation.',
                    ]);

                    echo formField([
                        'id'       => 'org-handle',
                        'name'     => 'org_handle',
                        'label'    => 'Handle / Slug',
                        'type'     => 'text',
                        'required' => true,
                        'value'    => g2ml_sanitiseOutput($_POST['org_handle'] ?? ''),
                        'helpText' => 'Lowercase letters, numbers, and hyphens only (3–50 characters). This is your unique identifier.',
                    ]);

                    echo formField([
                        'id'       => 'org-url',
                        'name'     => 'org_url',
                        'label'    => 'Website URL',
                        'type'     => 'url',
                        'required' => false,
                        'value'    => g2ml_sanitiseOutput($_POST['org_url'] ?? ''),
                        'helpText' => 'Your organisation\'s primary website (optional).',
                    ]);

                    echo formField([
                        'id'       => 'org-description',
                        'name'     => 'org_description',
                        'label'    => 'Description',
                        'type'     => 'textarea',
                        'required' => false,
                        'value'    => g2ml_sanitiseOutput($_POST['org_description'] ?? ''),
                        'helpText' => 'A brief description of your organisation (optional).',
                    ]);
                    ?>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-plus-circle" aria-hidden="true"></i> Create Organisation
                        </button>
                        <a href="/org" class="btn btn-outline-secondary">Cancel</a>
                    </div>
                </form>
            </div>
        </div>
        <?php } ?>

    </div>
</section>
