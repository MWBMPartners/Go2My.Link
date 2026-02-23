<?php
/**
 * ============================================================================
 * ✏️ Go2My.Link — Edit Link (Admin Dashboard)
 * ============================================================================
 *
 * Edit an existing short link. Ownership-checked — only the creator can edit.
 * Short code is read-only; all other fields are editable.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.5.0
 * @since      Phase 4
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('edit_link.title') : 'Edit Link';
$pageDesc  = function_exists('__') ? __('edit_link.description') : 'Edit your short link settings.';

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

// ============================================================================
// Load the link
// ============================================================================

$shortCode = g2ml_sanitiseInput($_GET['id'] ?? '');
$linkData  = null;
$loadError = '';

if ($shortCode === '')
{
    $loadError = 'No link specified.';
}
else
{
    $linkData = dbSelectOne(
        "SELECT s.shortURLUID, s.shortCode, s.destinationURL, s.title,
                s.notes, s.categoryID, s.startDate, s.endDate,
                s.isActive, s.createdAt, s.clickCount, s.orgHandle
         FROM tblShortURLs s
         WHERE s.shortCode = ? AND s.createdByUserUID = ?
         LIMIT 1",
        'si',
        [$shortCode, $userUID]
    );

    if ($linkData === null || $linkData === false)
    {
        $loadError = 'Link not found or you do not have permission to edit it.';
        $linkData  = null;
    }
}

// Fetch categories for dropdown
$categories = dbSelect(
    "SELECT categoryID, categoryName FROM tblCategories WHERE orgHandle = ? ORDER BY categoryName ASC",
    's',
    [$currentUser['orgHandle']]
);

if ($categories === false)
{
    $categories = [];
}

// ============================================================================
// Process form submission
// ============================================================================

$formError   = '';
$formSuccess = false;

if ($linkData !== null && $_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'edit_link_form'))
    {
        $formError = 'Your session has expired. Please reload the page and try again.';
    }
    else
    {
        $destinationURL = trim($_POST['destination_url'] ?? '');
        $title          = trim(g2ml_sanitiseInput($_POST['title'] ?? ''));
        $notes          = trim(g2ml_sanitiseInput($_POST['notes'] ?? ''));
        $categoryID     = g2ml_sanitiseInput($_POST['category_id'] ?? '');
        $startDate      = g2ml_sanitiseInput($_POST['start_date'] ?? '');
        $endDate        = g2ml_sanitiseInput($_POST['end_date'] ?? '');
        $isActive       = isset($_POST['is_active']) ? 1 : 0;

        // Validate destination URL
        $sanitisedURL = g2ml_sanitiseURL($destinationURL);

        if ($sanitisedURL === false)
        {
            $formError = 'Invalid URL format. Please enter a valid HTTP or HTTPS URL.';
        }
        else
        {
            $result = dbUpdate(
                "UPDATE tblShortURLs SET
                    destinationURL = ?, title = ?, notes = ?, categoryID = ?,
                    startDate = ?, endDate = ?, isActive = ?
                 WHERE shortCode = ? AND createdByUserUID = ?",
                'ssssssisi',
                [
                    $sanitisedURL,
                    $title !== '' ? $title : null,
                    $notes !== '' ? $notes : null,
                    $categoryID !== '' ? $categoryID : null,
                    $startDate !== '' ? $startDate : null,
                    $endDate !== '' ? $endDate : null,
                    $isActive,
                    $shortCode,
                    $userUID,
                ]
            );

            if ($result !== false)
            {
                // Refresh link data
                $linkData['destinationURL'] = $sanitisedURL;
                $linkData['title']          = $title !== '' ? $title : null;
                $linkData['notes']          = $notes !== '' ? $notes : null;
                $linkData['categoryID']     = $categoryID !== '' ? $categoryID : null;
                $linkData['startDate']      = $startDate !== '' ? $startDate : null;
                $linkData['endDate']        = $endDate !== '' ? $endDate : null;
                $linkData['isActive']       = $isActive;

                $formSuccess = true;

                logActivity('edit_link', 'success', 200, [
                    'userUID'   => $userUID,
                    'shortCode' => $shortCode,
                ]);
            }
            else
            {
                $formError = 'Failed to update link. Please try again.';
            }
        }
    }
}

// Get default short domain for display
$shortDomain = function_exists('getDefaultShortDomain')
    ? getDefaultShortDomain($currentUser['orgHandle'])
    : 'g2my.link';
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="edit-heading">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 id="edit-heading" class="h2 mb-0">
                <i class="fas fa-edit" aria-hidden="true"></i>
                <?php echo function_exists('__') ? __('edit_link.heading') : 'Edit Link'; ?>
            </h1>
            <a href="/links" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Links
            </a>
        </div>

        <?php if ($loadError !== ''): ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($loadError); ?>
        </div>

        <?php elseif ($linkData !== null): ?>
        <div class="row justify-content-center">
            <div class="col-lg-8">

                <?php if ($formSuccess): ?>
                <div class="alert alert-success" role="status">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    Link updated successfully.
                </div>
                <?php endif; ?>

                <?php if ($formError !== ''): ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo g2ml_sanitiseOutput($formError); ?>
                </div>
                <?php endif; ?>

                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <!-- Read-only short code display -->
                        <div class="mb-3">
                            <label for="short-url-display" class="form-label fw-bold">Short URL</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="short-url-display" readonly
                                       value="<?php echo g2ml_sanitiseOutput('https://' . $shortDomain . '/' . $linkData['shortCode']); ?>">
                                <span class="input-group-text">
                                    <span class="badge bg-secondary"><?php echo number_format((int) $linkData['clickCount']); ?> clicks</span>
                                </span>
                            </div>
                        </div>

                        <form action="/links/edit?id=<?php echo urlencode($shortCode); ?>" method="POST" id="edit-link-form" novalidate>
                            <?php echo g2ml_csrfField('edit_link_form'); ?>

                            <?php
                            echo formField([
                                'id'          => 'destination-url',
                                'name'        => 'destination_url',
                                'label'       => 'Destination URL',
                                'type'        => 'url',
                                'placeholder' => 'https://example.com/your-long-url',
                                'required'    => true,
                                'value'       => g2ml_sanitiseOutput($linkData['destinationURL']),
                            ]);

                            echo formField([
                                'id'          => 'link-title',
                                'name'        => 'title',
                                'label'       => 'Title (Optional)',
                                'type'        => 'text',
                                'placeholder' => 'My Link',
                                'required'    => false,
                                'value'       => g2ml_sanitiseOutput($linkData['title'] ?? ''),
                            ]);

                            echo formField([
                                'id'          => 'link-notes',
                                'name'        => 'notes',
                                'label'       => 'Notes (Optional)',
                                'type'        => 'textarea',
                                'placeholder' => 'Internal notes about this link...',
                                'required'    => false,
                                'rows'        => 3,
                                'value'       => g2ml_sanitiseOutput($linkData['notes'] ?? ''),
                            ]);
                            ?>

                            <!-- Category Dropdown -->
                            <div class="mb-3">
                                <label for="category-id" class="form-label">Category (Optional)</label>
                                <select class="form-select" id="category-id" name="category_id">
                                    <option value="">No category</option>
                                    <?php foreach ($categories as $cat): ?>
                                    <option value="<?php echo g2ml_sanitiseOutput($cat['categoryID']); ?>"
                                        <?php echo ($linkData['categoryID'] === $cat['categoryID']) ? 'selected' : ''; ?>>
                                        <?php echo g2ml_sanitiseOutput($cat['categoryName']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Date Range -->
                            <div class="row">
                                <div class="col-md-6">
                                    <?php
                                    $startVal = !empty($linkData['startDate']) ? date('Y-m-d\TH:i', strtotime($linkData['startDate'])) : '';
                                    echo formField([
                                        'id'       => 'start-date',
                                        'name'     => 'start_date',
                                        'label'    => 'Start Date (Optional)',
                                        'type'     => 'datetime-local',
                                        'required' => false,
                                        'value'    => $startVal,
                                    ]);
                                    ?>
                                </div>
                                <div class="col-md-6">
                                    <?php
                                    $endVal = !empty($linkData['endDate']) ? date('Y-m-d\TH:i', strtotime($linkData['endDate'])) : '';
                                    echo formField([
                                        'id'       => 'end-date',
                                        'name'     => 'end_date',
                                        'label'    => 'End Date (Optional)',
                                        'type'     => 'datetime-local',
                                        'required' => false,
                                        'value'    => $endVal,
                                    ]);
                                    ?>
                                </div>
                            </div>

                            <!-- Active Toggle -->
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is-active" name="is_active" value="1"
                                    <?php echo (int) $linkData['isActive'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="is-active">Active</label>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save" aria-hidden="true"></i> Save Changes
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
</section>
