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
 * 🌐 Go2My.Link — Custom Domain Management (Admin Dashboard) — DEPRECATED
 * ============================================================================
 *
 * ⚠️  DEPRECATED (GT-6, docs/LAUNCH_PLAN_2026-07-09.md §5 / issue #91) —
 * VIEW / VERIFY / REMOVE only for any row an org already has. Verification
 * here (verifyDomain(), tblOrgDomains) has NEVER had any routing effect —
 * nothing in the redirect hot path or the Component C.4 (#46) custom-domain
 * LinksPage fallback reads this table. Live domain routing AND the
 * LinksPage fallback designation both live entirely on the Short Domains
 * page (org/short-domains/index.php, tblOrgShortDomains) — see that page's
 * own docblock. The "Add Domain" form below has been removed and the
 * add_domain action is rejected server-side so this table cannot grow any
 * further; see web/_sql/migrations/018_deprecate_org_domains.sql for the
 * full write-up and the owner-reviewed reconciliation path for any rows
 * already here.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.7.0
 * @since      Phase 5; deprecated GT-6 (v1.1.0 — Phase 7)
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('org.domains_title');
} else {
    $pageTitle = 'Custom Domains';
}
if (function_exists('__')) {
    $pageDesc = __('org.domains_description');
} else {
    $pageDesc = 'Manage your organisation\'s custom domains.';
}

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

// ============================================================================
// Handle POST actions
// ============================================================================
// 🔒 GT-6 — this page is DEPRECATED (see docblock above): only 'verify_domain'
// and 'remove_domain' remain reachable, so an org can still see, harmlessly
// re-check, or clean up any row it already has. 'add_domain' is explicitly
// rejected server-side below — the page no longer renders that form at all
// (so no valid CSRF token for it is ever issued in the first place), but the
// rejection is kept explicit rather than relying on that alone.
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken  = $_POST['_csrf_token'] ?? '';
    $actionType = $_POST['action_type'] ?? '';
    $domainUID  = (int) ($_POST['domain_uid'] ?? 0);

    // ========================================================================
    // 🔒 #147 — CSRF form-name namespacing
    // ========================================================================
    // "verify" and "remove" are rendered once PER ROW (one form per custom
    // domain), so their CSRF tokens must be namespaced by the row's own
    // domainUID. Otherwise g2ml_generateCSRFToken() (single-slot-per-form-name,
    // see security.php) overwrites the previous row's token every time the
    // loop renders the next row's form, and only the LAST-rendered row's
    // action would still validate.
    // ========================================================================

    if ($actionType === 'verify_domain')
    {
        $csrfFormName = 'verify_domain_' . $domainUID;
    }
    elseif ($actionType === 'remove_domain')
    {
        $csrfFormName = 'remove_domain_' . $domainUID;
    }
    else
    {
        $csrfFormName = '';
    }

    if ($actionType === 'add_domain')
    {
        // GT-6: adding new rows to this deprecated table is no longer
        // permitted — direct the org to the page that actually does
        // something (Short Domains) instead of silently accepting the row.
        $actionError = 'Adding new custom domains here has moved. Please use Organisation → Short Domains instead — that is where domain routing and verification actually happen.';
    }
    elseif (!g2ml_validateCSRFToken($csrfToken, $csrfFormName))
    {
        $actionError = 'Session expired. Please try again.';
    }
    else
    {
        switch ($actionType)
        {
            case 'verify_domain':
                // $domainUID was already parsed above to build the namespaced
                // CSRF form name for this row; reused here unchanged.
                $result = verifyDomain($domainUID, $orgHandle);
                if ($result['verified'])
                {
                    $actionSuccess = 'Domain verified successfully! (Note: this table no longer drives any routing — see the notice above.)';
                }
                else
                {
                    $actionError = $result['error'] ?? 'Verification failed.';
                }
                break;

            case 'remove_domain':
                // $domainUID was already parsed above to build the namespaced
                // CSRF form name for this row; reused here unchanged.
                $result = removeOrgDomain($domainUID, $orgHandle);
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
            <i class="fas fa-globe" aria-hidden="true"></i> Custom Domains <span class="badge bg-secondary align-middle">Legacy</span>
        </h1>

        <!-- ================================================================ -->
        <!-- GT-6 deprecation notice                                           -->
        <!-- ================================================================ -->
        <div class="alert alert-warning" role="status">
            <i class="fas fa-info-circle" aria-hidden="true"></i>
            This page is <strong>deprecated</strong> and does not drive any live
            routing — verifying a domain here has no effect on where traffic
            goes. Domain routing, ownership verification, and the LinksPage
            fallback designation are all managed from
            <a href="/org/short-domains">Organisation &rarr; Short Domains</a>.
            You can still view, re-check, or remove any domain already listed
            below, but new domains can no longer be added here.
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

        <?php if (!empty($domains)) { ?>
        <!-- ================================================================ -->
        <!-- DNS Instructions (only shown while legacy rows still exist)       -->
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
                <p class="mb-0 mt-2 text-body-secondary small">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    Reminder: verifying here is cosmetic only — it does not make
                    anything routable. See the notice above.
                </p>
            </div>
        </div>
        <?php } ?>

        <!-- ================================================================ -->
        <!-- Domains Table                                                     -->
        <!-- ================================================================ -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">Your Domains (<?php echo count($domains); ?>)</h2>
            </div>

            <?php if (empty($domains)) { ?>
            <div class="card-body text-center text-body-secondary py-4">
                <i class="fas fa-globe fa-2x mb-2" aria-hidden="true"></i>
                <p class="mb-0">Nothing here. Manage domains from Short Domains instead.</p>
            </div>
            <?php } else { ?>
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
                        <?php foreach ($domains as $d) { ?>
                        <tr>
                            <td><strong><?php echo g2ml_sanitiseOutput($d['domainName']); ?></strong></td>
                            <td><span class="badge bg-info text-dark"><?php echo g2ml_sanitiseOutput($d['domainType']); ?></span></td>
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
                                    <?php if ($d['verificationStatus'] !== 'verified') { ?>
                                    <form action="/org/domains" method="POST" class="d-inline">
                                        <?php echo g2ml_csrfField('verify_domain_' . (int) $d['domainUID']); ?>
                                        <input type="hidden" name="action_type" value="verify_domain">
                                        <input type="hidden" name="domain_uid" value="<?php echo (int) $d['domainUID']; ?>">
                                        <button type="submit" class="btn btn-outline-success btn-sm" title="Verify DNS">
                                            <i class="fas fa-check" aria-hidden="true"></i> Verify
                                        </button>
                                    </form>
                                    <?php } ?>

                                    <form action="/org/domains" method="POST" class="d-inline"
                                          onsubmit="return confirm('Remove this domain?');">
                                        <?php echo g2ml_csrfField('remove_domain_' . (int) $d['domainUID']); ?>
                                        <input type="hidden" name="action_type" value="remove_domain">
                                        <input type="hidden" name="domain_uid" value="<?php echo (int) $d['domainUID']; ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"
                                                title="Remove domain" aria-label="Remove <?php echo g2ml_sanitiseOutput($d['domainName']); ?>">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
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
        <!-- GT-6 — "Add Domain" form removed; this card replaces it           -->
        <!-- ================================================================ -->
        <div class="card shadow-sm border-secondary-subtle">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-arrow-right-to-bracket" aria-hidden="true"></i> This feature has moved
                </h2>
            </div>
            <div class="card-body">
                <p class="mb-2">
                    Adding a new custom domain is now done entirely from
                    <a href="/org/short-domains">Organisation &rarr; Short Domains</a>,
                    which is also where domain ownership verification actually
                    gates live routing (#91) and where a verified domain can be
                    assigned to publish one of your LinksPages at its root (#46).
                </p>
                <a href="/org/short-domains" class="btn btn-primary">
                    <i class="fas fa-bolt" aria-hidden="true"></i> Go to Short Domains
                </a>
            </div>
        </div>

    </div>
</section>
