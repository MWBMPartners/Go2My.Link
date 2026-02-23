<?php
/**
 * ============================================================================
 * 🏠 Go2My.Link — Dashboard Home (Admin)
 * ============================================================================
 *
 * Overview page showing link count, total clicks, recent links, and recent
 * activity for the authenticated user.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.5.0
 * @since      Phase 4
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('dashboard.title') : 'Dashboard';
$pageDesc  = function_exists('__') ? __('dashboard.description') : 'Overview of your short links and activity.';

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

// ============================================================================
// Fetch dashboard stats
// ============================================================================

// Total links created by this user
$linkCountRow = dbSelectOne(
    "SELECT COUNT(*) AS cnt FROM tblShortURLs WHERE createdByUserUID = ?",
    'i',
    [$userUID]
);
$totalLinks = ($linkCountRow !== null && $linkCountRow !== false) ? (int) $linkCountRow['cnt'] : 0;

// Total clicks across all user's links
$clickCountRow = dbSelectOne(
    "SELECT COALESCE(SUM(clickCount), 0) AS total FROM tblShortURLs WHERE createdByUserUID = ?",
    'i',
    [$userUID]
);
$totalClicks = ($clickCountRow !== null && $clickCountRow !== false) ? (int) $clickCountRow['total'] : 0;

// Active links
$activeCountRow = dbSelectOne(
    "SELECT COUNT(*) AS cnt FROM tblShortURLs WHERE createdByUserUID = ? AND isActive = 1",
    'i',
    [$userUID]
);
$activeLinks = ($activeCountRow !== null && $activeCountRow !== false) ? (int) $activeCountRow['cnt'] : 0;

// Recent links (last 5)
$recentLinks = dbSelect(
    "SELECT shortCode, destinationURL, clickCount, isActive, createdAt
     FROM tblShortURLs
     WHERE createdByUserUID = ?
     ORDER BY createdAt DESC
     LIMIT 5",
    'i',
    [$userUID]
);

if ($recentLinks === false)
{
    $recentLinks = [];
}

// Get default short domain for building full URLs
$shortDomain = function_exists('getDefaultShortDomain')
    ? getDefaultShortDomain($currentUser['orgHandle'])
    : 'g2my.link';
?>

<!-- ====================================================================== -->
<!-- Dashboard Header                                                        -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="dashboard-heading">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1 id="dashboard-heading" class="h2 mb-1">
                    <i class="fas fa-tachometer-alt" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('dashboard.heading') : 'Dashboard'; ?>
                </h1>
                <p class="text-body-secondary mb-0">
                    <?php echo function_exists('__')
                        ? __('dashboard.welcome')
                        : 'Welcome back, ' . g2ml_sanitiseOutput($currentUser['firstName']) . '!'; ?>
                </p>
            </div>
            <a href="/links/create" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <?php echo function_exists('__') ? __('dashboard.create_link') : 'Create Link'; ?>
            </a>
        </div>

        <!-- ============================================================== -->
        <!-- Stats Cards                                                     -->
        <!-- ============================================================== -->
        <div class="row g-4 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-link fa-2x text-primary mb-2" aria-hidden="true"></i>
                        <p class="display-6 fw-bold mb-0"><?php echo number_format($totalLinks); ?></p>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('dashboard.stat_total_links') : 'Total Links'; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-mouse-pointer fa-2x text-success mb-2" aria-hidden="true"></i>
                        <p class="display-6 fw-bold mb-0"><?php echo number_format($totalClicks); ?></p>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('dashboard.stat_total_clicks') : 'Total Clicks'; ?>
                        </p>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card shadow-sm h-100">
                    <div class="card-body text-center">
                        <i class="fas fa-check-circle fa-2x text-info mb-2" aria-hidden="true"></i>
                        <p class="display-6 fw-bold mb-0"><?php echo number_format($activeLinks); ?></p>
                        <p class="text-body-secondary mb-0">
                            <?php echo function_exists('__') ? __('dashboard.stat_active_links') : 'Active Links'; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- Recent Links                                                    -->
        <!-- ============================================================== -->
        <div class="card shadow-sm">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h2 class="h5 mb-0">
                    <i class="fas fa-clock" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('dashboard.recent_links') : 'Recent Links'; ?>
                </h2>
                <a href="/links" class="btn btn-sm btn-outline-primary">
                    <?php echo function_exists('__') ? __('dashboard.view_all') : 'View All'; ?>
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (count($recentLinks) === 0): ?>
                <div class="p-4 text-center text-body-secondary">
                    <i class="fas fa-link fa-2x mb-2" aria-hidden="true"></i>
                    <p class="mb-2"><?php echo function_exists('__') ? __('dashboard.no_links') : 'No links yet.'; ?></p>
                    <a href="/links/create" class="btn btn-primary btn-sm">
                        <i class="fas fa-plus" aria-hidden="true"></i>
                        <?php echo function_exists('__') ? __('dashboard.create_first') : 'Create Your First Link'; ?>
                    </a>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" aria-label="<?php echo function_exists('__') ? __('dashboard.recent_links') : 'Recent Links'; ?>">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo function_exists('__') ? __('dashboard.col_short_url') : 'Short URL'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('dashboard.col_destination') : 'Destination'; ?></th>
                                <th scope="col" class="text-center"><?php echo function_exists('__') ? __('dashboard.col_clicks') : 'Clicks'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('dashboard.col_status') : 'Status'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('dashboard.col_created') : 'Created'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentLinks as $link): ?>
                            <tr>
                                <td>
                                    <a href="https://<?php echo g2ml_sanitiseOutput($shortDomain . '/' . $link['shortCode']); ?>"
                                       target="_blank" rel="noopener noreferrer" class="fw-bold text-decoration-none">
                                        <?php echo g2ml_sanitiseOutput($shortDomain . '/' . $link['shortCode']); ?>
                                    </a>
                                </td>
                                <td class="text-truncate" style="max-width:250px;" title="<?php echo g2ml_sanitiseOutput($link['destinationURL']); ?>">
                                    <?php echo g2ml_sanitiseOutput($link['destinationURL']); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?php echo number_format((int) $link['clickCount']); ?></span>
                                </td>
                                <td>
                                    <?php if ((int) $link['isActive']): ?>
                                    <span class="badge bg-success"><?php echo function_exists('__') ? __('dashboard.active') : 'Active'; ?></span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo function_exists('__') ? __('dashboard.inactive') : 'Inactive'; ?></span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <time datetime="<?php echo g2ml_sanitiseOutput($link['createdAt']); ?>">
                                        <?php echo date('j M Y', strtotime($link['createdAt'])); ?>
                                    </time>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</section>
