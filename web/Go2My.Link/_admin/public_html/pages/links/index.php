<?php
/**
 * ============================================================================
 * 🔗 Go2My.Link — Links List (Admin Dashboard)
 * ============================================================================
 *
 * Paginated list of the user's short links with search, filter, and
 * delete actions.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.5.0
 * @since      Phase 4
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('links.title') : 'My Links';
$pageDesc  = function_exists('__') ? __('links.description') : 'Manage your short links.';

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

// ============================================================================
// Handle delete action (POST)
// ============================================================================

$deleteMessage = '';
$deleteError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'delete_link'))
    {
        $deleteError = 'Session expired. Please try again.';
    }
    else
    {
        $deleteCode = g2ml_sanitiseInput($_POST['short_code'] ?? '');

        // Ownership check + soft delete (set isActive = 0)
        $result = dbUpdate(
            "UPDATE tblShortURLs SET isActive = 0 WHERE shortCode = ? AND createdByUserUID = ?",
            'si',
            [$deleteCode, $userUID]
        );

        if ($result !== false && $result > 0)
        {
            logActivity('delete_link', 'success', 200, [
                'userUID'   => $userUID,
                'shortCode' => $deleteCode,
            ]);
            $deleteMessage = 'Link deactivated successfully.';
        }
        else
        {
            $deleteError = 'Could not deactivate link. It may not exist or you may not own it.';
        }
    }
}

// ============================================================================
// Search, filter, pagination parameters
// ============================================================================

$search     = g2ml_sanitiseInput($_GET['search'] ?? '');
$filter     = g2ml_sanitiseInput($_GET['filter'] ?? ''); // 'active', 'inactive', or ''
$page       = max(1, (int) ($_GET['page'] ?? 1));
$perPage    = 20;
$offset     = ($page - 1) * $perPage;

// Build query
$whereConditions = ['s.createdByUserUID = ?'];
$whereTypes      = 'i';
$whereParams     = [$userUID];

if ($search !== '')
{
    $whereConditions[] = '(s.shortCode LIKE ? OR s.destinationURL LIKE ? OR s.title LIKE ?)';
    $searchWild        = '%' . $search . '%';
    $whereTypes       .= 'sss';
    $whereParams[]     = $searchWild;
    $whereParams[]     = $searchWild;
    $whereParams[]     = $searchWild;
}

if ($filter === 'active')
{
    $whereConditions[] = 's.isActive = 1';
}
elseif ($filter === 'inactive')
{
    $whereConditions[] = 's.isActive = 0';
}

$whereSQL = implode(' AND ', $whereConditions);

// Get total count for pagination
$countRow = dbSelectOne(
    "SELECT COUNT(*) AS cnt FROM tblShortURLs s WHERE " . $whereSQL,
    $whereTypes,
    $whereParams
);
$totalCount = ($countRow !== null && $countRow !== false) ? (int) $countRow['cnt'] : 0;
$totalPages = max(1, (int) ceil($totalCount / $perPage));

// Fetch links for current page
$linksParams   = $whereParams;
$linksTypes    = $whereTypes . 'ii';
$linksParams[] = $perPage;
$linksParams[] = $offset;

$links = dbSelect(
    "SELECT s.shortCode, s.destinationURL, s.title, s.clickCount,
            s.isActive, s.createdAt, s.startDate, s.endDate,
            c.categoryName
     FROM tblShortURLs s
     LEFT JOIN tblCategories c ON s.categoryID = c.categoryID
     WHERE " . $whereSQL . "
     ORDER BY s.createdAt DESC
     LIMIT ? OFFSET ?",
    $linksTypes,
    $linksParams
);

if ($links === false)
{
    $links = [];
}

// Get default short domain
$shortDomain = function_exists('getDefaultShortDomain')
    ? getDefaultShortDomain($currentUser['orgHandle'])
    : 'g2my.link';
?>

<!-- ====================================================================== -->
<!-- Links Header                                                            -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="links-heading">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 id="links-heading" class="h2 mb-0">
                <i class="fas fa-link" aria-hidden="true"></i>
                <?php echo function_exists('__') ? __('links.heading') : 'My Links'; ?>
                <span class="badge bg-secondary fs-6"><?php echo number_format($totalCount); ?></span>
            </h1>
            <a href="/links/create" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <?php echo function_exists('__') ? __('links.create_new') : 'Create Link'; ?>
            </a>
        </div>

        <?php if ($deleteMessage !== '') { ?>
        <div class="alert alert-success alert-dismissible fade show" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($deleteMessage); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php } ?>

        <?php if ($deleteError !== '') { ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($deleteError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php } ?>

        <!-- ============================================================== -->
        <!-- Search & Filter Bar                                             -->
        <!-- ============================================================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-body py-3">
                <form action="/links" method="GET" class="row g-2 align-items-end">
                    <div class="col-md-6">
                        <label for="search-input" class="form-label visually-hidden">Search</label>
                        <input type="text" class="form-control" id="search-input" name="search"
                               placeholder="<?php echo function_exists('__') ? __('links.search_placeholder') : 'Search by short code, URL, or title...'; ?>"
                               value="<?php echo g2ml_sanitiseOutput($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label for="filter-select" class="form-label visually-hidden">Filter</label>
                        <select class="form-select" id="filter-select" name="filter">
                            <option value="" <?php echo $filter === '' ? 'selected' : ''; ?>>All Links</option>
                            <option value="active" <?php echo $filter === 'active' ? 'selected' : ''; ?>>Active Only</option>
                            <option value="inactive" <?php echo $filter === 'inactive' ? 'selected' : ''; ?>>Inactive Only</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <button type="submit" class="btn btn-outline-primary w-100">
                            <i class="fas fa-search" aria-hidden="true"></i> Search
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- Links Table                                                     -->
        <!-- ============================================================== -->
        <div class="card shadow-sm">
            <div class="card-body p-0">
                <?php if (count($links) === 0) { ?>
                <div class="p-4 text-center text-body-secondary">
                    <i class="fas fa-link fa-2x mb-2" aria-hidden="true"></i>
                    <p class="mb-0"><?php echo function_exists('__') ? __('links.no_links') : 'No links found.'; ?></p>
                </div>
                <?php } else { ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0" aria-label="<?php echo function_exists('__') ? __('links.heading') : 'My Links'; ?>">
                        <thead>
                            <tr>
                                <th scope="col">Short URL</th>
                                <th scope="col">Destination</th>
                                <th scope="col" class="text-center">Clicks</th>
                                <th scope="col">Status</th>
                                <th scope="col">Created</th>
                                <th scope="col" class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($links as $link) { ?>
                            <tr>
                                <td>
                                    <a href="https://<?php echo g2ml_sanitiseOutput($shortDomain . '/' . $link['shortCode']); ?>"
                                       target="_blank" rel="noopener noreferrer" class="fw-bold text-decoration-none">
                                        <?php echo g2ml_sanitiseOutput($link['shortCode']); ?>
                                    </a>
                                    <?php if (!empty($link['title'])) { ?>
                                    <br><small class="text-body-secondary"><?php echo g2ml_sanitiseOutput($link['title']); ?></small>
                                    <?php } ?>
                                </td>
                                <td class="text-truncate" style="max-width:200px;" title="<?php echo g2ml_sanitiseOutput($link['destinationURL']); ?>">
                                    <?php echo g2ml_sanitiseOutput($link['destinationURL']); ?>
                                </td>
                                <td class="text-center">
                                    <span class="badge bg-secondary"><?php echo number_format((int) $link['clickCount']); ?></span>
                                </td>
                                <td>
                                    <?php if ((int) $link['isActive']) { ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php } else { ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php } ?>
                                </td>
                                <td>
                                    <time datetime="<?php echo g2ml_sanitiseOutput($link['createdAt']); ?>">
                                        <?php echo date('j M Y', strtotime($link['createdAt'])); ?>
                                    </time>
                                </td>
                                <td class="text-end">
                                    <a href="/links/edit?id=<?php echo urlencode($link['shortCode']); ?>"
                                       class="btn btn-sm btn-outline-primary"
                                       title="Edit" aria-label="Edit <?php echo g2ml_sanitiseOutput($link['shortCode']); ?>">
                                        <i class="fas fa-edit" aria-hidden="true"></i>
                                    </a>
                                    <form action="/links" method="POST" class="d-inline"
                                          onsubmit="return confirm('Deactivate this link?');">
                                        <?php echo g2ml_csrfField('delete_link'); ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="short_code" value="<?php echo g2ml_sanitiseOutput($link['shortCode']); ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                title="Deactivate" aria-label="Deactivate <?php echo g2ml_sanitiseOutput($link['shortCode']); ?>">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
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

            <?php if ($totalPages > 1) { ?>
            <!-- Pagination -->
            <div class="card-footer">
                <nav aria-label="Links pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <li class="page-item <?php echo $page <= 1 ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page - 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                                &laquo; Prev
                            </a>
                        </li>

                        <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++) { ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $i; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                        <?php } ?>

                        <li class="page-item <?php echo $page >= $totalPages ? 'disabled' : ''; ?>">
                            <a class="page-link" href="?page=<?php echo $page + 1; ?>&search=<?php echo urlencode($search); ?>&filter=<?php echo urlencode($filter); ?>">
                                Next &raquo;
                            </a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php } ?>
        </div>
    </div>
</section>
