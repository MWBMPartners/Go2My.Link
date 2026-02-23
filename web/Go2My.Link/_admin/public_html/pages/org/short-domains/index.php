<?php
/**
 * ============================================================================
 * 🔗 Go2My.Link — Short Domain Management (Admin Dashboard)
 * ============================================================================
 *
 * Add, remove, and set default short URL domains for an organisation.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.6.0
 * @since      Phase 5
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('org.short_domains_title');
} else {
    $pageTitle = 'Short Domains';
}
if (function_exists('__')) {
    $pageDesc = __('org.short_domains_description');
} else {
    $pageDesc = 'Manage your organisation\'s short URL domains.';
}

$currentUser = getCurrentUser();
$orgHandle   = $currentUser['orgHandle'];

if ($orgHandle === '[default]' || !canManageOrg($orgHandle))
{
    echo '<div class="container py-5"><div class="alert alert-danger">';
    echo '<i class="fas fa-lock" aria-hidden="true"></i> ';
    echo 'You do not have permission to manage short domains.';
    echo '</div></div>';
    return;
}

$actionSuccess = '';
$actionError   = '';

// ============================================================================
// Handle POST actions
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken  = $_POST['_csrf_token'] ?? '';
    $actionType = $_POST['action_type'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'org_short_domains_form'))
    {
        $actionError = 'Session expired. Please try again.';
    }
    else
    {
        switch ($actionType)
        {
            case 'add_short_domain':
                $domain = strtolower(trim(g2ml_sanitiseInput($_POST['short_domain'] ?? '')));
                $result = addOrgShortDomain($orgHandle, $domain);
                if ($result['success']) { $actionSuccess = 'Short domain added.'; }
                else { $actionError = $result['error']; }
                break;

            case 'set_default':
                $domainUID = (int) ($_POST['domain_uid'] ?? 0);
                $result    = setDefaultShortDomain($domainUID, $orgHandle);
                if ($result['success']) { $actionSuccess = 'Default short domain updated.'; }
                else { $actionError = $result['error']; }
                break;

            case 'remove_short_domain':
                $domainUID = (int) ($_POST['domain_uid'] ?? 0);
                $result    = removeOrgShortDomain($domainUID, $orgHandle);
                if ($result['success']) { $actionSuccess = 'Short domain removed.'; }
                else { $actionError = $result['error']; }
                break;
        }
    }
}

// Load data
$shortDomains = getOrgShortDomains($orgHandle);
?>

<section class="py-4" aria-labelledby="short-domains-heading">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/org">Organisation</a></li>
                <li class="breadcrumb-item active" aria-current="page">Short Domains</li>
            </ol>
        </nav>

        <h1 id="short-domains-heading" class="h2 mb-4">
            <i class="fas fa-bolt" aria-hidden="true"></i> Short Domains
        </h1>

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

        <!-- Info -->
        <div class="alert alert-info mb-4">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            Short domains are used in your shortened URLs (e.g., <code>yourdomain.link/abc123</code>).
            The domain must be configured at the DNS level to point to the Go2My.Link redirect engine servers.
        </div>

        <!-- ================================================================ -->
        <!-- Short Domains Table                                               -->
        <!-- ================================================================ -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">Your Short Domains (<?php echo count($shortDomains); ?>)</h2>
            </div>

            <?php if (empty($shortDomains)) { ?>
            <div class="card-body text-center text-body-secondary py-4">
                <i class="fas fa-bolt fa-2x mb-2" aria-hidden="true"></i>
                <p class="mb-0">No short domains configured yet.</p>
            </div>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Domain</th>
                            <th scope="col">Status</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($shortDomains as $sd) { ?>
                        <tr>
                            <td>
                                <strong><?php echo g2ml_sanitiseOutput($sd['shortDomain']); ?></strong>
                                <?php if ((int) $sd['isDefault']) { ?>
                                <span class="badge bg-primary ms-1">Default</span>
                                <?php } ?>
                            </td>
                            <td>
                                <?php if ((int) $sd['isActive']) { ?>
                                <span class="badge bg-success">Active</span>
                                <?php } else { ?>
                                <span class="badge bg-secondary">Inactive</span>
                                <?php } ?>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if (!(int) $sd['isDefault']) { ?>
                                    <form action="/org/short-domains" method="POST" class="d-inline">
                                        <?php echo g2ml_csrfField('org_short_domains_form'); ?>
                                        <input type="hidden" name="action_type" value="set_default">
                                        <input type="hidden" name="domain_uid" value="<?php echo (int) $sd['shortDomainUID']; ?>">
                                        <button type="submit" class="btn btn-outline-primary btn-sm" title="Set as default">
                                            <i class="fas fa-star" aria-hidden="true"></i> Set Default
                                        </button>
                                    </form>

                                    <form action="/org/short-domains" method="POST" class="d-inline"
                                          onsubmit="return confirm('Remove this short domain?');">
                                        <?php echo g2ml_csrfField('org_short_domains_form'); ?>
                                        <input type="hidden" name="action_type" value="remove_short_domain">
                                        <input type="hidden" name="domain_uid" value="<?php echo (int) $sd['shortDomainUID']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                title="Remove" aria-label="Remove <?php echo g2ml_sanitiseOutput($sd['shortDomain']); ?>">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <?php } ?>
                                </div>
                            </td>
                        </tr>
                        <?php } ?>
                    </tbody>
                </table>
            </div>
            <?php } ?>
        </div>

        <!-- ================================================================ -->
        <!-- Add Short Domain Form                                             -->
        <!-- ================================================================ -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-plus-circle" aria-hidden="true"></i> Add Short Domain
                </h2>
            </div>
            <div class="card-body">
                <form action="/org/short-domains" method="POST" novalidate>
                    <?php echo g2ml_csrfField('org_short_domains_form'); ?>
                    <input type="hidden" name="action_type" value="add_short_domain">

                    <div class="row g-3">
                        <div class="col-md-8">
                            <?php
                            echo formField([
                                'id'       => 'short-domain',
                                'name'     => 'short_domain',
                                'label'    => 'Short Domain',
                                'type'     => 'text',
                                'required' => true,
                                'value'    => '',
                                'helpText' => 'e.g., mylinks.co — must be configured in DNS to point to our servers.',
                            ]);
                            ?>
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary mb-3 w-100">
                                <i class="fas fa-plus" aria-hidden="true"></i> Add
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

    </div>
</section>
