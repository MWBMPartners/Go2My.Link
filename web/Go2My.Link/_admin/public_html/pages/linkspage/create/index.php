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
 * ✨ Go2My.Link — Create LinksPage (Admin Dashboard, Component C.2 — #48)
 * ============================================================================
 *
 * Full LinksPage creation form: slug, title, description, avatar, template,
 * theme/background colour, font family, social links, and published state.
 * Every field is a discrete, validated, structured value — there is no raw
 * HTML/CSS field here (WYSIWYG editing is C.6 / #49, deliberately not built).
 *
 * The template field is a visual PICKER (Component C.3, #47): a gallery of
 * the active system templates as selectable cards, each with a LIVE-rendered
 * thumbnail (the Component C renderer, reused not reimplemented — see
 * g2ml_linkspageManageRenderTemplateCardThumbnail()). Selecting a card sets
 * the same `template_uid` radio value the old plain <select> submitted.
 *
 * Gated by the maxLinksPages entitlement (web/_functions/entitlements.php,
 * #146) via g2ml_linkspageManageCreatePage() — an over-limit submission is
 * rejected server-side with no insert, regardless of what this form allows
 * the user to attempt.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    1.1.0
 * @since      v1.2.0 — Phase 8 (#48; template picker added by #47)
 *
 * 📖 References:
 *     - Backend:     web/_functions/linkspage_manage.php (#48, picker helpers #47)
 *     - Renderer:    web/Lnks.page/_functions/linkspage_renderer.php (#45, reused for picker thumbnails)
 *     - Modelled on: web/Go2My.Link/_admin/public_html/pages/links/create/index.php
 * ============================================================================
 */

// ============================================================================
// 🎨 Load the Component C LinksPage renderer (#45) for the template picker's
// live-render thumbnails (#47) — REUSED, never reimplemented. Component A
// Admin and Component C (Lnks.page) are channel-split only for their
// public_html/ web roots; everything else (including Lnks.page/_functions)
// deploys to the SAME remote root (see .github/workflows/sftp-deploy.yml),
// so this relative require is deploy-safe. Guarded so a missing file (e.g. a
// stripped-down local checkout) degrades gracefully to the thumbnail-image/
// placeholder fallback in g2ml_linkspageManageRenderTemplateCardThumbnail()
// rather than a fatal error.
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
    $pageTitle = __('linkspage.create_title');
} else {
    $pageTitle = 'Create LinksPage';
}
if (function_exists('__')) {
    $pageDesc = __('linkspage.create_description');
} else {
    $pageDesc = 'Set up a new LinksPage listing page.';
}

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];
$orgHandle   = $currentUser['orgHandle'];

$systemTemplates = g2ml_linkspageManageListSystemTemplates();
$socialNetworks  = G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS;

$currentPageCount = g2ml_linkspageManageCountActivePagesForUser($userUID);
$limitStatus      = g2ml_checkLimit($orgHandle, 'maxLinksPages', $currentPageCount);

$formError   = '';
$formSuccess = false;
$createdSlug = '';
$createdUID  = 0;

// Sticky form values (repopulated on a validation failure, or defaulted for
// a first GET render).
$formSlug             = '';
$formPageTitle        = '';
$formPageDescription  = '';
$formAvatarPath       = '';
$formTemplateUID      = '';
$formThemeColour      = '';
$formBackgroundColour = '';
$formFontFamily       = '';
$formShowSocialIcons  = true;
$formSocialValues     = [];
$formIsPublished      = false;

if ($_SERVER['REQUEST_METHOD'] === 'GET')
{
    // Convenience default: prefill the avatar with the user's own already-set
    // profile avatar, if any (there is no separate file-upload pipeline for
    // LinksPage avatars — both are plain, scheme-validated image URLs).
    if (isset($currentUser['avatar']) && is_string($currentUser['avatar']) && trim($currentUser['avatar']) !== '')
    {
        $formAvatarPath = $currentUser['avatar'];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    $formSlug = trim($_POST['slug'] ?? '');
    $formPageTitle = trim($_POST['page_title'] ?? '');
    $formPageDescription = trim($_POST['page_description'] ?? '');
    $formAvatarPath = trim($_POST['avatar_path'] ?? '');
    $formTemplateUID = trim($_POST['template_uid'] ?? '');
    $formThemeColour = trim($_POST['theme_colour'] ?? '');
    $formBackgroundColour = trim($_POST['background_colour'] ?? '');
    $formFontFamily = trim($_POST['font_family'] ?? '');

    if (isset($_POST['show_social_icons']))
    {
        $formShowSocialIcons = true;
    }
    else
    {
        $formShowSocialIcons = false;
    }

    if (isset($_POST['is_published']))
    {
        $formIsPublished = true;
    }
    else
    {
        $formIsPublished = false;
    }

    $formSocialValues = [];

    if (isset($_POST['social']) && is_array($_POST['social']))
    {
        foreach ($socialNetworks as $networkKey => $networkLabel)
        {
            if (isset($_POST['social'][$networkKey]) && is_string($_POST['social'][$networkKey]))
            {
                $formSocialValues[$networkKey] = trim($_POST['social'][$networkKey]);
            }
        }
    }

    if (!g2ml_validateCSRFToken($csrfToken, 'linkspage_create_form'))
    {
        $formError = 'Your session has expired. Please reload the page and try again.';
    }
    else
    {
        $createResult = g2ml_linkspageManageCreatePage($userUID, $orgHandle, [
            'slug'             => $formSlug,
            'pageTitle'        => $formPageTitle,
            'pageDescription'  => $formPageDescription,
            'avatarPath'       => $formAvatarPath,
            'templateUID'      => $formTemplateUID,
            'themeColour'      => $formThemeColour,
            'backgroundColour' => $formBackgroundColour,
            'fontFamily'       => $formFontFamily,
            'showSocialIcons'  => $formShowSocialIcons,
            'socialLinks'      => $formSocialValues,
            'isPublished'      => $formIsPublished,
        ]);

        if ($createResult['success'] === true)
        {
            $formSuccess = true;
            $createdSlug = $formSlug;
            $createdUID  = $createResult['pageUID'];
        }
        else
        {
            $formError = $createResult['error'];
        }
    }
}
?>

<link rel="stylesheet" href="/css/linkspage-picker.css">

<section class="py-4" aria-labelledby="linkspage-create-heading">
    <div class="container">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1 id="linkspage-create-heading" class="h2 mb-0">
                <i class="fas fa-plus-circle" aria-hidden="true"></i>
                <?php if (function_exists('__')) { echo __('linkspage.create_heading'); } else { echo 'Create LinksPage'; } ?>
            </h1>
            <a href="/linkspage" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left" aria-hidden="true"></i> Back to LinksPages
            </a>
        </div>

        <?php if ($formSuccess === true) { ?>
        <div class="alert alert-success" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            LinksPage created at <strong>lnks.page/<?php echo g2ml_sanitiseOutput($createdSlug); ?></strong>.
            <a href="/linkspage/edit?id=<?php echo (int) $createdUID; ?>" class="alert-link">Add links now &raquo;</a>
        </div>
        <?php } else { ?>

        <?php if ($limitStatus['allowed'] === false) { ?>
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
            You have reached your plan's LinksPage limit<?php if ($limitStatus['limit'] !== null) { echo ' of ' . (int) $limitStatus['limit']; } ?>.
            Please upgrade your plan to create more LinksPages.
        </div>
        <?php } else { ?>

        <?php if ($formError !== '') { ?>
        <div class="alert alert-danger" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($formError); ?>
        </div>
        <?php } ?>

        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <form action="/linkspage/create" method="POST" novalidate>
                            <?php echo g2ml_csrfField('linkspage_create_form'); ?>

                            <div class="input-group mb-3">
                                <span class="input-group-text">lnks.page/</span>
                                <?php
                                echo formField([
                                    'id'          => 'slug',
                                    'name'        => 'slug',
                                    'label'       => 'URL Slug',
                                    'type'        => 'text',
                                    'required'    => true,
                                    'value'       => g2ml_sanitiseOutput($formSlug),
                                    'placeholder' => 'your-name',
                                    'helpText'    => 'Letters, numbers, hyphens, and underscores only (1-100 characters).',
                                    'class'       => 'rounded-start-0',
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
                                'value'    => g2ml_sanitiseOutput($formPageTitle),
                            ]);

                            echo formField([
                                'id'       => 'page-description',
                                'name'     => 'page_description',
                                'label'    => 'Bio / Description (Optional)',
                                'type'     => 'textarea',
                                'rows'     => 3,
                                'required' => false,
                                'value'    => g2ml_sanitiseOutput($formPageDescription),
                            ]);

                            echo formField([
                                'id'          => 'avatar-path',
                                'name'        => 'avatar_path',
                                'label'       => 'Avatar Image URL (Optional)',
                                'type'        => 'url',
                                'required'    => false,
                                'value'       => g2ml_sanitiseOutput($formAvatarPath),
                                'placeholder' => 'https://example.com/me.png',
                                'helpText'    => 'Link to any hosted image — for example your profile picture URL.',
                            ]);
                            ?>

                            <fieldset class="mb-4">
                                <legend class="form-label h6" id="template-picker-legend-create">
                                    <?php if (function_exists('__')) { echo __('linkspage.template_picker_legend'); } else { echo 'Choose a Template'; } ?>
                                </legend>
                                <div class="form-text mb-2">
                                    <?php if (function_exists('__')) { echo __('linkspage.template_picker_help'); } else { echo 'Each preview uses sample content so you can compare styles before choosing.'; } ?>
                                </div>

                                <div class="row row-cols-2 row-cols-md-3 g-3" role="radiogroup" aria-labelledby="template-picker-legend-create">

                                    <div class="col">
                                        <input type="radio" class="visually-hidden lp-picker-radio" name="template_uid" id="template-uid-create-default" value=""
                                            <?php if ($formTemplateUID === '') { echo 'checked'; } ?>>
                                        <label class="lp-picker-card" for="template-uid-create-default">
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
                                    $pickerRadioID      = 'template-uid-create-' . $pickerTemplateUID;
                                    ?>
                                    <div class="col">
                                        <input type="radio" class="visually-hidden lp-picker-radio" name="template_uid" id="<?php echo $pickerRadioID; ?>" value="<?php echo $pickerTemplateUID; ?>"
                                            <?php if ($formTemplateUID === (string) $pickerTemplateUID) { echo 'checked'; } ?>>
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
                                                if ($formThemeColour !== '') {
                                                    echo g2ml_sanitiseOutput($formThemeColour);
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
                                                if ($formBackgroundColour !== '') {
                                                    echo g2ml_sanitiseOutput($formBackgroundColour);
                                                } else {
                                                    echo '#ffffff';
                                                }
                                               ?>" title="Choose the page background colour">
                                    </div>
                                </div>
                            </div>

                            <?php
                            echo formField([
                                'id'          => 'font-family',
                                'name'        => 'font_family',
                                'label'       => 'Font Family (Optional)',
                                'type'        => 'text',
                                'required'    => false,
                                'value'       => g2ml_sanitiseOutput($formFontFamily),
                                'placeholder' => 'e.g. "Segoe UI", Roboto, sans-serif',
                                'helpText'    => 'Letters, numbers, spaces, commas, hyphens, and quotes only.',
                            ]);
                            ?>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="show-social-icons" name="show_social_icons" value="1"
                                    <?php if ($formShowSocialIcons === true) { echo 'checked'; } ?>>
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

                                        if (isset($formSocialValues[$networkKey]))
                                        {
                                            $socialValue = $formSocialValues[$networkKey];
                                        }

                                        echo formField([
                                            'id'          => $socialInputID,
                                            'name'        => 'social[' . $networkKey . ']',
                                            'label'       => $networkLabel,
                                            'type'        => 'url',
                                            'required'    => false,
                                            'value'       => g2ml_sanitiseOutput($socialValue),
                                            'placeholder' => 'https://...',
                                        ]);
                                        ?>
                                    </div>
                                    <?php } ?>
                                </div>
                            </fieldset>

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" id="is-published" name="is_published" value="1"
                                    <?php if ($formIsPublished === true) { echo 'checked'; } ?>>
                                <label class="form-check-label" for="is-published">Publish immediately</label>
                                <div class="form-text">You can leave this as a draft and publish it later from the LinksPage list.</div>
                            </div>

                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save" aria-hidden="true"></i> Create LinksPage
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <?php } ?>
        <?php } ?>
    </div>
</section>
