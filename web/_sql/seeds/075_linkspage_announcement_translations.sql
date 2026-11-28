-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: announcing Lnks.Page (en-GB)
-- ============================================================================
--
-- Issue #320, decision D-012: the first version announces LinksPage. These are
-- the words for the lnks.page home page (which used to say "coming soon") and
-- for the mentions on go2my.link: home page, features, pricing, help and the
-- footer.
--
-- Checked against the code (#210's rule that marketing text must be true):
-- any signed-in user can create a page, on the free plan too (one page per
-- person in a free-plan organisation, seed 001; no limit in the default group); there are five system designs (seed
-- 004); a picture is a web address, not an upload; the 18-or-older step is a
-- visitor's own confirmation. Custom HTML, click statistics for a page and
-- custom domains are not mentioned: they are off, not built, or not on the
-- free plan.
--
-- The old keys linkspage.coming_soon and linkspage.learn_more are no longer
-- shown. New key names are used, so an existing database picks up the new
-- words although this file never overwrites a row.
--
-- ⚠️ Apply this file to every EXISTING database, not only a fresh install:
-- without it the go2my.link pages show key names. (The lnks.page home page
-- falls back to the English in the code.) It is INSERT IGNORE, so re-running
-- it is safe.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.0.0 — Launch (#320)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO `tblTranslations` (`localeCode`, `translationKey`, `translationValue`, `context`, `isVerified`)
VALUES
('en-GB', 'linkspage.home.heading', 'Lnks.Page', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.page_title', 'Lnks.Page — one page for all your links', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.meta_description', 'Make one page that lists all your links, in a ready-made design. Included with a free Go2My.Link account.', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.subtitle', 'One page for all your links', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.intro', 'Lnks.Page gives you one web address that lists your links. Share it in a social media profile, an email signature or on printed material.', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.features_heading', 'What you can do', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.feature_address', 'Choose your own ending for the address, such as lnks.page/yourname, if nobody else has taken it.', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.feature_links', 'Add your Go2My.Link short links or any other web address, and change their order or hide them later.', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.feature_designs', 'Pick one of five ready-made designs and the font you like.', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.feature_profile', 'Add a title, a short description, a picture that is already online, and links to your social media profiles.', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.feature_publish', 'Preview your page, and publish or unpublish it whenever you like.', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.feature_age_gate', 'Mark a link as adult content, and visitors must confirm they are 18 or older before they see the page.', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.start_heading', 'Make your page', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.start_text', 'Lnks.Page is part of Go2My.Link. Create a free account, then open LinksPage in your dashboard.', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.register', 'Create a free account', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.dashboard', 'Already have an account? Open your dashboard', 'Lnks.Page home page', 1),
('en-GB', 'linkspage.home.about_link', 'Visit Go2My.Link', 'Lnks.Page home page', 1),
('en-GB', 'home.linkspage_heading', 'One page for all your links', 'Homepage', 1),
('en-GB', 'home.linkspage_text', 'With Lnks.Page you can put all your links on one page, pick a ready-made design and share a single address. It is included with a free account.', 'Homepage', 1),
('en-GB', 'home.linkspage_button', 'See what Lnks.Page does', 'Homepage', 1),
('en-GB', 'features.linkspage_title', 'Link pages on Lnks.Page', 'Features page', 1),
('en-GB', 'features.linkspage_desc', 'Make one page that lists all your links. It is included with a free account, and you manage it from your dashboard.', 'Features page', 1),
('en-GB', 'features.linkspage_item_address', 'Your own ending for the address, such as lnks.page/yourname, if it is free', 'Features page', 1),
('en-GB', 'features.linkspage_item_links', 'Your short links or any other web address, in the order you choose', 'Features page', 1),
('en-GB', 'features.linkspage_item_designs', 'Five ready-made designs and your choice of font', 'Features page', 1),
('en-GB', 'features.linkspage_item_publish', 'Preview first, then publish or unpublish whenever you like', 'Features page', 1),
('en-GB', 'features.linkspage_item_age_gate', 'An 18-or-older confirmation for pages with links marked as adult content', 'Features page', 1),
('en-GB', 'features.linkspage_button', 'See what Lnks.Page does', 'Features page', 1),
('en-GB', 'pricing.free_feature_linkspage', 'A link page on Lnks.Page', 'Pricing page', 1),
('en-GB', 'help.home.qa_linkspage_q', 'Can I make one page that lists all my links?', 'Help — Home', 1),
('en-GB', 'help.home.qa_linkspage_a', 'Yes. Sign in, open LinksPage in your dashboard and create a page. Choose its address on lnks.page, add your links and publish it. Visitors cannot see the page until it is published.', 'Help — Home', 1),
('en-GB', 'help.home.qa_linkspage_link', 'More about Lnks.Page', 'Help — Home', 1),
('en-GB', 'footer.linkspage', 'Lnks.Page link pages', 'Footer', 1);
