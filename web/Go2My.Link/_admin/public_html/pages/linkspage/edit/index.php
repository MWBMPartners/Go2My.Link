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
 * ✏️ Go2My.Link — Edit LinksPage (Admin Dashboard, Component C.2 — #48)
 * ============================================================================
 *
 * Edit an existing LinksPage's settings AND manage its items (add, edit,
 * delete, toggle active, reorder) in one view. Ownership-checked throughout —
 * only the creator can edit their own page or its items; a pageUID/itemUID
 * belonging to another user behaves exactly like one that does not exist.
 *
 * Template selection is a visual PICKER (Component C.3, #47): a gallery of
 * the active system templates as selectable cards, each with a LIVE-rendered
 * thumbnail (the Component C renderer, reused not reimplemented — see
 * g2ml_linkspageManageRenderTemplateCardThumbnail()).
 *
 * 🧨 Component C.6 (#49) adds a GATED custom-HTML/CSS editor (source textareas
 * + .html upload + a SANITISED sandboxed live preview). It is shown ONLY when
 * g2ml_linkspageCustomHtmlAllowedForOrg() is true (operator kill-switch ON +
 * premium hasCustomHTML entitlement); the save/upload run
 * g2ml_sanitiseUserHTML()/g2ml_sanitiseUserCSS() and store the SANITISED form.
 * A full drag-drop WYSIWYG is a follow-up — a source editor plus a sanitised
 * live preview is the pragmatic, safe MVP under the strict CSP.
 *
 * Also links to the owner-only page preview (pages/linkspage/preview/index.php,
 * #47), which renders this page's ACTUAL content — including while still a
 * draft — through the same Component C renderer.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    1.1.0
 * @since      v1.2.0 — Phase 8 (#48; template picker + preview added by #47)
 *
 * 📖 References:
 *     - Backend:     web/_functions/linkspage_manage.php (#48, picker helpers #47)
 *     - Renderer:    web/Lnks.page/_functions/linkspage_renderer.php (#45, reused for picker thumbnails)
 *     - Preview:     pages/linkspage/preview/index.php (#47)
 *     - Modelled on: web/Go2My.Link/_admin/public_html/pages/links/edit/index.php
 * ============================================================================
 */

// ============================================================================
// 🎨 Load the Component C LinksPage renderer (#45) for the template picker's
// live-render thumbnails (#47) — REUSED, never reimplemented. See the
// matching comment in pages/linkspage/create/index.php for why this
// cross-component relative require is deploy-safe (both mirror to the same
// SFTP remote root outside the channel-split public_html/ web roots).
// ============================================================================

$linkspageRendererPath = dirname(__DIR__, 6)
    . DIRECTORY_SEPARATOR . 'Lnks.page'
    . DIRECTORY_SEPARATOR . '_functions'
    . DIRECTORY_SEPARATOR . 'linkspage_renderer.php';

if (file_exists($linkspageRendererPath) && !function_exists('g2ml_renderLinksPage'))
{
    require_once $linkspageRendererPath;
}

if (function_exists('__')) {
    $pageTitle = __('linkspage.edit_title');
} else {
    $pageTitle = 'Edit LinksPage';
}
if (function_exists('__')) {
    $pageDesc = __('linkspage.edit_description');
} else {
    $pageDesc = 'Edit your LinksPage settings and manage its links.';
}

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

// Load getDefaultShortDomain() (Component-A-specific, not part of
// page_init.php's shared Layer 1-4) if not already loaded, so "add item from
// one of my short URLs" resolves the correct per-org custom short domain
// rather than silently falling back to the hardcoded 'g2my.link' default.
$shorturlPath = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . '_functions' . DIRECTORY_SEPARATOR . 'shorturl_create.php';

if (file_exists($shorturlPath) && !function_exists('getDefaultShortDomain'))
{
    require_once $shorturlPath;
}

$pageUID   = (int) ($_GET['id'] ?? 0);
$loadError = '';
$pageData  = null;

if ($pageUID <= 0)
{
    $loadError = 'No LinksPage specified.';
}
else
{
    $pageData = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    if ($pageData === null)
    {
        $loadError = 'LinksPage not found, or you do not have permission to edit it.';
    }
}

$socialNetworks = G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS;

$pageFormError   = '';
$pageFormSuccess = false;
$itemFormError   = '';
$itemFormSuccess = false;
$customFormError   = '';
$customFormSuccess = false;

// ============================================================================
// Handle POST actions
// ============================================================================

if ($pageData !== null && $_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken  = $_POST['_csrf_token'] ?? '';
    $actionType = $_POST['action_type'] ?? '';
    $itemUIDRaw = (int) ($_POST['item_uid'] ?? 0);

    // 🔒 This view renders MANY forms on one page (the page-settings form,
    // the add-link form, and — per item — move-up, move-down, toggle, delete,
    // and inline-edit forms). g2ml_generateCSRFToken() overwrites the single
    // session slot for a given form name every time it is called, so sharing
    // one form name across all of these would leave only the LAST-rendered
    // form's token valid. Each logical form is therefore namespaced by
    // pageUID or itemUID + its specific action, matching exactly what
    // g2ml_csrfField() is called with when that form was rendered below.
    switch ($actionType)
    {
        case 'update_page':
            $csrfFormName = 'linkspage_page_settings_' . $pageUID;
            break;

        case 'add_item':
            $csrfFormName = 'linkspage_add_item_' . $pageUID;
            break;

        case 'save_custom_html':
            $csrfFormName = 'linkspage_custom_html_' . $pageUID;
            break;

        case 'upload_custom_html':
            $csrfFormName = 'linkspage_custom_html_upload_' . $pageUID;
            break;

        case 'update_item':
            $csrfFormName = 'linkspage_item_edit_' . $itemUIDRaw;
            break;

        case 'delete_item':
            $csrfFormName = 'linkspage_item_delete_' . $itemUIDRaw;
            break;

        case 'toggle_item':
            $csrfFormName = 'linkspage_item_toggle_' . $itemUIDRaw;
            break;

        case 'move_item_up':
            $csrfFormName = 'linkspage_item_move_up_' . $itemUIDRaw;
            break;

        case 'move_item_down':
            $csrfFormName = 'linkspage_item_move_down_' . $itemUIDRaw;
            break;

        default:
            $csrfFormName = 'linkspage_edit_unknown';
            break;
    }

    if (!g2ml_validateCSRFToken($csrfToken, $csrfFormName))
    {
        if ($actionType === 'add_item' || $actionType === 'update_item')
        {
            $itemFormError = 'Your session has expired. Please reload the page and try again.';
        }
        elseif ($actionType === 'save_custom_html' || $actionType === 'upload_custom_html')
        {
            $customFormError = 'Your session has expired. Please reload the page and try again.';
        }
        else
        {
            $pageFormError = 'Your session has expired. Please reload the page and try again.';
        }
    }
    else
    {
        switch ($actionType)
        {
            case 'update_page':

                $postedSocialValues = [];

                if (isset($_POST['social']) && is_array($_POST['social']))
                {
                    foreach ($socialNetworks as $networkKey => $networkLabel)
                    {
                        if (isset($_POST['social'][$networkKey]) && is_string($_POST['social'][$networkKey]))
                        {
                            $postedSocialValues[$networkKey] = trim($_POST['social'][$networkKey]);
                        }
                    }
                }

                if (isset($_POST['show_social_icons']))
                {
                    $postedShowSocialIcons = true;
                }
                else
                {
                    $postedShowSocialIcons = false;
                }

                if (isset($_POST['is_published']))
                {
                    $postedIsPublished = true;
                }
                else
                {
                    $postedIsPublished = false;
                }

                $updateResult = g2ml_linkspageManageUpdatePage($userUID, $pageUID, [
                    'slug'             => trim($_POST['slug'] ?? ''),
                    'pageTitle'        => trim($_POST['page_title'] ?? ''),
                    'pageDescription'  => trim($_POST['page_description'] ?? ''),
                    'avatarPath'       => trim($_POST['avatar_path'] ?? ''),
                    'templateUID'      => trim($_POST['template_uid'] ?? ''),
                    'themeColour'      => trim($_POST['theme_colour'] ?? ''),
                    'backgroundColour' => trim($_POST['background_colour'] ?? ''),
                    'fontFamily'       => trim($_POST['font_family'] ?? ''),
                    'showSocialIcons'  => $postedShowSocialIcons,
                    'socialLinks'      => $postedSocialValues,
                    'isPublished'      => $postedIsPublished,
                ]);

                if ($updateResult['success'] === true)
                {
                    $pageFormSuccess = true;
                    $pageData        = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
                }
                else
                {
                    $pageFormError = $updateResult['error'];
                }
                break;

            case 'save_custom_html':

                $saveCustomResult = g2ml_linkspageManageSaveCustomHTML(
                    $userUID,
                    $pageUID,
                    (string) ($_POST['custom_html'] ?? ''),
                    (string) ($_POST['custom_css'] ?? '')
                );

                if ($saveCustomResult['success'] === true)
                {
                    $customFormSuccess = true;
                    $pageData          = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
                }
                else
                {
                    $customFormError = $saveCustomResult['error'];
                }
                break;

            case 'upload_custom_html':

                if (isset($_FILES['custom_html_file']) && is_array($_FILES['custom_html_file']))
                {
                    $uploadedFileEntry = $_FILES['custom_html_file'];
                }
                else
                {
                    $uploadedFileEntry = [];
                }

                $uploadCustomResult = g2ml_linkspageManageSaveCustomHTMLFromUpload(
                    $userUID,
                    $pageUID,
                    $uploadedFileEntry,
                    (string) ($_POST['custom_css'] ?? '')
                );

                if ($uploadCustomResult['success'] === true)
                {
                    $customFormSuccess = true;
                    $pageData          = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);
                }
                else
                {
                    $customFormError = $uploadCustomResult['error'];
                }
                break;

            case 'add_item':

                $addResult = g2ml_linkspageManageAddItem($userUID, $pageUID, [
                    'source'          => $_POST['item_source'] ?? '',
                    'urlUID'          => $_POST['url_uid'] ?? null,
                    'manualURL'       => $_POST['manual_url'] ?? '',
                    'itemTitle'       => $_POST['item_title'] ?? '',
                    'itemDescription' => $_POST['item_description'] ?? '',
                    'itemIcon'        => $_POST['item_icon'] ?? '',
                    'requiresAgeGate' => isset($_POST['requires_age_gate']),
                ]);

                if ($addResult['success'] === true)
                {
                    $itemFormSuccess = true;
                }
                else
                {
                    $itemFormError = $addResult['error'];
                }
                break;

            case 'update_item':

                $itemUIDToUpdate = (int) ($_POST['item_uid'] ?? 0);

                $updateItemResult = g2ml_linkspageManageUpdateItem($userUID, $itemUIDToUpdate, [
                    'itemTitle'       => $_POST['item_title'] ?? '',
                    'itemDescription' => $_POST['item_description'] ?? '',
                    'itemIcon'        => $_POST['item_icon'] ?? '',
                    'manualURL'       => $_POST['manual_url'] ?? '',
                    'requiresAgeGate' => isset($_POST['requires_age_gate']),
                ]);

                if ($updateItemResult['success'] === true)
                {
                    $itemFormSuccess = true;
                }
                else
                {
                    $itemFormError = $updateItemResult['error'];
                }
                break;

            case 'delete_item':

                $itemUIDToDelete = (int) ($_POST['item_uid'] ?? 0);
                $deleteItemResult = g2ml_linkspageManageDeleteItem($userUID, $itemUIDToDelete);

                if ($deleteItemResult['success'] === true)
                {
                    $itemFormSuccess = true;
                }
                else
                {
                    $itemFormError = $deleteItemResult['error'];
                }
                break;

            case 'toggle_item':

                $itemUIDToToggle = (int) ($_POST['item_uid'] ?? 0);
                $toggleItemResult = g2ml_linkspageManageToggleItemActive($userUID, $itemUIDToToggle);

                if ($toggleItemResult['success'] === false)
                {
                    $itemFormError = $toggleItemResult['error'];
                }
                break;

            case 'move_item_up':

                $itemUIDToMove = (int) ($_POST['item_uid'] ?? 0);
                $moveUpResult  = g2ml_linkspageManageMoveItem($userUID, $itemUIDToMove, 'up');

                if ($moveUpResult['success'] === false)
                {
                    $itemFormError = $moveUpResult['error'];
                }
                break;

            case 'move_item_down':

                $itemUIDToMove  = (int) ($_POST['item_uid'] ?? 0);
                $moveDownResult = g2ml_linkspageManageMoveItem($userUID, $itemUIDToMove, 'down');

                if ($moveDownResult['success'] === false)
                {
                    $itemFormError = $moveDownResult['error'];
                }
                break;

            default:
                $pageFormError = 'Unknown action.';
                break;
        }
    }
}

$systemTemplates = [];
$ownedShortURLs  = [];
$pageItems       = [];

if ($pageData !== null)
{
    $systemTemplates = g2ml_linkspageManageListSystemTemplates();
    $ownedShortURLs  = g2ml_linkspageManageListShortURLsForUser($userUID);
    $pageItems       = g2ml_linkspageManageListItemsForPage($pageUID, $userUID);
}

/**
 * Decode a page's stored socialLinks JSON back into a plain assoc array for
 * form prefill. Never trusts the stored value's shape blindly.
 *
 * @param  string|null $socialLinksJSON
 * @return array<string, string>
 */
function g2ml_linkspageEditDecodeSocialLinks(?string $socialLinksJSON): array
{
    if ($socialLinksJSON === null || trim($socialLinksJSON) === '')
    {
        return [];
    }

    $decoded = json_decode($socialLinksJSON, true);

    if (!is_array($decoded))
    {
        return [];
    }

    $result = [];

    foreach ($decoded as $key => $value)
    {
        if (is_string($key) && is_string($value))
        {
            $result[$key] = $value;
        }
    }

    return $result;
}

if ($pageData !== null)
{
    $storedSocialLinks = g2ml_linkspageEditDecodeSocialLinks($pageData['socialLinks'] ?? null);
}
else
{
    $storedSocialLinks = [];
}

// ============================================================================
// 🧨 Custom HTML/CSS editor state (Component C.6, #49)
// ============================================================================
// The card is shown ONLY when the page's org is permitted right now (operator
// kill-switch ON + premium hasCustomHTML entitlement). Otherwise an upgrade /
// disabled note is shown, and no editor is rendered.
$customHtmlAllowed   = false;
$customHtmlValue     = '';
$customCssValue      = '';
$customPreviewSrcDoc = '';

if ($pageData !== null)
{
    $pageOrgHandle = null;

    if (isset($pageData['orgHandle']) && is_string($pageData['orgHandle']))
    {
        $pageOrgHandle = $pageData['orgHandle'];
    }

    if (function_exists('g2ml_linkspageCustomHtmlAllowedForOrg'))
    {
        $customHtmlAllowed = g2ml_linkspageCustomHtmlAllowedForOrg($pageOrgHandle);
    }

    if (isset($pageData['customHTML']) && is_string($pageData['customHTML']))
    {
        $customHtmlValue = $pageData['customHTML'];
    }

    if (isset($pageData['customCSS']) && is_string($pageData['customCSS']))
    {
        $customCssValue = $pageData['customCSS'];
    }

    // Build the SANITISED live preview (what a visitor would actually see) only
    // when permitted and non-empty. The whole rendered document is embedded via
    // a sandboxed <iframe srcdoc> — sandbox="" blocks all script regardless, and
    // the document itself is re-sanitised by g2ml_linkspageBuildCustomDocument().
    if ($customHtmlAllowed === true
        && trim($customHtmlValue) !== ''
        && function_exists('g2ml_linkspageBuildCustomDocument'))
    {
        $customPreviewDocument = g2ml_linkspageBuildCustomDocument($pageData);
        $customPreviewSrcDoc   = htmlspecialchars($customPreviewDocument, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
?>

<link rel="stylesheet" href="/css/linkspage-picker.css">

<section class="py-4" aria-labelledby="linkspage-edit-heading">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <h1 id="linkspage-edit-heading" class="h2 mb-0">
                <i class="fas fa-edit" aria-hidden="true"></i>
                <?php if (function_exists('__')) { echo __('linkspage.edit_heading'); } else { echo 'Edit LinksPage'; } ?>
            </h1>
            <div class="d-flex gap-2">
                <?php if ($pageData !== null) { ?>
                <a href="/linkspage/preview?id=<?php echo (int) $pageUID; ?>" class="btn btn-outline-primary">
                    <i class="fas fa-eye" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('linkspage.preview_link'); } else { echo 'Preview'; } ?>
                </a>
                <?php } ?>
                <a href="/linkspage" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to LinksPages
                </a>
            </div>
        </div>

        <?php if ($loadError !== '') { ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($loadError); ?>
        </div>

        <?php } else { ?>

        <div class="row justify-content-center">
            <div class="col-lg-9">

                <!-- ======================================================== -->
                <!-- Page Settings                                             -->
                <!-- ======================================================== -->

                <?php if ($pageFormSuccess === true) { ?>
                <div class="alert alert-success" role="status">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    LinksPage settings saved.
                </div>
                <?php } ?>

                <?php if ($pageFormError !== '') { ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo g2ml_sanitiseOutput($pageFormError); ?>
                </div>
                <?php } ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-sliders-h" aria-hidden="true"></i> Page Settings
                        </h2>
                    </div>
                    <div class="card-body p-4">
                        <p class="text-body-secondary">
                            Public URL:
                            <a href="https://lnks.page/<?php echo g2ml_sanitiseOutput($pageData['slug']); ?>" target="_blank" rel="noopener noreferrer">
                                lnks.page/<?php echo g2ml_sanitiseOutput($pageData['slug']); ?>
                            </a>
                        </p>

                        <form action="/linkspage/edit?id=<?php echo (int) $pageUID; ?>" method="POST" novalidate>
                            <?php echo g2ml_csrfField('linkspage_page_settings_' . $pageUID); ?>
                            <input type="hidden" name="action_type" value="update_page">

                            <div class="input-group mb-3">
                                <span class="input-group-text">lnks.page/</span>
                                <?php
                                echo formField([
                                    'id'       => 'slug',
                                    'name'     => 'slug',
                                    'label'    => 'URL Slug',
                                    'type'     => 'text',
                                    'required' => true,
                                    'value'    => g2ml_sanitiseOutput($pageData['slug']),
                                    'helpText' => 'Letters, numbers, hyphens, and underscores only (1-100 characters).',
                                ]);
                                ?>
                            </div>

                            <?php
                            echo formField([
                                'id'       => 'page-title',
                                'name'     => 'page_title',
                                'label'    => 'Page Title',
                                'type'     => 'text',
                                'required' => true,
                                'value'    => g2ml_sanitiseOutput($pageData['pageTitle']),
                            ]);

                            $descriptionValue = '';

                            if ($pageData['pageDescription'] !== null)
                            {
                                $descriptionValue = $pageData['pageDescription'];
                            }

                            echo formField([
                                'id'       => 'page-description',
                                'name'     => 'page_description',
                                'label'    => 'Bio / Description (Optional)',
                                'type'     => 'textarea',
                                'rows'     => 3,
                                'required' => false,
                                'value'    => g2ml_sanitiseOutput($descriptionValue),
                            ]);

                            $avatarValue = '';

                            if ($pageData['avatarPath'] !== null)
                            {
                                $avatarValue = $pageData['avatarPath'];
                            }

                            echo formField([
                                'id'       => 'avatar-path',
                                'name'     => 'avatar_path',
                                'label'    => 'Avatar Image URL (Optional)',
                                'type'     => 'url',
                                'required' => false,
                                'value'    => g2ml_sanitiseOutput($avatarValue),
                            ]);
                            ?>

                            <fieldset class="mb-4">
                                <legend class="form-label h6" id="template-picker-legend-edit">
                                    <?php if (function_exists('__')) { echo __('linkspage.template_picker_legend'); } else { echo 'Choose a Template'; } ?>
                                </legend>
                                <div class="form-text mb-2">
                                    <?php if (function_exists('__')) { echo __('linkspage.template_picker_help'); } else { echo 'Each preview uses sample content so you can compare styles before choosing.'; } ?>
                                </div>

                                <div class="row row-cols-2 row-cols-md-3 g-3" role="radiogroup" aria-labelledby="template-picker-legend-edit">

                                    <div class="col">
                                        <input type="radio" class="visually-hidden lp-picker-radio" name="template_uid" id="template-uid-edit-default" value=""
                                            <?php if ($pageData['templateUID'] === null) { echo 'checked'; } ?>>
                                        <label class="lp-picker-card" for="template-uid-edit-default">
                                            <span class="lp-picker-thumb lp-picker-thumb-placeholder" aria-hidden="true">
                                                <i class="fas fa-wand-magic-sparkles" aria-hidden="true"></i>
                                            </span>
                                            <span class="lp-picker-name">
                                                <?php if (function_exists('__')) { echo __('linkspage.template_default_option'); } else { echo 'Site Default'; } ?>
                                            </span>
                                        </label>
                                    </div>

                                    <?php foreach ($systemTemplates as $templateRow) { ?>
                                    <?php
                                    $pickerTemplateUID = (int) $templateRow['templateUID'];
                                    $pickerRadioID      = 'template-uid-edit-' . $pickerTemplateUID;
                                    ?>
                                    <div class="col">
                                        <input type="radio" class="visually-hidden lp-picker-radio" name="template_uid" id="<?php echo $pickerRadioID; ?>" value="<?php echo $pickerTemplateUID; ?>"
                                            <?php if ((string) $pageData['templateUID'] === (string) $pickerTemplateUID) { echo 'checked'; } ?>>
                                        <label class="lp-picker-card" for="<?php echo $pickerRadioID; ?>">
                                            <?php echo g2ml_linkspageManageRenderTemplateCardThumbnail($templateRow); ?>
                                            <span class="lp-picker-name"><?php echo g2ml_sanitiseOutput($templateRow['templateName']); ?></span>
                                            <?php if (!empty($templateRow['templateDescription'])) { ?>
                                            <span class="lp-picker-desc"><?php echo g2ml_sanitiseOutput($templateRow['templateDescription']); ?></span>
                                            <?php } ?>
                                        </label>
                                    </div>
                                    <?php } ?>

                                </div>
                            </fieldset>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="theme-colour" class="form-label">Theme Colour (Optional)</label>
                                        <input type="color" class="form-control form-control-color" id="theme-colour"
                                               name="theme_colour" value="<?php
                                                if ($pageData['themeColour'] !== null) {
                                                    echo g2ml_sanitiseOutput($pageData['themeColour']);
                                                } else {
                                                    echo '#1e88e5';
                                                }
                                               ?>" title="Choose the theme accent colour">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="background-colour" class="form-label">Background Colour (Optional)</label>
                                        <input type="color" class="form-control form-control-color" id="background-colour"
                                               name="background_colour" value="<?php
                                                if ($pageData['backgroundColour'] !== null) {
                                                    echo g2ml_sanitiseOutput($pageData['backgroundColour']);
                                                } else {
                                                    echo '#ffffff';
                                                }
                                               ?>" title="Choose the page background colour">
                                    </div>
                                </div>
                            </div>

                            <?php
                            $fontFamilyValue = '';

                            if ($pageData['fontFamily'] !== null)
                            {
                                $fontFamilyValue = $pageData['fontFamily'];
                            }

                            echo formField([
                                'id'       => 'font-family',
                                'name'     => 'font_family',
                                'label'    => 'Font Family (Optional)',
                                'type'     => 'text',
                                'required' => false,
                                'value'    => g2ml_sanitiseOutput($fontFamilyValue),
                                'helpText' => 'Letters, numbers, spaces, commas, hyphens, and quotes only.',
                            ]);
                            ?>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="show-social-icons" name="show_social_icons" value="1"
                                    <?php if ((int) $pageData['showSocialIcons'] === 1) { echo 'checked'; } ?>>
                                <label class="form-check-label" for="show-social-icons">Show social icons</label>
                            </div>

                            <fieldset class="mb-3">
                                <legend class="form-label h6">Social Links (Optional)</legend>
                                <div class="row">
                                    <?php foreach ($socialNetworks as $networkKey => $networkLabel) { ?>
                                    <div class="col-md-6">
                                        <?php
                                        $socialInputID = 'social-' . $networkKey;
                                        $socialValue    = '';

                                        if (isset($storedSocialLinks[$networkKey]))
                                        {
                                            $socialValue = $storedSocialLinks[$networkKey];
                                        }

                                        echo formField([
                                            'id'       => $socialInputID,
                                            'name'     => 'social[' . $networkKey . ']',
                                            'label'    => $networkLabel,
                                            'type'     => 'url',
                                            'required' => false,
                                            'value'    => g2ml_sanitiseOutput($socialValue),
                                        ]);
                                        ?>
                                    </div>
                                    <?php } ?>
                                </div>
                            </fieldset>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" id="is-published" name="is_published" value="1"
                                    <?php if ((int) $pageData['isPublished'] === 1) { echo 'checked'; } ?>>
                                <label class="form-check-label" for="is-published">Published (publicly visible)</label>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save" aria-hidden="true"></i> Save Page Settings
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- Custom HTML / CSS (Component C.6, #49)                    -->
                <!-- ======================================================== -->

                <?php if ($customFormSuccess === true) { ?>
                <div class="alert alert-success" role="status">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    <?php if (function_exists('__')) { echo __('linkspage.custom_html_saved'); } else { echo 'Custom HTML saved. It was sanitised for safety before saving.'; } ?>
                </div>
                <?php } ?>

                <?php if ($customFormError !== '') { ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo g2ml_sanitiseOutput($customFormError); ?>
                </div>
                <?php } ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-code" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('linkspage.custom_html_heading'); } else { echo 'Custom HTML / CSS'; } ?>
                        </h2>
                    </div>
                    <div class="card-body p-4">

                        <?php if ($customHtmlAllowed !== true) { ?>
                        <div class="alert alert-info mb-0" role="note">
                            <i class="fas fa-lock" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('linkspage.custom_html_unavailable'); } else { echo 'Custom HTML/CSS is a premium feature and is not available on your current plan (or has been disabled by the administrator). Your page uses one of the safe built-in templates.'; } ?>
                        </div>
                        <?php } else { ?>

                        <div class="alert alert-warning" role="note">
                            <i class="fas fa-shield-alt" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('linkspage.custom_html_safety_note'); } else { echo 'Your HTML and CSS are sanitised before saving and again when rendered: scripts, event handlers, iframes, forms, and unsafe URLs are removed, and the page is served with a strict security policy that blocks all scripts. The preview below shows the sanitised result.'; } ?>
                        </div>

                        <form action="/linkspage/edit?id=<?php echo (int) $pageUID; ?>" method="POST" novalidate>
                            <?php echo g2ml_csrfField('linkspage_custom_html_' . $pageUID); ?>
                            <input type="hidden" name="action_type" value="save_custom_html">

                            <div class="mb-3">
                                <label for="custom-html" class="form-label">
                                    <?php if (function_exists('__')) { echo __('linkspage.custom_html_label'); } else { echo 'Custom HTML'; } ?>
                                </label>
                                <textarea class="form-control font-monospace" id="custom-html" name="custom_html" rows="10" spellcheck="false"
                                    placeholder="&lt;h1&gt;My page&lt;/h1&gt;&#10;&lt;p&gt;Welcome&lt;/p&gt;"><?php echo g2ml_sanitiseOutput($customHtmlValue); ?></textarea>
                                <div class="form-text">
                                    <?php if (function_exists('__')) { echo __('linkspage.custom_html_help'); } else { echo 'Allowed tags include headings, paragraphs, lists, links, images, and basic layout elements. Scripts, iframes, forms, and event handlers are removed. Leave both boxes empty to revert to a built-in template.'; } ?>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="custom-css" class="form-label">
                                    <?php if (function_exists('__')) { echo __('linkspage.custom_css_label'); } else { echo 'Custom CSS'; } ?>
                                </label>
                                <textarea class="form-control font-monospace" id="custom-css" name="custom_css" rows="8" spellcheck="false"
                                    placeholder=".my-class { color: #333; }"><?php echo g2ml_sanitiseOutput($customCssValue); ?></textarea>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save" aria-hidden="true"></i>
                                    <?php if (function_exists('__')) { echo __('linkspage.custom_html_save'); } else { echo 'Save Custom HTML'; } ?>
                                </button>
                            </div>
                        </form>

                        <hr class="my-4">

                        <form action="/linkspage/edit?id=<?php echo (int) $pageUID; ?>" method="POST" enctype="multipart/form-data" novalidate>
                            <?php echo g2ml_csrfField('linkspage_custom_html_upload_' . $pageUID); ?>
                            <input type="hidden" name="action_type" value="upload_custom_html">

                            <div class="mb-3">
                                <label for="custom-html-file" class="form-label">
                                    <?php if (function_exists('__')) { echo __('linkspage.custom_html_upload_label'); } else { echo 'Upload an HTML file'; } ?>
                                </label>
                                <input type="file" class="form-control" id="custom-html-file" name="custom_html_file" accept=".html,.htm,text/html">
                                <div class="form-text">
                                    <?php if (function_exists('__')) { echo __('linkspage.custom_html_upload_help'); } else { echo 'Upload a .html file (max 100 KB). It is sanitised the same way and replaces the HTML above — the raw file is never stored.'; } ?>
                                </div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-outline-primary">
                                    <i class="fas fa-upload" aria-hidden="true"></i>
                                    <?php if (function_exists('__')) { echo __('linkspage.custom_html_upload_button'); } else { echo 'Upload &amp; Sanitise'; } ?>
                                </button>
                            </div>
                        </form>

                        <?php if ($customPreviewSrcDoc !== '') { ?>
                        <hr class="my-4">
                        <h3 class="h6">
                            <i class="fas fa-eye" aria-hidden="true"></i>
                            <?php if (function_exists('__')) { echo __('linkspage.custom_html_preview_heading'); } else { echo 'Sanitised live preview'; } ?>
                        </h3>
                        <div class="lp-preview-frame-wrapper">
                            <iframe
                                class="lp-preview-frame"
                                srcdoc="<?php echo $customPreviewSrcDoc; ?>"
                                title="<?php if (function_exists('__')) { echo __('linkspage.custom_html_preview_iframe_title'); } else { echo 'Sanitised preview of your custom HTML'; } ?>"
                                sandbox="">
                            </iframe>
                        </div>
                        <?php } ?>

                        <?php } ?>
                    </div>
                </div>

                <!-- ======================================================== -->
                <!-- Items                                                     -->
                <!-- ======================================================== -->

                <?php if ($itemFormSuccess === true) { ?>
                <div class="alert alert-success" role="status">
                    <i class="fas fa-check-circle" aria-hidden="true"></i>
                    Link saved.
                </div>
                <?php } ?>

                <?php if ($itemFormError !== '') { ?>
                <div class="alert alert-danger" role="alert">
                    <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
                    <?php echo g2ml_sanitiseOutput($itemFormError); ?>
                </div>
                <?php } ?>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-link" aria-hidden="true"></i> Links (<?php echo count($pageItems); ?>)
                        </h2>
                    </div>

                    <?php if (count($pageItems) === 0) { ?>
                    <div class="card-body text-center text-body-secondary py-4">
                        <i class="fas fa-link fa-2x mb-2" aria-hidden="true"></i>
                        <p class="mb-0">No links added yet.</p>
                    </div>
                    <?php } else { ?>
                    <ul class="list-group list-group-flush">
                        <?php
                        $totalItemCount = count($pageItems);

                        for ($itemIndex = 0; $itemIndex < $totalItemCount; $itemIndex++)
                        {
                            $itemRow = $pageItems[$itemIndex];
                            ?>
                        <li class="list-group-item">
                            <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
                                <div>
                                    <strong><?php echo g2ml_sanitiseOutput($itemRow['itemTitle']); ?></strong>

                                    <?php if ((int) $itemRow['isActive'] === 1) { ?>
                                    <span class="badge bg-success">Active</span>
                                    <?php } else { ?>
                                    <span class="badge bg-secondary">Inactive</span>
                                    <?php } ?>

                                    <?php if ($itemRow['urlUID'] !== null) { ?>
                                    <span class="badge bg-info text-dark">Short URL</span>
                                    <?php } else { ?>
                                    <span class="badge bg-info text-dark">Manual</span>
                                    <?php } ?>

                                    <?php if ((int) $itemRow['requiresAgeGate'] === 1) { ?>
                                    <span class="badge bg-warning text-dark">🔞 Age-gated</span>
                                    <?php } ?>

                                    <br>
                                    <small class="text-body-secondary"><?php echo g2ml_sanitiseOutput($itemRow['itemURL']); ?></small>
                                </div>

                                <div class="d-flex gap-1 flex-wrap">
                                    <form action="/linkspage/edit?id=<?php echo (int) $pageUID; ?>" method="POST" class="d-inline">
                                        <?php echo g2ml_csrfField('linkspage_item_move_up_' . (int) $itemRow['itemUID']); ?>
                                        <input type="hidden" name="action_type" value="move_item_up">
                                        <input type="hidden" name="item_uid" value="<?php echo (int) $itemRow['itemUID']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                <?php if ($itemIndex === 0) { echo 'disabled'; } ?>
                                                title="Move up" aria-label="Move <?php echo g2ml_sanitiseOutput($itemRow['itemTitle']); ?> up">
                                            <i class="fas fa-arrow-up" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <form action="/linkspage/edit?id=<?php echo (int) $pageUID; ?>" method="POST" class="d-inline">
                                        <?php echo g2ml_csrfField('linkspage_item_move_down_' . (int) $itemRow['itemUID']); ?>
                                        <input type="hidden" name="action_type" value="move_item_down">
                                        <input type="hidden" name="item_uid" value="<?php echo (int) $itemRow['itemUID']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                <?php if ($itemIndex === ($totalItemCount - 1)) { echo 'disabled'; } ?>
                                                title="Move down" aria-label="Move <?php echo g2ml_sanitiseOutput($itemRow['itemTitle']); ?> down">
                                            <i class="fas fa-arrow-down" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <form action="/linkspage/edit?id=<?php echo (int) $pageUID; ?>" method="POST" class="d-inline">
                                        <?php echo g2ml_csrfField('linkspage_item_toggle_' . (int) $itemRow['itemUID']); ?>
                                        <input type="hidden" name="action_type" value="toggle_item">
                                        <input type="hidden" name="item_uid" value="<?php echo (int) $itemRow['itemUID']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-secondary"
                                                title="Toggle active" aria-label="Toggle active for <?php echo g2ml_sanitiseOutput($itemRow['itemTitle']); ?>">
                                            <i class="fas fa-power-off" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                    <form action="/linkspage/edit?id=<?php echo (int) $pageUID; ?>" method="POST" class="d-inline"
                                          onsubmit="return confirm('Delete this link?');">
                                        <?php echo g2ml_csrfField('linkspage_item_delete_' . (int) $itemRow['itemUID']); ?>
                                        <input type="hidden" name="action_type" value="delete_item">
                                        <input type="hidden" name="item_uid" value="<?php echo (int) $itemRow['itemUID']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger"
                                                title="Delete" aria-label="Delete <?php echo g2ml_sanitiseOutput($itemRow['itemTitle']); ?>">
                                            <i class="fas fa-trash" aria-hidden="true"></i>
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <details class="mt-2">
                                <summary>Edit this link</summary>
                                <form action="/linkspage/edit?id=<?php echo (int) $pageUID; ?>" method="POST" class="mt-3" novalidate>
                                    <?php echo g2ml_csrfField('linkspage_item_edit_' . (int) $itemRow['itemUID']); ?>
                                    <input type="hidden" name="action_type" value="update_item">
                                    <input type="hidden" name="item_uid" value="<?php echo (int) $itemRow['itemUID']; ?>">

                                    <?php
                                    echo formField([
                                        'id'       => 'item-title-' . (int) $itemRow['itemUID'],
                                        'name'     => 'item_title',
                                        'label'    => 'Title',
                                        'type'     => 'text',
                                        'required' => true,
                                        'value'    => g2ml_sanitiseOutput($itemRow['itemTitle']),
                                    ]);

                                    $itemDescriptionValue = '';

                                    if ($itemRow['itemDescription'] !== null)
                                    {
                                        $itemDescriptionValue = $itemRow['itemDescription'];
                                    }

                                    echo formField([
                                        'id'       => 'item-description-' . (int) $itemRow['itemUID'],
                                        'name'     => 'item_description',
                                        'label'    => 'Description (Optional)',
                                        'type'     => 'textarea',
                                        'rows'     => 2,
                                        'required' => false,
                                        'value'    => g2ml_sanitiseOutput($itemDescriptionValue),
                                    ]);

                                    $itemIconValue = '';

                                    if ($itemRow['itemIcon'] !== null)
                                    {
                                        $itemIconValue = $itemRow['itemIcon'];
                                    }

                                    echo formField([
                                        'id'       => 'item-icon-' . (int) $itemRow['itemUID'],
                                        'name'     => 'item_icon',
                                        'label'    => 'Icon Image URL (Optional)',
                                        'type'     => 'url',
                                        'required' => false,
                                        'value'    => g2ml_sanitiseOutput($itemIconValue),
                                    ]);

                                    if ($itemRow['urlUID'] === null)
                                    {
                                        echo formField([
                                            'id'       => 'item-manual-url-' . (int) $itemRow['itemUID'],
                                            'name'     => 'manual_url',
                                            'label'    => 'Destination URL',
                                            'type'     => 'url',
                                            'required' => true,
                                            'value'    => g2ml_sanitiseOutput($itemRow['itemURL']),
                                        ]);
                                    }
                                    else
                                    {
                                        ?>
                                        <p class="text-body-secondary small">
                                            This link points to one of your short URLs. Edit the short URL itself to change its destination.
                                        </p>
                                        <?php
                                    }
                                    ?>

                                    <div class="form-check mb-3">
                                        <input class="form-check-input" type="checkbox" id="requires-age-gate-<?php echo (int) $itemRow['itemUID']; ?>" name="requires_age_gate" value="1"
                                            <?php if ((int) $itemRow['requiresAgeGate'] === 1) { echo 'checked'; } ?>>
                                        <label class="form-check-label" for="requires-age-gate-<?php echo (int) $itemRow['itemUID']; ?>">
                                            🔞 Age-restricted (18+) — require visitors to confirm their age before this link is shown
                                        </label>
                                        <div class="form-text">Known adult-content domains are always gated, even if this box is unchecked.</div>
                                    </div>

                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <i class="fas fa-save" aria-hidden="true"></i> Save Link
                                    </button>
                                </form>
                            </details>
                        </li>
                            <?php
                        }
                        ?>
                    </ul>
                    <?php } ?>
                </div>

                <!-- ======================================================== -->
                <!-- Add Item                                                  -->
                <!-- ======================================================== -->
                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h2 class="h5 mb-0">
                            <i class="fas fa-plus-circle" aria-hidden="true"></i> Add a Link
                        </h2>
                    </div>
                    <div class="card-body p-4">
                        <form action="/linkspage/edit?id=<?php echo (int) $pageUID; ?>" method="POST" novalidate>
                            <?php echo g2ml_csrfField('linkspage_add_item_' . $pageUID); ?>
                            <input type="hidden" name="action_type" value="add_item">

                            <fieldset class="mb-3">
                                <legend class="form-label h6">Link Source</legend>

                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="item_source" id="source-shorturl" value="shorturl"
                                        <?php if (count($ownedShortURLs) > 0) { echo 'checked'; } ?>
                                        <?php if (count($ownedShortURLs) === 0) { echo 'disabled'; } ?>>
                                    <label class="form-check-label" for="source-shorturl">One of my short URLs</label>
                                </div>

                                <?php if (count($ownedShortURLs) > 0) { ?>
                                <div class="mb-2 ms-4">
                                    <label for="url-uid" class="form-label visually-hidden">Choose a short URL</label>
                                    <select class="form-select" id="url-uid" name="url_uid">
                                        <?php foreach ($ownedShortURLs as $shortURLRow) { ?>
                                        <option value="<?php echo (int) $shortURLRow['urlUID']; ?>">
                                            <?php
                                            $optionLabel = $shortURLRow['shortCode'];

                                            if (!empty($shortURLRow['title']))
                                            {
                                                $optionLabel = $optionLabel . ' — ' . $shortURLRow['title'];
                                            }

                                            echo g2ml_sanitiseOutput($optionLabel);
                                            ?>
                                        </option>
                                        <?php } ?>
                                    </select>
                                </div>
                                <?php } else { ?>
                                <p class="text-body-secondary small ms-4">You have no short URLs yet — <a href="/links/create">create one</a>.</p>
                                <?php } ?>

                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="item_source" id="source-manual" value="manual"
                                        <?php if (count($ownedShortURLs) === 0) { echo 'checked'; } ?>>
                                    <label class="form-check-label" for="source-manual">A manual URL</label>
                                </div>
                                <div class="mb-2 ms-4">
                                    <label for="manual-url" class="form-label visually-hidden">Manual URL</label>
                                    <input type="url" class="form-control" id="manual-url" name="manual_url" placeholder="https://example.com">
                                </div>
                            </fieldset>

                            <?php
                            echo formField([
                                'id'       => 'new-item-title',
                                'name'     => 'item_title',
                                'label'    => 'Title',
                                'type'     => 'text',
                                'required' => true,
                                'value'    => '',
                            ]);

                            echo formField([
                                'id'       => 'new-item-description',
                                'name'     => 'item_description',
                                'label'    => 'Description (Optional)',
                                'type'     => 'textarea',
                                'rows'     => 2,
                                'required' => false,
                                'value'    => '',
                            ]);

                            echo formField([
                                'id'       => 'new-item-icon',
                                'name'     => 'item_icon',
                                'label'    => 'Icon Image URL (Optional)',
                                'type'     => 'url',
                                'required' => false,
                                'value'    => '',
                            ]);
                            ?>

                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" id="new-item-requires-age-gate" name="requires_age_gate" value="1">
                                <label class="form-check-label" for="new-item-requires-age-gate">
                                    🔞 Age-restricted (18+) — require visitors to confirm their age before this link is shown
                                </label>
                                <div class="form-text">Known adult-content domains are automatically flagged even if this box is left unchecked.</div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus" aria-hidden="true"></i> Add Link
                                </button>
                            </div>
                        </form>
                    </div>
                </div>

            </div>
        </div>

        <?php } ?>
    </div>
</section>
