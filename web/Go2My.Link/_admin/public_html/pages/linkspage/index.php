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
 * 📄 Go2My.Link — LinksPage Overview (Admin Dashboard, Component C.2 — #48)
 * ============================================================================
 *
 * Lists the current user's own LinksPages (published/draft status, public
 * URL, edit/delete/publish actions). Creation happens on the dedicated
 * /linkspage/create form (entitlement-gated there); item management lives
 * inside /linkspage/edit?id=... alongside the page's own settings.
 *
 * Every mutation here (delete, publish toggle) is delegated to the
 * ownership-enforced backend in web/_functions/linkspage_manage.php — this
 * page only handles the form/CSRF/rendering plumbing, modelled on
 * web/Go2My.Link/_admin/public_html/pages/links/index.php and pages/api-keys/index.php.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    1.0.0
 * @since      v1.2.0 — Phase 8 (#48)
 *
 * 📖 References:
 *     - Backend:     web/_functions/linkspage_manage.php (#48)
 *     - Schema:      web/_sql/schema/032_linkspage.sql
 *     - Modelled on: web/Go2My.Link/_admin/public_html/pages/links/index.php
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('linkspage.title');
} else {
    $pageTitle = 'LinksPage';
}
if (function_exists('__')) {
    $pageDesc = __('linkspage.description');
} else {
    $pageDesc = 'Manage your LinksPage listing pages and their links.';
}

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];
$orgHandle   = $currentUser['orgHandle'];

$actionSuccess = '';
$actionError   = '';

// ============================================================================
// Handle POST actions (delete / publish-toggle quick actions)
// ============================================================================

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken  = $_POST['_csrf_token'] ?? '';
    $actionType = $_POST['action_type'] ?? '';
    $pageUID    = (int) ($_POST['page_uid'] ?? 0);

    // 🔒 Each row on this list renders its OWN publish/unpublish/delete form,
    // and g2ml_generateCSRFToken() overwrites the single session slot for a
    // given form name every time it is called — so a form name shared across
    // every row would leave only the LAST-rendered row's token valid. The
    // form name is therefore namespaced by pageUID + action, matching
    // exactly what g2ml_csrfField() below is called with when the form was
    // rendered, so every row's own token stays independently valid.
    if ($actionType === 'delete_page')
    {
        $csrfFormName = 'linkspage_page_delete_' . $pageUID;
    }
    else
    {
        $csrfFormName = 'linkspage_page_status_' . $pageUID;
    }

    if (!g2ml_validateCSRFToken($csrfToken, $csrfFormName))
    {
        $actionError = 'Your session has expired. Please reload the page and try again.';
    }
    else
    {
        switch ($actionType)
        {
            case 'delete_page':

                $deleteResult = g2ml_linkspageManageDeletePage($userUID, $pageUID);

                if ($deleteResult['success'] === true)
                {
                    $actionSuccess = 'LinksPage deleted.';
                }
                else
                {
                    $actionError = $deleteResult['error'];
                }
                break;

            case 'publish_page':

                $publishResult = g2ml_linkspageManageSetPublished($userUID, $pageUID, true);

                if ($publishResult['success'] === true)
                {
                    $actionSuccess = 'LinksPage published.';
                }
                else
                {
                    $actionError = $publishResult['error'];
                }
                break;

            case 'unpublish_page':

                $unpublishResult = g2ml_linkspageManageSetPublished($userUID, $pageUID, false);

                if ($unpublishResult['success'] === true)
                {
                    $actionSuccess = 'LinksPage unpublished.';
                }
                else
                {
                    $actionError = $unpublishResult['error'];
                }
                break;

            default:
                $actionError = 'Unknown action.';
                break;
        }
    }
}

// ============================================================================
// Load data
// ============================================================================

$pages = g2ml_linkspageManageListPagesForUser($userUID);

$currentPageCount = g2ml_linkspageManageCountActivePagesForUser($userUID);
$limitStatus      = g2ml_checkLimit($orgHandle, 'maxLinksPages', $currentPageCount);
?>

<section class="py-4" aria-labelledby="linkspage-heading">
    <div class="container">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 id="linkspage-heading" class="h2 mb-0">
                <i class="fas fa-address-card" aria-hidden="true"></i>
                <?php if (function_exists('__')) { echo __('linkspage.heading'); } else { echo 'LinksPage'; } ?>
                <span class="badge bg-secondary fs-6"><?php echo count($pages); ?></span>
            </h1>

            <?php if ($limitStatus['allowed'] === true) { ?>
            <a href="/linkspage/create" class="btn btn-primary">
                <i class="fas fa-plus" aria-hidden="true"></i>
                <?php if (function_exists('__')) { echo __('linkspage.create_new'); } else { echo 'Create LinksPage'; } ?>
            </a>
            <?php } else { ?>
            <button type="button" class="btn btn-secondary" disabled title="Plan limit reached">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <?php if (function_exists('__')) { echo __('linkspage.create_new'); } else { echo 'Create LinksPage'; } ?>
            </button>
            <?php } ?>
        </div>

        <?php if ($actionSuccess !== '') { ?>
        <div class="alert alert-success alert-dismissible fade show" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionSuccess); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php } ?>

        <?php if ($actionError !== '') { ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php } ?>

        <?php if ($limitStatus['allowed'] === false) { ?>
        <div class="alert alert-warning" role="status">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            <?php if (function_exists('__')) { echo __('linkspage.limit_reached'); } else { ?>
            You have reached your plan's LinksPage limit<?php if ($limitStatus['limit'] !== null) { echo ' of ' . (int) $limitStatus['limit']; } ?>.
            Please upgrade your plan to create more LinksPages.
            <?php } ?>
        </div>
        <?php } ?>

        <!-- ================================================================ -->
        <!-- LinksPages Table                                                  -->
        <!-- ================================================================ -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">Your LinksPages (<?php echo count($pages); ?>)</h2>
            </div>

            <?php if (count($pages) === 0) { ?>
            <div class="card-body text-center text-body-secondary py-4">
                <i class="fas fa-address-card fa-2x mb-2" aria-hidden="true"></i>
                <p class="mb-0">
                    <?php if (function_exists('__')) { echo __('linkspage.no_pages'); } else { echo 'You have not created a LinksPage yet.'; } ?>
                </p>
            </div>
            <?php } else { ?>
            <div class="table-responsive">
                <table class="table table-hover mb-0" aria-label="Your LinksPages">
                    <thead>
                        <tr>
                            <th scope="col">Title</th>
                            <th scope="col">Public URL</th>
                            <th scope="col">Status</th>
                            <th scope="col">Updated</th>
                            <th scope="col" class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($pages as $pageRow) { ?>
                        <?php
                        $publicURL = 'https://lnks.page/' . $pageRow['slug'];

                        if ((int) $pageRow['isPublished'] === 1)
                        {
                            $statusBadgeClass = 'bg-success';
                            $statusLabel      = 'Published';
                        }
                        else
                        {
                            $statusBadgeClass = 'bg-secondary';
                            $statusLabel      = 'Draft';
                        }
                        ?>
                        <tr>
                            <td>
                                <strong><?php echo g2ml_sanitiseOutput($pageRow['pageTitle']); ?></strong>
                            </td>
                            <td>
                                <?php if ((int) $pageRow['isPublished'] === 1) { ?>
                                <a href="<?php echo g2ml_sanitiseOutput($publicURL); ?>" target="_blank" rel="noopener noreferrer">
                                    <?php echo g2ml_sanitiseOutput('lnks.page/' . $pageRow['slug']); ?>
                                </a>
                                <?php } else { ?>
                                <span class="text-body-secondary"><?php echo g2ml_sanitiseOutput('lnks.page/' . $pageRow['slug']); ?></span>
                                <?php } ?>
                            </td>
                            <td>
                                <span class="badge <?php echo $statusBadgeClass; ?>"><?php echo $statusLabel; ?></span>
                            </td>
                            <td>
                                <time datetime="<?php echo g2ml_sanitiseOutput((string) $pageRow['updatedAt']); ?>">
                                    <?php echo date('j M Y', strtotime((string) $pageRow['updatedAt'])); ?>
                                </time>
                            </td>
                            <td class="text-end">
                                <a href="/linkspage/edit?id=<?php echo (int) $pageRow['pageUID']; ?>"
                                   class="btn btn-sm btn-outline-primary"
                                   title="Edit" aria-label="Edit <?php echo g2ml_sanitiseOutput($pageRow['pageTitle']); ?>">
                                    <i class="fas fa-edit" aria-hidden="true"></i>
                                </a>

                                <?php
                                // 🔒 See the file header note above the POST handler: each
                                // row's forms are namespaced by pageUID + action so they do
                                // not clobber one another's CSRF token.
                                $rowStatusFormName = 'linkspage_page_status_' . (int) $pageRow['pageUID'];
                                $rowDeleteFormName = 'linkspage_page_delete_' . (int) $pageRow['pageUID'];
                                ?>

                                <?php if ((int) $pageRow['isPublished'] === 1) { ?>
                                <form action="/linkspage" method="POST" class="d-inline">
                                    <?php echo g2ml_csrfField($rowStatusFormName); ?>
                                    <input type="hidden" name="action_type" value="unpublish_page">
                                    <input type="hidden" name="page_uid" value="<?php echo (int) $pageRow['pageUID']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-secondary"
                                            title="Unpublish" aria-label="Unpublish <?php echo g2ml_sanitiseOutput($pageRow['pageTitle']); ?>">
                                        <i class="fas fa-eye-slash" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <?php } else { ?>
                                <form action="/linkspage" method="POST" class="d-inline">
                                    <?php echo g2ml_csrfField($rowStatusFormName); ?>
                                    <input type="hidden" name="action_type" value="publish_page">
                                    <input type="hidden" name="page_uid" value="<?php echo (int) $pageRow['pageUID']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-success"
                                            title="Publish" aria-label="Publish <?php echo g2ml_sanitiseOutput($pageRow['pageTitle']); ?>">
                                        <i class="fas fa-eye" aria-hidden="true"></i>
                                    </button>
                                </form>
                                <?php } ?>

                                <form action="/linkspage" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this LinksPage and all of its links? This cannot be undone.');">
                                    <?php echo g2ml_csrfField($rowDeleteFormName); ?>
                                    <input type="hidden" name="action_type" value="delete_page">
                                    <input type="hidden" name="page_uid" value="<?php echo (int) $pageRow['pageUID']; ?>">
                                    <button type="submit" class="btn btn-sm btn-outline-danger"
                                            title="Delete" aria-label="Delete <?php echo g2ml_sanitiseOutput($pageRow['pageTitle']); ?>">
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

    </div>
</section>
