<?php
/**
 * ============================================================================
 * 🌐 Go2My.Link — Custom Domain Management (Admin Dashboard)
 * ============================================================================
 *
 * Add, verify (via DNS TXT), and remove custom domains for an organisation.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.6.0
 * @since      Phase 5
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('org.domains_title') : 'Custom Domains';
$pageDesc  = function_exists('__') ? __('org.domains_description') : 'Manage your organisation\'s custom domains.';

$currentUser = getCurrentUser();
$orgHandle   = $currentUser['orgHandle'];

if ($orgHandle === '[default]' || !canManageOrg($orgHandle))
{
    echo '<div class="container py-5"><div class="alert alert-danger">';
    echo '<i class="fas fa-lock" aria-hidden="true"></i> ';
    echo 'You do not have permission to manage domains.';
    echo '</div></div>';
    return;
}

$actionSuccess   = '';
$actionError     = '';
$verifyResult    = null;
$newVerifyToken  = null;

// ============================================================================
// Handle POST actions
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken  = $_POST['_csrf_token'] ?? '';
    $actionType = $_POST['action_type'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'org_domains_form'))
    {
        $actionError = 'Session expired. Please try again.';
    }
    else
    {
        switch ($actionType)
        {
            case 'add_domain':
                $domainName = strtolower(trim(g2ml_sanitiseInput($_POST['domain_name'] ?? '')));
                $domainType = $_POST['domain_type'] ?? 'primary';
                $result     = addOrgDomain($orgHandle, $domainName, $domainType);
                if ($result['success'])
                {
                    $actionSuccess  = "Domain added. Please add the DNS TXT record to verify ownership.";
                    $newVerifyToken = $result['verificationToken'];
                }
                else
                {
                    $actionError = $result['error'];
                }
                break;

            case 'verify_domain':
                $domainUID = (int) ($_POST['domain_uid'] ?? 0);
                $result    = verifyDomain($domainUID, $orgHandle);
                if ($result['verified'])
                {
                    $actionSuccess = 'Domain verified successfully!';
                }
                else
                {
                    $actionError = $result['error'] ?? 'Verification failed.';
                }
                break;

            case 'remove_domain':
                $domainUID = (int) ($_POST['domain_uid'] ?? 0);
                $result    = removeOrgDomain($domainUID, $orgHandle);
                if ($result['success'])
                {
                    $actionSuccess = 'Domain removed.';
                }
                else
                {
                    $actionError = $result['error'];
                }
                break;
        }
    }
}

// Load data
$domains    = getOrgDomains($orgHandle);
$dnsPrefix  = getSetting('org.dns_verify_prefix', '_g2ml-verify');
?>

<section class="py-4" aria-labelledby="domains-heading">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/org">Organisation</a></li>
                <li class="breadcrumb-item active" aria-current="page">Custom Domains</li>
            </ol>
        </nav>

        <h1 id="domains-heading" class="h2 mb-4">
            <i class="fas fa-globe" aria-hidden="true"></i> Custom Domains
        </h1>

        <?php if ($actionSuccess !== ''): ?>
        <div class="alert alert-success" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionSuccess); ?>
        </div>
        <?php endif; ?>

        <?php if ($actionError !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionError); ?>
        </div>
        <?php endif; ?>

        <!-- ================================================================ -->
        <!-- DNS Instructions                                                  -->
        <!-- ================================================================ -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-info-circle" aria-hidden="true"></i> DNS Verification Instructions
                </h2>
            </div>
            <div class="card-body">
                <p class="mb-2">To verify domain ownership, add a <strong>TXT record</strong> to your domain's DNS settings:</p>
                <ol class="mb-0">
                    <li>Go to your domain registrar's DNS management panel</li>
                    <li>Add a new <strong>TXT</strong> record with host/name: <code><?php echo g2ml_sanitiseOutput($dnsPrefix); ?>.yourdomain.com</code></li>
                    <li>Set the value to the <strong>verification token</strong> shown below</li>
                    <li>Wait for DNS propagation (may take up to 48 hours) and click <strong>Verify</strong></li>
                </ol>
            </div>
        </div>

        <!-- ================================================================ -->
        <!-- Domains Table                                                     -->
        <!-- ================================================================ -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">Your Domains (<?php echo count($domains); ?>)</h2>
            </div>

            <?php if (empty($domains)): ?>
            <div class="card-body text-center text-body-secondary py-4">
                <i class="fas fa-globe fa-2x mb-2" aria-hidden="true"></i>
                <p class="mb-0">No custom domains added yet.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th scope="col">Domain</th>
                            <th scope="col">Type</th>
                            <th scope="col">Status</th>
                            <th scope="col">Token</th>
                            <th scope="col">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($domains as $d): ?>
                        <tr>
                            <td><strong><?php echo g2ml_sanitiseOutput($d['domainName']); ?></strong></td>
                            <td><span class="badge bg-info"><?php echo g2ml_sanitiseOutput($d['domainType']); ?></span></td>
                            <td>
                                <?php
                                $statusBadge = match($d['verificationStatus']) {
                                    'verified' => 'bg-success',
                                    'pending'  => 'bg-warning text-dark',
                                    'failed'   => 'bg-danger',
                                    default    => 'bg-secondary',
                                };
                                ?>
                                <span class="badge <?php echo $statusBadge; ?>">
                                    <?php echo g2ml_sanitiseOutput(ucfirst($d['verificationStatus'])); ?>
                                </span>
                            </td>
                            <td>
                                <code class="small"><?php echo g2ml_sanitiseOutput($d['verificationToken']); ?></code>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <?php if ($d['verificationStatus'] !== 'verified'): ?>
                                    <form action="/org/domains" method="POST" class="d-inline">
                                        <?php echo g2ml_csrfField('org_domains_form'); ?>
                                        <input type="hidden" name="action_type" value="verify_domain">
                                        <input type="hidden" name="domain_uid" value="<?php echo (int) $d['domainUID']; ?>">
                                        <button type="submit" class="btn btn-outline-success btn-sm" title="Verify DNS">
                                            <i class="fas fa-check" aria-hidden="true"></i> Verify
                                        </button>
                                    </form>
                                    <?php endif; ?>

                                    <form action="/org/domains" method="POST" class="d-inline"
                                          onsubmit="return confirm('Remove this domain?');">
                                        <?php echo g2ml_csrfField('org_domains_form'); ?>
                                        <input type="hidden" name="action_type" value="remove_domain">
                                        <input type="hidden" name="domain_uid" value="<?php echo (int) $d['domainUID']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm" title="Remove domain">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- ================================================================ -->
        <!-- Add Domain Form                                                   -->
        <!-- ================================================================ -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-plus-circle" aria-hidden="true"></i> Add Domain
                </h2>
            </div>
            <div class="card-body">
                <form action="/org/domains" method="POST" novalidate>
                    <?php echo g2ml_csrfField('org_domains_form'); ?>
                    <input type="hidden" name="action_type" value="add_domain">

                    <div class="row g-3">
                        <div class="col-md-5">
                            <?php
                            echo formField([
                                'id'       => 'domain-name',
                                'name'     => 'domain_name',
                                'label'    => 'Domain Name',
                                'type'     => 'text',
                                'required' => true,
                                'value'    => '',
                                'helpText' => 'e.g., example.com',
                            ]);
                            ?>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="domain-type" class="form-label">Type</label>
                                <select class="form-select" id="domain-type" name="domain_type">
                                    <option value="primary">Primary</option>
                                    <option value="redirect">Redirect</option>
                                    <option value="linkspage">LinksPage</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-3 d-flex align-items-end">
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
