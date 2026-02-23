<?php
/**
 * ============================================================================
 * 🏢 Go2My.Link — Organisation Overview (Admin Dashboard)
 * ============================================================================
 *
 * Shows the user's current organisation details, stats, and quick links
 * to management pages. Users in [default] org see a "Create Organisation" CTA.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.6.0
 * @since      Phase 5
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('org.title') : 'Organisation';
$pageDesc  = function_exists('__') ? __('org.description') : 'Manage your organisation settings and members.';

$currentUser = getCurrentUser();
$orgHandle   = $currentUser['orgHandle'];
$isDefault   = ($orgHandle === '[default]');
$isAdmin     = canManageOrg($orgHandle);
?>

<section class="py-4" aria-labelledby="org-heading">
    <div class="container">

        <?php if ($isDefault): ?>
        <!-- ================================================================ -->
        <!-- No Organisation — Create CTA                                      -->
        <!-- ================================================================ -->
        <div class="text-center py-5">
            <i class="fas fa-building fa-4x text-body-secondary mb-4" aria-hidden="true"></i>
            <h1 id="org-heading" class="h2 mb-3">
                <?php echo function_exists('__') ? __('org.no_org_heading') : 'No Organisation'; ?>
            </h1>
            <p class="text-body-secondary mb-4" style="max-width:500px; margin:0 auto;">
                <?php echo function_exists('__') ? __('org.no_org_desc') : 'You\'re not currently part of an organisation. Create one to manage team members, custom domains, and branded short links.'; ?>
            </p>
            <a href="/org/create" class="btn btn-primary btn-lg">
                <i class="fas fa-plus-circle" aria-hidden="true"></i>
                <?php echo function_exists('__') ? __('org.create_btn') : 'Create Organisation'; ?>
            </a>
        </div>

        <?php else: ?>
        <!-- ================================================================ -->
        <!-- Organisation Overview                                             -->
        <!-- ================================================================ -->
        <?php
        $org = getOrganisation($orgHandle);

        if ($org === null)
        {
            echo '<div class="alert alert-danger">Organisation not found.</div>';
            return;
        }

        $memberCount    = getOrgMemberCount($orgHandle);
        $domains        = getOrgDomains($orgHandle);
        $shortDomains   = getOrgShortDomains($orgHandle);
        $linkCount      = dbSelectOne(
            "SELECT COUNT(*) AS cnt FROM tblShortURLs WHERE orgHandle = ?",
            's',
            [$orgHandle]
        );
        $totalLinks = (int) ($linkCount['cnt'] ?? 0);
        ?>

        <h1 id="org-heading" class="h2 mb-4">
            <i class="fas fa-building" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($org['orgName']); ?>
        </h1>

        <!-- Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <i class="fas fa-users fa-2x text-primary mb-2" aria-hidden="true"></i>
                        <h2 class="h4 mb-0"><?php echo number_format($memberCount); ?></h2>
                        <p class="text-body-secondary small mb-0">Members</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <i class="fas fa-link fa-2x text-success mb-2" aria-hidden="true"></i>
                        <h2 class="h4 mb-0"><?php echo number_format($totalLinks); ?></h2>
                        <p class="text-body-secondary small mb-0">Short Links</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <i class="fas fa-globe fa-2x text-info mb-2" aria-hidden="true"></i>
                        <h2 class="h4 mb-0"><?php echo number_format(count($domains)); ?></h2>
                        <p class="text-body-secondary small mb-0">Custom Domains</p>
                    </div>
                </div>
            </div>
            <div class="col-sm-6 col-lg-3">
                <div class="card shadow-sm text-center">
                    <div class="card-body">
                        <i class="fas fa-bolt fa-2x text-warning mb-2" aria-hidden="true"></i>
                        <h2 class="h4 mb-0"><?php echo number_format(count($shortDomains)); ?></h2>
                        <p class="text-body-secondary small mb-0">Short Domains</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Organisation Details Card -->
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-info-circle" aria-hidden="true"></i> Details
                        </h2>
                        <?php if ($isAdmin): ?>
                        <a href="/org/settings" class="btn btn-outline-primary btn-sm">
                            <i class="fas fa-cog" aria-hidden="true"></i> Settings
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Handle</dt>
                            <dd class="col-sm-8"><code><?php echo g2ml_sanitiseOutput($org['orgHandle']); ?></code></dd>

                            <dt class="col-sm-4">Tier</dt>
                            <dd class="col-sm-8">
                                <span class="badge bg-secondary"><?php echo g2ml_sanitiseOutput($org['tierName'] ?? $org['tierID']); ?></span>
                            </dd>

                            <?php if (!empty($org['orgURL'])): ?>
                            <dt class="col-sm-4">Website</dt>
                            <dd class="col-sm-8">
                                <a href="<?php echo g2ml_sanitiseOutput($org['orgURL']); ?>" target="_blank" rel="noopener">
                                    <?php echo g2ml_sanitiseOutput($org['orgURL']); ?>
                                </a>
                            </dd>
                            <?php endif; ?>

                            <?php if (!empty($org['orgDescription'])): ?>
                            <dt class="col-sm-4">Description</dt>
                            <dd class="col-sm-8"><?php echo g2ml_sanitiseOutput($org['orgDescription']); ?></dd>
                            <?php endif; ?>

                            <dt class="col-sm-4">Created</dt>
                            <dd class="col-sm-8">
                                <time datetime="<?php echo g2ml_sanitiseOutput($org['createdAt']); ?>">
                                    <?php echo date('j M Y', strtotime($org['createdAt'])); ?>
                                </time>
                            </dd>
                        </dl>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="col-lg-6">
                <div class="card shadow-sm h-100">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-th-large" aria-hidden="true"></i> Management
                        </h2>
                    </div>
                    <div class="list-group list-group-flush">
                        <?php if ($isAdmin): ?>
                        <a href="/org/members" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-users fa-fw" aria-hidden="true"></i> Members</span>
                            <span class="badge bg-primary rounded-pill"><?php echo number_format($memberCount); ?></span>
                        </a>
                        <a href="/org/domains" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-globe fa-fw" aria-hidden="true"></i> Custom Domains</span>
                            <span class="badge bg-primary rounded-pill"><?php echo number_format(count($domains)); ?></span>
                        </a>
                        <a href="/org/short-domains" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-bolt fa-fw" aria-hidden="true"></i> Short Domains</span>
                            <span class="badge bg-primary rounded-pill"><?php echo number_format(count($shortDomains)); ?></span>
                        </a>
                        <a href="/org/settings" class="list-group-item list-group-item-action">
                            <i class="fas fa-cog fa-fw" aria-hidden="true"></i> Organisation Settings
                        </a>
                        <?php else: ?>
                        <div class="list-group-item text-body-secondary">
                            <i class="fas fa-lock fa-fw" aria-hidden="true"></i>
                            Organisation management requires Admin permissions.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <?php endif; ?>

    </div>
</section>
