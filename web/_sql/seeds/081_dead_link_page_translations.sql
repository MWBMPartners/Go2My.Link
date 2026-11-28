-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- ============================================================================
-- 🔗 Go2My.Link — Seed: the pages for links that do not open (#290)
-- ============================================================================
--
-- Owner decision D-006: a short link that does not exist, has expired, is not
-- active yet or is switched off shows a page saying why. This file holds the
-- words of those pages and of the safety check page on g2my.link, and three
-- corrected help paragraphs.
--
-- Contents:
--   - shortlink.*                  the g2my.link pages
--   - help.short_links.s4_p3_v2    what a visitor sees before or after the dates
--   - help.short_links.s7_p2_v3    what a visitor sees for a switched-off link
--   - help.analytics.s1_p2_v2      why such a visit is not counted
--   - the descriptions of the settings redirect.fallback_url (seed 003) and
--     redirect.fallback_delay (seed 006), which no longer described what the
--     settings do. Only the description changes; no value is touched. Seeds
--     003 and 006 are left as they are, so run this file again after either
--     of them is re-imported.
--
-- The three help paragraphs get new keys (ending _v2) rather than changing the
-- rows in 022, so a shipped row is never edited (the rule seed 058 follows).
--
-- ⚠️ Apply this file to EVERY existing database, not only a fresh install.
-- Until it is applied, the g2my.link pages show the English written in the
-- code, but the three help paragraphs show their key names. Safe to re-run:
-- INSERT IGNORE keeps a row that is already there, and the two description
-- updates write the same words each time.
--
-- Numbering: there is no 078, and 080 is left free for other work in progress.
--
-- Dependencies: 035_translations.sql (schema), 005_languages.sql (en-GB).
--
-- @package    Go2My.Link
-- @subpackage Seeds
-- @version    1.0.0
-- @since      #290 (owner decision D-006)
-- ============================================================================

USE `mwtools_Go2MyLink`;

INSERT IGNORE INTO `tblTranslations` (`localeCode`, `translationKey`, `translationValue`, `context`, `isVerified`)
VALUES
('en-GB', 'shortlink.not_found_title', 'Link Not Found', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.not_found_message', 'This short link does not exist, or it has been removed. Check that the address was typed correctly.', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.inactive_title', 'Link Switched Off', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.inactive_message', 'This short link has been switched off, so it no longer goes anywhere.', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.not_yet_active_title', 'Link Not Yet Active', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.not_yet_active_message', 'This short link is not active yet. Please try again later.', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.expired_title', 'Link Expired', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.expired_message', 'This short link has expired and is no longer available.', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.max_hops_title', 'Link Cannot Be Followed', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.max_hops_message', 'This short link points to other short links too many times, so we cannot work out where it should go. Its owner needs to correct it.', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.no_destination_title', 'Link Not Configured', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.no_destination_message', 'This short link has no destination address set.', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.domain_not_configured_title', 'Domain Not Configured', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.domain_not_configured_message', 'This domain is not yet verified for use with Go2My.Link. If you own the domain, finish the DNS verification from your dashboard.', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.unavailable_title', 'Service Temporarily Unavailable', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.unavailable_message', 'We cannot look up this link just now. Please try again in a few minutes.', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.home_link', 'Go to the {site} homepage', 'Short link pages (g2my.link)', 1),
('en-GB', 'shortlink.checking_title', 'Checking Link Safety', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'shortlink.checking_message', 'We could not verify the safety of the destination for this short link.', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'shortlink.checking_destination', 'Destination:', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'shortlink.checking_caution', 'Proceed with caution.', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'shortlink.checking_advice', 'The destination may be temporarily unavailable or may have moved. If you do not trust this link, use the safe alternative below.', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'shortlink.checking_proceeding', 'Proceeding in {seconds} seconds...', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'shortlink.checking_announce_one', 'Proceeding in 1 second', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'shortlink.checking_announce_many', 'Proceeding in {seconds} seconds', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'shortlink.checking_countdown_label', 'Redirect countdown', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'shortlink.checking_proceed', 'Proceed Anyway', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'shortlink.checking_safe', 'Go Somewhere Safe', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'shortlink.checking_safe_noscript', 'Go to a safe page if you are not redirected automatically.', 'Short link safety check page (g2my.link)', 1),
('en-GB', 'help.short_links.s4_p3_v2', 'If somebody clicks the link before its start date, or after its end date, they do not reach your destination address. Instead they see a page saying the link is not yet active, or has expired, with a link to the Go2My.Link homepage. If your organisation has set its own fallback address, they are sent there instead.', 'Help — Short Links', 1),
('en-GB', 'help.short_links.s7_p2_v3', 'While a link is switched off, anyone who visits its short address sees a page saying so. This is a different message from the one shown for a link that has not started yet or has already ended (see Start and end dates above). If your organisation has set its own fallback address, visitors are sent there instead. Go2My.Link staff can also switch a link off. If they do, your dashboard says so, visitors are not sent anywhere else, and you cannot switch it back on. If staff remove the block, the link goes back to how it was before, so a link you had switched off stays off.', 'Help — Short Links', 1),
('en-GB', 'help.analytics.s1_p2_v2', 'If a short link does not exist, has been switched off, has expired, or has not started yet, following it does not add to your click count. The visitor sees a page saying why, or is sent to your organisation''s fallback address if it has set one. Either way they never reach your destination, so there is nothing to count.', 'Help — Click statistics', 1);

-- The settings' own descriptions, as an administrator reads them.
UPDATE `tblSettings`
SET    `settingDescription` = 'Where a visit to a short domain with no code goes, and the safe address offered on the destination safety check page when the organisation has none. It does not decide what a link that does not open shows'
WHERE  `settingID` = 'redirect.fallback_url';

UPDATE `tblSettings`
SET    `settingDescription` = 'Seconds counted down on the destination safety check page before it moves on. The pages for links that do not open have no countdown'
WHERE  `settingID` = 'redirect.fallback_delay';
