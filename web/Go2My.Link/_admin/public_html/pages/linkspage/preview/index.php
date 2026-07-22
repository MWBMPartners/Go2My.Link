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
 * 👁️ Go2My.Link — LinksPage Owner Preview (Admin Dashboard, Component C.3 — #47)
 * ============================================================================
 *
 * Renders the CURRENT USER'S OWN LinksPage — including an UNPUBLISHED draft —
 * through the SAME Component C rendering pipeline the public lnks.page/<slug>
 * uses, framed in a sandboxed <iframe srcdoc> with a clear "not yet public"
 * banner whenever the page is still a draft.
 *
 * 🔒 SECURITY — ownership / IDOR:
 *   - The page is loaded EXCLUSIVELY via g2ml_linkspageManageGetPageForOwner()
 *     (web/_functions/linkspage_manage.php, #48), which binds `userUID = ?`
 *     in SQL. A pageUID belonging to another user returns null here,
 *     IDENTICALLY to a pageUID that does not exist — this route never
 *     distinguishes "not found" from "not yours" in its response, so it
 *     cannot be used to enumerate other users' pageUIDs.
 *   - Items are loaded via g2ml_linkspageManageListItemsForPage(), which
 *     JOINs back to tblLinksPages ON userUID = ? — the same ownership
 *     boundary.
 *   - g2ml_linkspageBuildOwnerPreviewModel() (web/Lnks.page/_functions/
 *     linkspage_resolver.php, #45/#47) NEVER itself queries tblLinksPages —
 *     it trusts the already-ownership-verified $pageData/$pageItems
 *     completely and can therefore never be used to loosen the PUBLIC
 *     resolver's `isPublished = 1` filter (g2ml_resolveLinksPage() is
 *     untouched by this page).
 *
 * 🔒 SECURITY — rendering:
 *   - g2ml_renderLinksPage() (Component C's renderer, #45) is REUSED, never
 *     reimplemented — every user-authored string is escaped and every URL is
 *     scheme-validated exactly as for the public page. C.6 (#49): if the org
 *     is permitted (and the page has custom HTML), g2ml_linkspageBuildOwnerPreviewModel()
 *     keeps the SANITISED customHTML/customCSS and the renderer re-sanitises it
 *     on output; if NOT permitted it is stripped so the preview matches the
 *     public system-template render. The sandbox="" iframe below blocks all
 *     script regardless, so the preview is safe even for custom HTML.
 *   - The rendered document is embedded via `<iframe srcdoc="...">` — the
 *     ENTIRE document string is additionally attribute-escaped
 *     (htmlspecialchars ENT_QUOTES) so it sits safely inside srcdoc="...".
 *     The iframe carries `sandbox=""` (no allow-scripts token) — the framed
 *     content can never execute script, submit forms, or navigate the top
 *     window, which is appropriate for static, fully-escaped, system-
 *     template HTML. The admin dashboard's CSP now also declares
 *     `frame-src 'self'` (see ../../../.htaccess) so the browser permits
 *     framing this same-origin preview at all, without weakening script-src.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    1.0.0
 * @since      v1.2.0 — Phase 8 (#47)
 *
 * 📖 References:
 *     - Ownership layer: web/_functions/linkspage_manage.php (#48)
 *     - Preview model:   web/Lnks.page/_functions/linkspage_resolver.php — g2ml_linkspageBuildOwnerPreviewModel() (#47)
 *     - Renderer:        web/Lnks.page/_functions/linkspage_renderer.php — g2ml_renderLinksPage() (#45)
 * ============================================================================
 */

// ============================================================================
// 🔐 Defence in depth — admin's index.php already calls requireAuth('User')
// for every routed page; this explicit call costs nothing and documents the
// requirement directly on this specific, ownership-sensitive route.
// ============================================================================

requireAuth('User');

// ============================================================================
// 🎨 Load the Component C LinksPage resolver + renderer (#45) so this page
// can build/render the SAME model the public site uses — REUSED, never
// reimplemented. Component A Admin and Component C (Lnks.page) are
// channel-split only for their public_html/ web roots; everything else
// (including Lnks.page/_functions) deploys to the SAME remote root (see
// .github/workflows/sftp-deploy.yml), so this relative require is
// deploy-safe. Guarded so a missing file degrades to a friendly error below
// rather than a fatal error.
// ============================================================================

$linkspageResolverPath = dirname(__DIR__, 6)
    . DIRECTORY_SEPARATOR . 'Lnks.page'
    . DIRECTORY_SEPARATOR . '_functions'
    . DIRECTORY_SEPARATOR . 'linkspage_resolver.php';

$linkspageRendererPath = dirname(__DIR__, 6)
    . DIRECTORY_SEPARATOR . 'Lnks.page'
    . DIRECTORY_SEPARATOR . '_functions'
    . DIRECTORY_SEPARATOR . 'linkspage_renderer.php';

if (file_exists($linkspageResolverPath) && !function_exists('g2ml_linkspageBuildOwnerPreviewModel'))
{
    require_once $linkspageResolverPath;
}

if (file_exists($linkspageRendererPath) && !function_exists('g2ml_renderLinksPage'))
{
    require_once $linkspageRendererPath;
}

if (function_exists('__')) {
    $pageTitle = __('linkspage.preview_title');
} else {
    $pageTitle = 'Preview LinksPage';
}
if (function_exists('__')) {
    $pageDesc = __('linkspage.preview_description');
} else {
    $pageDesc = 'Preview your LinksPage before publishing it.';
}

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

$pageUID       = (int) ($_GET['id'] ?? 0);
$loadError     = '';
$pageData      = null;
$pageItems     = [];
$previewSrcDoc = '';

if ($pageUID <= 0)
{
    $loadError = 'No LinksPage specified.';
}
else
{
    // 🔒 Ownership enforced IN the query (see linkspage_manage.php) — a
    // pageUID belonging to another user returns null here, identically to
    // one that does not exist at all. This is the ONLY gate standing between
    // this route and an IDOR — there is no separate, bypassable check.
    $pageData = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    if ($pageData === null)
    {
        $loadError = 'LinksPage not found, or you do not have permission to preview it.';
    }
}

if ($pageData !== null)
{
    $pageItems = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);

    if (function_exists('g2ml_linkspageBuildOwnerPreviewModel') && function_exists('g2ml_renderLinksPage'))
    {
        $previewModel = g2ml_linkspageBuildOwnerPreviewModel($pageData, $pageItems);

        if ($previewModel === null)
        {
            $loadError = 'This LinksPage cannot be previewed right now — no active template is available. Please contact support.';
        }
        else
        {
            $renderedHTML = g2ml_renderLinksPage($previewModel);

            // Cosmetic only (never touches escaping/validation): resolve the
            // rendered document's relative asset paths (default avatar,
            // favicon) against the REAL lnks.page origin rather than
            // admin.go2my.link, so the preview looks exactly as a visitor
            // would see it.
            $renderedHTML = str_replace(
                '<head>',
                '<head>' . "\n" . '    <base href="https://lnks.page/">',
                $renderedHTML
            );

            // The ENTIRE rendered document is attribute-escaped so it can sit
            // safely inside srcdoc="..." below — this escapes the OUTER
            // admin page's attribute; it is not a change to the renderer's
            // own escaping of the page content itself.
            $previewSrcDoc = htmlspecialchars($renderedHTML, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
        }
    }
    else
    {
        $loadError = 'The LinksPage preview is temporarily unavailable. Please try again later.';
    }
}

if (function_exists('logActivity'))
{
    if ($pageData === null)
    {
        $previewLogStatus = 'not_found';
    }
    else
    {
        $previewLogStatus = 'success';
    }

    logActivity('view_linkspage_preview', $previewLogStatus, 200, [
        'userUID' => $userUID,
        'logData' => ['pageUID' => $pageUID],
    ]);
}
?>

<link rel="stylesheet" href="/css/linkspage-picker.css">

<section class="py-4" aria-labelledby="linkspage-preview-heading">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 id="linkspage-preview-heading" class="h2 mb-0">
                <i class="fas fa-eye" aria-hidden="true"></i>
                <?php if (function_exists('__')) { echo __('linkspage.preview_heading'); } else { echo 'Preview LinksPage'; } ?>
            </h1>
            <div class="d-flex gap-2">
                <?php if ($pageData !== null) { ?>
                <a href="/linkspage/edit?id=<?php echo (int) $pageUID; ?>" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('linkspage.preview_back_to_edit'); } else { echo 'Back to Edit'; } ?>
                </a>
                <?php } else { ?>
                <a href="/linkspage" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to LinksPages
                </a>
                <?php } ?>
            </div>
        </div>

        <?php if ($loadError !== '') { ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($loadError); ?>
        </div>

        <?php } else { ?>

        <?php if ((int) $pageData['isPublished'] === 1) { ?>
        <div class="alert alert-success" role="status">
            <i class="fas fa-globe" aria-hidden="true"></i>
            <?php if (function_exists('__')) { echo __('linkspage.preview_banner_published'); } else { echo 'This LinksPage is already published and publicly visible.'; } ?>
        </div>
        <?php } else { ?>
        <div class="alert alert-warning" role="status">
            <i class="fas fa-eye-slash" aria-hidden="true"></i>
            <strong><?php if (function_exists('__')) { echo __('linkspage.preview_banner_draft_title'); } else { echo 'Preview — not yet public.'; } ?></strong>
            <?php if (function_exists('__')) { echo __('linkspage.preview_banner_draft_body'); } else { echo ' This page has not been published yet — nobody else can see it until you publish it.'; } ?>
        </div>
        <?php } ?>

        <div class="lp-preview-frame-wrapper">
            <iframe
                class="lp-preview-frame"
                srcdoc="<?php echo $previewSrcDoc; ?>"
                title="<?php if (function_exists('__')) { echo __('linkspage.preview_iframe_title'); } else { echo 'Live preview of your LinksPage'; } ?>"
                sandbox="">
            </iframe>
        </div>

        <?php } ?>
    </div>
</section>
