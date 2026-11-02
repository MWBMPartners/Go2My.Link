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
 * 🔗 Go2My.Link — Links to Main-Site Pages
 * ============================================================================
 *
 * The shared page layout (web/_includes/nav.php and footer.php) is used by
 * the main site (go2my.link) and by the dashboard (admin.go2my.link). Pages
 * such as /about, /pricing, /login and /legal/terms exist only on the main
 * site, so a plain "/about" link works there but gives a "page not found"
 * on the dashboard.
 *
 * g2ml_mainSiteURLForComponent() decides what a link to one of those pages
 * should look like. It is kept in its own small file, taking the component
 * as a plain argument, so that the unit tests can run it without loading
 * page_init.php (which needs a database and a session).
 * g2ml_mainSiteURL() in page_init.php is the one the layout files call. It
 * adds the current component and passes the rest on.
 *
 * Dependencies: none.
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.0.0
 * @since      v1.2.0 — Dashboard fixes (#279)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
// Compares the real resolved paths, not just the file names. Comparing names
// wrongly fired when a different file with the same name (for example the
// admin cron.php endpoint loading the shared cron.php library) was the script
// being run, which redirected before the endpoint's jobs could run (#198).
// realpath('') returns the current folder, hence the empty check.
$g2mlGuardScriptPath = (string) ($_SERVER['SCRIPT_FILENAME'] ?? '');
if ($g2mlGuardScriptPath !== '' && realpath($g2mlGuardScriptPath) === realpath(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}
unset($g2mlGuardScriptPath);

/**
 * Build a link to a page that lives only on the main site (go2my.link).
 *
 * On the main site (component 'A') the path is returned unchanged, so the
 * link stays relative. On any other component the path is joined to the main
 * site's address, because that component does not have the page. Only the
 * main site and the dashboard use the shared layout today, so in practice
 * this means the dashboard.
 *
 * @param  string $path       Where the page lives on the main site. Must
 *                            start with a slash, for example '/about'.
 * @param  string $component  Component code: 'A', 'B', 'C' or 'Admin'.
 * @return string
 */
function g2ml_mainSiteURLForComponent(string $path, string $component): string
{
    if ($component === 'A')
    {
        return $path;
    }

    return 'https://go2my.link' . $path;
}
