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
 * 📄 Go2My.Link — LinksPage Management Functions (Component C.2, #48)
 * ============================================================================
 *
 * CRUD backend for the admin dashboard's LinksPage manager: page create/
 * update/delete and item add/update/delete/toggle/move — every mutation is
 * OWNERSHIP-ENFORCED against the caller-supplied acting userUID.
 *
 * 🔒 SECURITY — ownership / IDOR:
 *   - Every function below takes the ACTING user's userUID as a parameter.
 *     Callers (the admin dashboard pages) must ALWAYS pass
 *     getCurrentUser()['userUID'] — never a client-supplied value from
 *     $_POST/$_GET — as that argument. A page/item row is only ever
 *     read, updated, deleted, or reordered when its owning userUID (for a
 *     page) or its PARENT page's owning userUID (for an item) matches the
 *     acting user. There is no "org-wide" management here: a member of the
 *     same organisation cannot touch another member's LinksPage.
 *   - Ownership is enforced IN THE SQL itself (a `userUID = ?` / a
 *     correlated `pageUID IN (SELECT pageUID FROM tblLinksPages WHERE
 *     userUID = ?)` clause on every read AND every write) — never checked
 *     only in PHP after an unscoped query. A row that does not belong to the
 *     caller behaves EXACTLY like a row that does not exist at all (a
 *     single, generic "not found" outcome), so this layer never leaks
 *     whether a given pageUID/itemUID exists for someone else.
 *
 * 🔒 SECURITY — structured fields vs. gated custom HTML:
 *   - The page/item CRUD fields are all discrete, validated, structured values
 *     (slug, title, description, avatar URL, template selection, hex colour,
 *     font family, a fixed social-network URL set, item title/url/description/
 *     icon) — never free-form HTML.
 *   - The ONLY free-form HTML path is the Component C.6 (#49) custom-HTML/CSS
 *     editor: g2ml_linkspageManageSaveCustomHTML() /
 *     g2ml_linkspageManageSaveCustomHTMLFromUpload(), sharing
 *     _g2ml_linkspageManageStoreSanitisedCustom(). Every such write is
 *     ownership-checked, GATED (operator kill-switch + premium hasCustomHTML
 *     entitlement, both via g2ml_linkspageCustomHtmlAllowedForOrg()), and
 *     SANITISED on input with g2ml_sanitiseUserHTML()/g2ml_sanitiseUserCSS()
 *     (web/_functions/html_sanitiser.php) BEFORE storage — the SANITISED form
 *     is what lands in tblLinksPages.customHTML/customCSS, never the raw
 *     submission. It is re-sanitised again on output by the renderer and served
 *     under a strict `script-src 'none'` CSP.
 *
 * 🔒 SECURITY — template picker + owner preview (C.3, #47):
 *   - g2ml_linkspageManageRenderTemplateCardThumbnail() builds each picker
 *     card's LIVE-rendered thumbnail by calling the Component C renderer's
 *     g2ml_renderLinksPage() (web/Lnks.page/_functions/linkspage_renderer.php)
 *     — REUSED, never reimplemented, so the exact same escaping/URL-allowlist/
 *     hex/font validation the public page uses also protects this preview.
 *     It is guarded by function_exists('g2ml_renderLinksPage') and falls
 *     back to the template's templateThumbnail image (or a static
 *     placeholder) whenever the renderer has not been loaded by the caller —
 *     this file itself never require()s the Component C renderer (Component
 *     A/Admin and Component C remain independently requirable trees; only
 *     the calling admin page decides to load the renderer).
 *   - g2ml_linkspageManageBuildTemplatePreviewSampleModel() uses ONLY a
 *     fixed, hard-coded sample name/bio/links/social — never the current
 *     user's in-progress form input — so a template preview can never carry
 *     anything an authenticated user typed before it was validated/saved.
 *   - The OWNER PAGE preview itself (a live render of a user's own, possibly
 *     UNPUBLISHED page) is assembled by
 *     web/Lnks.page/_functions/linkspage_resolver.php's
 *     g2ml_linkspageBuildOwnerPreviewModel() — NOT by this file. That
 *     function takes an ALREADY ownership-verified page/items pair (see
 *     g2ml_linkspageManageGetPageForOwner() / g2ml_linkspageManageListItemsForPage()
 *     below) and never queries tblLinksPages itself, so it can never be used
 *     to loosen the public resolver's `isPublished = 1` filter — the caller
 *     (the admin preview route) is solely responsible for the ownership check.
 *
 * 🔒 SECURITY — validation parity with the PUBLIC renderer:
 *   - slug / hex-colour / font-family validation here is a DELIBERATE mirror
 *     of web/Lnks.page/_functions/linkspage_resolver.php
 *     (g2ml_linkspageIsValidSlug) and linkspage_renderer.php
 *     (g2ml_linkspageValidateHexColour / g2ml_linkspageValidateFontFamily) —
 *     same regexes, same length limits. The two files are NOT require'd into
 *     one another (Component A admin vs Component C public renderer are
 *     separate deployable trees; only web/_functions/* is shared across all
 *     3 components per project convention), so the logic is duplicated
 *     on purpose with this cross-reference rather than a cross-component
 *     require_once. A value this file accepts is guaranteed to also be
 *     accepted (and rendered identically) by the public renderer.
 *   - Item/avatar/icon/social-link URLs are validated with the shared
 *     g2ml_sanitiseURL() (web/_functions/security.php) http/https allowlist —
 *     the SAME function the public renderer falls back to when
 *     g2ml_sanitiseURL() is loaded, so a value saved here is renderable.
 *
 * Functions:
 *   Validators:
 *     - g2ml_linkspageManageIsValidSlug()
 *     - g2ml_linkspageManageValidateHexColour()
 *     - g2ml_linkspageManageValidateFontFamily()
 *     - g2ml_linkspageManageValidateSocialLinks()
 *   Templates (Component C.2/#48; picker + live preview added by C.3/#47):
 *     - g2ml_linkspageManageListSystemTemplates()
 *     - g2ml_linkspageManageIsValidSystemTemplate()
 *     - g2ml_linkspageManageBuildTemplatePreviewSampleModel()
 *     - g2ml_linkspageManageRenderTemplateCardThumbnail()
 *   Pages:
 *     - g2ml_linkspageManageListPagesForUser()
 *     - g2ml_linkspageManageGetPageForOwner()
 *     - g2ml_linkspageManageCountActivePagesForUser()
 *     - g2ml_linkspageManageCreatePage()
 *     - g2ml_linkspageManageUpdatePage()
 *     - g2ml_linkspageManageSetPublished()
 *     - g2ml_linkspageManageDeletePage()
 *   Items:
 *     - g2ml_linkspageManageListItemsForPage()
 *     - g2ml_linkspageManageGetItemForOwner()
 *     - g2ml_linkspageManageListShortURLsForUser()
 *     - _g2ml_linkspageManageResolveRequiresAgeGate() — C.5/#50 auto-flag resolver
 *     - g2ml_linkspageManageAddItem()
 *     - g2ml_linkspageManageUpdateItem()
 *     - g2ml_linkspageManageDeleteItem()
 *     - g2ml_linkspageManageToggleItemActive()
 *     - g2ml_linkspageManageMoveItem()
 *
 * 🔒 SECURITY / PRIVACY — age verification auto-flag (C.5, #50):
 *   - g2ml_linkspageManageAddItem() / g2ml_linkspageManageUpdateItem() both
 *     resolve the item's FINAL requiresAgeGate value via
 *     _g2ml_linkspageManageResolveRequiresAgeGate(): the owner's own
 *     submitted checkbox choice is honoured, EXCEPT that a destination whose
 *     host matches the curated/operator-configured adult-domain allowlist
 *     (web/_functions/adult_content.php's g2ml_isAdultDomain()) always forces
 *     the gate ON — a known-adult destination cannot be silently left
 *     unprotected by leaving a form checkbox unticked. For any other
 *     destination the owner has full manual control (their checkbox choice
 *     is used as-is), which is the "the owner can still toggle it" freedom.
 *   - No date of birth or other personal data is collected/stored anywhere
 *     in this file — requiresAgeGate is a single boolean column.
 *
 * 🐛 CORRECTNESS — bind-type letters and affected-row counting (#218):
 *   - THE RULE: every mysqli bind_param() type string in this file is one
 *     letter per bound value, in COLUMN ORDER — 'i' for an integer column
 *     and 's' for everything else, including a nullable string column
 *     (MySQLi accepts NULL under any type letter, so nullability never
 *     changes which letter to use; only the column's own type does).
 *   - BUG 1 (fixed): g2ml_linkspageManageCreatePage() bound socialLinks (a
 *     JSON string) as an integer. CORRECTED after #218 review round 1. The
 *     mechanism is not "a JSON string cannot be coerced to an int" — it
 *     can: PHP's normal string-to-number rules turn a non-numeric string
 *     like a JSON object into 0 without complaint, on the CLIENT side,
 *     before anything is sent to the server. The actual failure happens on
 *     the OTHER side of that conversion: mysqli sends the resulting bare
 *     integer 0 to MySQL for a column whose type is JSON, and MySQL 8
 *     refuses a plain number there outright (error 3140), because it is
 *     not valid JSON text and was never explicitly CAST(... AS JSON) — so
 *     a page created with any social link failed to save at all. MariaDB,
 *     which is more permissive about what it will store in a JSON-typed
 *     column, accepted the same bare 0 without erroring, so on MariaDB
 *     this did not fail loudly — it silently wiped the social links the
 *     owner had just entered, storing 0 in their place with no error at
 *     all.
 *   - BUG 2 (fixed): g2ml_linkspageManageUpdatePage() bound fontFamily (a
 *     plain string) as an integer, which forces PHP/mysqli to convert the
 *     non-numeric string "Georgia" to 0 on the CLIENT side, before
 *     anything is sent to the server — the same client-side conversion
 *     described above for socialLinks, just without a JSON column on the
 *     other end to refuse the result. So every edit-form save overwrote
 *     the page's font family with the literal string "0".
 *   - Both bugs are fixed, with a comment at each bind_param() call
 *     recording the exact column-by-column mapping, so the next person who
 *     adds a column has something to check their own type string against.
 *   - g2ml_linkspageManageSetPublished() used to treat "0 rows affected" by
 *     its UPDATE as "page not found". mysqli's affected_rows counts rows
 *     whose VALUE CHANGED, not rows that MATCHED the WHERE clause — so
 *     publishing an already-published page (nothing to change) always
 *     failed with a false "not found", even though the page existed and
 *     belonged to the caller. It now checks ownership/existence with a
 *     plain read FIRST, and treats the UPDATE's affected-row count as
 *     informational only.
 *
 * 🔗 CORRECTNESS — reserved slugs (#218):
 *   - g2ml_linkspageManageIsValidSlug() now also refuses the fixed set of
 *     words in G2ML_LINKSPAGE_RESERVED_SLUGS (see that constant's docblock
 *     for the full, word-by-word reason). Six of them — "index", "403",
 *     "404", "500", "icons", "img" — are slugs the real web server can
 *     never actually route to a LinksPage today, because a real file or
 *     directory with that name is served first; before this fix, such a
 *     slug saved successfully and then could never be viewed by anyone,
 *     with no error anywhere explaining why. The other eleven ("robots"
 *     through "css") are NOT currently blocked by anything on the server —
 *     they are reserved ahead of time, so a future static file, folder or
 *     management route can use one of those names without ever colliding
 *     with an existing owner's page. Mirrored in the public resolver
 *     (web/Lnks.page/_functions/linkspage_resolver.php) so a slug refused
 *     here is also refused there.
 *
 * 🚫 ABUSE — per-page item cap (#218):
 *   - g2ml_linkspageManageAddItem() now refuses to add past
 *     G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE items on one page. Nothing enforced
 *     this before, so a runaway script (or a stuck retry loop) could grow a
 *     single page to an unbounded number of rows, every one of them
 *     rendered on every public page view.
 *
 * 🌍 I18N — every user-facing error message is translated (CX-01, #218
 *    catch-up): every 'error' string this file returns now goes through
 *    __(), each call guarded by `if (function_exists('__'))` with the
 *    original English sentence kept as the `else` fallback — the SAME
 *    shape already used below for the avatar/icon https-only errors
 *    (#221/#273). The fallback exists because some callers never load
 *    web/_functions/i18n.php, chiefly the automated tests (unit and
 *    integration) — see the note near the top of
 *    tests/unit/linkspage_manage_test.php for why.
 *  - On a real request, page_init.php always loads i18n.php, so that
 *    fallback never runs there, and a database MISSING seed 065 shows each
 *    raw key name (for example "linkspage.error.slug_reserved") instead of
 *    English — that is __()'s own last-resort behaviour for a missing key
 *    (see i18n.php). Seed 065 must therefore be applied to every existing
 *    database, not only a fresh install.
 *  - The plan-limit message is two keys — ..._with_limit and ..._no_limit —
 *    one for when the limit is known and one for when it is not, so each is
 *    a whole sentence a translator can word freely. The two avatar/icon
 *    errors keep their existing keys from seed 064; every other key here is
 *    new, seeded in web/_sql/seeds/065_linkspage_manage_error_translations.sql.
 *
 * Dependencies: db_query.php (dbSelect/dbSelectOne/dbInsert/dbUpdate/dbDelete/
 *               dbBeginTransaction/dbCommit/dbRollback/dbLastErrno), security.php
 *               (g2ml_sanitiseInput/g2ml_sanitiseURL), entitlements.php
 *               (g2ml_checkLimit), activity_logger.php (logActivity) — all
 *               already loaded by page_init.php before this file.
 *               OPTIONAL (C.3, #47): g2ml_linkspageManageRenderTemplateCardThumbnail()
 *               calls the Component C renderer's g2ml_renderLinksPage() when the
 *               CALLING admin page has loaded it (web/Lnks.page/_functions/
 *               linkspage_renderer.php) — guarded by function_exists(), never
 *               require()d from this file itself.
 *               OPTIONAL (C.5, #50): _g2ml_linkspageManageResolveRequiresAgeGate()
 *               calls web/_functions/adult_content.php's g2ml_isAdultDomain()
 *               when loaded — guarded by function_exists(); page_init.php
 *               already loads adult_content.php application-wide.
 *               OPTIONAL (CX-01, #218 catch-up): every 'error' message calls
 *               __() (web/_functions/i18n.php) when loaded — guarded by
 *               function_exists(), never require()d from this file itself;
 *               page_init.php already loads i18n.php application-wide.
 *
 * @package    Go2My.Link
 * @subpackage Functions
 * @author     MWBM Partners Ltd (MWservices)
 * @version    1.3.0
 * @since      v1.2.0 — Phase 8 (#48; age-gate auto-flag #50; bind-type,
 *             re-publish, reserved-slug and item-cap fixes #218; #218
 *             review round 1: leading-underscore rejection, resolver's
 *             constant renamed so the two lists can no longer silently
 *             collapse into one, and corrected comments; CX-01 — every
 *             error message translated, #218 catch-up finding)
 *
 * 📖 References:
 *     - Schema:          web/_sql/schema/032_linkspage.sql
 *     - Public resolver:  web/Lnks.page/_functions/linkspage_resolver.php (#45)
 *     - Public renderer:  web/Lnks.page/_functions/linkspage_renderer.php (#45)
 *     - Entitlement gate: web/_functions/entitlements.php (#146) — maxLinksPages
 *     - Adult-domain detection / age-gate cookie: web/_functions/adult_content.php (#50)
 *     - Translation seed: web/_sql/seeds/065_linkspage_manage_error_translations.sql (CX-01)
 * ============================================================================
 */

// ============================================================================
// 🛡️ Direct Access Guard
// ============================================================================
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__))
{
    header('Location: https://go2my.link');
    exit;
}

// ============================================================================
// 📋 Constants
// ============================================================================

/**
 * The fixed, allowlisted set of social-network fields the management form
 * exposes, and their display labels. Deliberately mirrors (a subset of) the
 * public renderer's own allowlist
 * (g2ml_linkspageAllowedSocialNetworks() in linkspage_renderer.php) so every
 * value saved here is guaranteed to be recognised and rendered publicly.
 * 'x' is omitted here as a distinct field — it is only an alternate JSON key
 * the renderer also accepts; the form offers a single 'twitter' field.
 *
 * NOTE: every value (including 'email') must be an http/https URL per the
 * renderer's own scheme allowlist — the renderer never supports mailto:
 * links, so an "Email" field here is a link TO a contact page, not a mailto:
 * address. This is documented in the form's help text.
 */
if (!defined('G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS'))
{
    define('G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS', [
        'twitter'   => 'X / Twitter',
        'instagram' => 'Instagram',
        'facebook'  => 'Facebook',
        'linkedin'  => 'LinkedIn',
        'youtube'   => 'YouTube',
        'tiktok'    => 'TikTok',
        'github'    => 'GitHub',
        'website'   => 'Website',
        'email'     => 'Email (link to a contact page)',
    ]);
}

/** Application-level cap on pageDescription / itemDescription length (TEXT column, no DB-enforced cap). */
if (!defined('G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH'))
{
    define('G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH', 2000);
}

/**
 * Slugs a LinksPage owner can NEVER claim. Each word's TRUE reason —
 * corrected after #218 review round 1 found the previous version of this
 * comment overstating several of them, which the house rule treats as
 * worse than no comment at all:
 *
 *   - 'index', '403', '404', '500' — a REAL .php file with that exact name
 *     already exists in web/Lnks.page/public_html/ (index.php, 403.php,
 *     404.php, 500.php). The .htaccess "Clean URLs — .php extension
 *     removal" rule rewrites a bare-word request straight to that file
 *     (`RewriteCond %{REQUEST_FILENAME}.php -f` / `RewriteRule ^(.+)$
 *     $1.php`) before the slug-routing rule below it ever runs.
 *   - 'icons', 'img' — REAL, COMMITTED directories in
 *     web/Lnks.page/public_html/ today, not folders the component "might
 *     grow into". Apache's `!-d` guard on the slug rewrite refuses to route
 *     a request for a directory that already exists.
 *   - 'robots', 'sitemap', 'favicon', 'manifest', 'sw', 'api', 'admin',
 *     'static', 'assets', 'js', 'css' — NOT currently blocked by anything
 *     on the server. The real static files are robots.txt, sitemap.xml,
 *     favicon.ico and manifest.json — not files literally named 'robots' or
 *     'favicon' with no extension — and 'sw' has no matching file at all
 *     (only sw.js, untouched by the .php-removal rule). A request for
 *     lnks.page/robots today falls straight through to the slug rule and
 *     resolves exactly like any other page. These 11 words are reserved
 *     AHEAD OF TIME, so a future static file, folder or management route
 *     can use one of these names without ever colliding with an existing
 *     owner's page — not because a page using one is already unreachable.
 *     See web/Lnks.page/_functions/linkspage_resolver.php's own copy of
 *     this list for the consequence that ahead-of-time reservation has for
 *     an existing page at RENDER time.
 *
 * A leading underscore (for example '_uploads' or '_sql') is refused
 * separately, by g2ml_linkspageManageIsValidSlug() itself below, rather
 * than being listed here — see that check's own comment for why, and for
 * the bug this closes (#218 review round 1: those specific words were
 * missing from this list even though the real server 403-blocks them,
 * because the plan that first wrote this list did not check .htaccess for
 * every folder it blocks).
 *
 * Before this list existed at all, a page created with one of the SIX
 * already-blocked words above ("index", "403", "404", "500", "icons",
 * "img") saved successfully but could NEVER be viewed by anyone — the
 * clearest possible silent failure, since the owner would see "page
 * created" and then a 404 forever, with nothing in this codebase telling
 * them why. The other eleven words ("robots" through "css") carry no such
 * history — as the bullet above says, they resolve and render like any
 * other page today — they are reserved so that story can never start for
 * them either, once something on the server does claim one of those names.
 *
 * 🔗 Cross-reference: this exact list of 17 words is mirrored in
 * web/Lnks.page/_functions/linkspage_resolver.php's OWN, separately-named
 * constant, G2ML_LINKSPAGE_RESOLVER_RESERVED_SLUGS — see that file's
 * docblock for why it is a SEPARATE constant name rather than this same
 * one (#218 review round 1: sharing one name across both files was itself
 * a bug, because this file's copy of the array is loaded into every
 * component — including Component C — via page_init.php, so the two
 * `if (!defined(...))` guards could never both fire in the same request
 * and one file's list silently overrode the other's). The two arrays'
 * CONTENTS are still required to match exactly — see
 * tests/unit/linkspage_manage_test.php's parity test, which loads both
 * files and compares them directly — the same cross-reference relationship
 * the slug REGEX already has between the two files (see this file's
 * header, "SECURITY — validation parity with the PUBLIC renderer").
 * Comparisons against it are always case-INsensitive (strtolower() first)
 * since a slug claimed as "Index" would be just as unreachable as "index".
 */
if (!defined('G2ML_LINKSPAGE_RESERVED_SLUGS'))
{
    define('G2ML_LINKSPAGE_RESERVED_SLUGS', [
        'index', '403', '404', '500', 'icons', 'img', 'robots', 'sitemap',
        'favicon', 'manifest', 'sw', 'api', 'admin', 'static', 'assets',
        'js', 'css',
    ]);
}

/**
 * Hard abuse cap on the number of items (links) a single LinksPage may hold.
 *
 * Nothing enforced this before: g2ml_linkspageManageAddItem() would accept
 * an add request forever, so a runaway script (or a single confused user
 * double-clicking "Add" in a loop) could grow one page to an unbounded
 * number of rows — every one of them rendered on every public page view,
 * so this was also an unmetered cost/performance exposure, not just a data
 * quality one. 200 is generously above anything a real LinksPage needs
 * (the whole point of the product is a short, scannable list of links) while
 * still being small enough that hitting it is a clear signal something is
 * wrong, not a real use case being blocked.
 */
if (!defined('G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE'))
{
    define('G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE', 200);
}

// ============================================================================
// ✅ Validators
// ============================================================================

/**
 * Validate a LinksPage slug's shape — see the file header's parity note.
 *
 * Mirrors web/Lnks.page/_functions/linkspage_resolver.php's
 * g2ml_linkspageIsValidSlug() exactly: URL-safe characters only, 1–100
 * characters, matching the UNIQUE `slug` column's VARCHAR(100) width — a
 * leading underscore, AND (added together with G2ML_LINKSPAGE_RESERVED_SLUGS)
 * the fixed set of words reserved for other reasons — see that constant's
 * docblock for the full, corrected explanation of each one.
 *
 * 🔗 Leading underscore (#218 review round 1): every folder
 * web/Lnks.page/public_html/.htaccess 403-blocks under "Block Private
 * Directories" — _includes, _functions, _libraries, _uploads, _backups,
 * _sql, _schemas — starts with an underscore, and the slug charset above
 * allows a leading underscore through. Before this check existed, an owner
 * could save (for example) '_uploads' as their slug: the save itself
 * succeeded, since nothing here or in the resolver refused it, but every
 * viewer would get a "forbidden" page instead of the LinksPage they
 * expected — this component's OWN branded 403 page, not a bare server
 * error, because .htaccess routes a 403 to
 * `ErrorDocument 403 /index.php?http_error=403`, which index.php then
 * serves as the branded page — and the owner would have no way to know
 * why. Listing each blocked folder name individually would need updating
 * here every time a new one is added to .htaccess; a slug cannot
 * legitimately need a leading underscore (it is not part of any normal
 * handle or word), so refusing the whole shape is the fix that cannot fail
 * quietly if another such folder is added later without this file being
 * remembered.
 *
 * @param  string $slug
 * @return bool
 */
function g2ml_linkspageManageIsValidSlug(string $slug): bool
{
    if ($slug === '')
    {
        return false;
    }

    if (preg_match('/^[A-Za-z0-9_-]{1,100}$/', $slug) !== 1)
    {
        return false;
    }

    if (str_starts_with($slug, '_'))
    {
        return false;
    }

    if (in_array(strtolower($slug), G2ML_LINKSPAGE_RESERVED_SLUGS, true))
    {
        return false;
    }

    return true;
}

/**
 * Validate a hex colour literal — see the file header's parity note.
 *
 * Mirrors web/Lnks.page/_functions/linkspage_renderer.php's
 * g2ml_linkspageValidateHexColour() exactly.
 *
 * @param  string|null $colour
 * @return string|false  The trimmed, validated colour, or false when invalid.
 */
function g2ml_linkspageManageValidateHexColour(?string $colour): string|false
{
    if ($colour === null)
    {
        return false;
    }

    $trimmed = trim($colour);

    if (preg_match('/^#[0-9A-Fa-f]{3,6}$/', $trimmed) !== 1)
    {
        return false;
    }

    return $trimmed;
}

/**
 * Validate a font-family value — see the file header's parity note.
 *
 * Mirrors web/Lnks.page/_functions/linkspage_renderer.php's
 * g2ml_linkspageValidateFontFamily() exactly: only letters, digits, spaces,
 * commas, hyphens, and single/double quotes are permitted — no `;`, `{`,
 * `}`, `(`, `)`, so a value can never break out of a CSS declaration.
 *
 * @param  string|null $fontFamily
 * @return string|false  The trimmed, validated value, or false when invalid/absent.
 */
function g2ml_linkspageManageValidateFontFamily(?string $fontFamily): string|false
{
    if ($fontFamily === null)
    {
        return false;
    }

    $trimmed = trim($fontFamily);

    if ($trimmed === '')
    {
        return false;
    }

    if (strlen($trimmed) > 100)
    {
        return false;
    }

    if (preg_match('/^[A-Za-z0-9 ,\'"\-]+$/', $trimmed) !== 1)
    {
        return false;
    }

    return $trimmed;
}

/**
 * Validate a raw social-links submission (e.g. straight from $_POST) down to
 * a clean associative array safe to json_encode() into tblLinksPages.socialLinks.
 *
 * Only keys present in G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS are considered —
 * any other key is silently ignored (closed allowlist, not a denylist). A
 * blank value for an allowlisted key is dropped (the network is simply not
 * set). A non-blank value that fails g2ml_sanitiseURL()'s http/https scheme
 * validation is also dropped rather than raising a hard error — it is a
 * cosmetic field, and this mirrors the public renderer's own "skip, don't
 * fail" behaviour for an individual bad link.
 *
 * @param  array<string, mixed> $rawSocialLinks  e.g. $_POST['social'] shape: ['twitter' => 'https://...', ...]
 * @return array<string, string>  Validated network => URL pairs (may be empty).
 */
function g2ml_linkspageManageValidateSocialLinks(array $rawSocialLinks): array
{
    $validated = [];

    foreach (G2ML_LINKSPAGE_MANAGE_SOCIAL_NETWORKS as $networkKey => $networkLabel)
    {
        if (!isset($rawSocialLinks[$networkKey]) || !is_string($rawSocialLinks[$networkKey]))
        {
            continue;
        }

        $rawValue = trim($rawSocialLinks[$networkKey]);

        if ($rawValue === '')
        {
            continue;
        }

        $sanitisedURL = g2ml_sanitiseURL($rawValue);

        if ($sanitisedURL === false || $sanitisedURL === '')
        {
            continue;
        }

        $validated[$networkKey] = $sanitisedURL;
    }

    return $validated;
}

// ============================================================================
// 🎨 System templates
// ============================================================================

/**
 * List every active SYSTEM template, for the management form's visual picker
 * (C.3, #47 — previously a plain dropdown, C.2/#48).
 *
 * Only isSystem = 1 rows are ever offered — user-authored/custom templates
 * (not built anywhere yet) would need a completely separate ownership-aware
 * listing, not this one.
 *
 * templateHTML/templateCSS/templateThumbnail are included (widened from the
 * original name/description-only SELECT) so the picker can render a LIVE
 * thumbnail of each template with sample data — see
 * g2ml_linkspageManageRenderTemplateCardThumbnail(). These are SYSTEM
 * (isSystem = 1, operator-authored, trusted) template bodies, never
 * user-supplied HTML.
 *
 * @return array
 */
function g2ml_linkspageManageListSystemTemplates(): array
{
    $rows = dbSelect(
        "SELECT templateUID, templateName, templateDescription, templateHTML, templateCSS, templateThumbnail
         FROM tblLinksPageTemplates
         WHERE isSystem = 1 AND isActive = 1
         ORDER BY sortOrder ASC, templateUID ASC"
    );

    if ($rows === false)
    {
        return [];
    }

    return $rows;
}

/**
 * Confirm a templateUID refers to an active SYSTEM template row.
 *
 * @param  int $templateUID
 * @return bool
 */
function g2ml_linkspageManageIsValidSystemTemplate(int $templateUID): bool
{
    $row = dbSelectOne(
        "SELECT templateUID FROM tblLinksPageTemplates WHERE templateUID = ? AND isSystem = 1 AND isActive = 1 LIMIT 1",
        'i',
        [$templateUID]
    );

    if ($row === null || $row === false)
    {
        return false;
    }

    return true;
}

/**
 * Build a FIXED, safe sample page model for the template picker's live-render
 * thumbnails (C.3, #47).
 *
 * Deliberately static: a hard-coded sample name/bio/items/social — NEVER the
 * current user's in-progress create/edit form input, and never anything
 * read from the database beyond the template row itself. This guarantees a
 * template preview is identical for every user and can never carry
 * unvalidated data.
 *
 * @param  array $templateRow  A row shaped like g2ml_linkspageManageListSystemTemplates()'s
 *                              return (must include templateHTML/templateCSS).
 * @return array  ['page' => array, 'template' => array, 'items' => array] ready for g2ml_renderLinksPage().
 */
function g2ml_linkspageManageBuildTemplatePreviewSampleModel(array $templateRow): array
{
    $sampleTemplateUID = null;

    if (isset($templateRow['templateUID']))
    {
        $sampleTemplateUID = (int) $templateRow['templateUID'];
    }

    return [
        'page' => [
            'pageTitle'        => 'Jane Doe',
            'pageDescription'  => 'Photographer and digital creator — sharing my favourite links below.',
            'avatarPath'       => null,
            'templateUID'      => $sampleTemplateUID,
            'themeColour'      => '#1E88E5',
            'backgroundColour' => '#FFFFFF',
            'fontFamily'       => null,
            'showSocialIcons'  => 1,
            'socialLinks'      => json_encode([
                'twitter'   => 'https://example.com/sample-profile',
                'instagram' => 'https://example.com/sample-profile',
            ]),
        ],
        'template' => $templateRow,
        'items'    => [
            [
                'itemUID'         => 0,
                'itemTitle'       => 'My Website',
                'itemURL'         => 'https://example.com/website',
                'itemDescription' => null,
                'itemIcon'        => null,
                'faviconCacheURL' => null,
                'requiresAgeGate' => 0,
            ],
            [
                'itemUID'         => 0,
                'itemTitle'       => 'Latest Project',
                'itemURL'         => 'https://example.com/project',
                'itemDescription' => 'A short sample description',
                'itemIcon'        => null,
                'faviconCacheURL' => null,
                'requiresAgeGate' => 0,
            ],
            [
                'itemUID'         => 0,
                'itemTitle'       => 'Get in Touch',
                'itemURL'         => 'https://example.com/contact',
                'itemDescription' => null,
                'itemIcon'        => null,
                'faviconCacheURL' => null,
                'requiresAgeGate' => 0,
            ],
        ],
    ];
}

/**
 * Render one template picker card's thumbnail markup (C.3, #47).
 *
 * Prefers a LIVE render of the template (via the Component C renderer's
 * g2ml_renderLinksPage() — REUSED, never reimplemented here) with the fixed
 * sample model above, embedded as a sandboxed, scaled-down `<iframe srcdoc>`
 * so visitors compare templates using the SAME escaping/validation the
 * public page uses. Falls back to the template's own templateThumbnail
 * image, and finally to a static placeholder icon, whenever a live render is
 * not practical (the renderer not loaded, or no templateHTML stored).
 *
 * The iframe is `aria-hidden="true"` and `tabindex="-1"` — it is purely
 * decorative; the accessible name of the picker card comes from the visible
 * template name/description text in its `<label>`, not from this markup.
 * `sandbox=""` (no tokens) fully sandboxes the framed content: no scripts,
 * no forms, no top-navigation, unique/opaque origin — appropriate for
 * static, escaped, system-template HTML that never needs script execution.
 *
 * @param  array $templateRow  A row shaped like g2ml_linkspageManageListSystemTemplates()'s
 *                              return (templateUID/templateName/templateHTML/
 *                              templateCSS/templateThumbnail).
 * @return string  Safe HTML for the card's thumbnail area (always non-empty).
 */
function g2ml_linkspageManageRenderTemplateCardThumbnail(array $templateRow): string
{
    $templateHTMLValue = '';

    if (isset($templateRow['templateHTML']) && is_string($templateRow['templateHTML']))
    {
        $templateHTMLValue = trim($templateRow['templateHTML']);
    }

    if ($templateHTMLValue !== '' && function_exists('g2ml_renderLinksPage'))
    {
        $sampleModel = g2ml_linkspageManageBuildTemplatePreviewSampleModel($templateRow);
        $renderedHTML = g2ml_renderLinksPage($sampleModel);

        // htmlspecialchars(ENT_QUOTES) makes the ENTIRE rendered document safe
        // to embed as the value of the srcdoc="..." attribute — this is
        // attribute escaping of the outer page, not a weakening of the
        // renderer's own escaping of the sample data inside the document.
        $safeSrcDoc = htmlspecialchars($renderedHTML, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        return '<span class="lp-picker-thumb lp-picker-thumb-live">'
            . '<iframe class="lp-picker-thumb-iframe" srcdoc="' . $safeSrcDoc . '" '
            . 'tabindex="-1" aria-hidden="true" loading="lazy" scrolling="no" sandbox="" '
            . 'title=""></iframe>'
            . '</span>';
    }

    $templateThumbnailValue = '';

    if (isset($templateRow['templateThumbnail']) && is_string($templateRow['templateThumbnail']))
    {
        $templateThumbnailValue = trim($templateRow['templateThumbnail']);
    }

    if ($templateThumbnailValue !== '')
    {
        return '<span class="lp-picker-thumb lp-picker-thumb-static">'
            . '<img src="' . htmlspecialchars($templateThumbnailValue, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '" alt="" loading="lazy">'
            . '</span>';
    }

    return '<span class="lp-picker-thumb lp-picker-thumb-placeholder" aria-hidden="true">'
        . '<i class="fas fa-image" aria-hidden="true"></i>'
        . '</span>';
}

// ============================================================================
// 📄 Pages — reads
// ============================================================================

/**
 * List every LinksPage owned by a user (both published and draft), most
 * recently created first.
 *
 * @param  int $userUID  The ACTING user's own userUID — never a client-supplied value.
 * @return array
 */
function g2ml_linkspageManageListPagesForUser(int $userUID): array
{
    $rows = dbSelect(
        "SELECT pageUID, slug, pageTitle, pageDescription, templateUID, isPublished, isActive, createdAt, updatedAt
         FROM tblLinksPages
         WHERE userUID = ?
         ORDER BY createdAt DESC",
        'i',
        [$userUID]
    );

    if ($rows === false)
    {
        return [];
    }

    return $rows;
}

/**
 * Fetch ONE LinksPage, scoped to its owning user.
 *
 * 🔒 Ownership is enforced IN the query (`userUID = ?`) — a pageUID that
 * belongs to someone else returns null, identically to a pageUID that does
 * not exist at all.
 *
 * @param  int $pageUID
 * @param  int $userUID  The ACTING user's own userUID.
 * @return array|null
 */
function g2ml_linkspageManageGetPageForOwner(int $pageUID, int $userUID): ?array
{
    $row = dbSelectOne(
        "SELECT pageUID, userUID, orgHandle, slug, pageTitle, pageDescription, avatarPath,
                templateUID, customHTML, customCSS, themeColour, backgroundColour, fontFamily, showSocialIcons,
                socialLinks, isPublished, isActive, createdAt, updatedAt
         FROM tblLinksPages
         WHERE pageUID = ? AND userUID = ?
         LIMIT 1",
        'ii',
        [$pageUID, $userUID]
    );

    if ($row === null || $row === false)
    {
        return null;
    }

    return $row;
}

/**
 * Count a user's own ACTIVE LinksPages — the count fed into
 * g2ml_checkLimit(orgHandle, 'maxLinksPages', ...) before a NEW page create.
 *
 * @param  int $userUID
 * @return int
 */
function g2ml_linkspageManageCountActivePagesForUser(int $userUID): int
{
    $row = dbSelectOne(
        "SELECT COUNT(*) AS cnt FROM tblLinksPages WHERE userUID = ? AND isActive = 1",
        'i',
        [$userUID]
    );

    if ($row === null || $row === false)
    {
        return 0;
    }

    return (int) $row['cnt'];
}

// ============================================================================
// 📄 Pages — internal field-validation helper (shared by create/update)
// ============================================================================

/**
 * Validate and normalise the shared structured-field subset of a page
 * create/update submission. Returns either ['ok' => true, 'fields' => [...]]
 * with every value ready to bind, or ['ok' => false, 'error' => string,
 * 'errorCode' => string] on the FIRST validation failure.
 *
 * Deliberately does NOT touch slug uniqueness or the entitlement limit —
 * those are caller-specific (create-only) concerns handled by
 * g2ml_linkspageManageCreatePage() itself.
 *
 * @param  array $input  Raw, already trim()-able submission fields.
 * @return array
 */
function _g2ml_linkspageManageValidateFields(array $input): array
{
    $slugRaw = '';

    if (isset($input['slug']) && is_string($input['slug']))
    {
        $slugRaw = trim($input['slug']);
    }

    // Checked SEPARATELY from the general shape check below, purely so the
    // owner sees the ACCURATE reason their slug was refused. Both checks
    // ultimately agree — g2ml_linkspageManageIsValidSlug() itself also
    // refuses a reserved word or a leading underscore (see
    // G2ML_LINKSPAGE_RESERVED_SLUGS and that function's own leading-
    // underscore comment) — but a generic "letters, numbers, hyphens,
    // underscores" message would be actively misleading here, since a word
    // like "index" (or a slug like "_uploads") already satisfies that shape
    // rule and the real reason for the rejection is that the word is
    // reserved, not that its shape is wrong (see the constant's docblock for
    // why each of the 17 words is reserved — some because a real file or
    // folder already takes that address today, others reserved ahead of
    // time for one that might — and g2ml_linkspageManageIsValidSlug()'s
    // leading-underscore comment for why that shape is refused). A leading
    // underscore is treated as "reserved" here too, with the same message,
    // rather than a separate one — from the owner's point of view it is the
    // same situation: a slug shape that is never allowed, for reasons they
    // do not need to know the details of.
    if (in_array(strtolower($slugRaw), G2ML_LINKSPAGE_RESERVED_SLUGS, true)
        || str_starts_with($slugRaw, '_'))
    {
        // CX-01 (#218 catch-up): see this file's own header comment for why
        // every 'error' message below goes through __() with a fallback.
        if (function_exists('__'))
        {
            $slugReservedError = __('linkspage.error.slug_reserved');
        }
        else
        {
            $slugReservedError = 'That slug is reserved. Please choose a different one.';
        }

        return [
            'ok'        => false,
            'error'     => $slugReservedError,
            'errorCode' => 'validation',
        ];
    }

    if (!g2ml_linkspageManageIsValidSlug($slugRaw))
    {
        if (function_exists('__'))
        {
            $slugInvalidError = __('linkspage.error.slug_invalid');
        }
        else
        {
            $slugInvalidError = 'Please enter a URL slug using only letters, numbers, hyphens, and underscores (1-100 characters).';
        }

        return [
            'ok'        => false,
            'error'     => $slugInvalidError,
            'errorCode' => 'validation',
        ];
    }

    $pageTitleRaw = '';

    if (isset($input['pageTitle']) && is_string($input['pageTitle']))
    {
        $pageTitleRaw = trim(g2ml_sanitiseInput($input['pageTitle']));
    }

    if ($pageTitleRaw === '')
    {
        if (function_exists('__'))
        {
            $pageTitleRequiredError = __('linkspage.error.page_title_required');
        }
        else
        {
            $pageTitleRequiredError = 'Please enter a page title.';
        }

        return [
            'ok'        => false,
            'error'     => $pageTitleRequiredError,
            'errorCode' => 'validation',
        ];
    }

    if (mb_strlen($pageTitleRaw) > 255)
    {
        if (function_exists('__'))
        {
            $pageTitleTooLongError = __('linkspage.error.page_title_too_long');
        }
        else
        {
            $pageTitleTooLongError = 'Page title must be 255 characters or fewer.';
        }

        return [
            'ok'        => false,
            'error'     => $pageTitleTooLongError,
            'errorCode' => 'validation',
        ];
    }

    $pageDescriptionRaw = '';

    if (isset($input['pageDescription']) && is_string($input['pageDescription']))
    {
        $pageDescriptionRaw = trim(g2ml_sanitiseInput($input['pageDescription']));
    }

    if (mb_strlen($pageDescriptionRaw) > G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH)
    {
        if (function_exists('__'))
        {
            $pageDescriptionTooLongError = __('linkspage.error.page_description_too_long', ['max' => G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH]);
        }
        else
        {
            $pageDescriptionTooLongError = 'Page description must be ' . G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH . ' characters or fewer.';
        }

        return [
            'ok'        => false,
            'error'     => $pageDescriptionTooLongError,
            'errorCode' => 'validation',
        ];
    }

    if ($pageDescriptionRaw === '')
    {
        $pageDescriptionValue = null;
    }
    else
    {
        $pageDescriptionValue = $pageDescriptionRaw;
    }

    $avatarRaw = '';

    if (isset($input['avatarPath']) && is_string($input['avatarPath']))
    {
        $avatarRaw = trim($input['avatarPath']);
    }

    if ($avatarRaw === '')
    {
        $avatarValue = null;
    }
    else
    {
        $sanitisedAvatar = g2ml_sanitiseURL($avatarRaw);

        // Issue #221 (LP-10) widened the public page's Content-Security-Policy
        // to allow https: images, so a creator's avatar can finally be SEEN by
        // a visitor (before, the CSP silently blocked every avatar, no matter
        // what the creator entered here). Issue #273 then found that this
        // validation still accepted a plain http:// avatar address, which is
        // unreliable on an https page: a modern browser (Chrome, Firefox)
        // quietly tries it over https:// instead, before the CSP is even
        // checked, and shows nothing if that server has no https; only an
        // older browser without that automatic upgrade is blocked outright
        // by the CSP, which lists https: alone. Either way the creator was
        // never told, and could not tell from their own browser whether it
        // would work for everyone. Fixed by refusing anything that is not
        // https here too, so the creator is told straight away on save
        // instead of guessing from how it looks in their own browser.
        // g2ml_sanitiseURL() already restricts the scheme to http or https,
        // so this only has to narrow that further down to https alone.
        $avatarScheme = false;

        if ($sanitisedAvatar !== false)
        {
            $avatarScheme = strtolower((string) parse_url($sanitisedAvatar, PHP_URL_SCHEME));
        }

        if ($sanitisedAvatar === false || mb_strlen($sanitisedAvatar) > 500 || $avatarScheme !== 'https')
        {
            if (function_exists('__'))
            {
                $avatarInvalidError = __('linkspage.avatar_url_https_error');
            }
            else
            {
                $avatarInvalidError = 'The avatar must be a valid https:// image URL.';
            }

            return [
                'ok'        => false,
                'error'     => $avatarInvalidError,
                'errorCode' => 'validation',
            ];
        }

        $avatarValue = $sanitisedAvatar;
    }

    $templateUIDValue = null;

    if (isset($input['templateUID']) && is_string($input['templateUID']) && trim($input['templateUID']) !== '')
    {
        $templateUIDCandidate = (int) trim($input['templateUID']);

        if (!g2ml_linkspageManageIsValidSystemTemplate($templateUIDCandidate))
        {
            if (function_exists('__'))
            {
                $templateInvalidError = __('linkspage.error.template_invalid');
            }
            else
            {
                $templateInvalidError = 'Please choose a valid template.';
            }

            return [
                'ok'        => false,
                'error'     => $templateInvalidError,
                'errorCode' => 'validation',
            ];
        }

        $templateUIDValue = $templateUIDCandidate;
    }

    $themeColourRaw = '';

    if (isset($input['themeColour']) && is_string($input['themeColour']))
    {
        $themeColourRaw = trim($input['themeColour']);
    }

    if ($themeColourRaw === '')
    {
        $themeColourValue = null;
    }
    else
    {
        $validatedThemeColour = g2ml_linkspageManageValidateHexColour($themeColourRaw);

        if ($validatedThemeColour === false)
        {
            if (function_exists('__'))
            {
                $themeColourInvalidError = __('linkspage.error.theme_colour_invalid');
            }
            else
            {
                $themeColourInvalidError = 'Theme colour must be a hex value like #1E88E5.';
            }

            return [
                'ok'        => false,
                'error'     => $themeColourInvalidError,
                'errorCode' => 'validation',
            ];
        }

        $themeColourValue = $validatedThemeColour;
    }

    $backgroundColourRaw = '';

    if (isset($input['backgroundColour']) && is_string($input['backgroundColour']))
    {
        $backgroundColourRaw = trim($input['backgroundColour']);
    }

    if ($backgroundColourRaw === '')
    {
        $backgroundColourValue = null;
    }
    else
    {
        $validatedBackgroundColour = g2ml_linkspageManageValidateHexColour($backgroundColourRaw);

        if ($validatedBackgroundColour === false)
        {
            if (function_exists('__'))
            {
                $backgroundColourInvalidError = __('linkspage.error.background_colour_invalid');
            }
            else
            {
                $backgroundColourInvalidError = 'Background colour must be a hex value like #FFFFFF.';
            }

            return [
                'ok'        => false,
                'error'     => $backgroundColourInvalidError,
                'errorCode' => 'validation',
            ];
        }

        $backgroundColourValue = $validatedBackgroundColour;
    }

    $fontFamilyRaw = '';

    if (isset($input['fontFamily']) && is_string($input['fontFamily']))
    {
        $fontFamilyRaw = trim($input['fontFamily']);
    }

    if ($fontFamilyRaw === '')
    {
        $fontFamilyValue = null;
    }
    else
    {
        $validatedFontFamily = g2ml_linkspageManageValidateFontFamily($fontFamilyRaw);

        if ($validatedFontFamily === false)
        {
            if (function_exists('__'))
            {
                $fontFamilyInvalidError = __('linkspage.error.font_family_invalid');
            }
            else
            {
                $fontFamilyInvalidError = 'Font family may only contain letters, numbers, spaces, commas, hyphens, and quotes.';
            }

            return [
                'ok'        => false,
                'error'     => $fontFamilyInvalidError,
                'errorCode' => 'validation',
            ];
        }

        $fontFamilyValue = $validatedFontFamily;
    }

    if (isset($input['showSocialIcons']) && $input['showSocialIcons'] === true)
    {
        $showSocialIconsValue = 1;
    }
    else
    {
        $showSocialIconsValue = 0;
    }

    $rawSocialLinksInput = [];

    if (isset($input['socialLinks']) && is_array($input['socialLinks']))
    {
        $rawSocialLinksInput = $input['socialLinks'];
    }

    $validatedSocialLinks = g2ml_linkspageManageValidateSocialLinks($rawSocialLinksInput);

    if (count($validatedSocialLinks) === 0)
    {
        $socialLinksValue = null;
    }
    else
    {
        $socialLinksValue = json_encode($validatedSocialLinks);
    }

    if (isset($input['isPublished']) && $input['isPublished'] === true)
    {
        $isPublishedValue = 1;
    }
    else
    {
        $isPublishedValue = 0;
    }

    return [
        'ok'     => true,
        'fields' => [
            'slug'             => $slugRaw,
            'pageTitle'        => $pageTitleRaw,
            'pageDescription'  => $pageDescriptionValue,
            'avatarPath'       => $avatarValue,
            'templateUID'      => $templateUIDValue,
            'themeColour'      => $themeColourValue,
            'backgroundColour' => $backgroundColourValue,
            'fontFamily'       => $fontFamilyValue,
            'showSocialIcons'  => $showSocialIconsValue,
            'socialLinks'      => $socialLinksValue,
            'isPublished'      => $isPublishedValue,
        ],
    ];
}

// ============================================================================
// 📄 Pages — mutations
// ============================================================================

/**
 * Create a NEW LinksPage owned by the acting user, gated by the
 * maxLinksPages entitlement.
 *
 * @param  int    $userUID    The ACTING user's own userUID — never a client-supplied value.
 * @param  string $orgHandle  The acting user's OWN orgHandle (from getCurrentUser(), not client input).
 * @param  array  $input      Raw form fields — see _g2ml_linkspageManageValidateFields().
 * @return array  ['success' => bool, 'pageUID' => int|null, 'error' => string|null, 'errorCode' => string|null]
 */
function g2ml_linkspageManageCreatePage(int $userUID, string $orgHandle, array $input): array
{
    $validation = _g2ml_linkspageManageValidateFields($input);

    if ($validation['ok'] === false)
    {
        return [
            'success'   => false,
            'pageUID'   => null,
            'error'     => $validation['error'],
            'errorCode' => $validation['errorCode'],
        ];
    }

    $currentPageCount = g2ml_linkspageManageCountActivePagesForUser($userUID);
    $limitCheck        = g2ml_checkLimit($orgHandle, 'maxLinksPages', $currentPageCount);

    if ($limitCheck['allowed'] === false)
    {
        // Two separate keys, not one key with an optional {limit} — see
        // this file's own header comment for why.
        if ($limitCheck['limit'] !== null)
        {
            if (function_exists('__'))
            {
                $limitMessage = __('linkspage.error.page_limit_reached_with_limit', ['limit' => $limitCheck['limit']]);
            }
            else
            {
                $limitMessage = 'You have reached your plan\'s LinksPage limit of ' . $limitCheck['limit'] . '. Please upgrade your plan to create more LinksPages.';
            }
        }
        else
        {
            if (function_exists('__'))
            {
                $limitMessage = __('linkspage.error.page_limit_reached_no_limit');
            }
            else
            {
                $limitMessage = 'You have reached your plan\'s LinksPage limit. Please upgrade your plan to create more LinksPages.';
            }
        }

        return [
            'success'   => false,
            'pageUID'   => null,
            'error'     => $limitMessage,
            'errorCode' => 'limit_reached',
        ];
    }

    $fields = $validation['fields'];

    // 🐛 Bind-type mapping, one letter per value IN THE SAME ORDER as the
    // column list above — 'i' for an integer column (even one that may be
    // NULL, such as templateUID below; MySQLi binds NULL correctly under
    // any type letter) and 's' for every string column, including a
    // nullable one. #218 review round 3 flagged an earlier version of this
    // comment that said "'s' for everything else (a string, or a value
    // that is sometimes NULL)" — read plainly, that told the next person
    // adding a nullable INTEGER column to bind it as 's', which is wrong.
    // The mapping below has always bound the nullable templateUID column
    // as 'i', correctly, so this only fixed the wording, not the bindings
    // themselves. This used to end
    // '...sssiii' (socialLinks bound as an INTEGER, position 12). CORRECTED
    // after #218 review round 1 — the failure is not "a JSON-shaped string
    // cannot be coerced to an int" (it can: PHP converts a non-numeric
    // string to 0 on the CLIENT side, before anything is sent to MySQL, and
    // does so without complaint). What actually goes wrong is the OTHER
    // side of that conversion: mysqli then sends the resulting bare integer
    // 0 to MySQL for a column typed JSON, and MySQL 8 refuses a plain
    // number there outright with error 3140, because a JSON column needs
    // real JSON text (or an explicit CAST(... AS JSON)), not a bare number
    // — so creating a page with any social link failed completely with
    // "Could not create the LinksPage". MariaDB, which is more permissive
    // about what it will store in a JSON-typed column, accepted the same
    // bare 0 without erroring, so on MariaDB this did not fail loudly — it
    // silently stored 0 in place of the social links the owner had just
    // entered, with no error at all. The column-by-column mapping is:
    //   userUID(i), orgHandle(s), slug(s), pageTitle(s), pageDescription(s),
    //   avatarPath(s), templateUID(i), themeColour(s), backgroundColour(s),
    //   fontFamily(s), showSocialIcons(i), socialLinks(s), isPublished(i)
    $insertedPageUID = dbInsert(
        "INSERT INTO tblLinksPages
            (userUID, orgHandle, slug, pageTitle, pageDescription, avatarPath, templateUID,
             themeColour, backgroundColour, fontFamily, showSocialIcons, socialLinks, isPublished)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
        'isssssisssisi',
        [
            $userUID,
            $orgHandle,
            $fields['slug'],
            $fields['pageTitle'],
            $fields['pageDescription'],
            $fields['avatarPath'],
            $fields['templateUID'],
            $fields['themeColour'],
            $fields['backgroundColour'],
            $fields['fontFamily'],
            $fields['showSocialIcons'],
            $fields['socialLinks'],
            $fields['isPublished'],
        ]
    );

    if ($insertedPageUID === false)
    {
        if (function_exists('dbLastErrno') && dbLastErrno() === 1062)
        {
            if (function_exists('__'))
            {
                $slugTakenError = __('linkspage.error.slug_taken');
            }
            else
            {
                $slugTakenError = 'That URL slug is already taken. Please choose a different one.';
            }

            return [
                'success'   => false,
                'pageUID'   => null,
                'error'     => $slugTakenError,
                'errorCode' => 'slug_taken',
            ];
        }

        if (function_exists('__'))
        {
            $createFailedError = __('linkspage.error.create_failed');
        }
        else
        {
            $createFailedError = 'Could not create the LinksPage. Please try again.';
        }

        return [
            'success'   => false,
            'pageUID'   => null,
            'error'     => $createFailedError,
            'errorCode' => 'server_error',
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('create_linkspage', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $insertedPageUID, 'slug' => $fields['slug']],
        ]);
    }

    return [
        'success'   => true,
        'pageUID'   => (int) $insertedPageUID,
        'error'     => null,
        'errorCode' => null,
    ];
}

/**
 * Update an EXISTING LinksPage — ownership-checked (the page must belong to
 * the acting user). No entitlement re-check (a page already counted against
 * the limit at creation time is never re-blocked by editing it).
 *
 * @param  int   $userUID  The ACTING user's own userUID.
 * @param  int   $pageUID
 * @param  array $input    Raw form fields — see _g2ml_linkspageManageValidateFields().
 * @return array  ['success' => bool, 'error' => string|null, 'errorCode' => string|null]
 */
function g2ml_linkspageManageUpdatePage(int $userUID, int $pageUID, array $input): array
{
    $existingPage = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    if ($existingPage === null)
    {
        if (function_exists('__'))
        {
            $pageNotFoundEditError = __('linkspage.error.page_not_found_edit');
        }
        else
        {
            $pageNotFoundEditError = 'LinksPage not found, or you do not have permission to edit it.';
        }

        return [
            'success'   => false,
            'error'     => $pageNotFoundEditError,
            'errorCode' => 'not_found',
        ];
    }

    $validation = _g2ml_linkspageManageValidateFields($input);

    if ($validation['ok'] === false)
    {
        return [
            'success'   => false,
            'error'     => $validation['error'],
            'errorCode' => $validation['errorCode'],
        ];
    }

    $fields = $validation['fields'];

    // 🔒 Ownership enforced again on the UPDATE itself (defence in depth,
    // beyond the pre-check above) via "WHERE pageUID = ? AND userUID = ?".
    //
    // 🐛 Bind-type mapping, one letter per value IN THE SAME ORDER as the SET
    // list above, then the two WHERE values. This used to have TWO letters
    // swapped: templateUID (position 5, an int-or-null column) was bound as
    // 's', and fontFamily (position 8, a plain string) was bound as 'i'.
    // Binding a string like "Georgia" as an integer forces MySQLi to convert
    // it to a NUMBER before sending it — "Georgia" has no leading digits, so
    // it converts to 0 — which is exactly the reported bug: every edit-form
    // save overwrote the page's font family with the literal string "0",
    // and the public renderer then emitted `font-family: 0, ...`, which
    // every browser silently ignores. templateUID being bound as 's' rather
    // than 'i' happened not to break anything visible (MySQL accepts a
    // numeric string, or NULL, for an INT column regardless of the bound
    // type letter), but it is still the wrong letter for an integer column
    // and is corrected here too, per the fixed rule: every string bound as
    // 's', every integer bound as 'i'. The column-by-column mapping is:
    //   slug(s), pageTitle(s), pageDescription(s), avatarPath(s),
    //   templateUID(i), themeColour(s), backgroundColour(s), fontFamily(s),
    //   showSocialIcons(i), socialLinks(s), isPublished(i), pageUID(i),
    //   userUID(i)
    $affectedRows = dbUpdate(
        "UPDATE tblLinksPages SET
            slug = ?, pageTitle = ?, pageDescription = ?, avatarPath = ?, templateUID = ?,
            themeColour = ?, backgroundColour = ?, fontFamily = ?, showSocialIcons = ?,
            socialLinks = ?, isPublished = ?
         WHERE pageUID = ? AND userUID = ?",
        'ssssisssisiii',
        [
            $fields['slug'],
            $fields['pageTitle'],
            $fields['pageDescription'],
            $fields['avatarPath'],
            $fields['templateUID'],
            $fields['themeColour'],
            $fields['backgroundColour'],
            $fields['fontFamily'],
            $fields['showSocialIcons'],
            $fields['socialLinks'],
            $fields['isPublished'],
            $pageUID,
            $userUID,
        ]
    );

    if ($affectedRows === false)
    {
        if (function_exists('dbLastErrno') && dbLastErrno() === 1062)
        {
            if (function_exists('__'))
            {
                $slugTakenError = __('linkspage.error.slug_taken');
            }
            else
            {
                $slugTakenError = 'That URL slug is already taken. Please choose a different one.';
            }

            return [
                'success'   => false,
                'error'     => $slugTakenError,
                'errorCode' => 'slug_taken',
            ];
        }

        if (function_exists('__'))
        {
            $updateFailedError = __('linkspage.error.update_failed');
        }
        else
        {
            $updateFailedError = 'Could not update the LinksPage. Please try again.';
        }

        return [
            'success'   => false,
            'error'     => $updateFailedError,
            'errorCode' => 'server_error',
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('update_linkspage', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $pageUID, 'slug' => $fields['slug']],
        ]);
    }

    return [
        'success'   => true,
        'error'     => null,
        'errorCode' => null,
    ];
}

/**
 * Quickly flip a page's isPublished flag — ownership-checked. Used by the
 * list view's one-click publish/unpublish action, so a quick status change
 * does not require resubmitting the entire edit form.
 *
 * @param  int  $userUID      The ACTING user's own userUID.
 * @param  int  $pageUID
 * @param  bool $isPublished  The new desired published state.
 * @return array  ['success' => bool, 'error' => string|null]
 */
function g2ml_linkspageManageSetPublished(int $userUID, int $pageUID, bool $isPublished): array
{
    // 🔒 Ownership + existence are checked HERE, via a plain read, rather
    // than being inferred from the UPDATE's affected-row count below. This
    // is deliberate, and replaces a real bug: mysqli's affected_rows counts
    // rows whose STORED VALUE actually changed, not rows that MATCHED the
    // WHERE clause. Publishing a page that is already published (isPublished
    // is already 1) sets a column to the value it already holds, so MySQL
    // reports 0 affected rows even though the row exists and belongs to the
    // caller — which the old code treated as "not found", so publishing an
    // already-published page (or simply clicking Publish twice) always
    // failed with a false "not found" error. Checking existence and
    // ownership up front, separately from the write, means the UPDATE's
    // affected-row count is no longer asked to answer a question it cannot
    // reliably answer.
    $existingPage = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    if ($existingPage === null)
    {
        if (function_exists('__'))
        {
            $pageNotFoundEditError = __('linkspage.error.page_not_found_edit');
        }
        else
        {
            $pageNotFoundEditError = 'LinksPage not found, or you do not have permission to edit it.';
        }

        return [
            'success' => false,
            'error'   => $pageNotFoundEditError,
        ];
    }

    if ($isPublished === true)
    {
        $publishedValue = 1;
    }
    else
    {
        $publishedValue = 0;
    }

    $affectedRows = dbUpdate(
        "UPDATE tblLinksPages SET isPublished = ? WHERE pageUID = ? AND userUID = ?",
        'iii',
        [$publishedValue, $pageUID, $userUID]
    );

    // Only a hard database failure is an error from this point on. Zero
    // affected rows is an EXPECTED, successful outcome whenever the page
    // already held the requested isPublished value — see the ownership
    // pre-check above for why that can never mean "the page is missing".
    if ($affectedRows === false)
    {
        if (function_exists('__'))
        {
            $updateFailedError = __('linkspage.error.update_failed');
        }
        else
        {
            $updateFailedError = 'Could not update the LinksPage. Please try again.';
        }

        return [
            'success' => false,
            'error'   => $updateFailedError,
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('toggle_linkspage_published', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $pageUID, 'isPublished' => $publishedValue],
        ]);
    }

    return [
        'success' => true,
        'error'   => null,
    ];
}

/**
 * Delete a LinksPage — ownership-checked. Cascades to its items via
 * FK_item_page ON DELETE CASCADE (see 032_linkspage.sql).
 *
 * @param  int $userUID  The ACTING user's own userUID.
 * @param  int $pageUID
 * @return array  ['success' => bool, 'error' => string|null]
 */
function g2ml_linkspageManageDeletePage(int $userUID, int $pageUID): array
{
    $deletedRows = dbDelete(
        "DELETE FROM tblLinksPages WHERE pageUID = ? AND userUID = ?",
        'ii',
        [$pageUID, $userUID]
    );

    if ($deletedRows === false || $deletedRows === 0)
    {
        if (function_exists('__'))
        {
            $pageNotFoundDeleteError = __('linkspage.error.page_not_found_delete');
        }
        else
        {
            $pageNotFoundDeleteError = 'LinksPage not found, or you do not have permission to delete it.';
        }

        return [
            'success' => false,
            'error'   => $pageNotFoundDeleteError,
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('delete_linkspage', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $pageUID],
        ]);
    }

    return [
        'success' => true,
        'error'   => null,
    ];
}

// ============================================================================
// 🔗 Items — reads
// ============================================================================

/**
 * List every item on a page, ownership-checked via a JOIN back to
 * tblLinksPages so an itemUID/pageUID belonging to another user can never be
 * enumerated through this function.
 *
 * @param  int $pageUID
 * @param  int $userUID  The ACTING user's own userUID.
 * @return array
 */
function g2ml_linkspageManageListItemsForPage(int $pageUID, int $userUID): array
{
    $rows = dbSelect(
        "SELECT i.itemUID, i.pageUID, i.urlUID, i.itemTitle, i.itemURL, i.itemDescription,
                i.itemIcon, i.requiresAgeGate, i.sortOrder, i.isActive
         FROM tblLinksPageItems i
         INNER JOIN tblLinksPages p ON i.pageUID = p.pageUID
         WHERE i.pageUID = ? AND p.userUID = ?
         ORDER BY i.sortOrder ASC, i.itemUID ASC",
        'ii',
        [$pageUID, $userUID]
    );

    if ($rows === false)
    {
        return [];
    }

    return $rows;
}

/**
 * Fetch ONE item, ownership-checked via a JOIN back to the OWNING page.
 *
 * @param  int $itemUID
 * @param  int $userUID  The ACTING user's own userUID.
 * @return array|null
 */
function g2ml_linkspageManageGetItemForOwner(int $itemUID, int $userUID): ?array
{
    $row = dbSelectOne(
        "SELECT i.itemUID, i.pageUID, i.urlUID, i.itemTitle, i.itemURL, i.itemDescription,
                i.itemIcon, i.requiresAgeGate, i.sortOrder, i.isActive
         FROM tblLinksPageItems i
         INNER JOIN tblLinksPages p ON i.pageUID = p.pageUID
         WHERE i.itemUID = ? AND p.userUID = ?
         LIMIT 1",
        'ii',
        [$itemUID, $userUID]
    );

    if ($row === null || $row === false)
    {
        return null;
    }

    return $row;
}

/**
 * List a user's own active short URLs, for the "add item from an existing
 * short URL" picker. Deliberately scoped to createdByUserUID (the same
 * ownership boundary the Links dashboard itself uses — see
 * web/Go2My.Link/_admin/public_html/pages/links/index.php), so a LinksPage
 * item can never be created by referencing another org member's link.
 * Capped at 200 rows — a picker dropdown, not a full listing page.
 *
 * @param  int $userUID  The ACTING user's own userUID.
 * @return array
 */
function g2ml_linkspageManageListShortURLsForUser(int $userUID): array
{
    $rows = dbSelect(
        "SELECT urlUID, shortCode, destinationURL, title, orgHandle
         FROM tblShortURLs
         WHERE createdByUserUID = ? AND isActive = 1
         ORDER BY createdAt DESC
         LIMIT 200",
        'i',
        [$userUID]
    );

    if ($rows === false)
    {
        return [];
    }

    return $rows;
}

// ============================================================================
// 🔞 Items — age-gate auto-flag resolver (Component C.5, #50)
// ============================================================================

/**
 * Resolve the FINAL requiresAgeGate value for an item being added or edited.
 *
 * Auto-flag: if the resolved destination URL's host matches the curated (or
 * operator-configured) adult-domain allowlist — see
 * web/_functions/adult_content.php's g2ml_isAdultDomain() — the gate is
 * FORCED on regardless of the owner's submitted checkbox state. This is a
 * deliberate, protective default: automatic age-gating for a verified
 * known-adult destination cannot be silently bypassed by simply leaving the
 * item form's checkbox unticked.
 *
 * For any OTHER destination, the owner's own explicit checkbox choice is
 * honoured exactly as submitted — this is the "the owner can still toggle
 * it" freedom described in the file header: full manual control over the
 * gate for anything not on the curated list.
 *
 * @param  bool        $ownerRequestedGate  Whether the item form's
 *                                           requiresAgeGate checkbox was
 *                                           submitted checked.
 * @param  string|null $resolvedURL         The item's FINAL resolved
 *                                           destination URL (already
 *                                           scheme-validated by the caller).
 * @return int  1 or 0, ready to bind into tblLinksPageItems.requiresAgeGate.
 */
function _g2ml_linkspageManageResolveRequiresAgeGate(bool $ownerRequestedGate, ?string $resolvedURL): int
{
    $autoDetectedAdultDomain = false;

    if (function_exists('g2ml_isAdultDomain'))
    {
        $autoDetectedAdultDomain = g2ml_isAdultDomain($resolvedURL);
    }

    if ($ownerRequestedGate === true || $autoDetectedAdultDomain === true)
    {
        return 1;
    }

    return 0;
}

// ============================================================================
// 🔗 Items — mutations
// ============================================================================

/**
 * Add an item to a page — ownership-checked (the page must belong to the
 * acting user). Two mutually exclusive sources:
 *   - 'shorturl': $input['urlUID'] must reference one of THIS user's own
 *     active short URLs (re-verified here, never trusted from the client
 *     beyond the numeric ID) — itemURL is derived server-side from the
 *     short URL's own domain + code, never taken from client input.
 *   - 'manual': $input['manualURL'] is scheme-validated via g2ml_sanitiseURL().
 *
 * requiresAgeGate (C.5, #50) is resolved via
 * _g2ml_linkspageManageResolveRequiresAgeGate() — the owner's own submitted
 * checkbox is honoured UNLESS the resolved destination matches the curated
 * adult-domain allowlist, in which case the gate is force-enabled.
 *
 * @param  int   $userUID  The ACTING user's own userUID.
 * @param  int   $pageUID
 * @param  array $input    ['source' => 'shorturl'|'manual', 'urlUID' => int|null,
 *                          'manualURL' => string|null, 'itemTitle' => string,
 *                          'itemDescription' => string|null, 'itemIcon' => string|null,
 *                          'requiresAgeGate' => bool|null]
 * @return array  ['success' => bool, 'itemUID' => int|null, 'error' => string|null]
 */
function g2ml_linkspageManageAddItem(int $userUID, int $pageUID, array $input): array
{
    $ownedPage = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    if ($ownedPage === null)
    {
        if (function_exists('__'))
        {
            $pageNotFoundEditError = __('linkspage.error.page_not_found_edit');
        }
        else
        {
            $pageNotFoundEditError = 'LinksPage not found, or you do not have permission to edit it.';
        }

        return [
            'success' => false,
            'itemUID' => null,
            'error'   => $pageNotFoundEditError,
        ];
    }

    // 🚫 Hard abuse cap — see G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE's docblock.
    // Checked BEFORE any of the (more expensive) field validation below, so
    // a page already at the cap fails fast without touching a short URL
    // lookup or a manual-URL sanitise pass for input that is going to be
    // rejected regardless.
    $currentItemCountRow = dbSelectOne(
        "SELECT COUNT(*) AS itemCount FROM tblLinksPageItems WHERE pageUID = ?",
        'i',
        [$pageUID]
    );

    $currentItemCount = 0;

    if ($currentItemCountRow !== null && $currentItemCountRow !== false && isset($currentItemCountRow['itemCount']))
    {
        $currentItemCount = (int) $currentItemCountRow['itemCount'];
    }

    if ($currentItemCount >= G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE)
    {
        if (function_exists('__'))
        {
            $itemsMaxError = __('linkspage.error.items_max', ['max' => G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE]);
        }
        else
        {
            $itemsMaxError = 'This page already has the maximum of ' . G2ML_LINKSPAGE_MAX_ITEMS_PER_PAGE . ' links.';
        }

        return [
            'success' => false,
            'itemUID' => null,
            'error'   => $itemsMaxError,
        ];
    }

    $source = '';

    if (isset($input['source']) && is_string($input['source']))
    {
        $source = $input['source'];
    }

    $resolvedURLUID = null;
    $resolvedItemURL = null;

    if ($source === 'shorturl')
    {
        $urlUIDCandidate = 0;

        if (isset($input['urlUID']))
        {
            $urlUIDCandidate = (int) $input['urlUID'];
        }

        if ($urlUIDCandidate <= 0)
        {
            if (function_exists('__'))
            {
                $shortURLRequiredError = __('linkspage.error.shorturl_required');
            }
            else
            {
                $shortURLRequiredError = 'Please choose one of your short URLs.';
            }

            return [
                'success' => false,
                'itemUID' => null,
                'error'   => $shortURLRequiredError,
            ];
        }

        // 🔒 Ownership re-verified here — a urlUID belonging to another user
        // (or to nobody) is rejected outright, regardless of client input.
        $ownedShortURL = dbSelectOne(
            "SELECT urlUID, shortCode, orgHandle FROM tblShortURLs WHERE urlUID = ? AND createdByUserUID = ? AND isActive = 1 LIMIT 1",
            'ii',
            [$urlUIDCandidate, $userUID]
        );

        if ($ownedShortURL === null || $ownedShortURL === false)
        {
            if (function_exists('__'))
            {
                $shortURLNotFoundError = __('linkspage.error.shorturl_not_found');
            }
            else
            {
                $shortURLNotFoundError = 'That short URL was not found, or you do not have permission to use it.';
            }

            return [
                'success' => false,
                'itemUID' => null,
                'error'   => $shortURLNotFoundError,
            ];
        }

        $shortDomain = 'g2my.link';

        if (function_exists('getDefaultShortDomain'))
        {
            $shortDomain = getDefaultShortDomain((string) $ownedShortURL['orgHandle']);
        }

        $resolvedURLUID  = (int) $ownedShortURL['urlUID'];
        $resolvedItemURL = 'https://' . $shortDomain . '/' . $ownedShortURL['shortCode'];
    }
    elseif ($source === 'manual')
    {
        $manualURLRaw = '';

        if (isset($input['manualURL']) && is_string($input['manualURL']))
        {
            $manualURLRaw = trim($input['manualURL']);
        }

        $sanitisedManualURL = g2ml_sanitiseURL($manualURLRaw);

        if ($sanitisedManualURL === false)
        {
            if (function_exists('__'))
            {
                $urlInvalidError = __('linkspage.error.url_invalid');
            }
            else
            {
                $urlInvalidError = 'Please enter a valid http:// or https:// URL.';
            }

            return [
                'success' => false,
                'itemUID' => null,
                'error'   => $urlInvalidError,
            ];
        }

        $resolvedURLUID  = null;
        $resolvedItemURL = $sanitisedManualURL;
    }
    else
    {
        if (function_exists('__'))
        {
            $sourceRequiredError = __('linkspage.error.source_required');
        }
        else
        {
            $sourceRequiredError = 'Please choose a link source.';
        }

        return [
            'success' => false,
            'itemUID' => null,
            'error'   => $sourceRequiredError,
        ];
    }

    $itemTitleRaw = '';

    if (isset($input['itemTitle']) && is_string($input['itemTitle']))
    {
        $itemTitleRaw = trim(g2ml_sanitiseInput($input['itemTitle']));
    }

    if ($itemTitleRaw === '')
    {
        if (function_exists('__'))
        {
            $itemTitleRequiredError = __('linkspage.error.item_title_required');
        }
        else
        {
            $itemTitleRequiredError = 'Please enter a title for this link.';
        }

        return [
            'success' => false,
            'itemUID' => null,
            'error'   => $itemTitleRequiredError,
        ];
    }

    if (mb_strlen($itemTitleRaw) > 255)
    {
        if (function_exists('__'))
        {
            $itemTitleTooLongError = __('linkspage.error.item_title_too_long');
        }
        else
        {
            $itemTitleTooLongError = 'The link title must be 255 characters or fewer.';
        }

        return [
            'success' => false,
            'itemUID' => null,
            'error'   => $itemTitleTooLongError,
        ];
    }

    $itemDescriptionRaw = '';

    if (isset($input['itemDescription']) && is_string($input['itemDescription']))
    {
        $itemDescriptionRaw = trim(g2ml_sanitiseInput($input['itemDescription']));
    }

    if (mb_strlen($itemDescriptionRaw) > G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH)
    {
        if (function_exists('__'))
        {
            $itemDescriptionTooLongError = __('linkspage.error.item_description_too_long', ['max' => G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH]);
        }
        else
        {
            $itemDescriptionTooLongError = 'The link description must be ' . G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH . ' characters or fewer.';
        }

        return [
            'success' => false,
            'itemUID' => null,
            'error'   => $itemDescriptionTooLongError,
        ];
    }

    if ($itemDescriptionRaw === '')
    {
        $itemDescriptionValue = null;
    }
    else
    {
        $itemDescriptionValue = $itemDescriptionRaw;
    }

    $itemIconRaw = '';

    if (isset($input['itemIcon']) && is_string($input['itemIcon']))
    {
        $itemIconRaw = trim($input['itemIcon']);
    }

    if ($itemIconRaw === '')
    {
        $itemIconValue = null;
    }
    else
    {
        $sanitisedIcon = g2ml_sanitiseURL($itemIconRaw);

        // See the matching comment on the avatarPath check in
        // _g2ml_linkspageManageValidateFields() above (issues #221/#273):
        // a per-link icon has the same problem as the avatar. An http://
        // icon is unreliable on the public page, not always blocked — a
        // modern browser quietly retries it over https:// first, so it can
        // look fine to the creator while still failing for some visitors
        // (an older browser, or an image server with no https at all), and
        // the creator has no way to tell which from their own screen. So
        // it is refused here on save, the same as the avatar, instead of
        // being accepted and left to fail quietly for someone else.
        $iconScheme = false;

        if ($sanitisedIcon !== false)
        {
            $iconScheme = strtolower((string) parse_url($sanitisedIcon, PHP_URL_SCHEME));
        }

        if ($sanitisedIcon === false || mb_strlen($sanitisedIcon) > 500 || $iconScheme !== 'https')
        {
            if (function_exists('__'))
            {
                $iconInvalidError = __('linkspage.item_icon_https_error');
            }
            else
            {
                $iconInvalidError = 'The icon must be a valid https:// image URL.';
            }

            return [
                'success' => false,
                'itemUID' => null,
                'error'   => $iconInvalidError,
            ];
        }

        $itemIconValue = $sanitisedIcon;
    }

    // 🔞 C.5/#50 — resolve the auto-flag: the owner's own checkbox is
    // honoured UNLESS the destination matches the curated adult-domain
    // allowlist, in which case the gate is force-enabled. See
    // _g2ml_linkspageManageResolveRequiresAgeGate()'s docblock.
    $ownerRequestedAgeGate = false;

    if (isset($input['requiresAgeGate']) && $input['requiresAgeGate'] === true)
    {
        $ownerRequestedAgeGate = true;
    }

    $requiresAgeGateValue = _g2ml_linkspageManageResolveRequiresAgeGate($ownerRequestedAgeGate, $resolvedItemURL);

    $maxSortRow = dbSelectOne(
        "SELECT MAX(sortOrder) AS maxSort FROM tblLinksPageItems WHERE pageUID = ?",
        'i',
        [$pageUID]
    );

    $nextSortOrder = 0;

    if ($maxSortRow !== null && $maxSortRow !== false && $maxSortRow['maxSort'] !== null)
    {
        $nextSortOrder = (int) $maxSortRow['maxSort'] + 1;
    }

    $insertedItemUID = dbInsert(
        "INSERT INTO tblLinksPageItems
            (pageUID, urlUID, itemTitle, itemURL, itemDescription, itemIcon, requiresAgeGate, sortOrder)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
        'iissssii',
        [
            $pageUID,
            $resolvedURLUID,
            $itemTitleRaw,
            $resolvedItemURL,
            $itemDescriptionValue,
            $itemIconValue,
            $requiresAgeGateValue,
            $nextSortOrder,
        ]
    );

    if ($insertedItemUID === false)
    {
        if (function_exists('__'))
        {
            $itemAddFailedError = __('linkspage.error.item_add_failed');
        }
        else
        {
            $itemAddFailedError = 'Could not add the link. Please try again.';
        }

        return [
            'success' => false,
            'itemUID' => null,
            'error'   => $itemAddFailedError,
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('add_linkspage_item', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $pageUID, 'itemUID' => $insertedItemUID],
        ]);
    }

    return [
        'success' => true,
        'itemUID' => (int) $insertedItemUID,
        'error'   => null,
    ];
}

/**
 * Update an item's title/description/icon — and, ONLY when it was originally
 * a manual link (urlUID IS NULL), its destination URL. An item sourced from
 * one of the user's own short URLs keeps that link locked (mirrors
 * edit_link's read-only short-code convention) — edit the short URL itself
 * to change where it points.
 *
 * requiresAgeGate (C.5, #50) is re-resolved via
 * _g2ml_linkspageManageResolveRequiresAgeGate() against the item's FINAL
 * destination URL every time it is edited — the owner's own submitted
 * checkbox is honoured unless that destination matches the curated
 * adult-domain allowlist, in which case the gate stays force-enabled.
 *
 * @param  int   $userUID  The ACTING user's own userUID.
 * @param  int   $itemUID
 * @param  array $input    ['itemTitle' => string, 'itemDescription' => string|null,
 *                          'itemIcon' => string|null, 'manualURL' => string|null,
 *                          'requiresAgeGate' => bool|null]
 * @return array  ['success' => bool, 'error' => string|null]
 */
function g2ml_linkspageManageUpdateItem(int $userUID, int $itemUID, array $input): array
{
    $existingItem = g2ml_linkspageManageGetItemForOwner($itemUID, $userUID);

    if ($existingItem === null)
    {
        if (function_exists('__'))
        {
            $itemNotFoundEditError = __('linkspage.error.item_not_found_edit');
        }
        else
        {
            $itemNotFoundEditError = 'Link not found, or you do not have permission to edit it.';
        }

        return [
            'success' => false,
            'error'   => $itemNotFoundEditError,
        ];
    }

    $itemTitleRaw = '';

    if (isset($input['itemTitle']) && is_string($input['itemTitle']))
    {
        $itemTitleRaw = trim(g2ml_sanitiseInput($input['itemTitle']));
    }

    if ($itemTitleRaw === '')
    {
        if (function_exists('__'))
        {
            $itemTitleRequiredError = __('linkspage.error.item_title_required');
        }
        else
        {
            $itemTitleRequiredError = 'Please enter a title for this link.';
        }

        return [
            'success' => false,
            'error'   => $itemTitleRequiredError,
        ];
    }

    if (mb_strlen($itemTitleRaw) > 255)
    {
        if (function_exists('__'))
        {
            $itemTitleTooLongError = __('linkspage.error.item_title_too_long');
        }
        else
        {
            $itemTitleTooLongError = 'The link title must be 255 characters or fewer.';
        }

        return [
            'success' => false,
            'error'   => $itemTitleTooLongError,
        ];
    }

    $itemDescriptionRaw = '';

    if (isset($input['itemDescription']) && is_string($input['itemDescription']))
    {
        $itemDescriptionRaw = trim(g2ml_sanitiseInput($input['itemDescription']));
    }

    if (mb_strlen($itemDescriptionRaw) > G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH)
    {
        if (function_exists('__'))
        {
            $itemDescriptionTooLongError = __('linkspage.error.item_description_too_long', ['max' => G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH]);
        }
        else
        {
            $itemDescriptionTooLongError = 'The link description must be ' . G2ML_LINKSPAGE_MANAGE_DESCRIPTION_MAX_LENGTH . ' characters or fewer.';
        }

        return [
            'success' => false,
            'error'   => $itemDescriptionTooLongError,
        ];
    }

    if ($itemDescriptionRaw === '')
    {
        $itemDescriptionValue = null;
    }
    else
    {
        $itemDescriptionValue = $itemDescriptionRaw;
    }

    $itemIconRaw = '';

    if (isset($input['itemIcon']) && is_string($input['itemIcon']))
    {
        $itemIconRaw = trim($input['itemIcon']);
    }

    if ($itemIconRaw === '')
    {
        $itemIconValue = null;
    }
    else
    {
        $sanitisedIcon = g2ml_sanitiseURL($itemIconRaw);

        // See the matching comment on the avatarPath check in
        // _g2ml_linkspageManageValidateFields() (issues #221/#273): an
        // http:// icon is unreliable on the public page (it may show fine
        // in the creator's own browser, because of the mixed-content
        // retry a modern browser does before the CSP is even checked, and
        // still fail for a visitor whose browser or image server does
        // not), so it is refused on an update just as it is on an add —
        // the same rule as the avatar — rather than accepted and left to
        // fail quietly for someone else.
        $iconScheme = false;

        if ($sanitisedIcon !== false)
        {
            $iconScheme = strtolower((string) parse_url($sanitisedIcon, PHP_URL_SCHEME));
        }

        if ($sanitisedIcon === false || mb_strlen($sanitisedIcon) > 500 || $iconScheme !== 'https')
        {
            if (function_exists('__'))
            {
                $iconInvalidError = __('linkspage.item_icon_https_error');
            }
            else
            {
                $iconInvalidError = 'The icon must be a valid https:// image URL.';
            }

            return [
                'success' => false,
                'error'   => $iconInvalidError,
            ];
        }

        $itemIconValue = $sanitisedIcon;
    }

    // Only a MANUAL item (no linked short URL) may have its destination URL
    // changed here.
    if ($existingItem['urlUID'] === null)
    {
        $manualURLRaw = '';

        if (isset($input['manualURL']) && is_string($input['manualURL']))
        {
            $manualURLRaw = trim($input['manualURL']);
        }

        $sanitisedManualURL = g2ml_sanitiseURL($manualURLRaw);

        if ($sanitisedManualURL === false)
        {
            if (function_exists('__'))
            {
                $urlInvalidError = __('linkspage.error.url_invalid');
            }
            else
            {
                $urlInvalidError = 'Please enter a valid http:// or https:// URL.';
            }

            return [
                'success' => false,
                'error'   => $urlInvalidError,
            ];
        }

        $itemURLValue = $sanitisedManualURL;
    }
    else
    {
        // Short-URL-sourced item — the destination is locked to the short URL.
        $itemURLValue = $existingItem['itemURL'];
    }

    // 🔞 C.5/#50 — re-resolve the auto-flag against the FINAL destination
    // URL. See _g2ml_linkspageManageResolveRequiresAgeGate()'s docblock.
    $ownerRequestedAgeGate = false;

    if (isset($input['requiresAgeGate']) && $input['requiresAgeGate'] === true)
    {
        $ownerRequestedAgeGate = true;
    }

    $requiresAgeGateValue = _g2ml_linkspageManageResolveRequiresAgeGate($ownerRequestedAgeGate, $itemURLValue);

    // 🔒 Ownership enforced again on the UPDATE itself via a correlated
    // subquery scoped to pages owned by the acting user.
    $affectedRows = dbUpdate(
        "UPDATE tblLinksPageItems SET itemTitle = ?, itemURL = ?, itemDescription = ?, itemIcon = ?, requiresAgeGate = ?
         WHERE itemUID = ? AND pageUID IN (SELECT pageUID FROM tblLinksPages WHERE userUID = ?)",
        'ssssiii',
        [
            $itemTitleRaw,
            $itemURLValue,
            $itemDescriptionValue,
            $itemIconValue,
            $requiresAgeGateValue,
            $itemUID,
            $userUID,
        ]
    );

    if ($affectedRows === false)
    {
        if (function_exists('__'))
        {
            $itemUpdateFailedError = __('linkspage.error.item_update_failed');
        }
        else
        {
            $itemUpdateFailedError = 'Could not update the link. Please try again.';
        }

        return [
            'success' => false,
            'error'   => $itemUpdateFailedError,
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('update_linkspage_item', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['itemUID' => $itemUID],
        ]);
    }

    return [
        'success' => true,
        'error'   => null,
    ];
}

/**
 * Delete an item — ownership-checked via a correlated subquery scoped to
 * pages owned by the acting user.
 *
 * @param  int $userUID  The ACTING user's own userUID.
 * @param  int $itemUID
 * @return array  ['success' => bool, 'error' => string|null]
 */
function g2ml_linkspageManageDeleteItem(int $userUID, int $itemUID): array
{
    $deletedRows = dbDelete(
        "DELETE FROM tblLinksPageItems
         WHERE itemUID = ? AND pageUID IN (SELECT pageUID FROM tblLinksPages WHERE userUID = ?)",
        'ii',
        [$itemUID, $userUID]
    );

    if ($deletedRows === false || $deletedRows === 0)
    {
        if (function_exists('__'))
        {
            $itemNotFoundDeleteError = __('linkspage.error.item_not_found_delete');
        }
        else
        {
            $itemNotFoundDeleteError = 'Link not found, or you do not have permission to delete it.';
        }

        return [
            'success' => false,
            'error'   => $itemNotFoundDeleteError,
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('delete_linkspage_item', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['itemUID' => $itemUID],
        ]);
    }

    return [
        'success' => true,
        'error'   => null,
    ];
}

/**
 * Flip an item's isActive flag — ownership-checked.
 *
 * @param  int $userUID  The ACTING user's own userUID.
 * @param  int $itemUID
 * @return array  ['success' => bool, 'isActive' => int|null, 'error' => string|null]
 */
function g2ml_linkspageManageToggleItemActive(int $userUID, int $itemUID): array
{
    $existingItem = g2ml_linkspageManageGetItemForOwner($itemUID, $userUID);

    if ($existingItem === null)
    {
        if (function_exists('__'))
        {
            $itemNotFoundEditError = __('linkspage.error.item_not_found_edit');
        }
        else
        {
            $itemNotFoundEditError = 'Link not found, or you do not have permission to edit it.';
        }

        return [
            'success'  => false,
            'isActive' => null,
            'error'    => $itemNotFoundEditError,
        ];
    }

    if ((int) $existingItem['isActive'] === 1)
    {
        $newActiveValue = 0;
    }
    else
    {
        $newActiveValue = 1;
    }

    $affectedRows = dbUpdate(
        "UPDATE tblLinksPageItems SET isActive = ?
         WHERE itemUID = ? AND pageUID IN (SELECT pageUID FROM tblLinksPages WHERE userUID = ?)",
        'iii',
        [$newActiveValue, $itemUID, $userUID]
    );

    if ($affectedRows === false)
    {
        if (function_exists('__'))
        {
            $itemUpdateFailedError = __('linkspage.error.item_update_failed');
        }
        else
        {
            $itemUpdateFailedError = 'Could not update the link. Please try again.';
        }

        return [
            'success'  => false,
            'isActive' => null,
            'error'    => $itemUpdateFailedError,
        ];
    }

    if (function_exists('logActivity'))
    {
        logActivity('toggle_linkspage_item', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['itemUID' => $itemUID, 'isActive' => $newActiveValue],
        ]);
    }

    return [
        'success'  => true,
        'isActive' => $newActiveValue,
        'error'    => null,
    ];
}

/**
 * Move an item up or down (swap sortOrder with its adjacent sibling on the
 * SAME page) — ownership-checked. A no-sibling case (already first/last) is
 * reported as a successful no-op, not an error.
 *
 * @param  int    $userUID    The ACTING user's own userUID.
 * @param  int    $itemUID
 * @param  string $direction  'up' or 'down' — any other value is rejected.
 * @return array  ['success' => bool, 'moved' => bool, 'error' => string|null]
 */
function g2ml_linkspageManageMoveItem(int $userUID, int $itemUID, string $direction): array
{
    if ($direction !== 'up' && $direction !== 'down')
    {
        if (function_exists('__'))
        {
            $moveDirectionInvalidError = __('linkspage.error.move_direction_invalid');
        }
        else
        {
            $moveDirectionInvalidError = 'Invalid move direction.';
        }

        return [
            'success' => false,
            'moved'   => false,
            'error'   => $moveDirectionInvalidError,
        ];
    }

    $existingItem = g2ml_linkspageManageGetItemForOwner($itemUID, $userUID);

    if ($existingItem === null)
    {
        if (function_exists('__'))
        {
            $itemNotFoundEditError = __('linkspage.error.item_not_found_edit');
        }
        else
        {
            $itemNotFoundEditError = 'Link not found, or you do not have permission to edit it.';
        }

        return [
            'success' => false,
            'moved'   => false,
            'error'   => $itemNotFoundEditError,
        ];
    }

    $pageUID          = (int) $existingItem['pageUID'];
    $currentSortOrder = (int) $existingItem['sortOrder'];

    if ($direction === 'up')
    {
        $sibling = dbSelectOne(
            "SELECT itemUID, sortOrder FROM tblLinksPageItems
             WHERE pageUID = ? AND sortOrder < ?
             ORDER BY sortOrder DESC, itemUID DESC
             LIMIT 1",
            'ii',
            [$pageUID, $currentSortOrder]
        );
    }
    else
    {
        $sibling = dbSelectOne(
            "SELECT itemUID, sortOrder FROM tblLinksPageItems
             WHERE pageUID = ? AND sortOrder > ?
             ORDER BY sortOrder ASC, itemUID ASC
             LIMIT 1",
            'ii',
            [$pageUID, $currentSortOrder]
        );
    }

    if ($sibling === null || $sibling === false)
    {
        // Already at the top/bottom — nothing to do, not an error.
        return [
            'success' => true,
            'moved'   => false,
            'error'   => null,
        ];
    }

    $siblingItemUID    = (int) $sibling['itemUID'];
    $siblingSortOrder  = (int) $sibling['sortOrder'];

    dbBeginTransaction();

    $firstUpdate = dbUpdate(
        "UPDATE tblLinksPageItems SET sortOrder = ? WHERE itemUID = ? AND pageUID = ?",
        'iii',
        [$siblingSortOrder, $itemUID, $pageUID]
    );

    $secondUpdate = dbUpdate(
        "UPDATE tblLinksPageItems SET sortOrder = ? WHERE itemUID = ? AND pageUID = ?",
        'iii',
        [$currentSortOrder, $siblingItemUID, $pageUID]
    );

    if ($firstUpdate === false || $secondUpdate === false)
    {
        dbRollback();

        if (function_exists('__'))
        {
            $reorderFailedError = __('linkspage.error.reorder_failed');
        }
        else
        {
            $reorderFailedError = 'Could not reorder the links. Please try again.';
        }

        return [
            'success' => false,
            'moved'   => false,
            'error'   => $reorderFailedError,
        ];
    }

    dbCommit();

    return [
        'success' => true,
        'moved'   => true,
        'error'   => null,
    ];
}

// ============================================================================
// 🧨 Custom HTML / CSS (Component C.6, #49)
// ============================================================================
// The single highest stored-XSS surface in the product. Every write here is:
//   1. OWNERSHIP-checked (the page must belong to the acting user);
//   2. GATED — the operator kill-switch (linkspage.custom_html_enabled) must be
//      ON *and* the PAGE'S OWN org tier must grant hasCustomHTML — via
//      g2ml_linkspageCustomHtmlAllowedForOrg() (web/_functions/html_sanitiser.php).
//      A non-premium org (or the whole feature being off) means the value is
//      NEITHER sanitised-and-stored NOR rendered;
//   3. SANITISED on input with g2ml_sanitiseUserHTML()/g2ml_sanitiseUserCSS()
//      (DOM allowlist + mXSS fixed-point) — the SANITISED form is what is
//      stored, never the raw submission;
//   4. re-sanitised again on OUTPUT by the renderer, and served under a strict
//      `script-src 'none'` CSP.
// Clearing custom HTML (submitting empty) is ALWAYS allowed (it is safe and
// simply reverts the page to its system template), even for a non-premium org.
// ============================================================================

/**
 * Shared gate + sanitise + store for a custom-HTML/CSS write. Internal — both
 * the textarea save and the file-upload save delegate here so the security
 * logic exists in exactly one place.
 *
 * @param  int    $userUID       The ACTING user's own userUID.
 * @param  int    $pageUID
 * @param  string $customHTMLRaw The raw (un-sanitised) HTML submission.
 * @param  string $customCSSRaw  The raw (un-sanitised) CSS submission.
 * @return array  ['success' => bool, 'error' => string|null, 'errorCode' => string|null,
 *                 'customHTML' => string|null, 'customCSS' => string|null]
 */
function _g2ml_linkspageManageStoreSanitisedCustom(int $userUID, int $pageUID, string $customHTMLRaw, string $customCSSRaw): array
{
    $ownedPage = g2ml_linkspageManageGetPageForOwner($pageUID, $userUID);

    if ($ownedPage === null)
    {
        if (function_exists('__'))
        {
            $pageNotFoundEditError = __('linkspage.error.page_not_found_edit');
        }
        else
        {
            $pageNotFoundEditError = 'LinksPage not found, or you do not have permission to edit it.';
        }

        return [
            'success'    => false,
            'error'      => $pageNotFoundEditError,
            'errorCode'  => 'not_found',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    $isClearing = false;

    if (trim($customHTMLRaw) === '' && trim($customCSSRaw) === '')
    {
        $isClearing = true;
    }

    $orgHandle = null;

    if (isset($ownedPage['orgHandle']) && is_string($ownedPage['orgHandle']))
    {
        $orgHandle = $ownedPage['orgHandle'];
    }

    // 🔒 Enforce the gate for a SET; a CLEAR (empty submission) is always safe
    // and is allowed regardless of tier/kill-switch.
    if ($isClearing === false)
    {
        $allowed = false;

        if (function_exists('g2ml_linkspageCustomHtmlAllowedForOrg'))
        {
            $allowed = g2ml_linkspageCustomHtmlAllowedForOrg($orgHandle);
        }

        if ($allowed !== true)
        {
            if (function_exists('__'))
            {
                $customHTMLUnavailableError = __('linkspage.error.custom_html_unavailable');
            }
            else
            {
                $customHTMLUnavailableError = 'Custom HTML is not available on your current plan, or has been disabled by the administrator.';
            }

            return [
                'success'    => false,
                'error'      => $customHTMLUnavailableError,
                'errorCode'  => 'feature_unavailable',
                'customHTML' => null,
                'customCSS'  => null,
            ];
        }
    }

    // Size caps on the RAW submission (before sanitisation) — reject oversized
    // input outright rather than silently truncating.
    if (defined('G2ML_CUSTOM_HTML_MAX_BYTES') && strlen($customHTMLRaw) > G2ML_CUSTOM_HTML_MAX_BYTES)
    {
        if (function_exists('__'))
        {
            $customHTMLTooLargeError = __('linkspage.error.custom_html_too_large', ['max' => (int) (G2ML_CUSTOM_HTML_MAX_BYTES / 1000)]);
        }
        else
        {
            $customHTMLTooLargeError = 'The custom HTML is too large. Please keep it under ' . (int) (G2ML_CUSTOM_HTML_MAX_BYTES / 1000) . ' KB.';
        }

        return [
            'success'    => false,
            'error'      => $customHTMLTooLargeError,
            'errorCode'  => 'too_large',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if (defined('G2ML_CUSTOM_CSS_MAX_BYTES') && strlen($customCSSRaw) > G2ML_CUSTOM_CSS_MAX_BYTES)
    {
        if (function_exists('__'))
        {
            $customCSSTooLargeError = __('linkspage.error.custom_css_too_large', ['max' => (int) (G2ML_CUSTOM_CSS_MAX_BYTES / 1000)]);
        }
        else
        {
            $customCSSTooLargeError = 'The custom CSS is too large. Please keep it under ' . (int) (G2ML_CUSTOM_CSS_MAX_BYTES / 1000) . ' KB.';
        }

        return [
            'success'    => false,
            'error'      => $customCSSTooLargeError,
            'errorCode'  => 'too_large',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    // 🔒 SANITISE ON INPUT — store the SANITISED form, never the raw submission.
    if (function_exists('g2ml_sanitiseUserHTML'))
    {
        $sanitisedHTML = g2ml_sanitiseUserHTML($customHTMLRaw);
    }
    else
    {
        // Fail closed: with no sanitiser available, refuse the write entirely
        // rather than store un-sanitised HTML.
        if (function_exists('__'))
        {
            $sanitiserUnavailableError = __('linkspage.error.custom_html_sanitiser_unavailable');
        }
        else
        {
            $sanitiserUnavailableError = 'The custom HTML editor is temporarily unavailable. Please try again later.';
        }

        return [
            'success'    => false,
            'error'      => $sanitiserUnavailableError,
            'errorCode'  => 'sanitiser_unavailable',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if (function_exists('g2ml_sanitiseUserCSS'))
    {
        $sanitisedCSS = g2ml_sanitiseUserCSS($customCSSRaw);
    }
    else
    {
        $sanitisedCSS = '';
    }

    // Empty sanitised values are stored as NULL (revert to the system template).
    if (trim($sanitisedHTML) === '')
    {
        $htmlToStore = null;
    }
    else
    {
        $htmlToStore = $sanitisedHTML;
    }

    if (trim($sanitisedCSS) === '')
    {
        $cssToStore = null;
    }
    else
    {
        $cssToStore = $sanitisedCSS;
    }

    // 🔒 Ownership enforced again on the UPDATE itself via "AND userUID = ?".
    $affectedRows = dbUpdate(
        "UPDATE tblLinksPages SET customHTML = ?, customCSS = ? WHERE pageUID = ? AND userUID = ?",
        'ssii',
        [$htmlToStore, $cssToStore, $pageUID, $userUID]
    );

    if ($affectedRows === false)
    {
        if (function_exists('__'))
        {
            $customHTMLSaveFailedError = __('linkspage.error.custom_html_save_failed');
        }
        else
        {
            $customHTMLSaveFailedError = 'Could not save the custom HTML. Please try again.';
        }

        return [
            'success'    => false,
            'error'      => $customHTMLSaveFailedError,
            'errorCode'  => 'server_error',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if (function_exists('logActivity'))
    {
        if ($isClearing === true)
        {
            $customLogStatus = 'cleared';
        }
        else
        {
            $customLogStatus = 'saved';
        }

        logActivity('update_linkspage_custom_html', 'success', 200, [
            'userUID' => $userUID,
            'logData' => ['pageUID' => $pageUID, 'action' => $customLogStatus],
        ]);
    }

    return [
        'success'    => true,
        'error'      => null,
        'errorCode'  => null,
        'customHTML' => $htmlToStore,
        'customCSS'  => $cssToStore,
    ];
}

/**
 * Save custom HTML/CSS typed into the editor's source textareas.
 *
 * @param  int    $userUID       The ACTING user's own userUID.
 * @param  int    $pageUID
 * @param  string $customHTMLRaw
 * @param  string $customCSSRaw
 * @return array  See _g2ml_linkspageManageStoreSanitisedCustom().
 */
function g2ml_linkspageManageSaveCustomHTML(int $userUID, int $pageUID, string $customHTMLRaw, string $customCSSRaw): array
{
    return _g2ml_linkspageManageStoreSanitisedCustom($userUID, $pageUID, $customHTMLRaw, $customCSSRaw);
}

/**
 * Save custom HTML from an UPLOADED .html file (plus optional CSS from the
 * editor textarea). The file is read and run through the SAME sanitiser — the
 * raw upload is NEVER stored. Enforces a size cap and rejects non-HTML files.
 *
 * @param  int         $userUID      The ACTING user's own userUID.
 * @param  int         $pageUID
 * @param  array       $file         One entry from $_FILES (name/type/tmp_name/error/size).
 * @param  string      $customCSSRaw Optional CSS from the editor textarea.
 * @return array  See _g2ml_linkspageManageStoreSanitisedCustom().
 */
function g2ml_linkspageManageSaveCustomHTMLFromUpload(int $userUID, int $pageUID, array $file, string $customCSSRaw): array
{
    $uploadError = UPLOAD_ERR_NO_FILE;

    if (isset($file['error']))
    {
        $uploadError = (int) $file['error'];
    }

    if ($uploadError === UPLOAD_ERR_NO_FILE)
    {
        if (function_exists('__'))
        {
            $uploadNoFileError = __('linkspage.error.upload_no_file');
        }
        else
        {
            $uploadNoFileError = 'Please choose an HTML file to upload.';
        }

        return [
            'success'    => false,
            'error'      => $uploadNoFileError,
            'errorCode'  => 'no_file',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if ($uploadError !== UPLOAD_ERR_OK)
    {
        if (function_exists('__'))
        {
            $uploadIncompleteError = __('linkspage.error.upload_incomplete');
        }
        else
        {
            $uploadIncompleteError = 'The file upload did not complete. Please try again.';
        }

        return [
            'success'    => false,
            'error'      => $uploadIncompleteError,
            'errorCode'  => 'upload_error',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    $fileSize = 0;

    if (isset($file['size']))
    {
        $fileSize = (int) $file['size'];
    }

    $maxBytes = 100000;

    if (defined('G2ML_CUSTOM_HTML_MAX_BYTES'))
    {
        $maxBytes = G2ML_CUSTOM_HTML_MAX_BYTES;
    }

    if ($fileSize <= 0 || $fileSize > $maxBytes)
    {
        if (function_exists('__'))
        {
            $uploadSizeRangeError = __('linkspage.error.upload_size_range', ['max' => (int) ($maxBytes / 1000)]);
        }
        else
        {
            $uploadSizeRangeError = 'The HTML file must be between 1 byte and ' . (int) ($maxBytes / 1000) . ' KB.';
        }

        return [
            'success'    => false,
            'error'      => $uploadSizeRangeError,
            'errorCode'  => 'too_large',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    // Reject non-HTML by extension (defence in depth — the content is
    // sanitised regardless, but there is no reason to accept a .php/.js/etc.).
    $fileName = '';

    if (isset($file['name']) && is_string($file['name']))
    {
        $fileName = $file['name'];
    }

    $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));

    if ($extension !== 'html' && $extension !== 'htm')
    {
        if (function_exists('__'))
        {
            $uploadWrongTypeError = __('linkspage.error.upload_wrong_type');
        }
        else
        {
            $uploadWrongTypeError = 'Only .html files are accepted.';
        }

        return [
            'success'    => false,
            'error'      => $uploadWrongTypeError,
            'errorCode'  => 'wrong_type',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    $tmpName = '';

    if (isset($file['tmp_name']) && is_string($file['tmp_name']))
    {
        $tmpName = $file['tmp_name'];
    }

    // Only ever read a genuine uploaded temp file (blocks a caller passing an
    // arbitrary server path). In the CLI/test context is_uploaded_file() is
    // false, so a test override global is honoured there instead.
    $isRealUpload = is_uploaded_file($tmpName);

    if ($isRealUpload === false && !isset($GLOBALS['g2ml_linkspage_test_allow_plain_upload']))
    {
        if (function_exists('__'))
        {
            $uploadUnreadableError = __('linkspage.error.upload_unreadable');
        }
        else
        {
            $uploadUnreadableError = 'The uploaded file could not be read. Please try again.';
        }

        return [
            'success'    => false,
            'error'      => $uploadUnreadableError,
            'errorCode'  => 'not_uploaded_file',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if ($tmpName === '' || !is_readable($tmpName))
    {
        if (function_exists('__'))
        {
            $uploadUnreadableError = __('linkspage.error.upload_unreadable');
        }
        else
        {
            $uploadUnreadableError = 'The uploaded file could not be read. Please try again.';
        }

        return [
            'success'    => false,
            'error'      => $uploadUnreadableError,
            'errorCode'  => 'unreadable',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    $contents = file_get_contents($tmpName, false, null, 0, $maxBytes + 1);

    if ($contents === false)
    {
        if (function_exists('__'))
        {
            $uploadUnreadableError = __('linkspage.error.upload_unreadable');
        }
        else
        {
            $uploadUnreadableError = 'The uploaded file could not be read. Please try again.';
        }

        return [
            'success'    => false,
            'error'      => $uploadUnreadableError,
            'errorCode'  => 'unreadable',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    if (strlen($contents) > $maxBytes)
    {
        if (function_exists('__'))
        {
            $uploadTooLargeError = __('linkspage.error.upload_too_large', ['max' => (int) ($maxBytes / 1000)]);
        }
        else
        {
            $uploadTooLargeError = 'The HTML file is too large. Please keep it under ' . (int) ($maxBytes / 1000) . ' KB.';
        }

        return [
            'success'    => false,
            'error'      => $uploadTooLargeError,
            'errorCode'  => 'too_large',
            'customHTML' => null,
            'customCSS'  => null,
        ];
    }

    return _g2ml_linkspageManageStoreSanitisedCustom($userUID, $pageUID, $contents, $customCSSRaw);
}
