<?php
/**
 * ============================================================================
 * 🔒 Go2My.Link — Cookie Consent Preferences (Admin Dashboard)
 * ============================================================================
 *
 * Allows the user to view and update their cookie consent preferences for
 * each category (essential, analytics, functional, marketing). Essential
 * cookies are always enabled and cannot be toggled off.
 *
 * Below the form, a consent history table shows the full audit trail of
 * all consent decisions.
 *
 * @package    Go2My.Link
 * @subpackage AdminDashboard
 * @version    0.7.0
 * @since      Phase 6
 * ============================================================================
 */

requireAuth();

$pageTitle = function_exists('__') ? __('consent.title') : 'Cookie Preferences';
$pageDesc  = function_exists('__') ? __('consent.description') : 'Manage your cookie consent preferences.';

$currentUser = getCurrentUser();
$userUID     = $currentUser['userUID'];

// ============================================================================
// Handle consent preference update (POST)
// ============================================================================

$actionSuccess = '';
$actionError   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken = $_POST['_csrf_token'] ?? '';

    if (!g2ml_validateCSRFToken($csrfToken, 'consent_prefs'))
    {
        $actionError = function_exists('__')
            ? __('consent.error_csrf')
            : 'Session expired. Please try again.';
    }
    else
    {
        $categories = ['analytics', 'functional', 'marketing'];
        $updated    = 0;
        $errors     = 0;

        foreach ($categories as $category)
        {
            $isEnabled = isset($_POST['consent_' . $category]) && $_POST['consent_' . $category] === '1';

            if ($isEnabled)
            {
                $result = g2ml_recordConsent($category, true, 'settings');
            }
            else
            {
                $result = g2ml_revokeConsent($category);
            }

            if ($result)
            {
                $updated++;
            }
            else
            {
                $errors++;
            }
        }

        if ($errors === 0)
        {
            $actionSuccess = function_exists('__')
                ? __('consent.success_updated')
                : 'Cookie preferences updated successfully.';

            if (function_exists('logActivity'))
            {
                logActivity('consent_preferences_updated', 'success', 200, [
                    'userUID' => $userUID,
                ]);
            }
        }
        else
        {
            $actionError = function_exists('__')
                ? __('consent.error_partial')
                : 'Some preferences could not be saved. Please try again.';
        }
    }
}

// ============================================================================
// Fetch current consent summary and history
// ============================================================================

$consentSummary = function_exists('g2ml_getConsentSummary') ? g2ml_getConsentSummary() : [];
$consentHistory = function_exists('g2ml_getConsentHistory') ? g2ml_getConsentHistory($userUID) : [];
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="py-4" aria-labelledby="consent-heading">
    <div class="container">

        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb" class="mb-3">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="/privacy"><?php echo function_exists('__') ? __('consent.breadcrumb_privacy') : 'Privacy & Data'; ?></a></li>
                <li class="breadcrumb-item active" aria-current="page"><?php echo function_exists('__') ? __('consent.breadcrumb_consent') : 'Cookie Preferences'; ?></li>
            </ol>
        </nav>

        <h1 id="consent-heading" class="h2 mb-4">
            <i class="fas fa-sliders-h" aria-hidden="true"></i>
            <?php echo function_exists('__') ? __('consent.heading') : 'Cookie Preferences'; ?>
        </h1>

        <p class="text-body-secondary mb-4">
            <?php echo function_exists('__')
                ? __('consent.intro')
                : 'Choose which types of cookies you allow. Essential cookies are required for the site to function and cannot be disabled. Changes take effect immediately.'; ?>
        </p>

        <!-- Alerts -->
        <?php if ($actionSuccess !== ''): ?>
        <div class="alert alert-success alert-dismissible fade show" role="status">
            <i class="fas fa-check-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionSuccess); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo function_exists('__') ? __('consent.close') : 'Close'; ?>"></button>
        </div>
        <?php endif; ?>

        <?php if ($actionError !== ''): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle" aria-hidden="true"></i>
            <?php echo g2ml_sanitiseOutput($actionError); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="<?php echo function_exists('__') ? __('consent.close') : 'Close'; ?>"></button>
        </div>
        <?php endif; ?>

        <!-- ============================================================== -->
        <!-- Consent Preferences Form                                        -->
        <!-- ============================================================== -->
        <div class="card shadow-sm mb-4">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-cookie" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('consent.preferences_heading') : 'Your Preferences'; ?>
                </h2>
            </div>
            <div class="card-body">
                <form action="/privacy/consent" method="POST" novalidate>
                    <?php echo g2ml_csrfField('consent_prefs'); ?>

                    <!-- Essential (always on, disabled) -->
                    <div class="form-check form-switch mb-3 pb-3 border-bottom">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="consent-essential" checked disabled
                               aria-describedby="essential-desc">
                        <label class="form-check-label fw-bold" for="consent-essential">
                            <i class="fas fa-lock text-secondary" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('consent.essential_label') : 'Essential Cookies'; ?>
                            <span class="badge bg-secondary ms-1"><?php echo function_exists('__') ? __('consent.always_on') : 'Always On'; ?></span>
                        </label>
                        <div id="essential-desc" class="form-text">
                            <?php echo function_exists('__')
                                ? __('consent.essential_desc')
                                : 'Required for the site to function. Includes session management, CSRF protection, and authentication cookies.'; ?>
                        </div>
                    </div>

                    <!-- Analytics -->
                    <div class="form-check form-switch mb-3 pb-3 border-bottom">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="consent-analytics" name="consent_analytics" value="1"
                               <?php echo ($consentSummary['analytics'] ?? null) === true ? 'checked' : ''; ?>
                               aria-describedby="analytics-desc">
                        <label class="form-check-label fw-bold" for="consent-analytics">
                            <i class="fas fa-chart-bar text-primary" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('consent.analytics_label') : 'Analytics Cookies'; ?>
                        </label>
                        <div id="analytics-desc" class="form-text">
                            <?php echo function_exists('__')
                                ? __('consent.analytics_desc')
                                : 'Help us understand how visitors interact with the site by collecting anonymous usage statistics.'; ?>
                        </div>
                    </div>

                    <!-- Functional -->
                    <div class="form-check form-switch mb-3 pb-3 border-bottom">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="consent-functional" name="consent_functional" value="1"
                               <?php echo ($consentSummary['functional'] ?? null) === true ? 'checked' : ''; ?>
                               aria-describedby="functional-desc">
                        <label class="form-check-label fw-bold" for="consent-functional">
                            <i class="fas fa-cogs text-primary" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('consent.functional_label') : 'Functional Cookies'; ?>
                        </label>
                        <div id="functional-desc" class="form-text">
                            <?php echo function_exists('__')
                                ? __('consent.functional_desc')
                                : 'Enable enhanced functionality and personalisation, such as remembering your preferences and settings.'; ?>
                        </div>
                    </div>

                    <!-- Marketing -->
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch"
                               id="consent-marketing" name="consent_marketing" value="1"
                               <?php echo ($consentSummary['marketing'] ?? null) === true ? 'checked' : ''; ?>
                               aria-describedby="marketing-desc">
                        <label class="form-check-label fw-bold" for="consent-marketing">
                            <i class="fas fa-bullhorn text-primary" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('consent.marketing_label') : 'Marketing Cookies'; ?>
                        </label>
                        <div id="marketing-desc" class="form-text">
                            <?php echo function_exists('__')
                                ? __('consent.marketing_desc')
                                : 'Used to deliver relevant advertisements and measure their effectiveness. May be shared with third-party advertising partners.'; ?>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save" aria-hidden="true"></i>
                            <?php echo function_exists('__') ? __('consent.save_preferences') : 'Save Preferences'; ?>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- ============================================================== -->
        <!-- Consent History                                                 -->
        <!-- ============================================================== -->
        <div class="card shadow-sm">
            <div class="card-header">
                <h2 class="h5 mb-0">
                    <i class="fas fa-history" aria-hidden="true"></i>
                    <?php echo function_exists('__') ? __('consent.history_heading') : 'Consent History'; ?>
                </h2>
            </div>
            <div class="card-body p-0">
                <?php if (empty($consentHistory)): ?>
                <div class="p-4 text-center text-body-secondary">
                    <i class="fas fa-inbox fa-2x mb-2" aria-hidden="true"></i>
                    <p class="mb-0">
                        <?php echo function_exists('__')
                            ? __('consent.no_history')
                            : 'No consent history recorded yet.'; ?>
                    </p>
                </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover table-sm mb-0" aria-label="<?php echo function_exists('__') ? __('consent.history_table_label') : 'Consent history'; ?>">
                        <thead>
                            <tr>
                                <th scope="col"><?php echo function_exists('__') ? __('consent.col_type') : 'Category'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('consent.col_decision') : 'Decision'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('consent.col_method') : 'Method'; ?></th>
                                <th scope="col"><?php echo function_exists('__') ? __('consent.col_date') : 'Date'; ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($consentHistory as $record): ?>
                            <tr>
                                <td>
                                    <?php echo g2ml_sanitiseOutput(ucfirst($record['consentType'] ?? '')); ?>
                                </td>
                                <td>
                                    <?php if ((int) ($record['consentGiven'] ?? 0) === 1): ?>
                                    <span class="badge bg-success">
                                        <i class="fas fa-check" aria-hidden="true"></i>
                                        <?php echo function_exists('__') ? __('consent.granted') : 'Granted'; ?>
                                    </span>
                                    <?php else: ?>
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-times" aria-hidden="true"></i>
                                        <?php echo function_exists('__') ? __('consent.refused') : 'Refused'; ?>
                                    </span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo g2ml_sanitiseOutput(ucfirst($record['consentMethod'] ?? '')); ?>
                                </td>
                                <td>
                                    <?php if (!empty($record['createdAt'])): ?>
                                    <time datetime="<?php echo g2ml_sanitiseOutput($record['createdAt']); ?>">
                                        <?php echo date('j M Y, H:i', strtotime($record['createdAt'])); ?>
                                    </time>
                                    <?php else: ?>
                                    &mdash;
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Back link -->
        <div class="mt-4">
            <a href="/privacy" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left" aria-hidden="true"></i>
                <?php echo function_exists('__') ? __('consent.back_privacy') : 'Back to Privacy & Data'; ?>
            </a>
        </div>
    </div>
</section>
