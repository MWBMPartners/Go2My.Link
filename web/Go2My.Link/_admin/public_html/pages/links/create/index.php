<?php
/**
 * ============================================================================
 * ✨ Go2My.Link — Create Link (Admin Dashboard)
 * ============================================================================
 *
 * Full link creation form for authenticated users. Includes title, notes,
 * category, date range, and active toggle (options not available to
 * anonymous users on the homepage).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    0.5.0
 * @since      Phase 4
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('create_link.title') : 'Create Link';
$pageDesc  = function_exists('__') ? __('create_link.description') : 'Create a new short link with full options.';

$currentUser = getCurrentUser();

// Load the shorturl_create.php if not already loaded
$shorturlPath = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . '_functions' . DIRECTORY_SEPARATOR . 'shorturl_create.php';

if (file_exists($shorturlPath) && !function_exists('createShortURL'))
{
    require_once $shorturlPath;
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
$resultURL   = '';
$resultCode  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'create_link_form'))
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

        $options = [
            'userUID'    => $currentUser['userUID'],
            'orgHandle'  => $currentUser['orgHandle'],
            'title'      => $title !== '' ? $title : null,
            'notes'      => $notes !== '' ? $notes : null,
            'categoryID' => $categoryID !== '' ? $categoryID : null,
            'startDate'  => $startDate !== '' ? $startDate : null,
            'endDate'    => $endDate !== '' ? $endDate : null,
        ];

        if (function_exists('createShortURL'))
        {
            $result = createShortURL($destinationURL, $options);

            if ($result['success'])
            {
                // Set isActive if unchecked (createShortURL defaults to active)
                if (!$isActive && !empty($result['shortCode']))
                {
                    dbUpdate(
                        "UPDATE tblShortURLs SET isActive = 0 WHERE shortCode = ?",
                        's',
                        [$result['shortCode']]
                    );
                }

                $formSuccess = true;
                $resultURL   = $result['shortURL'] ?? '';
                $resultCode  = $result['shortCode'] ?? '';
            }
            else
            {
                $formError = $result['error'] ?? 'Failed to create link.';
            }
        }
        else
        {
            $formError = 'Link creation service unavailable. Please try again later.';
        }
    }
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="create-heading">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 id="create-heading" class="h2 mb-0">
                <i class="fas fa-plus-circle" aria-hidden="true"></i>
                <?php echo function_exists('__') ? __('create_link.heading') : 'Create a New Link'; ?>
            </h1>
            <a href="/links" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to Links
            </a>
        </div>

        <div class="row justify-content-center">
            <div class="col-lg-8">

                <?php if ($formSuccess) { ?>
                <!-- Success Result -->
                <div class="card shadow-sm border-success">
                    <div class="card-body p-4 text-center">
                        <i class="fas fa-check-circle fa-3x text-success mb-3" aria-hidden="true"></i>
                        <h2 class="h4 mb-3">Link Created!</h2>
                        <div class="input-group input-group-lg mb-3 mx-auto" style="max-width:500px;">
                            <input type="text" class="form-control text-center fw-bold" id="result-url"
                                   value="<?php echo g2ml_sanitiseOutput($resultURL); ?>" readonly
                                   aria-label="<?php echo function_exists('__') ? __('create_link.result_url') : 'Created short URL'; ?>">
                            <button class="btn btn-primary" type="button" id="copy-btn"
                                    aria-label="<?php echo function_exists('__') ? __('create_link.copy_url') : 'Copy short URL to clipboard'; ?>"
                                    onclick="navigator.clipboard.writeText(document.getElementById('result-url').value).then(function(){var b=document.getElementById('copy-btn');b.textContent='\u2713 Copied!';var s=document.getElementById('global-status');if(s){s.textContent='URL copied to clipboard';}}).catch(function(){var s=document.getElementById('global-status');if(s){s.textContent='Failed to copy. Please select and copy manually.';}})">
                                <i class="fas fa-copy" aria-hidden="true"></i> Copy
                            </button>
                        </div>
                        <div class="d-flex justify-content-center gap-2">
                            <a href="/links/create" class="btn btn-outline-primary">
                                <i class="fas fa-plus" aria-hidden="true"></i> Create Another
                            </a>
                            <a href="/links" class="btn btn-outline-secondary">
                                <i class="fas fa-list" aria-hidden="true"></i> View All Links
                            </a>
                        </div>
                    </div>
                </div>

                <?php } else { ?>
                <!-- Create Form -->
                <div class="card shadow-sm">
                    <div class="card-body p-4">

                        <?php if ($formError !== '') { ?>
                        <div class="alert alert-danger" role="alert">
                            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                            <?php echo g2ml_sanitiseOutput($formError); ?>
                        </div>
                        <?php } ?>

                        <form action="/links/create" method="POST" id="create-link-form" novalidate>
                            <?php echo g2ml_csrfField('create_link_form'); ?>

                            <?php
                            echo formField([
                                'id'          => 'destination-url',
                                'name'        => 'destination_url',
                                'label'       => 'Destination URL',
                                'type'        => 'url',
                                'placeholder' => 'https://example.com/your-long-url',
                                'required'    => true,
                                'helpText'    => 'The URL you want to shorten. Must start with http:// or https://.',
                                'value'       => isset($_POST['destination_url']) ? g2ml_sanitiseOutput($_POST['destination_url']) : '',
                            ]);

                            echo formField([
                                'id'          => 'link-title',
                                'name'        => 'title',
                                'label'       => 'Title (Optional)',
                                'type'        => 'text',
                                'placeholder' => 'My Link',
                                'required'    => false,
                                'helpText'    => 'A descriptive title for your own reference.',
                                'value'       => isset($_POST['title']) ? g2ml_sanitiseOutput($_POST['title']) : '',
                            ]);

                            echo formField([
                                'id'          => 'link-notes',
                                'name'        => 'notes',
                                'label'       => 'Notes (Optional)',
                                'type'        => 'textarea',
                                'placeholder' => 'Internal notes about this link...',
                                'required'    => false,
                                'rows'        => 3,
                            ]);
                            ?>

                            <!-- Category Dropdown -->
                            <div class="mb-3">
                                <label for="category-id" class="form-label">Category (Optional)</label>
                                <select class="form-select" id="category-id" name="category_id">
                                    <option value="">No category</option>
                                    <?php foreach ($categories as $cat) { ?>
                                    <option value="<?php echo g2ml_sanitiseOutput($cat['categoryID']); ?>"
                                        <?php echo (isset($_POST['category_id']) && $_POST['category_id'] === $cat['categoryID']) ? 'selected' : ''; ?>>
                                        <?php echo g2ml_sanitiseOutput($cat['categoryName']); ?>
                                    </option>
                                    <?php } ?>
                                </select>
                            </div>

                            <!-- Date Range -->
                            <div class="row">
                                <div class="col-md-6">
                                    <?php
                                    echo formField([
                                        'id'       => 'start-date',
                                        'name'     => 'start_date',
                                        'label'    => 'Start Date (Optional)',
                                        'type'     => 'datetime-local',
                                        'required' => false,
                                        'helpText' => 'Link won\'t be active before this date.',
                                    ]);
                                    ?>
                                </div>
                                <div class="col-md-6">
                                    <?php
                                    echo formField([
                                        'id'       => 'end-date',
                                        'name'     => 'end_date',
                                        'label'    => 'End Date (Optional)',
                                        'type'     => 'datetime-local',
                                        'required' => false,
                                        'helpText' => 'Link will expire after this date.',
                                    ]);
                                    ?>
                                </div>
                            </div>

                            <!-- Active Toggle -->
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="is-active" name="is_active" value="1" checked>
                                <label class="form-check-label" for="is-active">Active</label>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-plus" aria-hidden="true"></i> Create Short Link
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php } ?>

            </div>
        </div>
    </div>
</section>
