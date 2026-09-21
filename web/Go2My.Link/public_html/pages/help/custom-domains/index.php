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
 * Go2My.Link — Help: Using Your Own Domain for Short Links (Component A)
 * ============================================================================
 *
 * Step-by-step, plain-English guide to adding, proving ownership of, and
 * routing a customer's own domain for their short links. This page replaces
 * the link the dashboard used to give customers to docs/CUSTOM_DOMAINS.md on
 * GitHub (a repository marked proprietary, so a dead end for a customer) —
 * see web/Go2My.Link/_admin/public_html/pages/org/short-domains/index.php.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    1.0.0
 * @since      Phase 8 (Help Centre)
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('help.custom_domains.title');
} else {
    $pageTitle = 'Using Your Own Domain — Help';
}
if (function_exists('__')) {
    $pageDesc = __('help.custom_domains.description');
} else {
    $pageDesc = 'A step-by-step guide to using your own domain name for your Go2My.Link short links.';
}

if (function_exists('getSetting')) {
    $contactEmail = getSetting('site.contact_email', 'hello@go2my.link');
} else {
    $contactEmail = 'hello@go2my.link';
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="cd-heading">
    <div class="container">
        <h1 id="cd-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('help.custom_domains.heading'); } else { echo 'Using Your Own Domain for Short Links'; } ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php if (function_exists('__')) { echo __('help.custom_domains.subtitle'); } else { echo 'Make your short links match your own brand, instead of g2my.link.'; } ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Guide Content                                                           -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="cd-content-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="cd-content-heading" class="visually-hidden">Using Your Own Domain — Guide</h2>

                <!-- ============================================================ -->
                <!-- Intro                                                         -->
                <!-- ============================================================ -->
                <p>
                    <?php if (function_exists('__')) { echo __('help.custom_domains.intro_p1'); } else { echo 'When someone shares one of your Go2My.Link short links, the address they see is the domain in it — normally g2my.link. Adding your own domain (for example, links.yourcompany.com or yourbrand.link) means every short link you create shows your own name instead, so the people who click it recognise it as coming from you rather than from an unfamiliar link-shortening service.'; } ?>
                </p>
                <p>
                    <?php if (function_exists('__')) { echo __('help.custom_domains.intro_p2'); } else { echo 'This page walks through the whole process: adding the domain, proving you own it, pointing it at Go2My.Link, and what to expect along the way.'; } ?>
                </p>

                <!-- ============================================================ -->
                <!-- Table of Contents                                             -->
                <!-- ============================================================ -->
                <nav aria-label="Table of Contents" class="mb-5">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 fw-bold mb-3">
                                <i class="fas fa-list" aria-hidden="true"></i>
                                <?php if (function_exists('__')) { echo __('help.custom_domains.toc_heading'); } else { echo 'Table of Contents'; } ?>
                            </h2>
                            <ol class="mb-0">
                                <li><a href="#cd-s1"><?php if (function_exists('__')) { echo __('help.custom_domains.s1_title'); } else { echo 'What you need before you start'; } ?></a></li>
                                <li><a href="#cd-s2"><?php if (function_exists('__')) { echo __('help.custom_domains.s2_title'); } else { echo 'Adding your domain in the dashboard'; } ?></a></li>
                                <li><a href="#cd-s3"><?php if (function_exists('__')) { echo __('help.custom_domains.s3_title'); } else { echo 'Proving you own it — the TXT record'; } ?></a></li>
                                <li><a href="#cd-s4"><?php if (function_exists('__')) { echo __('help.custom_domains.s4_title'); } else { echo 'Pointing your domain at Go2My.Link — the routing record'; } ?></a></li>
                                <li><a href="#cd-s5"><?php if (function_exists('__')) { echo __('help.custom_domains.s5_title'); } else { echo 'Checking it has worked — click Verify'; } ?></a></li>
                                <li><a href="#cd-s6"><?php if (function_exists('__')) { echo __('help.custom_domains.s6_title'); } else { echo 'Why new links wait for verification to finish'; } ?></a></li>
                                <li><a href="#cd-s7"><?php if (function_exists('__')) { echo __('help.custom_domains.s7_title'); } else { echo 'Choosing a default domain'; } ?></a></li>
                                <li><a href="#cd-s8"><?php if (function_exists('__')) { echo __('help.custom_domains.s8_title'); } else { echo 'Removing a domain'; } ?></a></li>
                                <li><a href="#cd-s9"><?php if (function_exists('__')) { echo __('help.custom_domains.s9_title'); } else { echo 'Troubleshooting common problems'; } ?></a></li>
                            </ol>
                        </div>
                    </div>
                </nav>

                <!-- ============================================================ -->
                <!-- Section 1: What you need before you start                    -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cd-s1">
                    <h2 id="cd-s1" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s1_heading'); } else { echo '1. What you need before you start'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s1_p1'); } else { echo 'You can only point a domain at Go2My.Link if you already own it. We do not sell, register, or transfer domain names ourselves — if you do not already have one, you will need to buy one first from a domain registrar (companies such as GoDaddy, Namecheap, or Cloudflare, among many others).'; } ?>
                    </p>

                    <ul>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.custom_domains.s1_item_domain'); } else { echo 'A domain name you own — either a whole domain, such as yourbrand.link, or a subdomain of one you own, such as links.yourcompany.com.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.custom_domains.s1_item_dns'); } else { echo 'Access to wherever that domain\'s DNS settings are managed. This is usually the account you bought the domain through, but some people manage their DNS separately (for example, through Cloudflare) even when the domain itself was bought somewhere else. If you are not sure who manages this for your organisation, ask whoever looks after your website or company email — they will know.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.custom_domains.s1_item_admin'); } else { echo 'Administrator access to your organisation on Go2My.Link. Ordinary members of an organisation cannot add or change short domains — only an Administrator can.'; } ?>
                        </li>
                    </ul>
                </section>

                <!-- ============================================================ -->
                <!-- Section 2: Adding your domain in the dashboard               -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cd-s2">
                    <h2 id="cd-s2" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s2_heading'); } else { echo '2. Adding your domain in the dashboard'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s2_p1'); } else { echo 'Sign in at'; } ?>
                        <a href="https://admin.go2my.link/">admin.go2my.link</a>,
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s2_p1b'); } else { echo 'then go to'; } ?>
                        <a href="https://admin.go2my.link/org/short-domains"><?php if (function_exists('__')) { echo __('help.custom_domains.s2_p1_link'); } else { echo 'Organisation &rarr; Short Domains'; } ?></a>.
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s2_p2'); } else { echo 'Under &ldquo;Add Short Domain&rdquo;, type your domain &mdash; just the domain itself, with no https:// in front and no slash at the end (for example, mylinks.co or links.yourcompany.com) &mdash; then click Add.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s2_p3'); } else { echo 'The page immediately shows you two DNS records to add, covered in the next two sections. You do not need to write them down &mdash; you can come back to this screen at any time before verification finishes by clicking &ldquo;Show DNS records&rdquo; next to your domain.'; } ?>
                    </p>

                    <div class="alert alert-info" role="alert">
                        <i class="fas fa-info-circle" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s2_note_limit'); } else { echo 'Depending on your plan, there may be a limit on how many domains your organisation can add. If you reach it, the Add button will tell you your current limit rather than adding the domain.'; } ?>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- Section 3: Proving you own it — the TXT record               -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cd-s3">
                    <h2 id="cd-s3" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s3_heading'); } else { echo '3. Proving you own it — the TXT record'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s3_p1_dns'); } else { echo 'Every domain name has a set of settings attached to it, called DNS records. They tell the internet things like which server runs your website and which server handles your email. You add, change, and remove these records on whichever site manages your domain\'s DNS (see &ldquo;What you need before you start&rdquo; above).'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s3_p2_txt'); } else { echo 'One kind of DNS record, called a TXT record, simply stores a short piece of text against your domain. It does not point visitors anywhere and it does not change how your website or email works &mdash; it is just a place to put a value that something else can check later. Go2My.Link uses a TXT record to prove that whoever is setting up the domain here also controls its DNS settings, without needing any other kind of access to your domain. Adding one is a normal, safe step that many online services ask for, and it will not affect your existing website or email in any way.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s3_p3_steps'); } else { echo 'Go2My.Link shows you the exact TXT record to add on the Add Short Domain screen. At your DNS provider, add a new record with these details:'; } ?>
                    </p>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <caption class="visually-hidden">
                                <?php if (function_exists('__')) { echo __('help.custom_domains.s3_table_caption'); } else { echo 'The TXT record to add at your DNS provider'; } ?>
                            </caption>
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('help.custom_domains.col_field'); } else { echo 'Field'; } ?></th>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('help.custom_domains.col_value'); } else { echo 'What to enter'; } ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <th scope="row"><?php if (function_exists('__')) { echo __('help.custom_domains.s3_field_type'); } else { echo 'Type'; } ?></th>
                                    <td><code>TXT</code></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php if (function_exists('__')) { echo __('help.custom_domains.s3_field_host'); } else { echo 'Host / Name'; } ?></th>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s3_field_host_value'); } else { echo 'The exact value shown on your screen — it will look something like'; } ?> <code>_g2ml-verify.yourdomain.co</code></td>
                                </tr>
                                <tr>
                                    <th scope="row"><?php if (function_exists('__')) { echo __('help.custom_domains.s3_field_value'); } else { echo 'Value'; } ?></th>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s3_field_value_value'); } else { echo 'The long string of random letters and numbers that Go2My.Link shows you for this domain'; } ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <p class="text-body-secondary small">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s3_p4_prefix_note'); } else { echo 'Some DNS providers (Cloudflare and GoDaddy among them) automatically add your domain name after whatever you type into the Name/Host field. If that happens to you, type only the first part — for example, _g2ml-verify — rather than the whole host including your domain. See the provider notes in the next section if you are not sure.'; } ?>
                    </p>

                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s3_warning'); } else { echo 'Use the exact value Go2My.Link shows you for the Value field — not your organisation\'s name, handle, or anything you make up yourself. Every domain gets its own unique value, and it is never reused, so we can be sure the record was really added by whoever controls that domain.'; } ?>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- Section 4: Pointing your domain at Go2My.Link                -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cd-s4">
                    <h2 id="cd-s4" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s4_heading'); } else { echo '4. Pointing your domain at Go2My.Link — the routing record'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s4_p1'); } else { echo 'The TXT record above only proves you own the domain — it does not send any visitors anywhere. To actually make your short links work, you need a second record, and which kind you need depends on the shape of your domain.'; } ?>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s4_subdomain_heading'); } else { echo 'If you are using a subdomain (recommended)'; } ?>
                    </h3>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s4_subdomain_desc'); } else { echo 'A subdomain is a domain with something in front of it, such as links.yourcompany.com or go.yourbrand.com. If your domain looks like this, add a CNAME record with your subdomain as the Host/Name and g2my.link as the Value. A CNAME record simply tells the internet &ldquo;this address is really just another name for that address&rdquo; — visitors are quietly sent on to Go2My.Link\'s servers without ever seeing g2my.link in their browser.'; } ?>
                    </p>

                    <h3 class="h6 fw-bold mt-4 mb-2">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s4_apex_heading'); } else { echo 'If you are using a bare (apex/root) domain'; } ?>
                    </h3>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s4_apex_desc'); } else { echo 'A bare domain has nothing in front of it, such as yourbrand.link. Domains like this cannot use a CNAME record at all — that is a rule built into how DNS works everywhere, not something specific to Go2My.Link. Instead:'; } ?>
                    </p>

                    <ul>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.custom_domains.s4_apex_alias'); } else { echo 'If your DNS provider offers a record type called ALIAS or ANAME (sometimes described as &ldquo;CNAME flattening&rdquo;), use that instead — it behaves like a CNAME even though bare domains cannot normally have one. Point it at g2my.link, the same as the CNAME above.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.custom_domains.s4_apex_a_record'); } else { echo 'If your provider does not offer that, use a plain A record instead, which points at a numeric address rather than a name. Since this address can occasionally change on our side, contact us once you are ready for this step and we will give you the current one to use.'; } ?>
                        </li>
                    </ul>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s4_recommend'); } else { echo 'If you can choose either shape for your domain, we would recommend a subdomain — it is simpler to set up and does not depend on a numeric address that can change.'; } ?>
                    </p>

                    <div class="card shadow-sm mt-4">
                        <div class="card-body">
                            <h3 class="h6 fw-bold mb-2">
                                <i class="fas fa-book" aria-hidden="true"></i>
                                <?php if (function_exists('__')) { echo __('help.custom_domains.s4_provider_heading'); } else { echo 'Notes for common DNS providers'; } ?>
                            </h3>
                            <ul class="small mb-0">
                                <li>
                                    <strong>Cloudflare</strong> —
                                    <?php if (function_exists('__')) { echo __('help.custom_domains.s4_provider_cloudflare'); } else { echo 'DNS &rarr; Records &rarr; Add record. Enter just the prefix (for example, _g2ml-verify) in the Name field, not your full domain — Cloudflare adds it for you. For the routing record, set the proxy status to &ldquo;DNS only&rdquo; (grey cloud), not &ldquo;Proxied&rdquo;.'; } ?>
                                </li>
                                <li>
                                    <strong>GoDaddy</strong> —
                                    <?php if (function_exists('__')) { echo __('help.custom_domains.s4_provider_godaddy'); } else { echo 'My Products &rarr; DNS &rarr; Add New Record. GoDaddy also wants just the prefix in the Name field, not your full domain.'; } ?>
                                </li>
                                <li>
                                    <strong>Namecheap</strong> —
                                    <?php if (function_exists('__')) { echo __('help.custom_domains.s4_provider_namecheap'); } else { echo 'Domain List &rarr; Manage &rarr; Advanced DNS &rarr; Add New Record. Namecheap does not support a plain CNAME on a bare domain — use their ALIAS or URL Redirect record type instead, or use a subdomain.'; } ?>
                                </li>
                            </ul>
                        </div>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- Section 5: Checking it has worked — click Verify             -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cd-s5">
                    <h2 id="cd-s5" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s5_heading'); } else { echo '5. Checking it has worked — click Verify'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s5_p1'); } else { echo 'Once both records are added, go back to Organisation &rarr; Short Domains in your dashboard and click Verify next to your domain.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s5_p2_propagation'); } else { echo 'DNS changes do not appear everywhere on the internet the instant you save them. Copies of your old and new records are temporarily stored (&ldquo;cached&rdquo;) by countless computers around the world, and it takes time for all of them to notice the change and pick up the new version — this is usually called DNS propagation.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s5_p3_timing'); } else { echo 'This is usually done within a few minutes, but it can occasionally take as long as 48 hours, depending on your DNS provider and how long the record you replaced was set to be cached for.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s5_p4_early_check'); } else { echo 'If you click Verify before your TXT record has spread everywhere, Go2My.Link may not be able to find it yet and will tell you so — that does not mean you have done anything wrong. It usually just means you need to wait a little longer and try again. It is completely safe to click Verify as many times as you need.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s5_p5_success'); } else { echo 'Once verification succeeds, your domain\'s badge changes to Verified, and a second badge shows Routing live — from that point on, it is ready to carry your short links.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 6: Why new links wait for verification to finish     -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cd-s6">
                    <h2 id="cd-s6" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s6_heading'); } else { echo '6. Why new links wait for verification to finish'; } ?>
                    </h2>

                    <div class="alert alert-warning" role="alert">
                        <i class="fas fa-exclamation-triangle" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s6_p1_warning'); } else { echo 'Until your domain shows Verified, Go2My.Link will not build any of your short links using it. A short link only works if the domain in its address is fully set up — a link built on a domain that is not ready yet would look fine but do nothing for anyone who clicked it.'; } ?>
                    </div>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s6_p2'); } else { echo 'So if you add a new domain and set it as your default before it finishes verifying, Go2My.Link automatically carries on giving out new links on g2my.link behind the scenes, rather than risk creating a link that does not work. As soon as your domain finishes verifying, new links start using it straight away — nothing you have already shared changes or breaks in the meantime.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 7: Choosing a default domain                         -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cd-s7">
                    <h2 id="cd-s7" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s7_heading'); } else { echo '7. Choosing a default domain'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s7_p1'); } else { echo 'If your organisation has more than one verified domain, one of them is your default. This is the address you are shown when you create a new link, and the address shown next to your existing links throughout the dashboard.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s7_p2'); } else { echo 'Your short links still work through any of your organisation\'s verified domains, not only the default one — the default setting only decides which address you are shown; it does not limit which domain a link can be reached on.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s7_p3'); } else { echo 'To make a different domain the default, find it in your list of Short Domains and click Set Default. This is only available for domains that are already Verified.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 8: Removing a domain                                 -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="cd-s8">
                    <h2 id="cd-s8" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s8_heading'); } else { echo '8. Removing a domain'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s8_p1'); } else { echo 'You can remove a short domain you no longer want from the same Short Domains page. You cannot remove your current default domain — set a different one as default first, then remove the one you no longer need.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s8_p2_links'); } else { echo 'Removing a domain does not delete any of your links. Your short links and their destinations stay exactly as they are, and they carry on working through any of your organisation\'s other verified domains. What stops working is that one address: once a domain is removed, anyone who visits a link using it will no longer reach your organisation at all.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s8_p3_dns'); } else { echo 'Removing the domain from Go2My.Link does not remove the DNS records at your provider. If you want the domain to stop pointing at us altogether, delete or change those records yourself afterwards. If you leave them in place without the domain registered here, visits to that domain will no longer resolve any of your short links.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 9: Troubleshooting                                    -->
                <!-- ============================================================ -->
                <section class="mb-4" aria-labelledby="cd-s9">
                    <h2 id="cd-s9" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s9_heading'); } else { echo '9. Troubleshooting common problems'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.s9_intro'); } else { echo 'Where a step below depends on your own DNS provider, we say so plainly rather than pretending there is one set of instructions that fits everyone — the exact screens differ between providers, even though the records themselves are the same.'; } ?>
                    </p>

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped table-sm">
                            <caption class="visually-hidden">
                                <?php if (function_exists('__')) { echo __('help.custom_domains.s9_table_caption'); } else { echo 'Common domain setup problems and how to fix them'; } ?>
                            </caption>
                            <thead class="table-dark">
                                <tr>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('help.custom_domains.col_symptom'); } else { echo 'What you see'; } ?></th>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('help.custom_domains.col_cause'); } else { echo 'Likely cause'; } ?></th>
                                    <th scope="col"><?php if (function_exists('__')) { echo __('help.custom_domains.col_fix'); } else { echo 'What to do'; } ?></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row1_symptom'); } else { echo 'Verify says &ldquo;No TXT record found&rdquo;'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row1_cause'); } else { echo 'Your DNS change has not spread everywhere yet, or the record was added at the wrong Host/Name.'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row1_fix'); } else { echo 'Double-check the exact Host/Name shown on your Short Domains screen, including the _g2ml-verify prefix and your domain. Then wait a while and click Verify again.'; } ?></td>
                                </tr>
                                <tr>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row2_symptom'); } else { echo 'Verify says &ldquo;the value does not match&rdquo;'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row2_cause'); } else { echo 'A TXT record exists at the right Host/Name, but its value is wrong — often a typo, or a leftover record from an earlier attempt.'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row2_fix'); } else { echo 'Check for and remove any duplicate or old TXT record at that Host/Name, then click Verify again.'; } ?></td>
                                </tr>
                                <tr>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row3_symptom'); } else { echo 'Visitors to my domain see &ldquo;Domain Not Configured&rdquo;'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row3_cause'); } else { echo 'You have not clicked Verify yet, or verification has not succeeded.'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row3_fix'); } else { echo 'Adding the DNS records alone does not switch anything on — click Verify and wait for it to succeed before sharing links on this domain.'; } ?></td>
                                </tr>
                                <tr>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row4_symptom'); } else { echo 'My DNS provider says &ldquo;CNAME not allowed&rdquo; on my bare domain'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row4_cause'); } else { echo 'A bare (apex/root) domain cannot have a CNAME record — this is a general DNS rule, not specific to your provider.'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row4_fix'); } else { echo 'Use an ALIAS/ANAME record if your provider offers one, or an A record, as described in section 4 above — or use a subdomain instead.'; } ?></td>
                                </tr>
                                <tr>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row5_symptom'); } else { echo 'My links work over http:// but not https://'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row5_cause'); } else { echo 'Secure browsing (https://) needs a security certificate set up for your domain. Today this is a manual step on our side that happens after verification, not something that switches on automatically.'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row5_fix'); } else { echo 'Once your domain shows Verified, contact us and we will arrange this for you.'; } ?></td>
                                </tr>
                                <tr>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row6_symptom'); } else { echo 'Propagation is taking a long time'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row6_cause'); } else { echo 'How long a DNS change takes to spread is limited by how long the record it replaced was set to be cached for (its &ldquo;TTL&rdquo;).'; } ?></td>
                                    <td><?php if (function_exists('__')) { echo __('help.custom_domains.s9_row6_fix'); } else { echo 'Wait it out — it will finish on its own. Next time, if you know you are about to change a record, you can lower its cache time in advance and raise it again afterwards.'; } ?></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>

                <!-- ============================================================ -->
                <!-- Contact + Related Help Topics                                 -->
                <!-- ============================================================ -->
                <hr class="my-5">

                <p class="text-center text-body-secondary">
                    <?php if (function_exists('__')) { echo __('help.custom_domains.contact_lead'); } else { echo 'Still stuck, or need us to set up your certificate?'; } ?>
                    <a href="mailto:<?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($contactEmail, ENT_QUOTES, 'UTF-8'); ?></a>
                </p>

                <p class="text-center fw-bold mb-3">
                    <?php if (function_exists('__')) { echo __('help.custom_domains.related_heading'); } else { echo 'Related help topics'; } ?>
                </p>

                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="/help" class="btn btn-outline-secondary">
                        <i class="fas fa-life-ring" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.link_help_home'); } else { echo 'Help Home'; } ?>
                    </a>
                    <a href="/help/short-links" class="btn btn-outline-secondary">
                        <i class="fas fa-link" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.link_short_links'); } else { echo 'Creating and Managing Short Links'; } ?>
                    </a>
                    <a href="/help/analytics" class="btn btn-outline-secondary">
                        <i class="fas fa-chart-bar" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.link_analytics'); } else { echo 'Understanding Your Click Statistics'; } ?>
                    </a>
                    <a href="/help/api" class="btn btn-outline-secondary">
                        <i class="fas fa-code" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.link_api'); } else { echo 'Using the API'; } ?>
                    </a>
                    <a href="/contact" class="btn btn-outline-primary">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.custom_domains.contact_cta'); } else { echo 'Questions? Contact Us'; } ?>
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>
