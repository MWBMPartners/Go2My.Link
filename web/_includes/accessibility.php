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
 * ♿ Go2My.Link — Accessibility Helpers (WCAG 2.1 AA)
 * ============================================================================
 *
 * Helper functions for generating accessible HTML patterns.
 * All UI components from Phase 2 onwards use these helpers.
 *
 * Covers:
 *   - Screen reader only text (visually hidden)
 *   - ARIA live regions for dynamic content
 *   - Focus management
 *   - Skip navigation links
 *   - Accessible form helpers
 *
 * Dependencies: None (standalone helper functions)
 *
 * @package    Go2My.Link
 * @subpackage Includes
 * @author     MWBM Partners Ltd (MWservices)
 * @version    0.3.0
 * @since      Phase 2
 *
 * 📖 References:
 *     - WCAG 2.1 AA: https://www.w3.org/TR/WCAG21/
 *     - ARIA:        https://www.w3.org/TR/wai-aria-1.2/
 *     - Bootstrap 5: https://getbootstrap.com/docs/5.3/getting-started/accessibility/
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
// 👁️ Screen Reader Only (Visually Hidden)
// ============================================================================

/**
 * Wrap text in a screen-reader-only span.
 *
 * Uses Bootstrap's .visually-hidden class (sr-only equivalent).
 * Text is hidden visually but announced by screen readers.
 *
 * @param  string $text  The text to make screen-reader only
 * @return string        HTML span element with visually-hidden class
 *
 * 📖 Reference: https://getbootstrap.com/docs/5.3/helpers/visually-hidden/
 *
 * Usage example:
 *   echo '<button>' . srOnly('Delete') . '<i class="fas fa-trash"></i></button>';
 */
function srOnly(string $text): string
{
    return '<span class="visually-hidden">'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        . '</span>';
}

/**
 * Generate a visually-hidden heading for screen reader navigation.
 *
 * Useful for adding invisible headings to sections that have visual
 * differentiation but no explicit heading.
 *
 * @param  string $text   The heading text
 * @param  int    $level  Heading level (1-6, default 2)
 * @return string         HTML heading element with visually-hidden class
 */
function srHeading(string $text, int $level = 2): string
{
    $level = max(1, min(6, $level));

    return '<h' . $level . ' class="visually-hidden">'
        . htmlspecialchars($text, ENT_QUOTES, 'UTF-8')
        . '</h' . $level . '>';
}

// ============================================================================
// 📢 ARIA Live Regions
// ============================================================================

/**
 * Generate an ARIA live region container for dynamic content updates.
 *
 * Screen readers will announce changes to the content of this container.
 *
 * @param  string $id        Unique ID for the container
 * @param  string $content   Initial content (can be empty)
 * @param  string $politeness 'polite' (waits for idle) or 'assertive' (interrupts)
 * @param  string $role      ARIA role ('status', 'alert', 'log', 'timer')
 * @return string            HTML div element configured as a live region
 *
 * 📖 Reference: https://www.w3.org/TR/wai-aria-1.2/#aria-live
 *
 * Usage example:
 *   echo ariaLiveRegion('status-message', '', 'polite', 'status');
 *   // Later, update via JavaScript: document.getElementById('status-message').textContent = 'Saved!';
 */
function ariaLiveRegion(string $id, string $content = '', string $politeness = 'polite', string $role = 'status'): string
{
    $safeContent = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
    if (in_array($politeness, ['polite', 'assertive', 'off'], true)) {
        $safePoliteness = $politeness;
    } else {
        $safePoliteness = 'polite';
    }
    $safeRole = htmlspecialchars($role, ENT_QUOTES, 'UTF-8');

    return '<div id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"'
        . ' aria-live="' . $safePoliteness . '"'
        . ' role="' . $safeRole . '"'
        . ' class="visually-hidden">'
        . $safeContent
        . '</div>';
}

// ============================================================================
// 🎯 Skip Navigation
// ============================================================================

/**
 * Generate a "Skip to main content" link.
 *
 * This is the first focusable element on the page, allowing keyboard users
 * to skip the navigation and jump directly to the main content area.
 *
 * @param  string $targetID  The ID of the main content container (default: 'main-content')
 * @param  string $text      The link text (default: translated 'Skip to main content')
 * @return string            HTML anchor element
 *
 * 📖 Reference: https://www.w3.org/TR/WCAG21/#bypass-blocks
 */
function skipToContent(string $targetID = 'main-content', ?string $text = null): string
{
    if ($text !== null) {
        $linkText = $text;
    } elseif (function_exists('__')) {
        $linkText = __('a11y.skip_to_content');
    } else {
        $linkText = 'Skip to main content';
    }

    // If translation returns the key (not found), use English default
    if ($linkText === 'a11y.skip_to_content')
    {
        $linkText = 'Skip to main content';
    }

    return '<a href="#' . htmlspecialchars($targetID, ENT_QUOTES, 'UTF-8') . '"'
        . ' class="visually-hidden-focusable skip-to-content">'
        . htmlspecialchars($linkText, ENT_QUOTES, 'UTF-8')
        . '</a>';
}

// ============================================================================
// 📝 Accessible Form Helpers
// ============================================================================

/**
 * Generate a form field with proper label association and error state.
 *
 * Returns a Bootstrap-styled form group with label, input, and optional
 * help text and error message.
 *
 * @param  array $options  Configuration array with keys:
 *   - id:          (string, required) Input ID and label for attribute
 *   - name:        (string, required) Input name attribute
 *   - label:       (string, required) Label text
 *   - type:        (string) Input type (default: 'text')
 *   - value:       (string) Current value
 *   - placeholder: (string) Placeholder text
 *   - required:    (bool) Whether the field is required
 *   - helpText:    (string) Descriptive help text
 *   - error:       (string) Error message to display
 *   - autocomplete:(string) Autocomplete attribute value
 *   - class:       (string) Additional CSS classes
 * @return string  HTML form group
 *
 * 📖 Reference: https://www.w3.org/TR/WCAG21/#labels-or-instructions
 */
function formField(array $options): string
{
    $id          = $options['id'] ?? 'field-' . uniqid();
    $name        = $options['name'] ?? $id;
    $label       = $options['label'] ?? '';
    $type        = $options['type'] ?? 'text';
    $value       = $options['value'] ?? '';
    $placeholder = $options['placeholder'] ?? '';
    $required    = $options['required'] ?? false;
    $helpText    = $options['helpText'] ?? '';
    $error       = $options['error'] ?? '';
    $autocomplete = $options['autocomplete'] ?? '';
    $extraClass  = $options['class'] ?? '';

    $safeId          = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
    $safeName        = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeLabel       = htmlspecialchars($label, ENT_QUOTES, 'UTF-8');
    $safeValue       = htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    $safePlaceholder = htmlspecialchars($placeholder, ENT_QUOTES, 'UTF-8');

    $html = '<div class="mb-3">';

    // Label (always visible, always associated via for/id)
    $html .= '<label for="' . $safeId . '" class="form-label">';
    $html .= $safeLabel;

    if ($required)
    {
        $html .= ' <span class="text-danger" aria-hidden="true">*</span>';
        $html .= srOnly('(required)');
    }

    $html .= '</label>';

    // Input/textarea field
    $inputClass = 'form-control ' . trim($extraClass);

    if ($error !== '')
    {
        $inputClass .= ' is-invalid';
    }

    // Build shared attributes
    $sharedAttrs = ' class="' . trim($inputClass) . '"'
        . ' id="' . $safeId . '"'
        . ' name="' . $safeName . '"';

    if ($safePlaceholder !== '')
    {
        $sharedAttrs .= ' placeholder="' . $safePlaceholder . '"';
    }

    if ($required)
    {
        $sharedAttrs .= ' required aria-required="true"';
    }

    if ($autocomplete !== '')
    {
        $sharedAttrs .= ' autocomplete="' . htmlspecialchars($autocomplete, ENT_QUOTES, 'UTF-8') . '"';
    }

    // Link input to help text and error message via aria-describedby
    $describedBy = [];

    if ($helpText !== '')
    {
        $describedBy[] = $safeId . '-help';
    }

    if ($error !== '')
    {
        $describedBy[] = $safeId . '-error';
        $sharedAttrs .= ' aria-invalid="true"';
    }

    if (!empty($describedBy))
    {
        $sharedAttrs .= ' aria-describedby="' . implode(' ', $describedBy) . '"';
    }

    // Render textarea or input based on type
    if ($type === 'textarea')
    {
        $rows = (int) ($options['rows'] ?? 3);
        $html .= '<textarea' . $sharedAttrs . ' rows="' . $rows . '">';
        $html .= $safeValue;
        $html .= '</textarea>';
    }
    else
    {
        $html .= '<input type="' . htmlspecialchars($type, ENT_QUOTES, 'UTF-8') . '"';
        $html .= $sharedAttrs;
        $html .= ' value="' . $safeValue . '">';
    }

    // Error message
    if ($error !== '')
    {
        $html .= '<div id="' . $safeId . '-error" class="invalid-feedback" role="alert">';
        $html .= htmlspecialchars($error, ENT_QUOTES, 'UTF-8');
        $html .= '</div>';
    }

    // Help text
    if ($helpText !== '')
    {
        $html .= '<div id="' . $safeId . '-help" class="form-text">';
        $html .= htmlspecialchars($helpText, ENT_QUOTES, 'UTF-8');
        $html .= '</div>';
    }

    $html .= '</div>';

    return $html;
}

/**
 * Generate an accessible alert/notification.
 *
 * @param  string $message  The alert message
 * @param  string $type     Bootstrap alert type: success, danger, warning, info
 * @param  bool   $dismissible  Whether the alert can be dismissed
 * @return string           HTML alert element with proper ARIA attributes
 *
 * 📖 Reference: https://www.w3.org/TR/wai-aria-1.2/#alert
 */
function accessibleAlert(string $message, string $type = 'info', bool $dismissible = true): string
{
    $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');
    if (in_array($type, ['success', 'danger', 'warning', 'info', 'primary', 'secondary'], true)) {
        $safeType = $type;
    } else {
        $safeType = 'info';
    }

    $classes = 'alert alert-' . $safeType;

    if ($dismissible)
    {
        $classes .= ' alert-dismissible fade show';
    }

    $html = '<div class="' . $classes . '" role="alert">';
    $html .= $safeMessage;

    if ($dismissible)
    {
        if (function_exists('_e')) {
            $closeLabel = _e('a11y.close_alert');
        } else {
            $closeLabel = 'Close';
        }
        $html .= '<button type="button" class="btn-close" data-bs-dismiss="alert"';
        $html .= ' aria-label="' . $closeLabel . '"></button>';
    }

    $html .= '</div>';

    return $html;
}
