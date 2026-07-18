# 🔧 Go2My.Link — Coding Conventions & Patterns

## 🚫 No Shorthand Notation (ALL Languages — Standing Rule)

**Applies to ALL code in ALL projects.** No shorthand notation for readability by developers/maintainers.

### PHP

- **Full curly-brace notation ONLY** — ALL BANNED:
  - PHP alternative/template syntax: `if():` / `endif;` / `else:` / `elseif():` / `foreach():` / `endforeach;` / `for():` / `endfor;`
  - Ternary operators: `condition ? trueVal : falseVal` — always use full `if/else` blocks
  - Elvis operator: `expr ?: fallback` — use full `if/else` instead
  - Single-line if without braces: `if (x) doSomething();` — always use braces and multi-line
  - Short open tags: `<?` — always use `<?php`
  - Short echo tags: `<?=` — always use `<?php echo`
  - Always use `if () { }`, `foreach () { }`, etc. with curly braces on ALL control structures
  - In HTML-template contexts: `<?php if ($x) { ?>...<?php } ?>` or `<?php if ($x) { echo 'val'; } ?>`
  - For array values that would need a ternary: extract to a variable with if/else before the array
  - `??` (null coalescing) is acceptable when both sides are simple values; if one side has a function call, use if/else

### JavaScript

- No ternary operators: `condition ? a : b` — always use full `if/else` blocks
- No short-circuit assignment: `x = x || default` — use explicit `if/else`
- No arrow functions as one-liners without braces: `() => expr` — always use `() => { return expr; }`
- No implicit returns in arrow functions
- No comma operator
- Always use full `if () { }` blocks with curly braces

### CSS

- Prefer explicit individual properties over shorthand where it aids readability and maintenance
- Standard shorthand properties (`margin`, `padding`, `border`, `font`, `background`) are acceptable when all values are being set intentionally
- When only setting one side/value, use the specific property (e.g., `margin-top` not `margin: 10px 0 0 0`)

### HTML

- Always use full closing tags (no self-closing for non-void elements)
- Always quote attribute values with double quotes
- Always include `type` attribute on `<script>` and `<style>` tags where applicable
- No boolean attribute shorthand — use `disabled="disabled"` not just `disabled`

### Shell / YAML Scripts

- Full `if/then/else/fi` blocks in bash (no `&&` chains for conditional logic)
- Full variable quoting: `"${VARIABLE}"` not `$VARIABLE`
- Explicit comparisons in conditions

## 🔍 Mandatory Lint & Syntax Checks (Standing Rule — ALL Projects)

**Applied to ALL changes in ALL projects:**

- Run thorough syntax, lint, and static analysis checks on all modified code
- Fix ALL issues including: errors, warnings, recommendations, and notifications
- PHP: `parallel-lint`, PHPStan (advisory), PHP_CodeSniffer (advisory)
- JavaScript: syntax validation, no undef vars
- CSS: valid properties, proper nesting
- Markdown: MD lint (headings spacing, list spacing, etc.)
- YAML: valid syntax, proper indentation
- SQL: valid syntax, consistent naming

## 🐘 PHP Standards

- PHP 8.5+ with 8.4 backward compat via `version_compare()`
- Use PHP predefined constants: `DIRECTORY_SEPARATOR`, `PHP_EOL`, etc.
- Detailed inline comments with links to official docs
- Proper indentation — consistent and readable throughout all code
- Modular structure: shared code via `require`/`require_once`/`include`/`include_once`
- Existence checks for included files before `require`/`include`
- Debug mode via `?debug=true` URL parameter
- Log all errors to tblErrorLog in database
- Log general activity to tblActivityLog
- Shared global functions use the `g2ml_` prefix (see `security.php` header). Client IP is
  obtained via `g2ml_getClientIP()` (`web/_functions/security.php`) — it always returns a
  non-empty, sane value (min. `'0.0.0.0'`), so callers should call it directly. Only when a
  call site may run before `security.php` has loaded (e.g. an early error handler) should it
  use `g2ml_clientIpOrDefault(?string $default)` instead of repeating its own
  `function_exists('g2ml_getClientIP')` guard (#118). A broader pass standardising the
  ~103 other global function names is tracked separately and intentionally out of scope here.

## 🗄️ Database Standards

- MySQLi ONLY (no PDO)
- Prepared statements for ALL SQL queries
- InnoDB engine, utf8mb4_unicode_ci collation
- Sensitive values: AES-256-GCM encrypted with SALT
- Settings stored in database (not config files), except DB connection creds

## 🎨 Frontend Standards

- HTML5 compliant, responsive (Bootstrap 5.3)
- CDN-first with local fallback for all libraries
- No `.php` visible in URLs (use .htaccess rewrites or directory-based routing)
- SVG graphics with Base64 fallback
- AJAX with graceful no-JS fallback
- Emojis OK in code comments

## 🌓 Dark/Light Mode Pattern

### CSS

- Use CSS custom properties (`--g2ml-*`) for all brand colours
- Define light values in `:root` / `[data-bs-theme="light"]`
- Define dark overrides in `[data-bs-theme="dark"]`
- **Never hardcode hex colours in CSS** — always use `var(--g2ml-*)`
- Bootstrap utility classes (`text-muted`, `bg-primary`, etc.) auto-adapt via `data-bs-theme`

### PHP (FOUC Prevention)

- Read `g2ml_theme` cookie in `header.php`
- Set `data-bs-theme` attribute on `<html>` server-side
- For 'auto' preference, default to 'light' server-side (JS corrects instantly)

### JavaScript (theme.js)

- localStorage key: `g2ml-theme` (values: auto, light, dark)
- Cookie name: `g2ml_theme` (values: auto, light, dark)
- Three-state cycle: auto → light → dark → auto
- Listen for `prefers-color-scheme` changes when in 'auto' mode
- Announce theme changes via ARIA live region (`#global-status`)

### Component Overrides

- Navbar: Always `data-bs-theme="dark"` (brand identity)
- Footer: Always `data-bs-theme="dark"` (brand identity)
- Main content area: Follows page theme

## ♿ Accessibility (WCAG 2.1 AA — Cross-Cutting)

- Built-in from Phase 2 onwards, NOT retrofitted
- Semantic HTML5 elements (`<nav>`, `<main>`, `<aside>`, `<header>`, `<footer>`)
- ARIA landmarks on all layout sections
- Skip-to-content link on every page
- All form fields must have associated `<label>` elements
- Keyboard navigation: visible focus indicators, logical tab order
- Colour contrast: 4.5:1 normal text, 3:1 large text
- `prefers-reduced-motion` and `prefers-color-scheme` media query support
- Screen reader compatible (ARIA live regions for dynamic/AJAX content)
- Use accessibility helpers from `web/_includes/accessibility.php`

## 🌍 i18n / Translation (Cross-Cutting)

- ALL UI strings must use `__('key')` — NEVER hardcode user-facing text
- Translation keys: dot notation `page.section.element` (e.g., `home.hero.title`)
- Placeholders: `{name}` syntax — `__('greeting', ['name' => $userName])`
- Base language: English (en-GB)
- Interim translation: Google/Bing/AI widget (until formal translations in Phase 10)
- Support RTL languages (`dir="rtl"` on `<html>`)
- Date/time/number/currency formatting must respect locale

## 📁 Directory Naming

- Private dirs: underscore prefix (`_auth_keys`, `_includes`, `_functions`, `_libraries`)
- Web roots: `public_html`, `public_html_dev_alpha`, `public_html_dev_beta`, `public_html_landing`, `public_html_redir`

## 📝 Documentation Emoji Vocabulary

Use these emojis consistently across all `.md` files:

**Status:** ✅ Complete · ⏳ In Progress · 🔜 Not Started · ❌ Blocked

**Section Headers:**
📋 Overview · 🚀 Features · 🛠️ Tech Stack · 📁 Structure · 🔒 Security · 🌍 i18n · ♿ Accessibility · 🗄️ Database · 📡 API · 🎨 Design/UI · 💰 Payments · ⚖️ Legal · 📦 Libraries · 🚢 Deployment · 🐛 Debug · 📝 Notes · 🔀 Routing · ⚙️ Settings · 🏢 Organisations · 👤 Users

**Changelog:** ✨ Added · 🔄 Changed · 🐛 Fixed · 🗑️ Removed · 🔒 Security
