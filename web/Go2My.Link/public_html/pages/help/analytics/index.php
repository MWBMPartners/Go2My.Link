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
 * Go2My.Link — Help: Understanding Your Click Statistics (Component A)
 * ============================================================================
 *
 * Public help page at /help/analytics. Explains, in plain language, what a
 * "click" means on this service, what is recorded about it, the date-range
 * options and figures on the Analytics dashboard, how to look at one link on
 * its own, how the CSV download works, and why the numbers can look lower
 * (or different) than expected.
 *
 * Every claim on this page is checked against the actual code rather than
 * described from memory:
 *   - web/_functions/analytics.php                                (data layer, date ranges)
 *   - web/Go2My.Link/_admin/public_html/pages/analytics/index.php (dashboard page)
 *   - web/Go2My.Link/_admin/public_html/analytics-export.php      (CSV download)
 *   - web/G2My.Link/public_html/index.php                         (what gets logged on a click)
 *   - web/_functions/activity_logger.php                          (exact columns written)
 *   - web/_functions/dnt.php                                      (Do Not Track / GPC)
 *   - web/_functions/cookie_consent.php                           (cookie consent categories)
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    1.0.0
 * @since      Help Centre
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('help.analytics.title');
} else {
    $pageTitle = 'Understanding your click statistics — Help';
}
if (function_exists('__')) {
    $pageDesc = __('help.analytics.description');
} else {
    $pageDesc = 'A plain-English guide to what a click means, what is recorded, and how to read your Go2My.Link statistics.';
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="help-analytics-heading">
    <div class="container">
        <h1 id="help-analytics-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('help.analytics.heading'); } else { echo 'Understanding your click statistics'; } ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php if (function_exists('__')) { echo __('help.analytics.subtitle'); } else { echo 'What a click means, what we record, and how to read the numbers on your dashboard.'; } ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Help Content                                                            -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="help-analytics-content-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="help-analytics-content-heading" class="visually-hidden">Help Content</h2>

                <p class="text-body-secondary">
                    <?php if (function_exists('__')) { echo __('help.analytics.intro'); } else { echo 'Every short link you create has its own set of statistics, and your account also has a combined view across all of your links. This page explains what counts as a click, what we store about it, and how to make sense of the charts and figures you will see when you sign in.'; } ?>
                </p>

                <!-- ============================================================ -->
                <!-- Table of Contents                                             -->
                <!-- ============================================================ -->
                <nav aria-label="Table of Contents" class="mb-5">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 fw-bold mb-3">
                                <i class="fas fa-list" aria-hidden="true"></i>
                                <?php if (function_exists('__')) { echo __('help.analytics.toc_heading'); } else { echo 'On this page'; } ?>
                            </h2>
                            <ol class="mb-0">
                                <li><a href="#help-analytics-s1"><?php if (function_exists('__')) { echo __('help.analytics.s1_title'); } else { echo 'What counts as a click'; } ?></a></li>
                                <li><a href="#help-analytics-s2"><?php if (function_exists('__')) { echo __('help.analytics.s2_title'); } else { echo 'What we record about each click'; } ?></a></li>
                                <li><a href="#help-analytics-s3"><?php if (function_exists('__')) { echo __('help.analytics.s3_title'); } else { echo 'Do Not Track, Global Privacy Control, and cookies'; } ?></a></li>
                                <li><a href="#help-analytics-s4"><?php if (function_exists('__')) { echo __('help.analytics.s4_title'); } else { echo 'Choosing a date range'; } ?></a></li>
                                <li><a href="#help-analytics-s5"><?php if (function_exists('__')) { echo __('help.analytics.s5_title'); } else { echo 'The charts and figures on your dashboard'; } ?></a></li>
                                <li><a href="#help-analytics-s6"><?php if (function_exists('__')) { echo __('help.analytics.s6_title'); } else { echo 'Looking at a single link'; } ?></a></li>
                                <li><a href="#help-analytics-s7"><?php if (function_exists('__')) { echo __('help.analytics.s7_title'); } else { echo 'Downloading your figures as a CSV file'; } ?></a></li>
                                <li><a href="#help-analytics-s8"><?php if (function_exists('__')) { echo __('help.analytics.s8_title'); } else { echo 'Why do my numbers look wrong?'; } ?></a></li>
                            </ol>
                        </div>
                    </div>
                </nav>

                <!-- ============================================================ -->
                <!-- Section 1: What counts as a click                             -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="help-analytics-s1">
                    <h2 id="help-analytics-s1" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.analytics.s1_heading'); } else { echo '1. What counts as a click'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s1_p1'); } else { echo 'A click is counted when someone follows your short link and we successfully send their browser on to your destination web address. That is the only thing that counts — nothing is added to your figures until the visitor has actually been redirected.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s1_p2'); } else { echo 'If a short link does not exist, has been switched off, has expired, or has not started yet, following it does not add to your click count — the visitor sees an error or "not available" page instead of being sent anywhere, so there is nothing meaningful to count.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 2: What we record about each click                   -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="help-analytics-s2">
                    <h2 id="help-analytics-s2" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.analytics.s2_heading'); } else { echo '2. What we record about each click'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s2_intro'); } else { echo 'When a click is counted, we store the following about that one visit:'; } ?>
                    </p>

                    <ul>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s2_item_when_label'); } else { echo 'When it happened'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s2_item_when_desc'); } else { echo 'the exact date and time of the click.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s2_item_link_label'); } else { echo 'Which link and web address'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s2_item_link_desc'); } else { echo 'which of your short links was used, and the destination web address it sent the visitor to.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s2_item_referrer_label'); } else { echo 'Where the click came from'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s2_item_referrer_desc'); } else { echo 'the web page the visitor was on immediately beforehand, when their browser tells us this (some apps and browsers do not send it, in which case it is simply blank).'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s2_item_device_label'); } else { echo 'Browser, operating system and device type'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s2_item_device_desc'); } else { echo 'worked out from the technical information every browser sends automatically (for example, that a visit came from Chrome on Windows using a desktop computer). We do not ask the visitor for any of this — it is read from what their browser already announces.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s2_item_ip_label'); } else { echo 'The visitor\'s IP address'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s2_item_ip_desc'); } else { echo 'the address identifying the visitor\'s device on the internet, stored as it was received. Being honest about this: it is not shortened, masked, or disguised before it is stored.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s2_item_bot_label'); } else { echo 'Our best guess at "human or automated"'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s2_item_bot_desc'); } else { echo 'whether the visit looks like it came from a person, or from an automated program such as a search-engine crawler or a link-preview fetcher. This is a best-effort guess based on the technical information mentioned above, not a certainty.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s2_item_qr_label'); } else { echo 'Whether it came from a QR code'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s2_item_qr_desc'); } else { echo 'if the link was reached by scanning one of our dynamic QR codes, we note that the click came from a scan.'; } ?>
                        </li>
                    </ul>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s2_geo_note'); } else { echo 'One thing is deliberately not always recorded: the visitor\'s country. This is only worked out from their IP address, and only when the site operator has specifically switched that feature on. Where it has not been switched on, that part of your statistics simply stays empty — it is off by default.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 3: DNT, GPC, and cookies                              -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="help-analytics-s3">
                    <h2 id="help-analytics-s3" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.analytics.s3_heading'); } else { echo '3. Do Not Track, Global Privacy Control, and cookies'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s3_p1'); } else { echo 'Some browsers send a "Do Not Track" signal, or the newer "Global Privacy Control" signal, to say the visitor does not want to be tracked. When this feature is switched on for the site (it is switched on by default), a click from a visitor sending either signal is not recorded at all — the link still works and the visitor is still sent to your destination, it simply is not added to your figures.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s3_p2'); } else { echo 'Declining analytics cookies in the cookie banner is a different thing, and it is worth being clear about the difference: a cookie banner controls what is stored in a visitor\'s own browser, not whether a click is written to your statistics on our server. This service does not currently set any analytics cookies at all — so, right now, declining them has no effect on your click count either way. Whether a click is counted is controlled entirely by the Do Not Track / Global Privacy Control setting described above.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 4: Choosing a date range                              -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="help-analytics-s4">
                    <h2 id="help-analytics-s4" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.analytics.s4_heading'); } else { echo '4. Choosing a date range'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s4_p1'); } else { echo 'At the top of the Analytics page you can pick one of three quick ranges — the last 7 days, the last 30 days (the one shown by default), or the last 90 days.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s4_p2'); } else { echo 'If you need a specific period instead, open "Custom range" and enter your own start ("From") and end ("To") dates. There is one limit worth knowing: a custom range cannot cover more than 366 days. If you pick a wider span, it is automatically trimmed back to the most recent 366 days ending on your chosen end date. If your start date is after your end date, or the dates are not filled in properly, your custom range is ignored and the page falls back to the last 30 days instead.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s4_p3'); } else { echo 'The "Clicks Over Time" chart automatically groups your figures to keep the line readable: by day for a range of up to 45 days, by week for a range of up to 180 days, and by month for anything longer. This grouping is chosen for you based on the range you picked — there is no separate setting for it.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 5: The charts and figures on your dashboard           -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="help-analytics-s5">
                    <h2 id="help-analytics-s5" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.analytics.s5_heading'); } else { echo '5. The charts and figures on your dashboard'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s5_intro'); } else { echo 'For the date range you have chosen, the Analytics page shows:'; } ?>
                    </p>

                    <ul>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s5_item_totals_label'); } else { echo 'Total clicks, human clicks and bot clicks'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s5_item_totals_desc'); } else { echo 'four summary figures at the top of the page: the total number of clicks, how many looked human (with a percentage), how many looked automated (also with a percentage), and an approximate number of unique visitors.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s5_item_unique_label'); } else { echo '"Unique Visitors (approx.)"'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s5_item_unique_desc'); } else { echo 'a count of the distinct IP addresses seen in the period. It is labelled "approx." on purpose: several people sharing the same internet connection (an office, a household, public Wi-Fi) can share one IP address and be counted once, while one person switching between Wi-Fi and mobile data can be counted twice.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s5_item_time_label'); } else { echo 'Clicks Over Time'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s5_item_time_desc'); } else { echo 'a line chart showing how your clicks are spread across the selected period, grouped by day, week or month as explained above.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s5_item_toplinks_label'); } else { echo 'Top Links'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s5_item_toplinks_desc'); } else { echo 'a ranking of your best-performing short links in the period (up to eight), shown only on the combined, all-links view — ranking a single link against itself would not mean anything.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s5_item_browser_label'); } else { echo 'Browser, operating system, and device type'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s5_item_browser_desc'); } else { echo 'three separate breakdowns showing which browsers, operating systems, and device types your visitors used.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s5_item_country_label'); } else { echo 'Country'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s5_item_country_desc'); } else { echo 'a breakdown by visitor country. As explained above, this only has anything in it when the site operator has switched on IP-based location lookup — otherwise it simply shows no data.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s5_item_referrers_label'); } else { echo 'Top Referrers'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s5_item_referrers_desc'); } else { echo 'a table listing the web pages that sent visitors to your links most often.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s5_item_qr_label'); } else { echo 'QR Scan Sources'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s5_item_qr_desc'); } else { echo 'a chart of clicks that came from scanning one of your dynamic QR codes. This only shows anything if you have QR codes that have actually been scanned in the period.'; } ?>
                        </li>
                    </ul>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s5_table_note'); } else { echo 'Underneath every chart is a "View data table" option, which shows exactly the same figures written out as plain numbers — useful if you would rather read the numbers directly than interpret a chart.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 6: Looking at a single link                           -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="help-analytics-s6">
                    <h2 id="help-analytics-s6" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.analytics.s6_heading'); } else { echo '6. Looking at a single link'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s6_p1'); } else { echo 'By default, the Analytics page shows combined figures across every one of your short links. To see just one link\'s own figures instead, click "View" next to it in the Top Links table.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s6_p2'); } else { echo 'When you are looking at a single link, the date range you had selected stays applied, and there is a link back to the combined view at the top of the page. The Top Links ranking itself is not shown while you are viewing one link, because ranking a link against itself would not mean anything.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 7: Downloading your figures as a CSV file             -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="help-analytics-s7">
                    <h2 id="help-analytics-s7" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.analytics.s7_heading'); } else { echo '7. Downloading your figures as a CSV file'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s7_p1'); } else { echo 'Underneath most charts and tables on the Analytics page, you will find a "Download CSV" button. A CSV file (short for "comma-separated values") is simply a plain text file that lists rows of data with a comma between each value — it is one of the most widely supported ways of moving figures between different pieces of software.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s7_p2'); } else { echo 'If you open it with a spreadsheet program, such as Microsoft Excel or Google Sheets, it will automatically split each row into columns for you, so it looks and behaves like an ordinary spreadsheet rather than a block of text. From there, you can sort it, chart it yourself, or keep it as a record for your own reporting.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s7_p3'); } else { echo 'Each download reflects exactly what you are currently looking at — the same date range, and, if you are viewing a single link, that link only.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 8: Why do my numbers look wrong?                      -->
                <!-- ============================================================ -->
                <section class="mb-4" aria-labelledby="help-analytics-s8">
                    <h2 id="help-analytics-s8" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.analytics.s8_heading'); } else { echo '8. Why do my numbers look wrong?'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.analytics.s8_intro'); } else { echo 'It is common for click counts to look lower, or different, than you expected. A few honest reasons why:'; } ?>
                    </p>

                    <ul>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s8_item_caching_label'); } else { echo 'Caching and automatic previews'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s8_item_caching_desc'); } else { echo 'when a link is shared in a messaging app or on social media, that app often fetches the link automatically to generate a preview, before any person has actually clicked it. That automatic fetch can be recorded as a click, even though a human has not seen your destination page yet.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s8_item_bots_label'); } else { echo 'Bots and crawlers'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s8_item_bots_desc'); } else { echo 'search engines and other automated programs follow links too. We try to identify these and split them out into the separate "Bot Clicks" figure, but this is a best-effort guess based on the technical information the visitor\'s software sends — it will not catch every automated visit, and it will occasionally mis-classify a genuine one.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s8_item_sharing_label'); } else { echo 'One link, many people'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s8_item_sharing_desc'); } else { echo 'if a single short link is shared onward and many people click the same one, all of those clicks are counted against that one link — there is no way to tell from the figures how many different places it was shared to.'; } ?>
                        </li>
                        <li>
                            <strong><?php if (function_exists('__')) { echo __('help.analytics.s8_item_unique_label'); } else { echo '"Unique Visitors" is only approximate'; } ?></strong> —
                            <?php if (function_exists('__')) { echo __('help.analytics.s8_item_unique_desc'); } else { echo 'as covered above, this figure counts distinct IP addresses, not distinct people, so it can under-count (shared connections) or over-count (one person on several networks) compared with what you might expect.'; } ?>
                        </li>
                    </ul>
                </section>

                <!-- ============================================================ -->
                <!-- Related Help + Contact CTA                                    -->
                <!-- ============================================================ -->
                <hr class="my-5">

                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="/help" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.analytics.link_help_home'); } else { echo 'Back to Help'; } ?>
                    </a>
                    <a href="/help/short-links" class="btn btn-outline-secondary">
                        <i class="fas fa-link" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.analytics.link_short_links'); } else { echo 'Creating and managing short links'; } ?>
                    </a>
                    <a href="/help/custom-domains" class="btn btn-outline-secondary">
                        <i class="fas fa-globe" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.analytics.link_custom_domains'); } else { echo 'Using your own short domain'; } ?>
                    </a>
                    <a href="/help/api" class="btn btn-outline-secondary">
                        <i class="fas fa-code" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.analytics.link_api'); } else { echo 'Using the API'; } ?>
                    </a>
                    <a href="/contact" class="btn btn-outline-primary">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.analytics.contact_cta'); } else { echo 'Still stuck? Contact us'; } ?>
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>
