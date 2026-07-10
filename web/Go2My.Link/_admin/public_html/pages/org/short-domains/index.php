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
 * 🔗 Go2My.Link — Short Domain Management (Admin Dashboard)
 * ============================================================================
 *
 * Add, verify (via DNS TXT ownership proof), remove, and set the default
 * short URL domain for an organisation.
 *
 * 🔒 #91 — a newly added domain is UNVERIFIED and NOT routable
 * (verificationStatus='pending', isActive=0) until its owner proves DNS
 * control via the Verify action, which calls verifyOrgShortDomain().
 * See docs/CUSTOM_DOMAINS.md for the full partner-facing walkthrough.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.7.0
 * @since      Phase 5 (ownership verification added v1.1.0 / #91)
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

$actionSuccess  = '';
$actionError    = '';
$newDnsRecords  = null;
$newDomainName  = '';

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
                if ($result['success'])
                {
                    $actionSuccess = 'Short domain added. Set the DNS records below, then click Verify.';
                    $newDnsRecords = $result['dnsRecords'];
                    $newDomainName = $domain;
                }
                else
                {
                    $actionError = $result['error'];
                }
                break;

            case 'verify_short_domain':
                $domainUID = (int) ($_POST['domain_uid'] ?? 0);
                $result    = verifyOrgShortDomain($domainUID, $orgHandle);
                if ($result['verified'])
                {
                    $actionSuccess = 'Domain verified — it is now live and routable.';
                }
                else
                {
                    $actionError = $result['error'] ?? 'Verification failed.';
                }
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
$dnsPrefix    = getSetting('org.dns_verify_prefix', '_g2ml-verify');
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
            A domain only starts routing traffic once its ownership is <strong>verified</strong> below —
            see the full step-by-step guide in
            <a href="https://github.com/MWBMPartners/Go2My.Link/blob/main/docs/CUSTOM_DOMAINS.md">docs/CUSTOM_DOMAINS.md</a>.
        </div>

        <?php if ($newDnsRecords !== null) { ?>
        <!-- ================================================================ -->
        <!-- Just-added domain — DNS records to set right now                  -->
        <!-- ================================================================ -->
        <div class="card shadow-sm mb-4 border-warning">
            <div class="card-header bg-warning-subtle">
                <h2 class="h5 mb-0">
                    <i class="fas fa-globe" aria-hidden="true"></i>
                    DNS records for <?php echo g2ml_sanitiseOutput($newDomainName); ?>
                </h2>
            </div>
            <div class="card-body">
                <p>Add these two records at your domain registrar's DNS panel, then come back and click <strong>Verify</strong>.</p>
                <div class="table-responsive mb-2">
                    <table class="table table-sm table-bordered mb-0">
                        <thead>
                            <tr>
                                <th scope="col">Purpose</th>
                                <th scope="col">Type</th>
                                <th scope="col">Host / Name</th>
                                <th scope="col">Value</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>Ownership proof</td>
                                <td><code>TXT</code></td>
                                <td><code><?php echo g2ml_sanitiseOutput($newDnsRecords['txt']['host']); ?></code></td>
                                <td><code class="small"><?php echo g2ml_sanitiseOutput($newDnsRecords['txt']['value']); ?></code></td>
                            </tr>
                            <tr>
                                <td>Routing</td>
                                <td><code><?php echo g2ml_sanitiseOutput($newDnsRecords['routing']['type']); ?></code></td>
                                <td><code><?php echo g2ml_sanitiseOutput($newDnsRecords['routing']['host']); ?></code></td>
                                <td><code class="small"><?php echo g2ml_sanitiseOutput($newDnsRecords['routing']['value']); ?></code></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
                <?php echo g2ml_dnsProviderHintsHTML(); ?>
            </div>
        </div>
        <?php } ?>

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
                            <th scope="col">Verification</th>
                            <th scope="col">Routing</th>
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
                                <?php
                                $shortDomainStatusBadgeClass = 'bg-secondary';
                                if ($sd['verificationStatus'] === 'verified') { $shortDomainStatusBadgeClass = 'bg-success'; }
                                else if ($sd['verificationStatus'] === 'pending') { $shortDomainStatusBadgeClass = 'bg-warning text-dark'; }
                                else if ($sd['verificationStatus'] === 'failed') { $shortDomainStatusBadgeClass = 'bg-danger'; }
                                ?>
                                <span class="badge <?php echo $shortDomainStatusBadgeClass; ?>">
                                    <?php echo g2ml_sanitiseOutput(ucfirst($sd['verificationStatus'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ((int) $sd['isActive']) { ?>
                                <span class="badge bg-success">Routing live</span>
                                <?php } else { ?>
                                <span class="badge bg-secondary">Not routing</span>
                                <?php } ?>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-1">
                                    <?php if ($sd['verificationStatus'] !== 'verified') { ?>
                                    <form action="/org/short-domains" method="POST" class="d-inline">
                                        <?php echo g2ml_csrfField('org_short_domains_form'); ?>
                                        <input type="hidden" name="action_type" value="verify_short_domain">
                                        <input type="hidden" name="domain_uid" value="<?php echo (int) $sd['shortDomainUID']; ?>">
                                        <button type="submit" class="btn btn-outline-success btn-sm" title="Check DNS and verify ownership">
                                            <i class="fas fa-check" aria-hidden="true"></i> Verify
                                        </button>
                                    </form>
                                    <?php } ?>

                                    <?php if (!(int) $sd['isDefault'] && $sd['verificationStatus'] === 'verified') { ?>
                                    <form action="/org/short-domains" method="POST" class="d-inline">
                                        <?php echo g2ml_csrfField('org_short_domains_form'); ?>
                                        <input type="hidden" name="action_type" value="set_default">
                                        <input type="hidden" name="domain_uid" value="<?php echo (int) $sd['shortDomainUID']; ?>">
                                        <button type="submit" class="btn btn-outline-primary btn-sm" title="Set as default">
                                            <i class="fas fa-star" aria-hidden="true"></i> Set Default
                                        </button>
                                    </form>
                                    <?php } ?>

                                    <?php if (!(int) $sd['isDefault']) { ?>
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

                                <?php if ($sd['verificationStatus'] !== 'verified' && $sd['verificationToken'] !== null) { ?>
                                <details class="mt-2">
                                    <summary class="small text-body-secondary" style="cursor: pointer;">Show DNS records</summary>
                                    <div class="table-responsive mt-1">
                                        <table class="table table-sm table-bordered mb-0">
                                            <tbody>
                                                <tr>
                                                    <th scope="row" class="small">TXT host</th>
                                                    <td><code class="small"><?php echo g2ml_sanitiseOutput($dnsPrefix . '.' . $sd['shortDomain']); ?></code></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row" class="small">TXT value</th>
                                                    <td><code class="small"><?php echo g2ml_sanitiseOutput($sd['verificationToken']); ?></code></td>
                                                </tr>
                                                <tr>
                                                    <th scope="row" class="small">Routing</th>
                                                    <td><code class="small">CNAME → g2my.link (subdomain) or A/ALIAS → your Dreamhost server IP (apex/root)</code></td>
                                                </tr>
                                            </tbody>
                                        </table>
                                    </div>
                                </details>
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
        <!-- Add Short Domain Form                                             -->
        <!-- ================================================================ -->
        <div class="card shadow-sm mb-4">
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
                                'helpText' => 'e.g., mylinks.co — you will be shown DNS records to verify ownership after adding.',
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

        <!-- ================================================================ -->
        <!-- DNS provider hints (reference)                                    -->
        <!-- ================================================================ -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0"><i class="fas fa-book" aria-hidden="true"></i> DNS Provider Reference</h2>
            </div>
            <div class="card-body">
                <?php echo g2ml_dnsProviderHintsHTML(); ?>
                <p class="text-body-secondary small mb-0">
                    TLS/HTTPS for custom domains is currently provisioned manually per domain — see
                    <a href="https://github.com/MWBMPartners/Go2My.Link/blob/main/docs/CUSTOM_DOMAINS.md#tls--https">docs/CUSTOM_DOMAINS.md</a>
                    for the current manual process and the planned automated (Cloudflare-for-SaaS) path.
                </p>
            </div>
        </div>

    </div>
</section>
