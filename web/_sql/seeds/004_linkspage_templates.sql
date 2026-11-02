-- Copyright (c) 2024–2026 MWBM Partners Ltd (MWservices).
-- All rights reserved.
--
-- This source code is proprietary and confidential.
-- Unauthorised copying, modification, or distribution is strictly prohibited.

-- =============================================================================
-- Go2My.Link — Seed Data: LinksPage Templates
-- =============================================================================
-- 5 system templates for the LinksPage service.
-- These are non-editable by users (isSystem = 1).
--
-- Template HTML and CSS use placeholder markers:
--   {{avatar}}     — Avatar picture address (for the img src)
--   {{avatarAlt}}  — Avatar alt text: empty for the default avatar, which is only decoration
--   {{name}}       — Display name
--   {{bio}}        — Page description
--   {{links}}      — Container for link items
--   {{social}}     — Social media icons
--   {{theme}}      — Theme accent colour
--   {{themeText}}  — Black or white, whichever is easier to read on the theme colour
--   {{background}} — Background colour
--
-- Layout rules that are the same for every template (the link boxes, the social
-- row, the footer, breaking long words) are not repeated below. The renderer
-- prints them before each template's CSS: g2ml_linkspageBaseCSS() in
-- web/Lnks.page/_functions/linkspage_renderer.php.
--
-- The centred templates add 2rem of padding to the link text (the icon's width
-- plus the gap beside it), so the text stays in the middle of the button.
--
-- Every colour pair below is checked against WCAG 2.1 AA (4.5:1 for normal text)
-- by tests/unit/linkspage_templates_test.php, which measures the colours in the
-- CSS the renderer really prints.
--
-- Re-running this file on an existing database REPLACES the stored HTML and CSS
-- of these five system templates with the text below (see the end of the file),
-- so a change here reaches databases that already have the rows.
--
-- @package    Go2My.Link
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.3.0
-- @since      Phase 1
-- =============================================================================

USE `mwtools_Go2MyLink`;

INSERT INTO `tblLinksPageTemplates` (
    `templateSlug`, `templateName`, `templateDescription`,
    `templateHTML`, `templateCSS`,
    `isSystem`, `isActive`, `sortOrder`
) VALUES

-- =========================================================================
-- Template 1: Default
-- =========================================================================
(
    'default',
    'Default',
    'Clean, centered layout with card-style links. Works well for all use cases.',
    '<main class="lp-container lp-default">
  <div class="lp-header">
    <img class="lp-avatar" src="{{avatar}}" alt="{{avatarAlt}}">
    <h1 class="lp-name">{{name}}</h1>
    <p class="lp-bio">{{bio}}</p>
  </div>
  <div class="lp-links">{{links}}</div>
  <div class="lp-social">{{social}}</div>
  <footer class="lp-footer">
    <a href="https://lnks.page" class="lp-branding">Powered by Lnks.page</a>
  </footer>
</main>',
    '.lp-default { max-width: 680px; margin: 0 auto; padding: 2rem 1rem; text-align: center; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.lp-default .lp-avatar { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem; }
.lp-default .lp-name { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.lp-default .lp-bio { color: #666; margin-bottom: 2rem; }
.lp-default .lp-link-item { padding: 1rem; margin-bottom: 0.75rem; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; text-decoration: none; color: #333; transition: transform 0.15s, box-shadow 0.15s; }
.lp-default .lp-link-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }
.lp-default .lp-link-text { padding-inline-end: 2rem; }
.lp-default .lp-link-desc { color: #595959; }
.lp-default .lp-social-icon { color: #1565C0; }
.lp-default .lp-branding { color: #666; }',
    1, 1, 1
),

-- =========================================================================
-- Template 2: Modern
-- =========================================================================
-- The text sits on dark see-through panels, not straight on the gradient. The
-- gradient runs from the owner's background colour to their theme colour, so
-- the text colour cannot be chosen for it. A black panel at 60% turns even a
-- pure white gradient into #666666, where white text is 5.74:1, and any darker
-- gradient only makes it darker.
(
    'modern',
    'Modern',
    'Bold gradient background with rounded pill-shaped links.',
    '<main class="lp-container lp-modern" style="background: linear-gradient(135deg, {{background}} 0%, {{theme}} 100%);">
  <div class="lp-header">
    <img class="lp-avatar" src="{{avatar}}" alt="{{avatarAlt}}">
    <h1 class="lp-name">{{name}}</h1>
    <p class="lp-bio">{{bio}}</p>
  </div>
  <div class="lp-links">{{links}}</div>
  <div class="lp-social">{{social}}</div>
  <footer class="lp-footer">
    <a href="https://lnks.page" class="lp-branding">Powered by Lnks.page</a>
  </footer>
</main>',
    'body { margin: 0; }
.lp-modern { box-sizing: border-box; min-height: 100vh; padding: 2rem 1rem; text-align: center; font-family: "Segoe UI", Roboto, sans-serif; color: #fff; }
.lp-modern .lp-header, .lp-modern .lp-links, .lp-modern .lp-social, .lp-modern .lp-footer { max-width: 600px; margin-left: auto; margin-right: auto; }
.lp-modern .lp-header { padding: 1.5rem 1rem; margin-bottom: 1.5rem; background: rgba(0,0,0,0.6); border-radius: 24px; }
.lp-modern .lp-avatar { width: 100px; height: 100px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.5); object-fit: cover; margin-bottom: 1rem; }
.lp-modern .lp-name { font-size: 1.75rem; font-weight: 700; margin-top: 0; margin-bottom: 0.5rem; }
.lp-modern .lp-bio { margin-top: 0; margin-bottom: 0; }
.lp-modern .lp-link-item { padding: 1rem 1.5rem; margin-bottom: 0.75rem; background: rgba(0,0,0,0.6); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 50px; text-decoration: none; color: #fff; font-weight: 600; transition: background 0.2s; }
.lp-modern .lp-link-item:hover { background: rgba(0,0,0,0.72); }
.lp-modern .lp-link-text { padding-inline-end: 2rem; }
.lp-modern .lp-social-icon, .lp-modern .lp-branding { padding: 0.5rem 1rem; background: rgba(0,0,0,0.6); border-radius: 50px; color: #fff; text-decoration: none; }',
    1, 1, 2
),

-- =========================================================================
-- Template 3: Minimal
-- =========================================================================
(
    'minimal',
    'Minimal',
    'Ultra-clean design with simple underlined links. No borders, no cards.',
    '<main class="lp-container lp-minimal">
  <div class="lp-header">
    <img class="lp-avatar" src="{{avatar}}" alt="{{avatarAlt}}">
    <h1 class="lp-name">{{name}}</h1>
    <p class="lp-bio">{{bio}}</p>
  </div>
  <div class="lp-links">{{links}}</div>
  <div class="lp-social">{{social}}</div>
  <footer class="lp-footer">
    <a href="https://lnks.page" class="lp-branding">Powered by Lnks.page</a>
  </footer>
</main>',
    '.lp-minimal { max-width: 500px; margin: 0 auto; padding: 3rem 1rem; font-family: Georgia, "Times New Roman", serif; }
.lp-minimal .lp-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 1.5rem; }
.lp-minimal .lp-name { font-size: 1.25rem; font-weight: 400; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.5rem; }
.lp-minimal .lp-bio { color: #666; font-size: 0.9rem; margin-bottom: 2.5rem; }
.lp-minimal .lp-link-item { padding: 0.75rem 0; border-bottom: 1px solid #eee; text-decoration: none; color: #333; }
.lp-minimal .lp-link-item:hover { color: #000; }
.lp-minimal .lp-link-desc { color: #595959; }
.lp-minimal .lp-social { justify-content: flex-start; }
.lp-minimal .lp-social-icon { color: #333; }
.lp-minimal .lp-footer { text-align: left; }
.lp-minimal .lp-branding { color: #666; padding-left: 0; }',
    1, 1, 3
),

-- =========================================================================
-- Template 4: Bold
-- =========================================================================
-- The link text colour is {{themeText}}: black or white, chosen from the
-- owner's theme colour so the text is always at least 4.5:1 on its button.
-- Plain white on the default theme colour (#1E88E5) was only 3.68:1.
(
    'bold',
    'Bold',
    'Large, colourful buttons with strong visual impact.',
    '<main class="lp-container lp-bold">
  <div class="lp-header">
    <img class="lp-avatar" src="{{avatar}}" alt="{{avatarAlt}}">
    <h1 class="lp-name">{{name}}</h1>
    <p class="lp-bio">{{bio}}</p>
  </div>
  <div class="lp-links">{{links}}</div>
  <div class="lp-social">{{social}}</div>
  <footer class="lp-footer">
    <a href="https://lnks.page" class="lp-branding">Powered by Lnks.page</a>
  </footer>
</main>',
    '.lp-bold { max-width: 600px; margin: 0 auto; padding: 2rem 1rem; text-align: center; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.lp-bold .lp-avatar { width: 110px; height: 110px; border-radius: 20px; object-fit: cover; margin-bottom: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
.lp-bold .lp-name { font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; }
.lp-bold .lp-bio { color: #555; margin-bottom: 2rem; font-size: 1.1rem; }
.lp-bold .lp-link-item { padding: 1.25rem; margin-bottom: 1rem; background: {{theme}}; border-radius: 12px; text-decoration: none; color: {{themeText}}; font-weight: 700; font-size: 1.1rem; transition: transform 0.15s; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.lp-bold .lp-link-item:hover { transform: scale(1.02); }
.lp-bold .lp-link-text { padding-inline-end: 2rem; }
.lp-bold .lp-social-icon { color: #1565C0; }
.lp-bold .lp-branding { color: #555; }',
    1, 1, 4
),

-- =========================================================================
-- Template 5: Professional
-- =========================================================================
-- On a narrow screen the sidebar sits above the links, and the links column
-- drops its minimum width, so the page fits a 320 px phone.
(
    'professional',
    'Professional',
    'Business-oriented layout with sidebar and structured link cards.',
    '<div class="lp-container lp-professional">
  <aside class="lp-sidebar">
    <img class="lp-avatar" src="{{avatar}}" alt="{{avatarAlt}}">
    <h1 class="lp-name">{{name}}</h1>
    <p class="lp-bio">{{bio}}</p>
    <div class="lp-social">{{social}}</div>
  </aside>
  <main class="lp-main">
    <div class="lp-links">{{links}}</div>
  </main>
  <footer class="lp-footer">
    <a href="https://lnks.page" class="lp-branding">Powered by Lnks.page</a>
  </footer>
</div>',
    '.lp-professional { display: flex; flex-wrap: wrap; max-width: 900px; margin: 0 auto; padding: 2rem; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; gap: 2rem; }
.lp-professional .lp-sidebar { flex: 0 0 250px; text-align: center; padding: 2rem 1rem; background: #f8f9fa; border-radius: 12px; }
.lp-professional .lp-main { flex: 1; min-width: 300px; }
.lp-professional .lp-avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem; }
.lp-professional .lp-name { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
.lp-professional .lp-bio { color: #666; font-size: 0.9rem; margin-bottom: 1.5rem; }
.lp-professional .lp-link-item { padding: 1rem; margin-bottom: 0.5rem; background: #fff; border: 1px solid #e8e8e8; border-radius: 8px; text-decoration: none; color: #333; }
.lp-professional .lp-link-item:hover { border-color: {{theme}}; }
.lp-professional .lp-link-desc { color: #595959; }
.lp-professional .lp-social { margin-top: 0; }
.lp-professional .lp-social-icon { color: #1565C0; }
.lp-professional .lp-footer { flex: 0 0 100%; margin-top: 0; }
.lp-professional .lp-branding { color: #666; }
@media (max-width: 640px) { .lp-professional { flex-direction: column; padding: 1rem; } .lp-professional .lp-sidebar { flex: none; } .lp-professional .lp-main { flex: none; min-width: 0; } .lp-professional .lp-footer { flex: none; } }',
    1, 1, 5
)

ON DUPLICATE KEY UPDATE
    `templateName` = VALUES(`templateName`),
    `templateHTML` = VALUES(`templateHTML`),
    `templateCSS`  = VALUES(`templateCSS`),
    `updatedAt` = NOW();
