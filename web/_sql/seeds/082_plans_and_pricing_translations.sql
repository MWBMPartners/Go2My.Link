-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🌍 Go2My.Link — Seed: plans, limits and the pricing page (en-GB)
-- ============================================================================
--
-- Issue #209, decision D-020 (the pricing page says only what is true at
-- launch), and item 8 of #294 (team roles and the country breakdown).
--
-- Checked against the code:
--   * The Free plan's 50 active links (seed 001) are checked by
--     createShortURL() when a link is created for an organisation. "Active"
--     means switched on (isActive = 1), so a link past its end date still
--     counts. When this seed was written the limit was not checked for people
--     in no organisation or when a link was switched back on, so its words
--     name organisations. Both are checked since #327: seed 084 replaces the
--     pricing and short-links help texts about the limit.
--   * Free allows no custom short domain (maxCustomDomains 0).
--   * An organisation has two roles, Admin and User.
--   * A country breakdown needs IP location lookup, which is off by default.
--   * The API's per-minute limit is counted per key. The daily limit is
--     shared by the organisation's keys, except a key with a daily limit of
--     its own (g2ml_apiCheckRateLimit()).
--
-- New key names are used, so an existing database picks up the new words
-- although this file never overwrites a row.
--
-- ⚠️ Apply this file to every EXISTING database, not only a fresh install:
-- without it the pages show key names. It is INSERT IGNORE, so re-running it
-- is safe.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB).
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    1.0.0
-- @since      v1.0.0 — Launch (#209)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO `tblTranslations` (`localeCode`, `translationKey`, `translationValue`, `context`, `isVerified`)
VALUES
('en-GB', 'pricing.description_v2', 'The Go2My.Link Free plan. Paid plans are coming.', 'Pricing page', 1),
('en-GB', 'pricing.subtitle_v2', 'The Free plan is available now. Paid plans are coming.', 'Pricing page', 1),
('en-GB', 'pricing.free_desc_v2', 'For shortening and looking after your links.', 'Pricing page', 1),
('en-GB', 'pricing.free_limit_organisations', 'For organisations: new links can be created while fewer than 50 are switched on. A link past its end date still counts until it is switched off. The 50 are shared by all members.', 'Pricing page', 1),
('en-GB', 'pricing.free_statistics', 'Click statistics for your links', 'Pricing page', 1),
('en-GB', 'pricing.paid_heading', 'Paid plans', 'Pricing page', 1),
('en-GB', 'pricing.paid_text', 'Paid plans are coming. They cannot be bought yet.', 'Pricing page', 1),
('en-GB', 'pricing.anonymous_note', 'You can also shorten links on the home page without an account. Hourly and daily limits apply.', 'Pricing page', 1),
('en-GB', 'features.domains_desc_v2', 'Organisations can use their own short domain instead of g2my.link. This is not part of the Free plan. Paid plans are coming and cannot be bought yet.', 'Features page', 1),
('en-GB', 'features.teams_desc_v2', 'Invite people to your organisation by email. Each one is an admin or a user. Admins manage members, organisation settings and API keys. Users create and manage their own links.', 'Features page', 1),
('en-GB', 'about.mission_text_v2', 'Go2My.Link was built to make link management simple, secure, and powerful. Whether you''re sharing a single web address or looking after links with your organisation, we provide tools to shorten, track, and manage your links with confidence.', 'About page', 1),
('en-GB', 'about.offer_shorten_desc_v2', 'Create clean, memorable short links from long web addresses on our g2my.link domain. Using your own domain is not part of the Free plan.', 'About page', 1),
('en-GB', 'about.offer_orgs_desc_v2', 'Work as a team with two roles, admin and user. Members can see click statistics for the whole organisation.', 'About page', 1),
('en-GB', 'help.home.qa5_a_v2', 'Sign in and open Analytics in your dashboard. It shows your total clicks over time, your best-performing links, and a breakdown by browser and device. A breakdown by country appears only where the site operator has switched on location lookup.', 'Help — Home', 1),
('en-GB', 'help.short_links.s9_item6_v2', '<strong>"You have reached your plan''s link limit"</strong> when creating a link for an organisation — the organisation''s plan allows only so many switched-on links (50 on the Free plan), shared by its members. A link past its end date still counts until it is turned off. Turn off links that are no longer needed to make room. Paid plans are coming and cannot be bought yet.', 'Help — Short Links', 1),
('en-GB', 'help.api.s6_p1_v2', 'To keep the service fair and reliable for everyone, there are limits on how many requests can be made. The per-minute limit is counted for each key separately. The daily limit is usually one allowance shared by all of your organisation''s keys, and its size usually depends on the organisation''s plan, so creating more keys does not give you more requests a day. A key that has been given its own daily limit is counted on its own.', 'Help — API', 1),
('en-GB', 'help.custom_domains.s2_note_limit_v2', 'Custom short domains are not part of the Free plan, and paid plans cannot be bought yet. If your plan does not allow another domain, you will see a message giving the limit when you click Add, and the domain is not added.', 'Help — Custom Domains', 1),
('en-GB', 'links.error_plan_limit', 'You have reached your plan''s link limit. To make room, turn off a link that is no longer needed (a link past its end date still counts until it is turned off). Paid plans are coming and cannot be bought yet.', 'Create link — plan limit reached', 1),
('en-GB', 'linkspage.limit_reached_v2', 'You have reached your plan''s LinksPage limit. To make room, delete a LinksPage you no longer need. Paid plans are coming and cannot be bought yet.', 'LinksPage management (admin)', 1),
('en-GB', 'linkspage.error.page_limit_reached_with_limit_v2', 'You have reached your plan''s LinksPage limit of {limit}. To make room, delete a LinksPage you no longer need. Paid plans are coming and cannot be bought yet.', 'LinksPage create — plan limit reached (known limit)', 1),
('en-GB', 'linkspage.error.page_limit_reached_no_limit_v2', 'You have reached your plan''s LinksPage limit. To make room, delete a LinksPage you no longer need. Paid plans are coming and cannot be bought yet.', 'LinksPage create — plan limit reached (limit not reported)', 1);
