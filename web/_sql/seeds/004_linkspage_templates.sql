-- =============================================================================
-- GoToMyLink — Seed Data: LinksPage Templates
-- =============================================================================
-- 5 system templates for the LinksPage service.
-- These are non-editable by users (isSystem = 1).
--
-- Template HTML uses placeholder markers:
--   {{avatar}}     — User/org avatar image
--   {{name}}       — Display name
--   {{bio}}        — Page description
--   {{links}}      — Container for link items
--   {{social}}     — Social media icons
--   {{theme}}      — Theme accent colour
--   {{background}} — Background colour
--
-- @package    GoToMyLink
-- @subpackage Database
-- @author     MWBM Partners Ltd (MWservices)
-- @version    0.2.0
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
    '<div class="lp-container lp-default">
  <div class="lp-header">
    <img class="lp-avatar" src="{{avatar}}" alt="{{name}}">
    <h1 class="lp-name">{{name}}</h1>
    <p class="lp-bio">{{bio}}</p>
  </div>
  <div class="lp-links">{{links}}</div>
  <div class="lp-social">{{social}}</div>
  <div class="lp-footer">
    <a href="https://lnks.page" class="lp-branding">Powered by Lnks.page</a>
  </div>
</div>',
    '.lp-default { max-width: 680px; margin: 0 auto; padding: 2rem 1rem; text-align: center; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.lp-default .lp-avatar { width: 96px; height: 96px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem; }
.lp-default .lp-name { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
.lp-default .lp-bio { color: #666; margin-bottom: 2rem; }
.lp-default .lp-link-item { display: block; padding: 1rem; margin-bottom: 0.75rem; background: #fff; border: 1px solid #e0e0e0; border-radius: 8px; text-decoration: none; color: #333; transition: transform 0.15s, box-shadow 0.15s; }
.lp-default .lp-link-item:hover { transform: translateY(-2px); box-shadow: 0 4px 12px rgba(0,0,0,0.1); }',
    1, 1, 1
),

-- =========================================================================
-- Template 2: Modern
-- =========================================================================
(
    'modern',
    'Modern',
    'Bold gradient background with rounded pill-shaped links.',
    '<div class="lp-container lp-modern" style="background: linear-gradient(135deg, {{background}} 0%, {{theme}} 100%);">
  <div class="lp-header">
    <img class="lp-avatar" src="{{avatar}}" alt="{{name}}">
    <h1 class="lp-name">{{name}}</h1>
    <p class="lp-bio">{{bio}}</p>
  </div>
  <div class="lp-links">{{links}}</div>
  <div class="lp-social">{{social}}</div>
  <div class="lp-footer">
    <a href="https://lnks.page" class="lp-branding">Powered by Lnks.page</a>
  </div>
</div>',
    '.lp-modern { min-height: 100vh; padding: 2rem 1rem; text-align: center; font-family: "Segoe UI", Roboto, sans-serif; color: #fff; }
.lp-modern .lp-avatar { width: 100px; height: 100px; border-radius: 50%; border: 3px solid rgba(255,255,255,0.5); object-fit: cover; margin-bottom: 1rem; }
.lp-modern .lp-name { font-size: 1.75rem; font-weight: 700; }
.lp-modern .lp-bio { opacity: 0.9; margin-bottom: 2rem; }
.lp-modern .lp-link-item { display: block; padding: 1rem 1.5rem; margin-bottom: 0.75rem; background: rgba(255,255,255,0.15); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.2); border-radius: 50px; text-decoration: none; color: #fff; font-weight: 600; transition: background 0.2s; }
.lp-modern .lp-link-item:hover { background: rgba(255,255,255,0.25); }',
    1, 1, 2
),

-- =========================================================================
-- Template 3: Minimal
-- =========================================================================
(
    'minimal',
    'Minimal',
    'Ultra-clean design with simple underlined links. No borders, no cards.',
    '<div class="lp-container lp-minimal">
  <div class="lp-header">
    <img class="lp-avatar" src="{{avatar}}" alt="{{name}}">
    <h1 class="lp-name">{{name}}</h1>
    <p class="lp-bio">{{bio}}</p>
  </div>
  <div class="lp-links">{{links}}</div>
  <div class="lp-social">{{social}}</div>
  <div class="lp-footer">
    <a href="https://lnks.page" class="lp-branding">Powered by Lnks.page</a>
  </div>
</div>',
    '.lp-minimal { max-width: 500px; margin: 0 auto; padding: 3rem 1rem; font-family: Georgia, "Times New Roman", serif; }
.lp-minimal .lp-avatar { width: 80px; height: 80px; border-radius: 50%; object-fit: cover; margin-bottom: 1.5rem; }
.lp-minimal .lp-name { font-size: 1.25rem; font-weight: 400; letter-spacing: 0.1em; text-transform: uppercase; margin-bottom: 0.5rem; }
.lp-minimal .lp-bio { color: #888; font-size: 0.9rem; margin-bottom: 2.5rem; }
.lp-minimal .lp-link-item { display: block; padding: 0.75rem 0; border-bottom: 1px solid #eee; text-decoration: none; color: #333; }
.lp-minimal .lp-link-item:hover { color: #000; }',
    1, 1, 3
),

-- =========================================================================
-- Template 4: Bold
-- =========================================================================
(
    'bold',
    'Bold',
    'Large, colourful buttons with strong visual impact.',
    '<div class="lp-container lp-bold">
  <div class="lp-header">
    <img class="lp-avatar" src="{{avatar}}" alt="{{name}}">
    <h1 class="lp-name">{{name}}</h1>
    <p class="lp-bio">{{bio}}</p>
  </div>
  <div class="lp-links">{{links}}</div>
  <div class="lp-social">{{social}}</div>
  <div class="lp-footer">
    <a href="https://lnks.page" class="lp-branding">Powered by Lnks.page</a>
  </div>
</div>',
    '.lp-bold { max-width: 600px; margin: 0 auto; padding: 2rem 1rem; text-align: center; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
.lp-bold .lp-avatar { width: 110px; height: 110px; border-radius: 20px; object-fit: cover; margin-bottom: 1rem; box-shadow: 0 4px 20px rgba(0,0,0,0.15); }
.lp-bold .lp-name { font-size: 2rem; font-weight: 800; margin-bottom: 0.5rem; }
.lp-bold .lp-bio { color: #555; margin-bottom: 2rem; font-size: 1.1rem; }
.lp-bold .lp-link-item { display: block; padding: 1.25rem; margin-bottom: 1rem; background: {{theme}}; border-radius: 12px; text-decoration: none; color: #fff; font-weight: 700; font-size: 1.1rem; transition: transform 0.15s; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
.lp-bold .lp-link-item:hover { transform: scale(1.02); }',
    1, 1, 4
),

-- =========================================================================
-- Template 5: Professional
-- =========================================================================
(
    'professional',
    'Professional',
    'Business-oriented layout with sidebar and structured link cards.',
    '<div class="lp-container lp-professional">
  <aside class="lp-sidebar">
    <img class="lp-avatar" src="{{avatar}}" alt="{{name}}">
    <h1 class="lp-name">{{name}}</h1>
    <p class="lp-bio">{{bio}}</p>
    <div class="lp-social">{{social}}</div>
  </aside>
  <main class="lp-main">
    <div class="lp-links">{{links}}</div>
  </main>
  <div class="lp-footer">
    <a href="https://lnks.page" class="lp-branding">Powered by Lnks.page</a>
  </div>
</div>',
    '.lp-professional { display: flex; flex-wrap: wrap; max-width: 900px; margin: 0 auto; padding: 2rem; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; gap: 2rem; }
.lp-professional .lp-sidebar { flex: 0 0 250px; text-align: center; padding: 2rem 1rem; background: #f8f9fa; border-radius: 12px; }
.lp-professional .lp-main { flex: 1; min-width: 300px; }
.lp-professional .lp-avatar { width: 120px; height: 120px; border-radius: 50%; object-fit: cover; margin-bottom: 1rem; }
.lp-professional .lp-name { font-size: 1.25rem; font-weight: 700; margin-bottom: 0.5rem; }
.lp-professional .lp-bio { color: #666; font-size: 0.9rem; margin-bottom: 1.5rem; }
.lp-professional .lp-link-item { display: flex; align-items: center; gap: 1rem; padding: 1rem; margin-bottom: 0.5rem; background: #fff; border: 1px solid #e8e8e8; border-radius: 8px; text-decoration: none; color: #333; }
.lp-professional .lp-link-item:hover { border-color: {{theme}}; }
@media (max-width: 640px) { .lp-professional { flex-direction: column; } .lp-professional .lp-sidebar { flex: none; } }',
    1, 1, 5
)

ON DUPLICATE KEY UPDATE
    `templateName` = VALUES(`templateName`),
    `updatedAt` = NOW();
