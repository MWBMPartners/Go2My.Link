<?php
/**
 * ============================================================================
 * Go2My.Link — URL Info / Preview Page (Component A)
 * ============================================================================
 *
 * Displays public information about a short URL. Accepts a short code
 * via the ?code= parameter (set by .htaccess rewrite from /info/CODE)
 * or via a search form.
 *
 * Public view shows: short URL, destination domain (masked path), status,
 * creation date, category. Full destination and analytics require login
 * (Phase 4).
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.4.0
 * @since      Phase 3
 * ============================================================================
 */

$pageTitle = function_exists('__') ? __('info.title') : 'Link Info';
$pageDesc  = function_exists('__') ? __('info.description') : 'Preview and inspect a Go2My.Link short URL before visiting.';

// ============================================================================
// Determine the short code to look up
// ============================================================================
// Accepts:
//   /info/CODE          — from .htaccess rewrite (?code=CODE)
//   /info?url=g2my.link/CODE — parses the short code from a pasted URL
//   /info?code=CODE     — direct query parameter
// ============================================================================

$shortCode  = '';
$linkData   = null;
$infoError  = '';

// Priority 1: ?code= parameter (from .htaccess rewrite or direct)
if (isset($_GET['code']) && $_GET['code'] !== '')
{
    $shortCode = preg_replace('/[^a-zA-Z0-9]/', '', $_GET['code']);
}
// Priority 2: ?url= parameter (parse code from a pasted short URL)
elseif (isset($_GET['url']) && $_GET['url'] !== '')
{
    $inputURL = trim(g2ml_sanitiseInput($_GET['url']));

    // Strip protocol
    $inputURL = preg_replace('#^https?://#i', '', $inputURL);

    // Known short domains
    $shortDomains = ['g2my.link', 'www.g2my.link'];

    // Also add custom org domains from DB
    $customDomains = dbSelect(
        "SELECT shortDomain FROM tblOrgShortDomains WHERE isActive = 1",
        '',
        []
    );

    if ($customDomains !== false && is_array($customDomains))
    {
        foreach ($customDomains as $row)
        {
            $shortDomains[] = strtolower($row['shortDomain']);
        }
    }

    // Try to match domain/code pattern
    foreach ($shortDomains as $domain)
    {
        if (stripos($inputURL, $domain . '/') === 0)
        {
            $codePart = substr($inputURL, strlen($domain) + 1);
            // Extract just the code (stop at ? or # or /)
            $codePart = strtok($codePart, '?#/');
            $shortCode = preg_replace('/[^a-zA-Z0-9]/', '', $codePart);
            break;
        }
    }

    if ($shortCode === '')
    {
        $infoError = function_exists('__')
            ? __('info.error_invalid_url')
            : 'Could not extract a short code from that URL. Please enter a valid Go2My.Link short URL.';
    }
}

// ============================================================================
// Look up the short URL if we have a code
// ============================================================================

if ($shortCode !== '')
{
    $linkData = dbSelectOne(
        "SELECT s.shortCode, s.destinationURL, s.destinationType,
                s.isActive, s.title, s.createdAt,
                s.startDate, s.endDate, s.orgHandle,
                c.categoryName
         FROM tblShortURLs s
         LEFT JOIN tblCategories c ON s.categoryID = c.categoryID
         WHERE s.shortCode = ?
           AND s.orgHandle = '[default]'
         LIMIT 1",
        's',
        [$shortCode]
    );

    if ($linkData === null || $linkData === false)
    {
        $infoError = function_exists('__')
            ? __('info.error_not_found')
            : 'No link found with that short code.';
        $linkData = null;
    }
}

// ============================================================================
// Helper: Mask the destination URL for public display
// ============================================================================
// Shows the domain but masks the path for privacy.
// e.g., https://example.com/very/long/path → example.com/...
// ============================================================================

$maskedDestination = '';

if ($linkData !== null)
{
    $parsed = parse_url($linkData['destinationURL']);
    $host   = $parsed['host'] ?? '';

    // Strip www. for cleaner display
    if (strpos($host, 'www.') === 0)
    {
        $host = substr($host, 4);
    }

    $path = $parsed['path'] ?? '';

    if ($path !== '' && $path !== '/')
    {
        $maskedDestination = $host . '/...';
    }
    else
    {
        $maskedDestination = $host;
    }
}

// ============================================================================
// Determine link status for display
// ============================================================================

$statusBadge = '';
$statusClass = '';

if ($linkData !== null)
{
    $now = time();

    if (!$linkData['isActive'])
    {
        $statusBadge = function_exists('__') ? __('info.status_inactive') : 'Inactive';
        $statusClass = 'bg-secondary';
    }
    elseif ($linkData['endDate'] !== null && strtotime($linkData['endDate']) < $now)
    {
        $statusBadge = function_exists('__') ? __('info.status_expired') : 'Expired';
        $statusClass = 'bg-warning text-dark';
    }
    elseif ($linkData['startDate'] !== null && strtotime($linkData['startDate']) > $now)
    {
        $statusBadge = function_exists('__') ? __('info.status_scheduled') : 'Scheduled';
        $statusClass = 'bg-info';
    }
    else
    {
        $statusBadge = function_exists('__') ? __('info.status_active') : 'Active';
        $statusClass = 'bg-success';
    }
}

// Build the full short URL for display
$shortURL = '';
if ($linkData !== null)
{
    // Use the default short domain for the org
    if (function_exists('getDefaultShortDomain'))
    {
        $shortDomain = getDefaultShortDomain($linkData['orgHandle']);
    }
    else
    {
        $shortDomain = 'g2my.link';
    }
    $shortURL = 'https://' . $shortDomain . '/' . $linkData['shortCode'];
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="info-heading">
    <div class="container">
        <h1 id="info-heading" class="display-4 fw-bold">
            <?php echo function_exists('__') ? __('info.heading') : 'Link Info'; ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php echo function_exists('__') ? __('info.subtitle') : 'Preview a short link before visiting.'; ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Search Form                                                             -->
<!-- ====================================================================== -->
<section class="pb-4" aria-labelledby="search-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 id="search-heading" class="h5 mb-3">
                            <i class="fas fa-search" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('info.search_heading') : 'Look Up a Link'; ?>
                        </h2>

                        <form action="/info" method="GET" id="info-search-form">
                            <?php
                            echo formField([
                                'id'          => 'info-url',
                                'name'        => 'url',
                                'label'       => function_exists('__') ? __('info.search_label') : 'Short URL or Code',
                                'type'        => 'text',
                                'placeholder' => 'g2my.link/abc123',
                                'required'    => true,
                                'helpText'    => function_exists('__') ? __('info.search_help') : 'Enter a Go2My.Link short URL or just the short code.',
                                'value'       => isset($_GET['url']) ? g2ml_sanitiseOutput($_GET['url']) : '',
                            ]);
                            ?>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search" aria-hidden="true"></i>
                                    <?php echo function_exists('__') ? __('info.search_button') : 'Look Up'; ?>
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<?php if ($infoError !== ''): ?>
<!-- ====================================================================== -->
<!-- Error Message                                                           -->
<!-- ====================================================================== -->
<section class="pb-5" aria-label="Error">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                    <?php echo g2ml_sanitiseOutput($infoError); ?>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($linkData !== null): ?>
<!-- ====================================================================== -->
<!-- Link Information Card                                                   -->
<!-- ====================================================================== -->
<section class="pb-5" aria-labelledby="link-info-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <h2 id="link-info-heading" class="h5 mb-4">
                            <i class="fas fa-info-circle" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('info.details_heading') : 'Link Details'; ?>
                        </h2>

                        <dl class="row mb-0">
                            <!-- Short URL -->
                            <dt class="col-sm-4 text-body-secondary">
                                <?php echo function_exists('__') ? __('info.label_short_url') : 'Short URL'; ?>
                            </dt>
                            <dd class="col-sm-8">
                                <a href="<?php echo g2ml_sanitiseOutput($shortURL); ?>"
                                   target="_blank" rel="noopener"
                                   class="fw-bold">
                                    <?php echo g2ml_sanitiseOutput($shortURL); ?>
                                </a>
                            </dd>

                            <!-- Destination -->
                            <dt class="col-sm-4 text-body-secondary">
                                <?php echo function_exists('__') ? __('info.label_destination') : 'Destination'; ?>
                            </dt>
                            <dd class="col-sm-8">
                                <i class="fas fa-external-link-alt text-body-secondary" aria-hidden="true"></i>
                                <?php echo g2ml_sanitiseOutput($maskedDestination); ?>
                            </dd>

                            <!-- Status -->
                            <dt class="col-sm-4 text-body-secondary">
                                <?php echo function_exists('__') ? __('info.label_status') : 'Status'; ?>
                            </dt>
                            <dd class="col-sm-8">
                                <span class="badge <?php echo $statusClass; ?>">
                                    <?php echo g2ml_sanitiseOutput($statusBadge); ?>
                                </span>
                            </dd>

                            <!-- Title (if set) -->
                            <?php if (!empty($linkData['title'])): ?>
                            <dt class="col-sm-4 text-body-secondary">
                                <?php echo function_exists('__') ? __('info.label_title') : 'Title'; ?>
                            </dt>
                            <dd class="col-sm-8">
                                <?php echo g2ml_sanitiseOutput($linkData['title']); ?>
                            </dd>
                            <?php endif; ?>

                            <!-- Category (if set) -->
                            <?php if (!empty($linkData['categoryName'])): ?>
                            <dt class="col-sm-4 text-body-secondary">
                                <?php echo function_exists('__') ? __('info.label_category') : 'Category'; ?>
                            </dt>
                            <dd class="col-sm-8">
                                <?php echo g2ml_sanitiseOutput($linkData['categoryName']); ?>
                            </dd>
                            <?php endif; ?>

                            <!-- Created Date -->
                            <dt class="col-sm-4 text-body-secondary">
                                <?php echo function_exists('__') ? __('info.label_created') : 'Created'; ?>
                            </dt>
                            <dd class="col-sm-8">
                                <time datetime="<?php echo g2ml_sanitiseOutput($linkData['createdAt']); ?>">
                                    <?php echo g2ml_sanitiseOutput(
                                        date('j M Y', strtotime($linkData['createdAt']))
                                    ); ?>
                                </time>
                            </dd>

                            <!-- Date Range (if set) -->
                            <?php if (!empty($linkData['startDate']) || !empty($linkData['endDate'])): ?>
                            <dt class="col-sm-4 text-body-secondary">
                                <?php echo function_exists('__') ? __('info.label_active_period') : 'Active Period'; ?>
                            </dt>
                            <dd class="col-sm-8">
                                <?php
                                $start = !empty($linkData['startDate'])
                                    ? date('j M Y', strtotime($linkData['startDate']))
                                    : '...';
                                $end = !empty($linkData['endDate'])
                                    ? date('j M Y', strtotime($linkData['endDate']))
                                    : '...';
                                echo g2ml_sanitiseOutput($start . ' — ' . $end);
                                ?>
                            </dd>
                            <?php endif; ?>
                        </dl>
                    </div>

                    <!-- Action Footer -->
                    <div class="card-footer bg-transparent text-center py-3">
                        <a href="<?php echo g2ml_sanitiseOutput($shortURL); ?>"
                           class="btn btn-primary"
                           target="_blank" rel="noopener">
                            <i class="fas fa-external-link-alt" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('info.visit_link') : 'Visit Link'; ?>
                        </a>
                        <a href="/" class="btn btn-outline-primary ms-2">
                            <i class="fas fa-plus" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('info.create_link') : 'Create a Short Link'; ?>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>

<?php if ($shortCode === '' && $infoError === ''): ?>
<!-- ====================================================================== -->
<!-- How It Works (shown when no search has been performed)                   -->
<!-- ====================================================================== -->
<section class="py-5 bg-body-tertiary" aria-labelledby="how-heading">
    <div class="container">
        <h2 id="how-heading" class="h3 text-center mb-4">
            <?php echo function_exists('__') ? __('info.how_heading') : 'How It Works'; ?>
        </h2>
        <div class="row text-center g-4">
            <div class="col-md-4">
                <div class="p-3">
                    <i class="fas fa-paste fa-2x text-primary mb-3" aria-hidden="true"></i>
                    <h3 class="h6"><?php echo function_exists('__') ? __('info.how_step1') : '1. Paste a Short URL'; ?></h3>
                    <p class="text-body-secondary small mb-0">
                        <?php echo function_exists('__') ? __('info.how_step1_desc') : 'Enter any Go2My.Link short URL in the search box above.'; ?>
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3">
                    <i class="fas fa-eye fa-2x text-success mb-3" aria-hidden="true"></i>
                    <h3 class="h6"><?php echo function_exists('__') ? __('info.how_step2') : '2. Preview the Destination'; ?></h3>
                    <p class="text-body-secondary small mb-0">
                        <?php echo function_exists('__') ? __('info.how_step2_desc') : 'See where the link goes before clicking — the destination domain is displayed.'; ?>
                    </p>
                </div>
            </div>
            <div class="col-md-4">
                <div class="p-3">
                    <i class="fas fa-shield-alt fa-2x text-danger mb-3" aria-hidden="true"></i>
                    <h3 class="h6"><?php echo function_exists('__') ? __('info.how_step3') : '3. Visit Safely'; ?></h3>
                    <p class="text-body-secondary small mb-0">
                        <?php echo function_exists('__') ? __('info.how_step3_desc') : 'If the link looks safe, click to visit. If not, close the tab and stay protected.'; ?>
                    </p>
                </div>
            </div>
        </div>
    </div>
</section>
<?php endif; ?>
