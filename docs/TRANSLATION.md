# 🌍 Go2My.Link — Translation Guide

> How the i18n system works, how to add translations, and contribution guidelines.

## 📋 Overview

Go2My.Link uses a database-backed internationalisation (i18n) system with PHP translation functions. All user-facing strings use translation keys, allowing the platform to be localised into any language.

**Key facts:**
- **Base language:** English (UK) — `en-GB`
- **Translation storage:** `tblTranslations` (MySQL)
- **Language registry:** `tblLanguages` (MySQL)
- **Core functions:** `web/_functions/i18n.php` (12 functions)
- **Total translation keys:** ~1,075

## 🏗️ Architecture

### 📖 Translation Function: `__()`

The primary function is `__($key, $replacements, $locale)`:

```php
// Simple translation
echo __('nav.login');
// Output: "Log In"

// With placeholder replacement
echo __('welcome', ['name' => 'Lance']);
// Output: "Welcome, Lance!"

// HTML-safe output (escapes special characters)
echo _e('button.delete');

// Pluralisation
echo _n('link.count', 5);
// Output: "5 links" (uses singular|plural format)
```

### 🔗 Fallback Chain

When `__('key')` is called, the system resolves the value through this priority chain:

1. **Current locale** — Translation in the active locale (e.g., `es`)
2. **Default locale** — Translation in `en-GB` (always the base)
3. **Key itself** — Returns the dot-notation key as a last resort (makes missing translations visible in the UI)

### 🗄️ Database Schema

**`tblLanguages`** — Language registry:

| Column | Type | Purpose |
| --- | --- | --- |
| `localeCode` | VARCHAR(10) | BCP 47 locale code (e.g., `en-GB`, `es`, `ar`) |
| `languageName` | VARCHAR(100) | English name (e.g., "Spanish") |
| `nativeName` | VARCHAR(100) | Native name (e.g., "Español") |
| `direction` | ENUM('ltr','rtl') | Text direction |
| `isDefault` | TINYINT(1) | Base language flag |
| `isActive` | TINYINT(1) | Available for selection |
| `completionPercent` | TINYINT | Translation coverage (0-100) |

**`tblTranslations`** — Translation strings:

| Column | Type | Purpose |
| --- | --- | --- |
| `localeCode` | VARCHAR(10) | BCP 47 locale code (FK to `tblLanguages`) |
| `translationKey` | VARCHAR(255) | Dot-notation key (e.g., `nav.login`) |
| `translationValue` | TEXT | Translated string |
| `context` | VARCHAR(255) | Hint for translators |
| `isVerified` | TINYINT(1) | Human-verified flag |

## 📏 Key Naming Conventions

Translation keys use **dot-notation** organised by page or component:

| Prefix | Scope | Examples |
| --- | --- | --- |
| `nav.*` | Navigation bar | `nav.home`, `nav.login`, `nav.profile` |
| `home.*` | Homepage | `home.shorten_url`, `home.shorten_button` |
| `login.*` | Login page | `login.heading`, `login.submit_button` |
| `register.*` | Registration page | `register.heading`, `register.label_email` |
| `dashboard.*` | User dashboard | `dashboard.heading`, `dashboard.stat_total_links` |
| `links.*` | Link management | `links.heading`, `links.create_new` |
| `org.*` | Organisation pages | `org.title`, `org.create_heading` |
| `legal.*` | Legal documents | `legal.terms_heading`, `legal.privacy_s1_title` |
| `consent.*` | Cookie consent UI | `consent.heading`, `consent.save_preferences` |
| `privacy.*` | Privacy dashboard | `privacy.heading`, `privacy.export_title` |
| `error.*` | Error pages | `error.400_heading`, `error.back_home` |
| `footer.*` | Footer links | `footer.terms`, `footer.privacy` |
| `common.*` | Shared UI elements | `common.close`, `common.cancel` |
| `a11y.*` | Accessibility | `a11y.skip_to_content` |

### 📐 Naming Rules

1. Use **lowercase** with **dots** as separators: `page.section_element`
2. Use **underscores** within segments: `forgot_password.submit_button`
3. Keep keys **descriptive** and **unique**: `login.label_email` not `email`
4. Group related keys under the same prefix
5. Use suffixes for element types: `_heading`, `_title`, `_label`, `_button`, `_desc`, `_help`, `_placeholder`, `_error`

## ✏️ Placeholder Syntax

### 🔤 Simple Placeholders

Use `{name}` syntax in translation values:

```
translationKey: welcome
translationValue: Welcome back, {name}!
```

```php
echo __('welcome', ['name' => $user['firstName']]);
// Output: "Welcome back, Lance!"
```

### 🔢 Pluralisation

Use pipe-separated `singular|plural` format:

```
translationKey: link.count
translationValue: {count} link|{count} links
```

```php
echo _n('link.count', 1);  // "1 link"
echo _n('link.count', 5);  // "5 links"
```

## 🆕 Adding a New Language

### Step 1: Register the language

Insert into `tblLanguages`:

```sql
INSERT INTO tblLanguages (localeCode, languageName, nativeName, direction, isDefault, isActive, completionPercent, sortOrder)
VALUES ('es', 'Spanish', 'Español', 'ltr', 0, 0, 0, 30);
```

### Step 2: Add translations

Insert translation rows for every key:

```sql
INSERT INTO tblTranslations (localeCode, translationKey, translationValue, context, isVerified)
VALUES
    ('es', 'nav.home', 'Inicio', 'Main navigation', 0),
    ('es', 'nav.about', 'Acerca de', 'Main navigation', 0),
    -- ... all ~1,075 keys
```

### Step 3: Update completion percentage

```sql
UPDATE tblLanguages SET completionPercent = 100, isActive = 1 WHERE localeCode = 'es';
```

### Step 4: Test

1. Visit `https://go2my.link/?lang=es`
2. Verify the language switcher shows the new language
3. Test at least 3 complete user flows in the new language
4. Check RTL layout if applicable (e.g., Arabic)

## 🔄 RTL Language Support

For right-to-left languages (Arabic, Hebrew, etc.):

- Set `direction = 'rtl'` in `tblLanguages`
- The `getTextDirection()` function returns `'rtl'`
- `header.php` sets `dir="rtl"` on the `<html>` element
- Bootstrap RTL CSS is loaded automatically when `$textDir === 'rtl'`
- Test all layouts for correct mirroring

## 📊 Current Language Status

| Locale | Language | Native Name | Direction | Active | Completion |
| --- | --- | --- | --- | --- | --- |
| `en-GB` | English (UK) | English | LTR | ✅ Yes | 100% |
| `en-US` | English (US) | English | LTR | ❌ No | 0% |
| `es` | Spanish | Español | LTR | ❌ No | 0% |
| `fr` | French | Français | LTR | ❌ No | 0% |
| `de` | German | Deutsch | LTR | ❌ No | 0% |
| `pt-BR` | Portuguese (Brazil) | Português | LTR | ❌ No | 0% |
| `ar` | Arabic | العربية | RTL | ❌ No | 0% |
| `zh-CN` | Chinese (Simplified) | 简体中文 | LTR | ❌ No | 0% |
| `ja` | Japanese | 日本語 | LTR | ❌ No | 0% |
| `hi` | Hindi | हिन्दी | LTR | ❌ No | 0% |

> 💡 The interim Google Translate widget provides machine translation for non-active locales until professional translations are completed.

## 🤝 Contributing Translations

### 📋 Guidelines

1. **Accuracy** — Translations must accurately convey the meaning of the English source
2. **Consistency** — Use consistent terminology throughout (e.g., always translate "short URL" the same way)
3. **Placeholders** — Preserve all `{placeholder}` tokens exactly as they appear in the English source
4. **HTML** — Preserve any HTML tags or entities (e.g., `<strong>`, `&mdash;`) in legal text translations
5. **Context** — Use the `context` column hints to understand where the string appears
6. **Tone** — Match the professional but approachable tone of the English source
7. **Length** — Be mindful of UI space constraints; very long translations may break layouts

### 🔍 Review Process

1. Translations start with `isVerified = 0`
2. A native speaker reviews and marks verified: `isVerified = 1`
3. Only verified translations are considered "complete"
4. `completionPercent` in `tblLanguages` should reflect verified translations only

## 📁 Related Files

| Purpose | File |
| --- | --- |
| 🔧 i18n Functions | `web/_functions/i18n.php` |
| 🗄️ Schema | `web/_sql/schema/035_translations.sql` |
| 🌱 Language Seeds | `web/_sql/seeds/005_languages.sql` |
| 🌱 Translation Seeds (en-GB) | `web/_sql/seeds/010_phase6_translations.sql` |
| 🌐 Language Switcher | `web/_includes/language_switcher.php` |
| 🔄 Google Translate Widget | `web/_includes/translate_widget.php` |
| 🚀 Page Initialisation | `web/_includes/page_init.php` |
| ♿ Accessibility Helpers | `web/_includes/accessibility.php` |

## 📚 Related Documentation

- 📋 [ARCHITECTURE.md](ARCHITECTURE.md) — System architecture overview
- 🗄️ [DATABASE.md](DATABASE.md) — Database schema reference
- ♿ [ACCESSIBILITY.md](ACCESSIBILITY.md) — Accessibility guidelines
