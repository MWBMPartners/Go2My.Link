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
 * Go2My.Link — Help: Creating and Managing Short Links (Component A)
 * ============================================================================
 *
 * Practical, step-by-step guide to shortening a web address, choosing a
 * custom ending, setting start/end dates, editing a link later, tags,
 * turning a link off, and checking where a link goes before clicking it.
 *
 * @package    Go2My.Link
 * @subpackage ComponentA
 * @version    0.1.0
 * @since      Phase 8
 * ============================================================================
 */

if (function_exists('__')) {
    $pageTitle = __('help.short_links.title');
} else {
    $pageTitle = 'Creating and Managing Short Links';
}
if (function_exists('__')) {
    $pageDesc = __('help.short_links.description');
} else {
    $pageDesc = 'A practical guide to shortening web addresses, choosing your own ending, setting dates, editing links, tags, and turning a link off.';
}
?>

<!-- ====================================================================== -->
<!-- Page Header                                                             -->
<!-- ====================================================================== -->
<section class="page-header text-center" aria-labelledby="shortlinks-heading">
    <div class="container">
        <h1 id="shortlinks-heading" class="display-4 fw-bold">
            <?php if (function_exists('__')) { echo __('help.short_links.heading'); } else { echo 'Creating and Managing Short Links'; } ?>
        </h1>
        <p class="lead text-body-secondary">
            <?php if (function_exists('__')) { echo __('help.short_links.subtitle'); } else { echo 'How to turn a long web address into a short one, choose your own ending, and manage it afterwards.'; } ?>
        </p>
    </div>
</section>

<!-- ====================================================================== -->
<!-- Guide Content                                                           -->
<!-- ====================================================================== -->
<section class="py-5" aria-labelledby="shortlinks-content-heading">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <h2 id="shortlinks-content-heading" class="visually-hidden">Guide Content</h2>

                <!-- ============================================================ -->
                <!-- Table of Contents                                             -->
                <!-- ============================================================ -->
                <nav aria-label="Table of Contents" class="mb-5">
                    <div class="card shadow-sm">
                        <div class="card-body">
                            <h2 class="h6 fw-bold mb-3">
                                <i class="fas fa-list" aria-hidden="true"></i>
                                <?php if (function_exists('__')) { echo __('help.short_links.toc_heading'); } else { echo 'On this page'; } ?>
                            </h2>
                            <ol class="mb-0">
                                <li><a href="#shortlinks-s1"><?php if (function_exists('__')) { echo __('help.short_links.s1_title'); } else { echo 'Shortening a link without signing in'; } ?></a></li>
                                <li><a href="#shortlinks-s2"><?php if (function_exists('__')) { echo __('help.short_links.s2_title'); } else { echo 'Shortening a link once you are signed in'; } ?></a></li>
                                <li><a href="#shortlinks-s3"><?php if (function_exists('__')) { echo __('help.short_links.s3_title'); } else { echo 'Choosing your own ending'; } ?></a></li>
                                <li><a href="#shortlinks-s4"><?php if (function_exists('__')) { echo __('help.short_links.s4_title'); } else { echo 'Start and end dates'; } ?></a></li>
                                <li><a href="#shortlinks-s5"><?php if (function_exists('__')) { echo __('help.short_links.s5_title'); } else { echo 'Editing a link later'; } ?></a></li>
                                <li><a href="#shortlinks-s6"><?php if (function_exists('__')) { echo __('help.short_links.s6_title'); } else { echo 'Tags'; } ?></a></li>
                                <li><a href="#shortlinks-s7"><?php if (function_exists('__')) { echo __('help.short_links.s7_title'); } else { echo 'Turning a link off'; } ?></a></li>
                                <li><a href="#shortlinks-s8"><?php if (function_exists('__')) { echo __('help.short_links.s8_title'); } else { echo 'Checking where a link goes before clicking it'; } ?></a></li>
                                <li><a href="#shortlinks-s9"><?php if (function_exists('__')) { echo __('help.short_links.s9_title'); } else { echo 'Things that can go wrong'; } ?></a></li>
                            </ol>
                        </div>
                    </div>
                </nav>

                <!-- ============================================================ -->
                <!-- Section 1: Shortening a link without signing in               -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="shortlinks-s1">
                    <h2 id="shortlinks-s1" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.short_links.s1_heading'); } else { echo '1. Shortening a link without signing in'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s1_p1'); } else { echo 'Go to the Go2My.Link homepage. There is one box on the page: paste your long web address into it and press Shorten URL.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s1_p2'); } else { echo 'You get back a short web address on the g2my.link domain, made of a random mix of upper and lower-case letters and numbers, seven characters long — for example https://g2my.link/aB3xQ9z. Anyone who visits that address is sent straight to the web address you pasted in.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s1_p3'); } else { echo 'This quick way of shortening a link does not need an account, but it comes with a few limits: you cannot choose your own ending, you cannot set a start or end date, and you cannot add a title, notes, or tags. A link made this way is also not attached to any account, so there is no way to go back and change its destination afterwards. To lift these limits, create a free account and shorten the link from your dashboard instead — see the next section.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s1_p4'); } else { echo 'To stop the homepage box being used to send spam, there is also a limit on how many links one connection can create this way: currently 10 in any hour and 50 in any day. If you reach that limit, wait a while and try again, or sign in and use your dashboard instead, which does not have this limit.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 2: Shortening a link once you are signed in           -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="shortlinks-s2">
                    <h2 id="shortlinks-s2" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.short_links.s2_heading'); } else { echo '2. Shortening a link once you are signed in'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s2_p1'); } else { echo 'Once you have an account, sign in and open Links in your dashboard, then choose Create a New Link. The form gives you more control than the homepage box:'; } ?>
                    </p>

                    <ul>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s2_field_destination'); } else { echo '<strong>Destination web address</strong> (required) — the long address you want to shorten. It must start with http:// or https://.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s2_field_title'); } else { echo '<strong>Title</strong> (optional) — a name for the link, purely for your own reference. It is never shown to anyone who clicks the link.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s2_field_alias'); } else { echo '<strong>Custom alias</strong> (optional) — choose your own ending instead of a random one. Covered in the next section.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s2_field_tags'); } else { echo '<strong>Tags</strong> (optional) — labels to help you organise your links. Covered further down.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s2_field_category'); } else { echo '<strong>Category</strong> (optional) — pick one of your own categories from a drop-down list, if you have set any up, to group related links together.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s2_field_notes'); } else { echo '<strong>Notes</strong> (optional) — a longer note to yourself about the link. Like the title, notes are never shown to visitors.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s2_field_dates'); } else { echo '<strong>Start Date and End Date</strong> (optional) — covered in the next section but one.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s2_field_active'); } else { echo '<strong>Active</strong> — a switch that is on by default. Covered in Turning a link off below.'; } ?>
                        </li>
                    </ul>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s2_p2'); } else { echo 'When you press Create Short Link, you get back your new short address straight away, ready to copy and share.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 3: Choosing your own ending                           -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="shortlinks-s3">
                    <h2 id="shortlinks-s3" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.short_links.s3_heading'); } else { echo '3. Choosing your own ending instead of random letters'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s3_p1'); } else { echo 'When you are signed in, the Custom alias field on the create form lets you choose the part after the slash yourself, instead of letting Go2My.Link pick one at random. It must be between 3 and 50 characters long, and can only contain letters, numbers, hyphens (-), and underscores (_) — no spaces or other punctuation.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s3_p2'); } else { echo 'If somebody has already used the ending you want, you will see a message telling you it is already taken, and you will need to choose a different one. A small number of endings are also reserved by Go2My.Link itself, because they are already used for other pages on the service — for example api, admin, and robots — so those cannot be chosen either.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s3_p3'); } else { echo 'Leave the Custom alias field blank to get a random ending instead, exactly like the homepage box gives you.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s3_p4'); } else { echo 'Once a link has been created, its ending cannot be changed — see Editing a link later below. Choose it carefully before you create the link, especially if you plan to print it or share it somewhere it will be hard to update.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 4: Start and end dates                                -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="shortlinks-s4">
                    <h2 id="shortlinks-s4" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.short_links.s4_heading'); } else { echo '4. Start and end dates'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s4_p1'); } else { echo 'When you are signed in, you can optionally give a link a Start Date, an End Date, or both, when you create or edit it. These are only available to signed-in users; the homepage box does not offer them.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s4_p2'); } else { echo 'A start date means the link will not work until that date and time arrive. An end date means the link will stop working once that date and time have passed. Leaving either one blank means there is no limit on that side — a link with no dates at all works for as long as you like.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s4_p3'); } else { echo 'If somebody clicks the link before its start date, or after its end date, they do not reach your destination address. Instead they see a short page saying the link is not yet active, or has expired, with a button to continue to Go2My.Link — and if they do nothing, that page moves on by itself after five seconds.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 5: Editing a link later                               -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="shortlinks-s5">
                    <h2 id="shortlinks-s5" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.short_links.s5_heading'); } else { echo '5. Editing a link later'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s5_p1'); } else { echo 'Open Links in your dashboard and press the pencil icon next to any link you created to edit it. You can change its destination web address, title, notes, category, start date, end date, and whether it is active. You can also see, but not change, its short address and how many times it has been clicked so far.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s5_p2'); } else { echo 'The important part: the short address itself never changes when you edit a link. Only the destination behind it does. That means a short link you have already printed on a flyer, put in an email footer, or posted on social media keeps working exactly as before — it will simply take people to the new destination address from now on, instead of the old one.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s5_p3'); } else { echo 'You can only edit a link you created while signed in. A link made from the homepage without signing in has no owner and cannot be edited afterwards.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 6: Tags                                               -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="shortlinks-s6">
                    <h2 id="shortlinks-s6" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.short_links.s6_heading'); } else { echo '6. Tags'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s6_p1'); } else { echo 'Tags are short labels you can attach to a link to help you find and group it later — for example marketing or q3. Type them into the Tags field on the create form, separated by commas, such as marketing, q3. You can add up to 10 tags to a single link.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s6_p2'); } else { echo 'Tags you have added appear as small labelled badges next to the link in your Links list, so you can see at a glance what each link is for.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s6_p3'); } else { echo 'Tags can only be added when you first create a link. There is currently no way to add or change a link\'s tags from the edit page afterwards, so decide on them at creation time.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 7: Turning a link off                                 -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="shortlinks-s7">
                    <h2 id="shortlinks-s7" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.short_links.s7_heading'); } else { echo '7. Turning a link off'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s7_p1'); } else { echo 'You do not have to delete a link to stop it working. On the Links list, press the bin icon next to a link and confirm Deactivate this link? — this switches the link off without removing it or its click history. You can also turn a link off, or back on, from its edit page using the Active switch.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s7_p2'); } else { echo 'While a link is switched off, anyone who visits its short address sees a page saying the link has been disabled by its owner — this is a different message from the one shown for a link that simply has not started yet or has already ended (see Start and end dates above).'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s7_p3'); } else { echo 'To turn a link back on, open it from the Links list and switch Active back on in the edit page.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 8: Checking where a link goes before clicking it       -->
                <!-- ============================================================ -->
                <section class="mb-5" aria-labelledby="shortlinks-s8">
                    <h2 id="shortlinks-s8" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.short_links.s8_heading'); } else { echo '8. Checking where a link goes before clicking it'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s8_p1'); } else { echo 'If you are ever unsure where a Go2My.Link short address will take you, go to go2my.link/info and paste the full short address, or just its code, into the box, then press Look Up.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s8_p2'); } else { echo 'You will see the full short address, its current status (Active, Inactive, Scheduled, or Expired), and where it leads. If you are not signed in, only the destination\'s domain is shown, with the rest of the address hidden, along with a link to sign in and see the whole thing; if you are signed in, the complete destination address is shown. If the link has a title, category, or start and end dates, those are shown too.'; } ?>
                    </p>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s8_p3'); } else { echo 'This lookup currently only finds links made from the Go2My.Link homepage without signing in. A link you created in your own signed-in dashboard does not currently appear in this lookup.'; } ?>
                    </p>
                </section>

                <!-- ============================================================ -->
                <!-- Section 9: Things that can go wrong                           -->
                <!-- ============================================================ -->
                <section class="mb-4" aria-labelledby="shortlinks-s9">
                    <h2 id="shortlinks-s9" class="h4 fw-bold mb-3">
                        <?php if (function_exists('__')) { echo __('help.short_links.s9_heading'); } else { echo '9. Things that can go wrong'; } ?>
                    </h2>

                    <p>
                        <?php if (function_exists('__')) { echo __('help.short_links.s9_intro'); } else { echo 'A few messages you might see, and what they mean:'; } ?>
                    </p>

                    <ul>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s9_item1'); } else { echo '<strong>"Invalid URL format"</strong> — the web address you entered must start with http:// or https://. Check for typos and try again.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s9_item2'); } else { echo '<strong>"Cannot shorten URLs that point to this service"</strong> — you cannot shorten a g2my.link, go2my.link, or lnks.page address, or your own custom short domain, because that would send visitors round in a loop.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s9_item3'); } else { echo '<strong>"That destination is not permitted"</strong> — the web address you enter has to be a normal, publicly reachable web page; addresses that point to internal or private networks are rejected for safety.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s9_item4'); } else { echo '<strong>A problem with your chosen ending</strong> — a message saying it is the wrong length, contains characters that are not allowed, is already taken, or is a reserved word, means you need to pick a different ending. See Choosing your own ending above.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s9_item5'); } else { echo '<strong>"Rate limit exceeded"</strong> when not signed in — the homepage box allows up to 10 new links an hour and 50 a day from the same connection, to stop it being used for spam. Wait a while, or sign in and use your dashboard instead, which does not have this limit.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s9_item6'); } else { echo '<strong>"You have reached your plan\'s link limit"</strong> when signed in — your account has a maximum number of active links allowed by your plan. Turn off links you no longer need, or upgrade your plan, to create more.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s9_item7'); } else { echo '<strong>"Link not found or you do not have permission to edit it"</strong> — you can only edit or turn off a link you created yourself while signed in.'; } ?>
                        </li>
                        <li>
                            <?php if (function_exists('__')) { echo __('help.short_links.s9_item8'); } else { echo '<strong>A link you made while signed in does not show up on the /info lookup page</strong> — this is expected for now (see Checking where a link goes above); it does not mean the link is broken.'; } ?>
                        </li>
                    </ul>
                </section>

                <!-- ============================================================ -->
                <!-- Related Guides + Contact CTA                                  -->
                <!-- ============================================================ -->
                <hr class="my-5">

                <h2 class="h6 fw-bold text-center mb-3">
                    <?php if (function_exists('__')) { echo __('help.short_links.related_heading'); } else { echo 'Related guides'; } ?>
                </h2>

                <div class="d-flex flex-wrap justify-content-center gap-2">
                    <a href="/help" class="btn btn-outline-secondary">
                        <i class="fas fa-life-ring" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.short_links.link_help_home'); } else { echo 'Help home'; } ?>
                    </a>
                    <a href="/help/analytics" class="btn btn-outline-secondary">
                        <i class="fas fa-chart-bar" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.short_links.link_analytics'); } else { echo 'Understanding your click statistics'; } ?>
                    </a>
                    <a href="/help/custom-domains" class="btn btn-outline-secondary">
                        <i class="fas fa-globe" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.short_links.link_custom_domains'); } else { echo 'Using your own short domain'; } ?>
                    </a>
                    <a href="/help/api" class="btn btn-outline-secondary">
                        <i class="fas fa-code" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.short_links.link_api'); } else { echo 'Using the API'; } ?>
                    </a>
                    <a href="/contact" class="btn btn-outline-primary">
                        <i class="fas fa-envelope" aria-hidden="true"></i>
                        <?php if (function_exists('__')) { echo __('help.short_links.contact_cta'); } else { echo 'Questions? Contact us'; } ?>
                    </a>
                </div>

            </div>
        </div>
    </div>
</section>
