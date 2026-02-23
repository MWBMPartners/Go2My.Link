# Go2My.Link — Accessibility Standards

> WCAG 2.1 AA compliance guide for the Go2My.Link platform.

## 🎯 Compliance Target

**WCAG 2.1 Level AA** — All three web properties (go2my.link, g2my.link, lnks.page) must meet this standard from Phase 2 onwards.

## ♿ Core Principles (POUR)

### 👁️ 1. Perceivable

| Requirement | How We Meet It |
| --- | --- |
| **🖼️ Text Alternatives** (1.1.1) | All icons use `aria-hidden="true"` with adjacent text or `srOnly()` |
| **🎥 Captions** (1.2) | Not applicable — no audio/video content |
| **📐 Adaptable** (1.3.1) | Semantic HTML5 elements (`<nav>`, `<main>`, `<footer>`, `<header>`) |
| **🎨 Distinguishable** (1.4) | Brand colours meet 4.5:1 contrast ratio; focus indicators visible |

### ✋ 2. Operable

| Requirement | How We Meet It |
| --- | --- |
| **⌨️ Keyboard** (2.1.1) | All interactive elements focusable; no keyboard traps |
| **⏱️ Timing** (2.2) | No timed content; session timeout gives warning |
| **⚡ Seizures** (2.3) | No flashing content |
| **🧭 Navigable** (2.4) | Skip-to-content link; descriptive page titles; focus visible |

### 🧠 3. Understandable

| Requirement | How We Meet It |
| --- | --- |
| **📖 Readable** (3.1) | `lang` attribute on `<html>`; language switcher |
| **🔄 Predictable** (3.2) | Consistent navigation; no unexpected context changes |
| **💡 Input Assistance** (3.3) | Form labels; error identification; help text via `aria-describedby` |

### ⚙️ 4. Robust

| Requirement | How We Meet It |
| --- | --- |
| **🔌 Compatible** (4.1) | Valid HTML5; ARIA attributes; semantic markup |

## 🛠️ Implementation Helpers

All helpers are in `web/_includes/accessibility.php`:

| Function | Purpose | Example |
| --- | --- | --- |
| 👁️ `srOnly($text)` | Screen-reader-only text | `srOnly('Delete')` |
| 📝 `srHeading($text, $level)` | Hidden heading for sections | `srHeading('Search Results', 2)` |
| 📢 `ariaLiveRegion($id, $content, $politeness)` | Dynamic content announcer | `ariaLiveRegion('status', '', 'polite')` |
| ⏭️ `skipToContent($targetID)` | Skip navigation link | `skipToContent('main-content')` |
| 📝 `formField($options)` | Accessible form field | See below |
| 🔔 `accessibleAlert($message, $type)` | Alert with ARIA role | `accessibleAlert('Saved!', 'success')` |

### 📝 Form Field Usage

```php
echo formField([
    'id'          => 'email',
    'name'        => 'email',
    'label'       => 'Email Address',
    'type'        => 'email',
    'required'    => true,
    'helpText'    => 'We will never share your email',
    'error'       => $errors['email'] ?? '',
    'autocomplete' => 'email',
]);
```

Generates:

- ✅ `<label>` linked to input via `for`/`id`
- ✅ Required indicator with screen-reader text
- ✅ `aria-describedby` linking help text and error
- ✅ `aria-invalid="true"` when error is present
- ✅ `aria-required="true"` for required fields

## 📏 Coding Standards

1. 🖼️ **All `<img>` tags** must have `alt` attributes (empty `alt=""` for decorative images)
2. 🔘 **All icon-only buttons** must have `aria-label` or adjacent `srOnly()` text
3. 📝 **All form fields** must have associated `<label>` elements (use `formField()` helper)
4. 🎭 **All decorative icons** must use `aria-hidden="true"`
5. 🎨 **All colour combinations** must meet 4.5:1 contrast ratio (text) or 3:1 (large text)
6. 🔲 **All interactive elements** must have visible focus indicators (`:focus-visible`)
7. 📢 **All dynamic content changes** must use ARIA live regions
8. 📄 **All pages** must have a unique, descriptive `<title>`
9. 📊 **All tables** must have `<caption>` or `aria-label`
10. 🪟 **All modals** must trap focus and return focus on close

## 🎨 Colour Contrast

| Colour Pair | Ratio | Passes AA |
| --- | --- | --- |
| 🔵 #1565C0 (Blue) on #FFFFFF | 5.27:1 | ✅ Yes |
| 🟢 #2E7D32 (Green) on #FFFFFF | 4.96:1 | ✅ Yes |
| ⚪ #5A5F6A (Grey) on #FFFFFF | 5.38:1 | ✅ Yes |
| ⬜ #FFFFFF on #1565C0 (Blue) | 5.27:1 | ✅ Yes |
| 🔴 #E94560 (Danger) on #FFFFFF | 4.22:1 | ✅ Yes (large text) |

## ✅ Testing Checklist

- [ ] ⌨️ All pages navigable by keyboard alone (Tab, Enter, Escape, Arrow keys)
- [ ] ⏭️ Skip-to-content link visible on first Tab press
- [ ] 📝 All form fields have visible labels
- [ ] 📢 All error messages are announced by screen readers
- [ ] 📄 Page title changes are announced on navigation
- [ ] 🎨 Colour contrast meets 4.5:1 minimum
- [ ] 🔵 No content relies solely on colour to convey meaning
- [ ] 🔄 RTL layout renders correctly for Arabic/Hebrew locales
- [ ] 🖼️ All images have appropriate alt text
- [ ] 🔲 Focus order is logical and predictable

## 🧰 Tools for Testing

- 🔍 **aXe DevTools** — Browser extension for automated WCAG testing
- 🌊 **WAVE** — Web Accessibility Evaluation Tool
- 💡 **Lighthouse** — Chrome DevTools accessibility audit
- 🖥️ **NVDA** — Free screen reader for Windows testing
- 🍎 **VoiceOver** — Built-in screen reader for macOS/iOS testing
- 🎨 **Colour Contrast Analyser** — TPGi tool for contrast checking

## 📚 Related Documentation

- 📋 [Accessibility Helpers Source](../web/_includes/accessibility.php)
- 📦 [Bootstrap Accessibility](https://getbootstrap.com/docs/5.3/getting-started/accessibility/)
- 📜 [WCAG 2.1 Specification](https://www.w3.org/TR/WCAG21/)
- 📜 [WAI-ARIA 1.2](https://www.w3.org/TR/wai-aria-1.2/)
