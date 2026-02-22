-- =============================================================================
-- GoToMyLink — Seed Data: Subscription Tiers
-- =============================================================================
-- Default subscription tier definitions.
-- Must be loaded BEFORE organisation migrations (tblOrganisations references tierID).
--
-- @package    GoToMyLink
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblSubscriptionTiers` (
    `tierID`, `tierName`, `tierDescription`,
    `tierPriceMonthly`, `tierPriceAnnual`, `tierCurrency`,
    `maxLinks`, `maxCustomDomains`, `maxAPIRequestsPerDay`, `maxLinksPages`,
    `hasAdvancedRedirects`, `hasAnalytics`, `hasQRCodes`, `hasAPIAccess`, `hasPrioritySupport`,
    `sortOrder`, `isActive`
) VALUES
-- Free tier
(
    'free', 'Free', 'Get started with basic URL shortening. Perfect for personal use.',
    0.00, 0.00, 'GBP',
    50,    -- maxLinks
    0,     -- maxCustomDomains
    100,   -- maxAPIRequestsPerDay
    1,     -- maxLinksPages
    0, 0, 1, 0, 0,  -- no advanced, no analytics, yes QR (ext. service), no API, no support
    1, 1
),
-- Basic tier
(
    'basic', 'Basic', 'More links and basic analytics. Great for small businesses.',
    4.99, 49.99, 'GBP',
    500,   -- maxLinks
    1,     -- maxCustomDomains
    5000,  -- maxAPIRequestsPerDay
    3,     -- maxLinksPages
    0, 1, 1, 1, 0,  -- no advanced, yes analytics, yes QR (ext. service), yes API, no support
    2, 1
),
-- Premium tier
(
    'premium', 'Premium', 'Advanced redirects, full analytics, and priority support. For growing teams.',
    14.99, 149.99, 'GBP',
    5000,  -- maxLinks
    5,     -- maxCustomDomains
    50000, -- maxAPIRequestsPerDay
    10,    -- maxLinksPages
    1, 1, 1, 1, 1,  -- all features
    3, 1
),
-- Enterprise tier
(
    'enterprise', 'Enterprise', 'Unlimited everything with dedicated support. For large organisations.',
    49.99, 499.99, 'GBP',
    NULL,  -- unlimited links
    NULL,  -- unlimited custom domains (treated as unlimited when NULL)
    NULL,  -- unlimited API requests
    NULL,  -- unlimited LinksPages
    1, 1, 1, 1, 1,  -- all features
    4, 1
)
ON DUPLICATE KEY UPDATE
    `tierName` = VALUES(`tierName`),
    `updatedAt` = NOW();
