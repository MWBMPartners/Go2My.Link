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
 * Go2My.Link — Help: Using the API (Component A)
 * ============================================================================
 *
 * Plain-English introduction to the public API for a customer who is not
 * necessarily a programmer. Served at /help/api.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    1.0.0
 * @since      Phase 7
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('help.api.title');
} else {
    $pageTitle = 'Using the API';
}
if (function_exists('__')) {
    $pageDesc = __('help.api.description');
} else {
    $pageDesc = 'How to use the Go2My.Link API to shorten links, read your click statistics, and manage your account from your own programs.';
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="api-heading">
    <div class="container">
        <h1 id="api-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('help.api.heading'); } else { echo 'Using the API'; } ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php if (function_exists('__')) { echo __('help.api.subtitle'); } else { echo 'Let your own programs create and manage short links, the same way you can from the dashboard.'; } ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Help Content                                                            -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="api-content-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="api-content-heading" class="visually-hidden">
                    <?php if (function_exists('__')) { echo __('help.api.content_heading'); } else { echo 'API Help Content'; } ?>
                </h2>

                <!-- ============================================================ -->
                <!-- Table of Contents                                             -->
                <!-- ============================================================ -->
                <nav aria-label="Table of Contents" class="mb-5">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 fw-bold mb-3">
                                <i class="fas fa-list" aria-hidden="true"></i>
                                <?php if (function_exists('__')) { echo __('help.api.toc_heading'); } else { echo 'On this page'; } ?>
                            </h2>
                            <ol class="mb-0">
                                <li><a href="#api-s1"><?php if (function_exists('__')) { echo __('help.api.s1_title'); } else { echo 'What is an API?'; } ?></a></li>
                                <li><a href="#api-s2"><?php if (function_exists('__')) { echo __('help.api.s2_title'); } else { echo 'What you can do with it'; } ?></a></li>
                                <li><a href="#api-s3"><?php if (function_exists('__')) { echo __('help.api.s3_title'); } else { echo 'Getting a key'; } ?></a></li>
                                <li><a href="#api-s4"><?php if (function_exists('__')) { echo __('help.api.s4_title'); } else { echo 'Permissions'; } ?></a></li>
                                <li><a href="#api-s5"><?php if (function_exists('__')) { echo __('help.api.s5_title'); } else { echo 'Keeping your key safe'; } ?></a></li>
                                <li><a href="#api-s6"><?php if (function_exists('__')) { echo __('help.api.s6_title'); } else { echo 'How often you can call it'; } ?></a></li>
                                <li><a href="#api-s7"><?php if (function_exists('__')) { echo __('help.api.s7_title'); } else { echo 'Full technical documentation'; } ?></a></li>
                            </ol>
                        </div>
                    </div>
                </nav>

                <!-- ============================================================ -->
                <!-- Section 1: What is an API?                                    -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="api-s1">
                    <h2 id="api-s1" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.api.s1_heading'); } else { echo '1. What is an API?'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s1_p1'); } else { echo 'An API is a way for another computer program to do the same things you can do on this website, without a person clicking any buttons. Instead of you signing in and creating a short link by hand, a program you write yourself — or one you buy from someone else, such as an automation tool — can create it for you automatically.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s1_p2'); } else { echo 'You only need this if you want to connect Go2My.Link to another system: your own website, a script you run at work, or a tool such as Zapier. If you only ever create links by signing in and using the dashboard, you do not need the API at all.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 2: What you can do with it                            -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="api-s2">
                    <h2 id="api-s2" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.api.s2_heading'); } else { echo '2. What you can do with it'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s2_intro'); } else { echo 'The API can do most of what you can already do in the dashboard. Every request needs an API key (see the next section), and a key can only ever see and change your own account — never anyone else\'s.'; } ?>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('help.api.s2_links_heading'); } else { echo 'Your short links'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s2_links_desc'); } else { echo 'Create a new short link, create several at once, look up one link\'s details, see a list of all your links, change a link\'s destination, or turn a link off. Turning a link off does not delete it — the link and its history stay in your account, it simply stops working.'; } ?>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('help.api.s2_stats_heading'); } else { echo 'Click statistics'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s2_stats_desc'); } else { echo 'Get an overview of clicks across all your links, or the click details for one specific link.'; } ?>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('help.api.s2_account_heading'); } else { echo 'Your account and organisation'; } ?>
                    </h3>
                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s2_account_desc'); } else { echo 'Read back your own account details, and the basic details of your organisation.'; } ?>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('help.api.s2_ping_heading'); } else { echo 'Checking the connection'; } ?>
                    </h3>
                    <p class="mb-0">
                        <?php if (function_exists('__')) { echo __('help.api.s2_ping_desc'); } else { echo 'A simple check a program can call to prove its key and connection are working, without reading or changing any of your data.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 3: Getting a key                                      -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="api-s3">
                    <h2 id="api-s3" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.api.s3_heading'); } else { echo '3. Getting a key'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s3_p1'); } else { echo 'You create a key from your dashboard, under Organisation, then API Keys. Give it a name that reminds you what it is for — for example, "CI pipeline" or "Zapier integration" — choose the permissions it needs, and, if you like, set a date it should stop working.'; } ?>
                    </p>

                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-triangle-exclamation" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.api.s3_warning'); } else { echo 'The key is shown to you exactly once, straight after you create it. Go2My.Link does not keep a copy of it anywhere — only a one-way scrambled version is stored, which cannot be turned back into the real key. If you close the page, navigate away, or lose the key, it is gone for good and cannot be shown again. Your only option at that point is to withdraw that key and create a new one.'; } ?>
                    </div>

                    <p class="mb-0">
                        <?php if (function_exists('__')) { echo __('help.api.s3_p2'); } else { echo 'Only an organisation administrator can create or withdraw API keys.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 4: Permissions                                        -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="api-s4">
                    <h2 id="api-s4" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.api.s4_heading'); } else { echo '4. Permissions'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s4_intro'); } else { echo 'In the technical documentation these are called "scopes", but they are simply a list of what a key is allowed to do. When you create a key, you tick the boxes for what it needs. A key can only do what it has been given permission for — nothing else.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s4_advice'); } else { echo 'Grant only what a key actually needs. A key that only reads click statistics, for example, should not also be given permission to turn links off. If that key is ever seen by someone it should not be, the damage it can do is limited to what you ticked.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s4_list_intro'); } else { echo 'The permissions you can grant a key are:'; } ?>
                    </p>

                    <ul>
                        <li class="mb-2">
                            <strong><?php if (function_exists('__')) { echo __('help.api.s4_urls_read_label'); } else { echo 'View short links'; } ?></strong>
                            <code>urls:read</code> —
                            <?php if (function_exists('__')) { echo __('help.api.s4_urls_read_desc'); } else { echo 'read your existing links and their details.'; } ?>
                        </li>
                        <li class="mb-2">
                            <strong><?php if (function_exists('__')) { echo __('help.api.s4_urls_write_label'); } else { echo 'Create and edit short links'; } ?></strong>
                            <code>urls:write</code> —
                            <?php if (function_exists('__')) { echo __('help.api.s4_urls_write_desc'); } else { echo 'create new links and change the destination of existing ones.'; } ?>
                        </li>
                        <li class="mb-2">
                            <strong><?php if (function_exists('__')) { echo __('help.api.s4_urls_delete_label'); } else { echo 'Turn off short links'; } ?></strong>
                            <code>urls:delete</code> —
                            <?php if (function_exists('__')) { echo __('help.api.s4_urls_delete_desc'); } else { echo 'stop a link working. This does not delete it — the link stays in your account, switched off.'; } ?>
                        </li>
                        <li class="mb-2">
                            <strong><?php if (function_exists('__')) { echo __('help.api.s4_analytics_read_label'); } else { echo 'View click statistics'; } ?></strong>
                            <code>analytics:read</code> —
                            <?php if (function_exists('__')) { echo __('help.api.s4_analytics_read_desc'); } else { echo 'read click counts and reports for your links.'; } ?>
                        </li>
                        <li class="mb-2">
                            <strong><?php if (function_exists('__')) { echo __('help.api.s4_domains_read_label'); } else { echo 'View custom domains'; } ?></strong>
                            <code>domains:read</code>
                            <span class="badge bg-secondary ms-1">
                                <?php if (function_exists('__')) { echo __('help.api.s4_not_used_badge'); } else { echo 'Not used yet'; } ?>
                            </span>
                            —
                            <?php if (function_exists('__')) { echo __('help.api.s4_domains_read_desc'); } else { echo 'not used by anything in the API yet. You can tick it when creating a key, but it currently has no effect.'; } ?>
                        </li>
                        <li class="mb-2">
                            <strong><?php if (function_exists('__')) { echo __('help.api.s4_domains_write_label'); } else { echo 'Manage custom domains'; } ?></strong>
                            <code>domains:write</code>
                            <span class="badge bg-secondary ms-1">
                                <?php if (function_exists('__')) { echo __('help.api.s4_not_used_badge'); } else { echo 'Not used yet'; } ?>
                            </span>
                            —
                            <?php if (function_exists('__')) { echo __('help.api.s4_domains_write_desc'); } else { echo 'not used by anything in the API yet. You can tick it when creating a key, but it currently has no effect.'; } ?>
                        </li>
                        <li class="mb-2">
                            <strong><?php if (function_exists('__')) { echo __('help.api.s4_org_read_label'); } else { echo 'View organisation details'; } ?></strong>
                            <code>org:read</code> —
                            <?php if (function_exists('__')) { echo __('help.api.s4_org_read_desc'); } else { echo 'read your organisation\'s basic information.'; } ?>
                        </li>
                        <li class="mb-2">
                            <strong><?php if (function_exists('__')) { echo __('help.api.s4_account_read_label'); } else { echo 'View account details'; } ?></strong>
                            <code>account:read</code> —
                            <?php if (function_exists('__')) { echo __('help.api.s4_account_read_desc'); } else { echo 'read your own account information.'; } ?>
                        </li>
                        <li class="mb-0">
                            <strong><?php if (function_exists('__')) { echo __('help.api.s4_qr_link_label'); } else { echo 'Link a QR code'; } ?></strong>
                            <code>qr:link</code> —
                            <?php if (function_exists('__')) { echo __('help.api.s4_qr_link_desc'); } else { echo 'connect a short link to a dynamic QR code. Only relevant if your organisation uses that feature.'; } ?>
                        </li>
                    </ul>
                </section>

                <!-- ============================================================ -->
                <!-- Section 5: Keeping your key safe                              -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="api-s5">
                    <h2 id="api-s5" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.api.s5_heading'); } else { echo '5. Keeping your key safe'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s5_intro'); } else { echo 'An API key works like a password for your account. Anyone who has it can do whatever it is permitted to do, as if they were you. A few simple habits keep it safe:'; } ?>
                    </p>

                    <ul>
                        <li class="mb-2">
                            <?php if (function_exists('__')) { echo __('help.api.s5_rule_web'); } else { echo 'Never put a key in a web page, an app that runs in someone\'s browser, or anywhere a visitor could view the page\'s underlying code. Keep it on your own server, in a script, or in a password manager.'; } ?>
                        </li>
                        <li class="mb-2">
                            <?php if (function_exists('__')) { echo __('help.api.s5_rule_email'); } else { echo 'Never send a key by email or instant message, and never paste it into a support ticket or a public forum post.'; } ?>
                        </li>
                        <li class="mb-2">
                            <?php if (function_exists('__')) { echo __('help.api.s5_rule_code'); } else { echo 'Never save a key inside code you share publicly, such as a public code repository.'; } ?>
                        </li>
                        <li class="mb-2">
                            <?php if (function_exists('__')) { echo __('help.api.s5_rule_separate'); } else { echo 'Create a separate key for each application or purpose. If one of them ever needs to be withdrawn, the others keep working undisturbed.'; } ?>
                        </li>
                        <li class="mb-0">
                            <?php if (function_exists('__')) { echo __('help.api.s5_rule_withdraw'); } else { echo 'Withdraw a key the moment you think it may have been seen by someone else. Withdrawing takes effect immediately, and anything still using that key will stop working straight away.'; } ?>
                        </li>
                    </ul>
                </section>

                <!-- ============================================================ -->
                <!-- Section 6: How often you can call it                          -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="api-s6">
                    <h2 id="api-s6" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.api.s6_heading'); } else { echo '6. How often you can call it'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s6_p1'); } else { echo 'To keep the service fair and reliable for everyone, each key is limited in how many requests it can make: a short-term limit on requests per minute, and a longer-term limit on requests per day. The exact numbers depend on your organisation\'s plan.'; } ?>
                    </p>

                    <p class="mb-0">
                        <?php if (function_exists('__')) { echo __('help.api.s6_p2'); } else { echo 'If a key goes over its limit, the next request gets an error back instead of the answer it asked for, along with a note saying how many seconds to wait before trying again. Once that time has passed, requests are accepted normally again.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 7: Full technical documentation                      -->
                <!-- ============================================================ -->
                <section class="mb-4" aria-labelledby="api-s7">
                    <h2 id="api-s7" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.api.s7_heading'); } else { echo '7. Full technical documentation'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.api.s7_intro'); } else { echo 'This page is a plain-English introduction. When you are ready to write code against the API, two more detailed resources are available:'; } ?>
                    </p>

                    <ul>
                        <li class="mb-2">
                            <strong><a href="/api/docs/"><?php if (function_exists('__')) { echo __('help.api.s7_docs_label'); } else { echo 'Reference manual'; } ?></a></strong> —
                            <?php if (function_exists('__')) { echo __('help.api.s7_docs_desc'); } else { echo 'every request and response, laid out in full. Best for reading and looking things up while you build.'; } ?>
                        </li>
                        <li class="mb-0">
                            <strong><a href="/api/docs/swagger/"><?php if (function_exists('__')) { echo __('help.api.s7_swagger_label'); } else { echo 'Interactive console'; } ?></a></strong> —
                            <?php if (function_exists('__')) { echo __('help.api.s7_swagger_desc'); } else { echo 'a page where you paste in your own key and send a request straight from your browser, to see exactly what comes back.'; } ?>
                        </li>
                    </ul>

                    <div class="alert alert-danger" role="alert">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.api.s7_swagger_warning'); } else { echo 'The interactive console sends real requests against your real account. If you use it to create a link, that link is created in your account, exactly as if you had called the API from your own code. There is no separate practice area.'; } ?>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- Closing + Related Help Pages + Contact CTA                    -->
                <!-- ============================================================ -->
                <hr class="my-5">

                <p class="text-center text-body-secondary">
                    <?php if (function_exists('__')) { echo __('help.api.closing'); } else { echo 'Anything not covered on this page can go to our contact page, and we will help.'; } ?>
                </p>

                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="/help" class="btn btn-outline-secondary">
                        <i class="fas fa-life-ring" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.api.link_home'); } else { echo 'Help home'; } ?>
                    </a>
                    <a href="/help/short-links" class="btn btn-outline-secondary">
                        <i class="fas fa-link" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.api.link_short_links'); } else { echo 'Creating and managing short links'; } ?>
                    </a>
                    <a href="/help/analytics" class="btn btn-outline-secondary">
                        <i class="fas fa-chart-bar" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.api.link_analytics'); } else { echo 'Understanding your click statistics'; } ?>
                    </a>
                    <a href="/help/custom-domains" class="btn btn-outline-secondary">
                        <i class="fas fa-globe" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.api.link_custom_domains'); } else { echo 'Using your own short domain'; } ?>
                    </a>
                    <a href="/contact" class="btn btn-outline-primary">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.api.contact_cta'); } else { echo 'Contact us'; } ?>
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>
