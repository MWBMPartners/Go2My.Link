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
 * 🛡️ Go2My.Link — Staff: Find a Short Link (#325)
 * ============================================================================
 *
 * GlobalAdmin-only page (owner decision D-023). Finds any short link by its
 * code or short address, across all organisations, switches it off, or removes
 * that block, which puts the link back as it was (D-026). A change takes two
 * steps and works without JavaScript: choose the button on a result, then
 * confirm. A confirmation sent twice (a double click) shows the same answer
 * both times.
 *
 * The destination address is shown as plain text only. It may be harmful, so it
 * is never made into a link, loaded or previewed.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA_Admin
 * @version    1.0.0
 * @since      v1.2.0 — Phase 8 (#325)
 * ============================================================================
 */

// 🔒 Signed in (the dashboard's front controller checks this too)
requireAuth('User');

$staffFunctionsDir = dirname(__DIR__, 5) . DIRECTORY_SEPARATOR . '_functions' . DIRECTORY_SEPARATOR;

require_once $staffFunctionsDir . 'staff_links.php';

// 🔒 GlobalAdmin only, refused inside the dashboard's own page with a 403, like
// the breach-response page. Nothing below this block runs for anyone else.
$staffUserRole = $_SESSION['user_role'] ?? 'User';

if (!hasMinimumRole($staffUserRole, 'GlobalAdmin'))
{
    logActivity('auth', 'insufficient_role', 403, [
        'userUID' => $_SESSION['user_uid'] ?? null,
        'logData' => ['required' => 'GlobalAdmin', 'actual' => $staffUserRole],
    ]);

    $pageTitle = g2ml_staffText('access.denied_heading', 'Access Denied');

    g2ml_renderAccessDenied(g2ml_staffText(
        'error.403_message',
        'You don\'t have permission to access this page. If you believe this is an error, please contact support.'
    ));

    return;
}

// The page shows people's e-mail addresses, so it is not to be cached.
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');

if (!function_exists('getDefaultShortDomain') && file_exists($staffFunctionsDir . 'shorturl_create.php'))
{
    require_once $staffFunctionsDir . 'shorturl_create.php';
}

$pageTitle = g2ml_staffText('staff_links.title', 'Find a Short Link');
$pageDesc  = g2ml_staffText('staff_links.description', 'Staff only: find any short link, switch it off, or remove a staff block.');

// ============================================================================
// 📥 What was asked for
// ============================================================================
$searchText = '';

if (isset($_GET['q']) && is_string($_GET['q']))
{
    $searchText = trim(mb_scrub($_GET['q'], 'UTF-8'));
}

$actionSuccess = '';
$actionWarning = '';
$actionError   = '';

// ============================================================================
// ✏️ A confirmed change
// ============================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST')
{
    $csrfToken = '';

    if (isset($_POST['_csrf_token']) && is_string($_POST['_csrf_token']))
    {
        $csrfToken = $_POST['_csrf_token'];
    }

    $postedUID = 0;

    if (isset($_POST['url_uid']) && is_string($_POST['url_uid']))
    {
        $postedUID = (int) $_POST['url_uid'];
    }

    $postedChange = '';

    if (isset($_POST['change']) && is_string($_POST['change']))
    {
        $postedChange = $_POST['change'];
    }

    // The form name carries the link's row number (#147).
    $csrfFormName = 'staff_link_state_' . $postedUID;
    $currentUser  = getCurrentUser();
    $resultOwner  = 'staff:' . (int) $currentUser['userUID'];
    $csrfIsValid  = g2ml_validateCSRFToken($csrfToken, $csrfFormName);
    $outcome      = null;

    if ($csrfIsValid)
    {
        $result = g2ml_staffSetLinkState($postedUID, $postedChange, (int) $currentUser['userUID'], g2ml_postedText('reason'));

        if ($result['success'])
        {
            $outcome = ['change' => $postedChange, 'restoredActive' => $result['restoredActive'], 'logged' => $result['logged']];

            // Remembered for a second copy of this form (a double click),
            // which arrives with the same, now used, token (#291).
            if (!g2ml_csrfRememberResult($csrfToken, $csrfFormName, $resultOwner, (string) json_encode($outcome)))
            {
                error_log('[Go2My.Link] WARNING: could not remember a staff link change for a repeated form (#325).');
            }
        }
        elseif ($result['errorCode'] === 'not_found')
        {
            $actionError = g2ml_staffText('staff_links.error_not_found', 'That link no longer exists.');
        }
        elseif ($result['errorCode'] === 'no_change')
        {
            $actionError = g2ml_staffText('staff_links.error_no_change', 'Nothing changed: the link was already in that state.');
        }
        else
        {
            $actionError = g2ml_staffText('staff_links.error_failed', 'The link could not be changed. Please try again.');
        }
    }
    else
    {
        $recalled = g2ml_csrfRecallResult($csrfToken, $csrfFormName, $resultOwner);

        if ($recalled !== null)
        {
            $outcome = json_decode($recalled, true);
        }

        if (!is_array($outcome) || !isset($outcome['change'], $outcome['restoredActive'], $outcome['logged']))
        {
            $outcome     = null;
            $actionError = g2ml_staffText('staff_links.error_session', 'Your session has expired. Please try again.');
        }
    }

    if ($outcome !== null)
    {
        if ($outcome['change'] === 'off')
        {
            $actionSuccess = g2ml_staffText('staff_links.success_off', 'The link is switched off. Its owner cannot switch it back on.');
        }
        elseif ($outcome['restoredActive'])
        {
            $actionSuccess = g2ml_staffText('staff_links.success_unblock_on', 'The block is removed. The link is switched on again, as it was before. Its start and end dates still apply.');
        }
        else
        {
            $actionSuccess = g2ml_staffText('staff_links.success_unblock_off', 'The block is removed. The link stays switched off, as it was before the block or as it was switched off since.');
        }

        if (!$outcome['logged'])
        {
            $actionWarning = g2ml_staffText('staff_links.warning_not_logged', 'The change was made, but it could not be written to the activity log. Tell whoever looks after the server.');
        }
    }
}

// ============================================================================
// ❓ A change waiting to be confirmed (GET only: it changes nothing)
// ============================================================================
$confirmLink   = null;
$confirmChange = '';

if ($_SERVER['REQUEST_METHOD'] !== 'POST' && isset($_GET['link'], $_GET['change'])
    && is_string($_GET['link']) && ($_GET['change'] === 'off' || $_GET['change'] === 'unblock'))
{
    $confirmLink = g2ml_staffGetLink((int) $_GET['link']);

    if ($confirmLink === null)
    {
        $actionError = g2ml_staffText('staff_links.error_not_found', 'That link no longer exists.');
    }
    elseif (!empty($confirmLink['staffDisabledAt']) !== ($_GET['change'] === 'unblock'))
    {
        // Already blocked, or no block to remove: nothing to confirm.
        $confirmLink = null;
        $actionError = g2ml_staffText('staff_links.error_no_change', 'Nothing changed: the link was already in that state.');
    }
    else
    {
        $confirmChange = $_GET['change'];
    }
}

// ============================================================================
// 🔎 The search
// ============================================================================
$results     = [];
$narrowedTo  = '';
$searched    = false;
$searchError = '';

if ($searchText !== '')
{
    $parsed = g2ml_staffParseLinkSearch($searchText);

    if ($parsed === null)
    {
        $searchError = g2ml_staffText('staff_links.error_search_invalid', 'Enter one short code, such as abc123, or one full short address, such as g2my.link/abc123.');
    }
    else
    {
        $found = g2ml_staffFindLinks($parsed['code'], $parsed['domain']);

        if ($found === false)
        {
            $searchError = g2ml_staffText('staff_links.error_search_failed', 'The search could not be run. Please try again.');
        }
        else
        {
            $searched   = true;
            $results    = $found['links'];
            $narrowedTo = $found['narrowedTo'];
        }
    }
}

// The link being confirmed is shown on its own, above the results.
if ($confirmLink !== null)
{
    $cards    = [$confirmLink];
    $searched = false;
}
else
{
    $cards = $results;
}

$statusLabels = [
    'staff_off' => ['bg-danger', g2ml_staffText('staff_links.status_staff_off', 'Switched off by staff')],
    'off'       => ['bg-secondary', g2ml_staffText('staff_links.status_off', 'Switched off (not by staff)')],
    'scheduled' => ['bg-info text-dark', g2ml_staffText('staff_links.status_scheduled', 'Switched on, not started yet')],
    'ended'     => ['bg-warning text-dark', g2ml_staffText('staff_links.status_ended', 'Switched on, but its end date has passed')],
    'on'        => ['bg-success', g2ml_staffText('staff_links.status_on', 'Switched on')],
];
?>

<section class="py-4" aria-labelledby="staff-links-heading">
    <div class="container">
        <h1 id="staff-links-heading" class="h2 mb-2">
            <i class="fas fa-shield-alt me-2" aria-hidden="true"></i><?php echo g2ml_sanitiseOutput($pageTitle); ?>
        </h1>
        <p class="text-body-secondary mb-4"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.intro', 'Staff only. Searches the links of every person and organisation.')); ?></p>

        <?php if ($actionSuccess !== '') { ?>
        <div class="alert alert-success" role="status"><?php echo g2ml_sanitiseOutput($actionSuccess); ?></div>
        <?php } ?>
        <?php if ($actionWarning !== '') { ?>
        <div class="alert alert-warning" role="alert"><?php echo g2ml_sanitiseOutput($actionWarning); ?></div>
        <?php } ?>
        <?php if ($actionError !== '') { ?>
        <div class="alert alert-danger" role="alert"><?php echo g2ml_sanitiseOutput($actionError); ?></div>
        <?php } ?>

        <form action="/staff/links" method="GET" class="card shadow-sm mb-4">
            <div class="card-body">
                <label for="staff-link-search" class="form-label"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.search_label', 'Short code or full short address')); ?></label>
                <input type="text" class="form-control mb-2" id="staff-link-search" name="q" maxlength="2048" required="required"
                       autocomplete="off" spellcheck="false" aria-describedby="staff-link-search-help"
                       value="<?php echo g2ml_sanitiseOutput($searchText); ?>">
                <div id="staff-link-search-help" class="form-text mb-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.search_help', 'For example abc123 or https://g2my.link/abc123.')); ?></div>
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search" aria-hidden="true"></i>
                    <?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.search_button', 'Find link')); ?>
                </button>
            </div>
        </form>

        <?php if ($searchError !== '') { ?>
        <div class="alert alert-danger" role="alert"><?php echo g2ml_sanitiseOutput($searchError); ?></div>
        <?php } ?>

        <?php if ($searched) { ?>
        <p role="status">
            <?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.result_count', 'Links found: {count}', ['count' => (string) count($results)])); ?>
            <?php if ($narrowedTo !== '') { ?>
            <br><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.narrowed', 'That address belongs to the organisation {org}, so only its link is shown. Search for the code on its own to see every organisation.', ['org' => $narrowedTo])); ?>
            <?php } ?>
        </p>
        <?php if (count($results) === 0) { ?>
        <p><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.no_results', 'No link has that code. Check the spelling, or search for the code on its own.')); ?></p>
        <?php } ?>
        <?php } ?>

        <?php foreach ($cards as $link) { ?>
        <?php
        $linkUID    = (int) $link['urlUID'];
        $linkStatus = g2ml_staffLinkStatus($link);

        $shortDomain = 'g2my.link';

        if (function_exists('getDefaultShortDomain'))
        {
            $shortDomain = getDefaultShortDomain((string) $link['orgHandle']);
        }

        if ($link['createdByUserUID'] === null)
        {
            $ownerText = g2ml_staffText('staff_links.no_owner', 'No owner');
        }
        else
        {
            $ownerText = trim((string) $link['ownerName'] . ' <' . (string) $link['ownerEmail'] . '>');
        }

        $organisationText = trim((string) $link['orgName'] . ' (' . (string) $link['orgHandle'] . ')');

        $destinationText = (string) $link['destinationURL'];

        if ($destinationText === '')
        {
            $destinationText = g2ml_staffText('staff_links.no_destination', 'None recorded');
        }

        $changeBase = '/staff/links?q=' . rawurlencode($searchText) . '&link=' . $linkUID . '&change=';
        ?>
        <article class="card shadow-sm mb-3" aria-labelledby="staff-link-<?php echo $linkUID; ?>">
            <div class="card-body">
                <h2 id="staff-link-<?php echo $linkUID; ?>" class="h5 text-break">
                    <?php echo g2ml_sanitiseOutput($shortDomain . '/' . $link['shortCode']); ?>
                </h2>
                <p><span class="badge <?php echo $statusLabels[$linkStatus][0]; ?> text-wrap"><?php echo g2ml_sanitiseOutput($statusLabels[$linkStatus][1]); ?></span></p>
                <dl class="row mb-0">
                    <dt class="col-sm-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.label_destination', 'Destination')); ?></dt>
                    <dd class="col-sm-9 text-break font-monospace"><?php echo g2ml_sanitiseOutput($destinationText); ?></dd>

                    <?php if (!empty($link['redirectAlias'])) { ?>
                    <dt class="col-sm-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.label_alias', 'Forwards to the code')); ?></dt>
                    <dd class="col-sm-9 text-break font-monospace"><?php echo g2ml_sanitiseOutput((string) $link['redirectAlias']); ?></dd>
                    <?php } ?>

                    <dt class="col-sm-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.label_owner', 'Owner')); ?></dt>
                    <dd class="col-sm-9 text-break"><?php echo g2ml_sanitiseOutput($ownerText); ?></dd>

                    <dt class="col-sm-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.label_organisation', 'Organisation')); ?></dt>
                    <dd class="col-sm-9 text-break"><?php echo g2ml_sanitiseOutput($organisationText); ?></dd>

                    <dt class="col-sm-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.label_clicks', 'Clicks')); ?></dt>
                    <dd class="col-sm-9"><?php echo number_format((int) $link['clickCount']); ?></dd>

                    <dt class="col-sm-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.label_created', 'Created')); ?></dt>
                    <dd class="col-sm-9"><?php echo g2ml_sanitiseOutput((string) $link['createdAt']); ?></dd>

                    <?php if ($linkStatus === 'staff_off') { ?>
                    <dt class="col-sm-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.label_disabled_at', 'Switched off by staff at (UTC)')); ?></dt>
                    <dd class="col-sm-9"><?php echo g2ml_sanitiseOutput((string) $link['staffDisabledAt']); ?></dd>

                    <dt class="col-sm-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.label_disabled_by', 'Staff member')); ?></dt>
                    <dd class="col-sm-9 text-break"><?php echo g2ml_sanitiseOutput((string) $link['staffEmail']); ?></dd>

                    <?php if (!empty($link['staffDisableReason'])) { ?>
                    <dt class="col-sm-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.label_reason', 'Reason')); ?></dt>
                    <dd class="col-sm-9 text-break"><?php echo g2ml_sanitiseOutput((string) $link['staffDisableReason']); ?></dd>
                    <?php } ?>

                    <dt class="col-sm-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.label_after_unblock', 'If the block is removed')); ?></dt>
                    <dd class="col-sm-9"><?php
                    if ((int) $link['staffPreviousIsActive'] === 1)
                    {
                        echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.after_unblock_on', 'The link is switched on again'));
                    }
                    else
                    {
                        echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.after_unblock_off', 'The link stays switched off'));
                    }
                    ?></dd>
                    <?php } ?>
                </dl>

                <?php if ($confirmLink === null) { ?>
                <div class="d-flex flex-wrap gap-2 mt-3">
                    <?php if ($linkStatus !== 'staff_off') { ?>
                    <a class="btn btn-outline-danger" href="<?php echo g2ml_sanitiseOutput($changeBase . 'off'); ?>">
                        <?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.button_off', 'Switch off')); ?>
                        <span class="visually-hidden"><?php echo g2ml_sanitiseOutput($link['shortCode'] . ' (' . $link['orgHandle'] . ')'); ?></span>
                    </a>
                    <?php } ?>
                    <?php if ($linkStatus === 'staff_off') { ?>
                    <a class="btn btn-outline-primary" href="<?php echo g2ml_sanitiseOutput($changeBase . 'unblock'); ?>">
                        <?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.button_unblock', 'Remove staff block')); ?>
                        <span class="visually-hidden"><?php echo g2ml_sanitiseOutput($link['shortCode'] . ' (' . $link['orgHandle'] . ')'); ?></span>
                    </a>
                    <?php } ?>
                </div>
                <?php } else { ?>
                <?php
                if ($confirmChange === 'off')
                {
                    $confirmHeading = g2ml_staffText('staff_links.confirm_off_heading', 'Switch this link off?');
                    $confirmText    = g2ml_staffText('staff_links.confirm_off_text', 'The short address stops redirecting, and so does any short code that forwards to it. The owner cannot switch it back on.');
                    $confirmButton  = g2ml_staffText('staff_links.confirm_off_button', 'Yes, switch it off');
                    $confirmClass   = 'btn-danger';
                }
                else
                {
                    $confirmHeading = g2ml_staffText('staff_links.confirm_unblock_heading', 'Remove the staff block?');
                    $confirmButton  = g2ml_staffText('staff_links.confirm_unblock_button', 'Yes, remove the block');
                    $confirmClass   = 'btn-primary';

                    if ((int) $link['staffPreviousIsActive'] === 1)
                    {
                        $confirmText = g2ml_staffText('staff_links.confirm_unblock_on_text', 'The link goes back to how it was before the block: switched on. The short address starts redirecting to the destination above again, if its start and end dates allow.');
                    }
                    else
                    {
                        $confirmText = g2ml_staffText('staff_links.confirm_unblock_off_text', 'The link goes back to how it was before the block: switched off. It does not start redirecting. Its owner, if it has one, can then switch it on.');
                    }
                }
                ?>
                <hr>
                <h3 class="h5"><?php echo g2ml_sanitiseOutput($confirmHeading); ?></h3>
                <p><?php echo g2ml_sanitiseOutput($confirmText); ?></p>
                <form action="/staff/links?q=<?php echo g2ml_sanitiseOutput(rawurlencode($searchText)); ?>" method="POST">
                    <?php echo g2ml_csrfField('staff_link_state_' . $linkUID); ?>
                    <input type="hidden" name="url_uid" value="<?php echo $linkUID; ?>">
                    <input type="hidden" name="change" value="<?php echo g2ml_sanitiseOutput($confirmChange); ?>">
                    <label for="staff-link-reason" class="form-label"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.reason_label', 'Reason (optional)')); ?></label>
                    <textarea class="form-control mb-2" id="staff-link-reason" name="reason" rows="3" maxlength="500" aria-describedby="staff-link-reason-help"></textarea>
                    <div id="staff-link-reason-help" class="form-text mb-3"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.reason_help', 'Recorded in the activity log with your account. The owner is not shown it.')); ?></div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn <?php echo $confirmClass; ?>"><?php echo g2ml_sanitiseOutput($confirmButton); ?></button>
                        <a class="btn btn-outline-secondary" href="/staff/links?q=<?php echo g2ml_sanitiseOutput(rawurlencode($searchText)); ?>"><?php echo g2ml_sanitiseOutput(g2ml_staffText('staff_links.cancel', 'Cancel')); ?></a>
                    </div>
                </form>
                <?php } ?>
            </div>
        </article>
        <?php } ?>
    </div>
</section>
